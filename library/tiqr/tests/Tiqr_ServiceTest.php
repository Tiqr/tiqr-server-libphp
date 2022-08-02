<?php

require_once 'tiqr_autoloader.inc';

require_once __DIR__ . '/../Tiqr/OATH/OCRA.php';

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
            'apns.environment' => 'sandbox',
            'statestorage' => [
                'type' => 'file',
                'path' => '/tmp',
            ],
            'devicestorage' => [
                'type' => 'dummy',
            ]
        );
    }

    // Test creating a new tiqr service
    public function testDefaultConstructor() {
        $_SERVER['SERVER_NAME'] = 'dummy.example.org';
        $service = new Tiqr_Service($this->logger, ['statestorage' => ['type' => 'file', 'path' => '/src']]);
        $this->assertInstanceOf(Tiqr_Service::class, $service);
    }

    // Test creating a new tiqr service
    public function testDefaultConstructorPathOnFileStateStorageIsRequired() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The path is missing in the StateStorage configuration');
        $service = new Tiqr_Service($this->logger, ['statestorage' => ['type' => 'file']]);
        $this->assertInstanceOf(Tiqr_Service::class, $service);
    }

    public function testOptions() {
        $service = new Tiqr_Service($this->logger, $this->getOptions());
        $this->assertInstanceOf(Tiqr_Service::class, $service);
        $this->assertSame($service->getIdentifier(), 'test.identifier.example.org');
    }

    public function testUniversalLinkEnrollURL() {
        $options=$this->getOptions();
        $options['enroll.protocol']='https://example.com/tiqrenroll';
        $service = new Tiqr_Service($this->logger, $options);
        $enrollment_key = $service->startEnrollmentSession('test_user_id', 'Test User Name', '');
        $metadataUrl='https://example.com/metadata/?key='.$enrollment_key;
        $enroll_string = $service->generateEnrollString($metadataUrl);
        $result = parse_url($enroll_string, -1);
        $this->assertEquals('https', $result['scheme']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals('/tiqrenroll', $result['path']);
        parse_str($result['query'], $result);
        $this->assertEquals($metadataUrl, $result['metadata']);
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
        // Expect a hex encoded key of Tiqr_Service::SESSION_KEY_LENGTH_BYTES * 2 long
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $enrollment_key);
        $this->assertEquals(Tiqr_Service::SESSION_KEY_LENGTH_BYTES * 2, strlen($enrollment_key));
        $this->assertTrue( Tiqr_Service::SESSION_KEY_LENGTH_BYTES >= 16, 'SECURITY: Review length of SESSION_KEY_LENGTH_BYTES');

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
        $this->assertEquals(Tiqr_Service::SESSION_KEY_LENGTH_BYTES * 2, strlen($enrollment_secret));
        $this->assertTrue( Tiqr_Service::SESSION_KEY_LENGTH_BYTES >= 16, 'SECURITY: Review length of SESSION_KEY_LENGTH_BYTES');


        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED);    // Status remains unchanged

        // 3b - We generate the metadata to return to the phone
        $enrollment_url = 'https://test.example.org/enrollment_url/' . $enrollment_secret;
        $enrollemnt_metadata = $service->getEnrollmentMetadata($enrollment_key, $authentication_url, $enrollment_url);
        // To return to the phone: json_encode($enrollemnt_metadata);
        $this->assertIsArray($enrollemnt_metadata);

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_RETRIEVED);

        try {
            $service->getEnrollmentMetadata($enrollment_key, $authentication_url, $enrollment_url);
            $this->fail('Expected exception');
        } catch (Exception $e) {}


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
        // This invalidates the enrollment secret
        // During a normal enrollment the server would store the userid together with the secret just before or after
        // calling finalizeEnrollment
        $this->assertEquals(true, $service->finalizeEnrollment($enrollment_secret));

        $status = $service->getEnrollmentStatus($session_id);
        $this->assertSame($status, Tiqr_Service::ENROLLMENT_STATUS_FINALIZED);

        // Finalizing enrollment twice fails
        $this->assertEquals(false, $service->finalizeEnrollment($enrollment_secret));

        // Check that the $enrollment_secret is now invalid
        try {
            $service->validateEnrollmentSecret($enrollment_secret);
            $this->fail('Expected exception');
        } catch (Exception $e) {}
    }

    public function testUniversalLinkAuthURLWithUser() {
        $options=$this->getOptions();
        $options['auth.protocol']='https://example.com/tiqrauth';
        $service = new Tiqr_Service($this->logger, $options);
        $session_key = $service->startAuthenticationSession('test_user_id', '' );
        $authUrl=$service->generateAuthURL($session_key);

        $result = parse_url($authUrl, -1);
        $this->assertEquals('https', $result['scheme']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals('/tiqrauth', $result['path']);
        parse_str($result['query'], $result);
        $this->assertEquals('test_user_id', $result['u']);
        $this->assertEquals($session_key, $result['s']);
        $this->assertTrue(strlen($result['q']) == 10 );
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $result['q']);
        $this->assertEquals('test.identifier.example.org', $result['i']);
        $this->assertEquals(2, $result['v']);
    }

    public function testUniversalLinkAuthURLWithoutUser() {
        $options=$this->getOptions();
        $options['auth.protocol']='https://example.com/tiqrauth';
        $service = new Tiqr_Service($this->logger, $options);
        $session_key = $service->startAuthenticationSession('', '' );
        $authUrl=$service->generateAuthURL($session_key);

        $result = parse_url($authUrl, -1);
        $this->assertEquals('https', $result['scheme']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals('/tiqrauth', $result['path']);
        parse_str($result['query'], $result);
        $this->assertFalse(isset($result['u']));
        $this->assertEquals($session_key, $result['s']);
        $this->assertTrue(strlen($result['q']) == 10 );
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $result['q']);
        $this->assertEquals('test.identifier.example.org', $result['i']);
        $this->assertEquals(2, $result['v']);
    }

    function testAuthentication() {
        // Create unique session ID
        $session_id = 'test_session_id_'.time();

        // Create tiqr service
        $service = new Tiqr_Service($this->logger, $this->getOptions());
        $this->assertInstanceOf(Tiqr_Service::class, $service);

        $userid = 'test-auth-user'; // The user to authenticate

        // No authenticated user in a session that does not exist
        $this->assertNull($service->getAuthenticatedUser($session_id));

        // Here we provide the userID, it will be put in the AuthURL. I.e. the stepup scenario
        // For the login scenario, where the server does not know the userid yet userid is left blank
        $session_key = $service->startAuthenticationSession($userid, $session_id );
        $this->assertIsString($session_key);
        $this->assertRegExp('/^([0-9a-z][0-9a-z])+$/', $session_key);
        $this->assertEquals(Tiqr_Service::SESSION_KEY_LENGTH_BYTES * 2, strlen($session_key));
        $this->assertTrue( Tiqr_Service::SESSION_KEY_LENGTH_BYTES >= 16, 'SECURITY: Review length of SESSION_KEY_LENGTH_BYTES');


        // No authenticated user in new session
        $this->assertNull($service->getAuthenticatedUser($session_id));

        // Generate auth URL for in QR code
        // The generated URL has the format:
        // testauth://test-auth-user@test.identifier.example.org/$session_key/$challenge/test.identifier.example.org/2
        // 0        1 2                                          3            4          5                           6
        $authUrl=$service->generateAuthURL($session_key);
        $this->assertIsString($authUrl);
        $this->assertNotEmpty($authUrl);

        // Get info from the auth URL like a tiqr client would
        $exploded = explode('/', $authUrl);
        $session_key_from_auth_url = $exploded[3]; // hex encoded session
        $challenge_from_auth_url = $exploded[4];   // 10 digit hex challenge
        $protocol_version = $exploded[6];
        $this->assertEquals($session_key, $session_key_from_auth_url);
        $this->assertEquals(2, $protocol_version);

        // The shared secret between tiqr client and tiqr server
        $userSecret = '3132333435363738393031323334353637383930313233343536373839303132';

        // Calculate a response like a tiqr client would do using the information from the auth URL
        $response = OCRA::generateOCRA( 'OCRA-1:HOTP-SHA1-6:QH10-S', $userSecret, '', $challenge_from_auth_url, '', $session_key_from_auth_url, '');

        // Test invalid response. 1234567 is always an invalid response, responses are 6 digits.
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE, $service->authenticate( 'test-auth-user', $userSecret, $session_key, '1234567' ) );
        // No authenticated after authentication error
        $this->assertNull($service->getAuthenticatedUser($session_id));

        // Authentication with an invalid session key fails with AUTH_RESULT_INVALID_CHALLENGE
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE, $service->authenticate( 'test-auth-user', $userSecret, 'session-key-that-does-not-exist', $response ) );
        // Not authenticated after authentication error
        $this->assertNull($service->getAuthenticatedUser($session_id));

        // Test invalid user id
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_INVALID_USERID, $service->authenticate( 'invalid-user', $userSecret, $session_key, $response ) );
        // Not authenticated after authentication error
        $this->assertNull($service->getAuthenticatedUser($session_id));
        
        // Test correct response
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_AUTHENTICATED, $service->authenticate( 'test-auth-user', $userSecret, $session_key, $response ) );
        // Authenticated!
        $this->assertEquals( 'test-auth-user', $service->getAuthenticatedUser($session_id));

        // Second authentication fails because session is deleted
        $this->assertEquals(Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE, $service->authenticate( 'test-auth-user', $userSecret, $session_key, $response ) );
        // Still authenticated
        $this->assertEquals( 'test-auth-user', $service->getAuthenticatedUser($session_id));

        $service->logout('invalid-session-id');
        // Still authenticated
        $this->assertEquals( 'test-auth-user', $service->getAuthenticatedUser($session_id));

        $service->logout($session_id);
        // Not authenticated after logout
        $this->assertNull($service->getAuthenticatedUser($session_id));
    }

}
