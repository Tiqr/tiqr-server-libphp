<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_ServiceTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        $this->logger  = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }

    private function getOptions() {
        return array(
            'auth.protocol' => 'testauth',
            'enroll.protocol' => 'testenroll',
            'ocra.suite' => 'OCRA-1:HOTP-SHA1-6:QH10-S',
            'identifier' => 'test.identifier.example.org',
            'name' => 'Test service name',
            'logoUrl' => 'http://tiqr.example.org/logoUrl',
            'infoUrl' => 'http://tiqr.example.org/infoUrl',
            // 'phpqrcode.path'
            // 'apns.path'
            // 'apns.certificate'
            'apns.environment' => 'sandbox',
            'c2dm.username' => 'test_c2dm_username',
            'c2dm.password' => 'test_c2dm_password',
            'c2dm.application' => 'org.example.authenticator.test',
            'statestorage' => array(
                'type' => 'file',
            ),
            'devicestorage' => array(
                'type' => 'dummy',
            )
        );
    }

    // Test creating a new tiqr service
    public function testDefaultConstructor() {
        $_SERVER['SERVER_NAME'] = 'dummy.example.org';
        $service = new Tiqr_Service($this->logger);
        $this->assertInstanceOf(Tiqr_Service::class, $service);
    }

    public function testOptions() {

        $service = new Tiqr_Service($this->logger, $this->getOptions());
        $this->assertInstanceOf(Tiqr_Service::class, $service);

        $this->assertSame($service->getIdentifier(), 'test.identifier.example.org');
    }

    public function testEnroll() {
        // Create unique session ID
        $session_id = 'test_session_id_'.time();

        // Creat tiqr service
        $service = new Tiqr_Service($this->logger, $this->getOptions());
        $this->assertInstanceOf(Tiqr_Service::class, $service);

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_IDLE);


        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        // 1. Start enroll
        //    Get an enrollment key. This is a unique key that the client can use once to get the actual shared
        //    authentication secret
        $enrollment_key = $service->startEnrollmentSession('test_user_id', 'Test User Name', $session_id);
        $this->assertIsString($enrollment_key);
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $enrollment_key);
        $this->assertEquals(64, strlen($enrollment_key));

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED);

        // 2. Generate QR code for the phone to scan
        // A server builds the an URL that the client wil connect to to get the actual key
        // The URL's format is up to the server, but must (logically) include the $enrollment_key
        $metadataUrl = 'https://test.example.org/metadata_url/' . $enrollment_key;
        // Use generateEnrollString, the generates the string that generateEnrollmentQR encodes in the QR code
        $enroll_string = $service->generateEnrollString($metadataUrl );
        // Yes, this will have two times "://" in there!
        $this->assertEquals('testenroll://' . $metadataUrl, $enroll_string);
        // $service->generateEnrollmentQR($metadataUrl); // Hot to test?

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED);


        ////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // 3. The phone has contacted the $metadataUrl we generated, which contains this session's $enrollment_key
        //    We return it the enrollment metadata
        //    Where the phone will authenticate, not relevant for enrollment
        $authentication_url = 'https://test.example.org/authentication_url';

        // 3a - We need to generate an enrollment URL, this must contain the enrollment secret
        // So we need to generate this enrollment secret first
        $enrollment_secret = $service->getEnrollmentSecret($enrollment_key);
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $enrollment_secret);
        $this->assertEquals(64, strlen($enrollment_secret));


        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED);    // Status remains unchanged

        // 3b - We generate the metadata to return to the phone
        $enrollment_url = 'https://test.example.org/enrollment_url/' . $enrollment_secret;
        $enrollemnt_metadata = $service->getEnrollmentMetadata($enrollment_key, $authentication_url, $enrollment_url);
        // To return to the phone: json_encode($enrollemnt_metadata);
        $this->assertIsArray($enrollemnt_metadata);

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_RETRIEVED);


        ////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // 4. The phone posts to the enrollment url with the actual tiqr authentication secret that it generated in the
        //    body
        //    The URL contains the $enrollment_secret
        //    We retrieve the $enrollment_secret from the url, and we validate it

        $user_id = $service->validateEnrollmentSecret($enrollment_secret);
        $this->assertSame($user_id, 'test_user_id' );   // Must match the userid we set in startEnrollmentSession

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_PROCESSED);

        // Finalize (clean up)
        $res = $service->finalizeEnrollment($enrollment_secret);
        $this->assertTrue($res);
    }

    function testAuthentication() {
        // Create unique session ID
        $session_id = 'test_session_id_'.time();

        // Create tiqr service
        $service = new Tiqr_Service($this->logger, $this->getOptions());
        $this->assertInstanceOf(Tiqr_Service::class, $service);

        $userid = 'test-auth-user'; // The user to authenticate

        $this->assertEquals(NULL, $service->getAuthenticatedUser($session_id));

        // Here we provide the userID, it will be put in the AuthURL. I.e. the stepup scenario
        // For the login scenario, where the server does not know the userid yet userid is left blank
        $session_key = $service->startAuthenticationSession($userid, $session_id );
        $this->assertIsString($session_key);
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $session_key);
        $this->assertEquals(64, strlen($session_key));
        $this->assertEquals(NULL, $service->getAuthenticatedUser($session_id));

        // Generate auth URL for in QR code
        // The generated URL has the format:
        // testauth://test-auth-user@test.identifier.example.org/$session_key/$challenge/test.identifier.example.org/2
        // 0        1 2                                          3            4          5                           6
        $authUrl=$service->generateAuthURL($session_key, 'test-auth-user', $session_id);
        $this->assertIsString($authUrl);
        $this->assertNotEmpty($authUrl);

        // Get info from the auth URL
        $exploded = explode('/', $authUrl);
        $session_key_from_auth_url = $exploded[3]; // hex encoded session
        $challenge_from_auth_url = $exploded[4];   // 10 digit hex challenge
        $protocol_version = $exploded[6];
        $this->assertEquals($session_key, $session_key_from_auth_url);
        $this->assertEquals(2, $protocol_version);

        // The shared secret between tiqr client and tiqr server
        $userSecret = '3132333435363738393031323334353637383930313233343536373839303132';

        // Calculate a response like a tiqr client would do using the information from the auth URL
        $ocra = new Tiqr_OCRAWrapper('OCRA-1:HOTP-SHA1-6:QH10-S' );
        $response = $ocra->calculateResponse( $userSecret, $challenge_from_auth_url, $session_key_from_auth_url);

        // Test invalid response. 1234567 is always an invalid response, responses are 6 digits.
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE, $service->authenticate( 'test-auth-user', $userSecret, $session_key, '1234567' ) );

        // Test invalid user id
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_INVALID_USERID, $service->authenticate( 'invalid-user', $userSecret, $session_key, $response ) );

        // Test correct response
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_AUTHENTICATED, $service->authenticate( 'test-auth-user', $userSecret, $session_key, $response ) );
    }

}
