<?php
/**
 * This file is part of the tiqr project.
 * 
 * The tiqr project aims to provide an open implementation for 
 * authentication using mobile devices. It was initiated by 
 * SURFnet and developed by Egeniq.
 *
 * More information: http://www.tiqr.org
 *
 * @author Ivo Jansch <ivo@egeniq.com>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2011 SURFnet BV
 */

/** 
 * @internal includes of utility classes
 */
require_once("Tiqr/StateStorage.php");
require_once("Tiqr/DeviceStorage.php");
require_once("Tiqr/Random.php");

require_once("Tiqr/OcraService.php");

use Psr\Log\LoggerInterface;

/** 
 * The main Tiqr Service class.
 * This is the class that an application interacts with to provide mobile 
 * authentication
 * @author ivo
 *
 */
class Tiqr_Service
{
    /**
     * @internal Various variables internal to the service class
     */
    /** @var array  */
    protected $_options;

    /** @var string */
    protected $_protocolAuth;
    /** @var string */
    protected $_protocolEnroll;
    /** @var string */
    protected $_identifier;
    /** @var string */
    protected $_ocraSuite;
    /** @var string */
    protected $_name;
    /** @var string */
    protected $_logoUrl;
    /** @var string */
    protected $_infoUrl;
    /** @var int */
    protected $_protocolVersion;
    /** @var Tiqr_StateStorage_StateStorageInterface */
    protected $_stateStorage;
    /** @var Tiqr_DeviceStorage_Abstract */
    protected $_deviceStorage;
    /** @var Tiqr_OcraService_Interface */
    protected $_ocraService;
    /** @var string */
    protected $_stateStorageSalt; // The salt used for creating stable hashes for use with the StateStorage

    /** @var LoggerInterface */
    private $logger;

    /**
     * Enrollment status codes
     */
    // IDLE: There is no enrollment going on in this session, or there was an error getting the enrollment status
    const ENROLLMENT_STATUS_IDLE = 1;
    // INITIALIZED: The enrollment session was started but the tiqr client has not retrieved the metadata yet
    const ENROLLMENT_STATUS_INITIALIZED = 2;
    // RETRIEVED: The tiqr client has retrieved the metadata
    const ENROLLMENT_STATUS_RETRIEVED = 3;
    // PROCESSED: The tiqr client has sent back the tiqr authentication secret
    const ENROLLMENT_STATUS_PROCESSED = 4;
    // FINALIZED: The server has stored the authentication secret
    const ENROLLMENT_STATUS_FINALIZED = 5;
    // VALIDATED: A first successful authentication was performed
    // Note: Not currently used
    const ENROLLMENT_STATUS_VALIDATED = 6;

    /**
     * Prefixes for StateStorage keys
     */
    const PREFIX_ENROLLMENT_SECRET = 'enrollsecret';
    const PREFIX_ENROLLMENT = 'enroll';
    const PREFIX_CHALLENGE = 'challenge';
    const PREFIX_ENROLLMENT_STATUS = 'enrollstatus';
    const PREFIX_AUTHENTICATED = 'authenticated_';

    /**
     * Default timeout values
     */
    const LOGIN_EXPIRE      = 3600; // Logins timeout after an hour
    const ENROLLMENT_EXPIRE = 300; // If enrollment isn't completed within 5 minutes, we discard data
    const CHALLENGE_EXPIRE  = 180; // If login is not performed within 3 minutes, we discard the challenge

    /**
     * Authentication result codes
     */
    // INVALID_REQUEST: Not currently used by the Tiqr_service
    const AUTH_RESULT_INVALID_REQUEST   = 1;
    // AUTHENTICATED: The user was successfully authenticated
    const AUTH_RESULT_AUTHENTICATED     = 2;
    // INVALID_RESPONSE: The response that was returned by the client was not correct
    const AUTH_RESULT_INVALID_RESPONSE  = 3;
    // INVALID_CHALLENGE: The server could find the challenge in its state storage. It may have been expired or the
    // client could have sent an invalid sessionKey
    const AUTH_RESULT_INVALID_CHALLENGE = 4;
    // INVALID_USERID: The client authenticated a different user than the server expected. This error is returned when
    // the application stated an authentication session specifying the userId and later during the authentication
    // provides a different userId
    const AUTH_RESULT_INVALID_USERID    = 5;
    
    /**
     * The default OCRA Suite (RFC 6287) to use for authentication in Tiqr
     * This basically calculates the HMAC-SHA1 over a buffer with:
     * - A 10 hex digit long challenge
     * - authentication session ID (32 hex digits)
     * - client secret key (64 hex digits)
     * and then from the calculated HMAC-SHA1 calculates a 6 decimal digit long response
     * This means that a client has a 1 in 10^6 chance of guessing the right response.
     * This is a tradeoff between having responses that a user can easily copy during offline authentication
     * and resistance against guessing.
     * The application must implement anti-guessing counter measures, e.g. locking an account after N-tries when using
     * the default of 6.
     * Chances of correctly guessing a 6 digit response code ofter N tries (calculated by multiplying N floats, YMMV):
     * N=1: 1/10^6 = 0.0001%; N=2: 0.0003%; N=3: 0.0006%; N=4: 0,0010%; N=5: 0,0015%; N=6: 0,0021%; N=7: 0,0028%;
     * N=8: 0,0036%; N=9: 0,0045%; N=10: 0,0055%l N=20: 0,0210; N=50: 0,1274%; N=100: 0,5037%; N=200: 1,708%
     */
    const DEFAULT_OCRA_SUITE = "OCRA-1:HOTP-SHA1-6:QH10-S";

    /**
     * session keys are used in multiple places during authentication and enrollment
     * and are generated by _uniqueSessionKey() using a secure pseudo-random number generator
     * SESSION_KEY_LENGTH_BYTES specifies the number of bytes of entropy in these keys.
     * Session keys are HEX encoded, so a 16 byte key (128 bits) will be 32 characters long
     *
     * We guarantee uniqueness by using a sufficiently number of bytes
     * By using 16 bytes (128 bits) we can expect a collision after having
     * generated 2^64 IDs. This more than enough for our purposes, the session
     * keys in the tiqr protocol are not persisted and have a lifetime of no
     * more than a few minutes
     *
     * It must be infeasible for an attacker to predict or guess session keys during enrollment
     * 128 bits should be sufficiently long for this purpose because of the short
     * lifetime of these keys
     *
     * A session key is used as session information in the OCRA authentication. Even if the session keys, challenges
     * and the correct responses of many authentications are known to an attacker it should be infeasible to
     * get the user secret as that is equivalent to reversing a hmac sha1 of a string the length of the secret
     * (32 bytes - 2^256 possibilities for a typical tiqr client implementation)
     *
     * When using the tiqr v1 protocol, with the v1 version of the OCRAWrapper, the library used
     * 16 bytes keys (i.e. 32 hex digits long). When using the v2 algorithm 32 byte keys (64 hex digits long) were
     * used.
     * 16 bytes should be more than enough. Using 32 bytes makes the QR codes bigger, because both for
     * authentication and enrollment a session key is embedded in the uri that is encoded in the QR code.
     */
    const SESSION_KEY_LENGTH_BYTES = 16;

    /**
     * Construct an instance of the Tiqr_Service. 
     * The server is configured using an array of options. All options have
     * reasonable defaults but it's recommended to at least specify a custom 
     * name and identifier and a randomly generated sessions secret.
     * If you use the Tiqr Service with your own apps, you must also specify
     * a custom auth.protocol and enroll.protocol specifier.
     * 
     * The options are:
     * - auth.protocol: The protocol specifier (e.g. "tiqrauth") that the server uses to communicate challenge urls to the
     *                  iOS/Android tiqr app. This must match the url handler specified in the iPhone app's build
     *                  settings. Do not add the '://', just the protocolname. Default: "tiqr"
     * - enroll.protocol: The protocol specifier for enrollment urls. Do not add the '://', just the protocolname.
     *                    Default: "tiqrenroll"
     *
     * - ocra.suite: The OCRA suite to use. Defaults to DEFAULT_OCRA_SUITE.
     *
     * - identifier: A short ASCII identifier for your service. Defaults to the SERVER_NAME of the server. This is what
     *               a tiqr client will use to identify the server.
     * - name: A longer description of your service. Defaults to the SERVER_NAME of the server. A descriptive name for
     *         display purposes
     *
     * - logoUrl: A full http url pointing to a logo for your service.
     * - infoUrl: An http url pointing to an info page of your service
     *
     * - ocraservice: Configuration for the OcraService to use.
     *                - type: The ocra service type. (default: "tiqr")
     *                - parameters depending on the ocra service. See classes inside to OcraService directory for
     *                  supported types and their parameters.
     *
     * - statestorage: An array with the configuration of the storage for temporary data. It has the following sub keys:
     *                 - type: The type of state storage. (default: "file")
     *                 - salt: The salt is used to hash the keys used the StateStorage
     *                 - parameters depending on the storage. See the classes inside the StateStorage folder for
     *                   supported types and their parameters.
     *
     *
     *  * For sending push notifications using the Apple push notification service (APNS)
     * - apns.certificate: The location of the file with the Apple push notification client certificate and private key
     *                     in PEM format.
     *                     Defaults to ../certificates/cert.pem
     * - apns.environment: Whether to use apple's "sandbox" or "production" apns environment
     * * For sending push notifications to Android devices using Google's firebase cloud messaging (FCM) API
     * - firebase.apikey: String containing the FCM API key
     *
     * - devicestorage: An array with the configuration of the storage for device push notification tokens. Only
     *                  necessary if you use the Tiqr Service to authenticate an already known userId (e.g. when using
     *                  tiqr a second authentication factor AND are using a tiqr client that uses the token exchange.
     *                  It has the following
     *                  keys:
     *                  - type: The type of  storage. (default: "dummy")
     *                  - parameters depending on the storage. See the classes inside the DeviceStorage folder for
     *                    supported types and their parameters.
     **
     * @param LoggerInterface $logger
     * @param array $options
     * @param int $version The tiqr protocol version to use (defaults to the latest)
     * @throws Exception
     */
    public function __construct(LoggerInterface $logger, array $options=array(), int $version = 2)
    {
        $this->_options = $options; // Used to later get settings for Tiqr_Message_*
        $this->logger = $logger;
        $this->_protocolAuth = $options["auth.protocol"] ?? 'tiqr';
        $this->_protocolEnroll = $options["enroll.protocol"] ?? 'tiqrenroll';
        $this->_ocraSuite = $options["ocra.suite"] ?? self::DEFAULT_OCRA_SUITE;
        $this->_identifier = $options["identifier"] ?? $_SERVER["SERVER_NAME"];
        $this->_name = $options["name"] ?? $_SERVER["SERVER_NAME"];
        $this->_logoUrl = $options["logoUrl"] ?? '';
        $this->_infoUrl = $options["infoUrl"] ?? '';

        // An idea is to create getStateStorage, getDeviceStorage and getOcraService functions to create these functions
        // at the point that we actually need them.

        // Create StateStorage
        if (!isset($options["statestorage"])) {
            throw new RuntimeException('No state storage configuration is configured, please provide one');
        }
        $this->_stateStorage = Tiqr_StateStorage::getStorage($options["statestorage"]["type"], $options["statestorage"], $logger);
        // Set a default salt, with the SESSION_KEY_LENGTH_BYTES (16) length keys we're using a publicly
        // known salt already gives excellent protection.
        $this->_stateStorageSalt = $options["statestorage"]['salt'] ?? '8xwk2pFd';

        // Create DeviceStorage - required when using Push Notification with a token exchange
        if (isset($options["devicestorage"])) {
            $this->_deviceStorage = Tiqr_DeviceStorage::getStorage($options["devicestorage"]["type"], $options["devicestorage"], $logger);
        } else {
            $this->_deviceStorage = Tiqr_DeviceStorage::getStorage('dummy', array(), $logger);
        }

        // Set Tiqr protocol version, only version 2 is currently supported
        if ($version !== 2) {
            throw new Exception("Unsupported protocol version '${version}'");
        }
        $this->_protocolVersion = $version;

        // Create OcraService
        // Library versions before 3.0 (confusingly) used the usersecretstorage key for this configuration
        // and used 'tiqr' as type when no type explicitly set to oathserviceclient was configured
        if (isset($options['ocraservice']) && $options['ocraservice']['type'] != 'tiqr') {
            $options['ocraservice']['ocra.suite'] = $this->_ocraSuite;
            $this->_ocraService = Tiqr_OcraService::getOcraService($options['ocraservice']['type'], $options['ocraservice'], $logger);
        }
        else { // Create default ocraservice
            $this->_ocraService = Tiqr_OcraService::getOcraService('tiqr', array('ocra.suite' => $this->_ocraSuite), $logger);
        }
    }
    
    /**
     * Get the identifier of the service.
     * @return String identifier
     */
    public function getIdentifier(): string
    {
        return $this->_identifier;
    }
    
    /**
     * Generate an authentication challenge QR image and send it directly to 
     * the browser.
     * 
     * In normal authentication mode, you would not specify a userId - however
     * in step up mode, where a user is already authenticated using a
     * different mechanism, pass the userId of the authenticated user to this 
     * function. 
     * @param String $sessionKey The sessionKey identifying this auth session (typically returned by startAuthenticationSession)
     * @throws Exception
     */
    public function generateAuthQR(string $sessionKey): void
    {
        $challengeUrl = $this->_getChallengeUrl($sessionKey);

        $this->generateQR($challengeUrl);
    }

    /**
     * Generate a QR image and send it directly to
     * the browser.
     *
     * @param String $s The string to be encoded in the QR image
     */
    public function generateQR(string $s): void
    {
        QRcode::png($s, false, 4, 5);
    }

    /**
     * Send a push notification to a user containing an authentication challenge
     * @param String $sessionKey          The session key identifying this authentication session
     * @param String $notificationType    Notification type returned by the tiqr client: APNS, GCM, FCM, APNS_DIRECT or FCM_DIRECT
     * @param String $notificationAddress Notification address, e.g. device token, phone number etc.
     **
     * @throws Exception
     */
    public function sendAuthNotification(string $sessionKey, string $notificationType, string $notificationAddress): void
    {
        $message = NULL;
        try {
            switch ($notificationType) {
                case 'APNS':
                case 'APNS_DIRECT':
                    $message = new Tiqr_Message_APNS($this->_options);
                    break;

                case 'GCM':
                case 'FCM':
                case 'FCM_DIRECT':
                    $message = new Tiqr_Message_FCM($this->_options);
                    break;

                default:
                    throw new InvalidArgumentException("Unsupported notification type '$notificationType'");
            }

            $this->logger->info(sprintf('Creating and sending a %s push notification', $notificationType));
            $message->setId(time());
            $message->setText("Please authenticate for " . $this->_name);
            $message->setAddress($notificationAddress);
            $message->setCustomProperty('challenge', $this->_getChallengeUrl($sessionKey));
            $message->send();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Sending "%s" push notification to address "%s" failed', $notificationType, $notificationAddress),
                array('exception' =>$e)
            );
            throw $e;
        }
    }

    /** 
     * Generate an authentication challenge URL.
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     * @param String $sessionKey The session key identifying this authentication session
     *
     * @return string Authentication URL for the tiqr client
     * @throws Exception
     */
    public function generateAuthURL(string $sessionKey): string
    {
        $challengeUrl = $this->_getChallengeUrl($sessionKey);  
        
        return $challengeUrl;
    }

    /**
     * Start an authentication session. This generates a challenge for this
     * session and stores it in memory. The returned sessionKey should be used
     * throughout the authentication process.
     *
     * @param String $userId The userId of the user to authenticate (optional), if this is left empty the
     *                       the client decides
     * @param String $sessionId The session id the application uses to identify its user sessions;
     *                          (optional defaults to the php session id).
     *                          This sessionId can later be used to get the authenticated user from the application
     *                          using getAuthenticatedUser(), or to clear the authentication state using logout()
     * @param String $spIdentifier If SP and IDP are 2 different things, pass the url/identifier of the SP the user is logging into.
     *                             For setups where IDP==SP, just leave this blank.
     * @return string The authentication sessionKey
     * @throws Exception when starting the authentication session failed
     */
    public function startAuthenticationSession(string $userId="", string $sessionId="", string $spIdentifier=""): string
    {
        if ($sessionId=="") {
            $sessionId = session_id();
        }

        if ($spIdentifier=="") {
            $spIdentifier = $this->_identifier;
        }

        $sessionKey = $this->_uniqueSessionKey();
        $challenge = $this->_ocraService->generateChallenge();
        
        $data = array("sessionId"=>$sessionId, "challenge"=>$challenge, "spIdentifier" => $spIdentifier);
        
        if ($userId!="") {
            $data["userId"] = $userId;
        }
        
        $this->_setStateValue(self::PREFIX_CHALLENGE, $sessionKey, $data, self::CHALLENGE_EXPIRE);
       
        return $sessionKey;
    }
    
    /**
     * Start an enrollment session. This can either be the enrollment of a new 
     * user or of an existing user, there is no difference from Tiqr's point
     * of view.
     * 
     * The call returns the temporary enrollmentKey that the phone needs to 
     * retrieve the metadata; you must therefor embed this key in the metadata
     * URL that you communicate to the phone.
     * 
     * @param String $userId The user's id
     * @param String $displayName The user's full name
     * @param String $sessionId The application's session identifier (defaults to php session)
     * @return String The enrollment key
     * @throws Exception when start the enrollement session failed
     */
    public function startEnrollmentSession(string $userId, string $displayName, string $sessionId=""): string
    {
        if ($sessionId=="") {
            $sessionId = session_id();
        }
        $enrollmentKey = $this->_uniqueSessionKey();
        $data = [
            "userId" => $userId,
            "displayName" => $displayName,
            "sessionId" => $sessionId
        ];
        $this->_setStateValue(self::PREFIX_ENROLLMENT, $enrollmentKey, $data, self::ENROLLMENT_EXPIRE);
        $this->_setEnrollmentStatus($sessionId, self::ENROLLMENT_STATUS_INITIALIZED);

        return $enrollmentKey;
    }

    /**
     * Reset an existing enrollment session. (start over)
     * @param string $sessionId The application's session identifier (defaults to php session)
     * @throws Exception when resetting the session failed
     */
    public function resetEnrollmentSession(string $sessionId=""): void
    {
        if ($sessionId=="") {
            $sessionId = session_id();
        }

        $this->_setEnrollmentStatus($sessionId, self::ENROLLMENT_STATUS_IDLE);
    }

    /**
     * Remove enrollment data based on the enrollment key (which is
     * encoded in the enrollment QR code).
     *
     * @param string $enrollmentKey returned by startEnrollmentSession
     * @throws Exception when clearing the enrollment state failed
     */
    public function clearEnrollmentState(string $enrollmentKey): void
    {
        $value = $this->_getStateValue(self::PREFIX_ENROLLMENT, $enrollmentKey);
        if (is_array($value) && array_key_exists('sessionId', $value)) {
            // Reset the enrollment session (used for polling the status of the enrollment)
            $this->resetEnrollmentSession($value['sessionId']);
        }
        // Remove the enrollment data for a specific enrollment key
        $this->_unsetStateValue(self::PREFIX_ENROLLMENT, $enrollmentKey);
    }

    /**
     * Retrieve the enrollment status of an enrollment session.
     * 
     * @param String $sessionId the application's session identifier 
     *                          (defaults to php session)
     * @return int Enrollment status.
     * @see Tiqr_Service for a definitation of the enrollment status codes
     *
     * @throws Exception when an error communicating with the state storage backend was detected
     */
    public function getEnrollmentStatus(string $sessionId=""): int
    { 
        if ($sessionId=="") {
            $sessionId = session_id(); 
        }
        $status = $this->_getStateValue(self::PREFIX_ENROLLMENT_STATUS, $sessionId);
        if (is_null($status)) return self::ENROLLMENT_STATUS_IDLE;
        return $status;
    }
        
    /**
     * Generate an enrollment QR code and send it to the browser.
     * @param String $metadataUrl The URL you provide to the phone to retrieve
     *                            metadata. This URL must contain the enrollmentKey
     *                            provided by startEnrollmentSession (you can choose
     *                            the variable name as you are responsible yourself
     *                            for retrieving this from the request and passing it
     *                            on to the Tiqr server.
     */
    public function generateEnrollmentQR(string $metadataUrl): void
    { 
        $enrollmentString = $this->_getEnrollString($metadataUrl);
        
        QRcode::png($enrollmentString, false, 4, 5);
    }

    /**
     * Generate an enrol string
     * This string can be used to feed to a QR code generator
     */
    public function generateEnrollString(string $metadataUrl): string
    {
        return $this->_getEnrollString($metadataUrl);
    }
    
    /**
     * Retrieve the metadata for an enrollment session.
     * 
     * When the phone calls the url that you have passed to
     * generateEnrollmentQR, you must provide it with the output
     * of this function. (Don't forget to json_encode the output.)
     * 
     * Note, you can call this function only once, as the enrollment session
     * data will be destroyed as soon as it is retrieved.
     *
     * When successful the enrollment status will be set to ENROLLMENT_STATUS_RETRIEVED
     *
     * @param String $enrollmentKey The enrollmentKey that the phone has posted along with its request.
     * @param String $authenticationUrl The url you provide to the phone to post authentication responses
     * @param String $enrollmentUrl The url you provide to the phone to post the generated user secret. You must include
     *                              a temporary enrollment secret in this URL to make this process secure.
     *                              Use getEnrollmentSecret() to get this secret
     * @return array An array of metadata that the phone needs to complete
     *               enrollment. You must encode it in JSON before you send
     *               it to the phone.
     * @throws Exception when generating the metadata failed
     */
    public function getEnrollmentMetadata(string $enrollmentKey, string $authenticationUrl, string $enrollmentUrl): array
    {
        $data = $this->_getStateValue(self::PREFIX_ENROLLMENT, $enrollmentKey);
        if (!is_array($data)) {
            $this->logger->error('Unable to find enrollment metadata in state storage');
            throw new Exception('Unable to find enrollment metadata in state storage');
        }

        $metadata = array("service"=>
                               array("displayName"       => $this->_name,
                                     "identifier"        => $this->_identifier,
                                     "logoUrl"           => $this->_logoUrl,
                                     "infoUrl"           => $this->_infoUrl,
                                     "authenticationUrl" => $authenticationUrl,
                                     "ocraSuite"         => $this->_ocraSuite,
                                     "enrollmentUrl"     => $enrollmentUrl
                               ),
                          "identity"=>
                               array("identifier" =>$data["userId"],
                                     "displayName"=>$data["displayName"]));

        $this->_unsetStateValue(self::PREFIX_ENROLLMENT, $enrollmentKey);

        $this->_setEnrollmentStatus($data["sessionId"], self::ENROLLMENT_STATUS_RETRIEVED);
        return $metadata;
    }

    /** 
     * Get a temporary enrollment secret to be able to securely post a user 
     * secret.
     *
     * In the last step of the enrollment process the phone will send the OCRA user secret.
     * This is the shared secret is used in the authentication process. To prevent an
     * attacker from impersonating a user during enrollment and post a user secret that is known to the attacker,
     * a temporary enrollment secret is added to the metadata. This secret must be included in the enrollmentUrl that is
     * passed to the getMetadata function so that when the client sends the OCRA user secret to the server this
     * enrollment secret is included. The server uses the enrollment secret to authenticate the client, and will
     * allow only one submission of a user secret for one enrollment secret.
     *
     * You MUST use validateEnrollmentSecret() to validate enrollment secret that the client sends before accepting
     * the associated OCRA client secret
     *
     * @param String $enrollmentKey The enrollmentKey generated by startEnrollmentSession() at the start of the
     *                              enrollment process.
     * @return String The enrollment secret
     * @throws Exception when generating the enrollment secret failed
     */
    public function getEnrollmentSecret(string $enrollmentKey): string
    {
         $data = $this->_getStateValue(self::PREFIX_ENROLLMENT, $enrollmentKey);
         if (!is_array($data)) {
             $this->logger->error('getEnrollmentSecret: enrollment key not found');
             throw new RuntimeException('enrollment key not found');
         }
         $userId = $data["userId"] ?? NULL;
         $sessionId = $data["sessionId"] ?? NULL;
         if (!is_string($userId) || !(is_string($sessionId))) {
             throw new RuntimeException('getEnrollmentSecret: invalid enrollment data');
         }
         $enrollmentData = [
             "userId" => $userId,
             "sessionId" => $sessionId
         ];
         $enrollmentSecret = $this->_uniqueSessionKey();
         $this->_setStateValue(
             self::PREFIX_ENROLLMENT_SECRET,
             $enrollmentSecret,
             $enrollmentData,
             self::ENROLLMENT_EXPIRE
         );
         return $enrollmentSecret;
    }

    /**
     * Validate if an enrollmentSecret that was passed from the phone is valid.
     *
     * Note: After validating the enrollmentSecret you must call finalizeEnrollment() to
     *       invalidate the enrollment secret.
     *
     * When successful the enrollment state will be set to ENROLLMENT_STATUS_PROCESSED
     *
     * @param string $enrollmentSecret The enrollmentSecret that the phone posted; it must match
     *                                 the enrollmentSecret that was generated using
     *                                 getEnrollmentSecret earlier in the process and that the phone
     *                                 received as part of the metadata.
     *                                 Note that this is not the OCRA user secret that the Phone posts to the server
     * @return string The userid of the user that was being enrolled if the enrollment secret is valid. The application
     *                should use this userid to store the OCRA user secret that the phone posted.
     *
     * @throws Exception when the validation failed
     */
    public function validateEnrollmentSecret(string $enrollmentSecret): string
    {
        try {
            $data = $this->_getStateValue(self::PREFIX_ENROLLMENT_SECRET, $enrollmentSecret);
            if (NULL === $data) {
                throw new RuntimeException('Enrollment secret not found');
            }
            if ( !is_array($data) || !is_string($data["userId"] ?? NULL)) {
                throw new RuntimeException('Invalid enrollment data');
            }

            // Secret is valid, application may accept the user secret.
            $this->_setEnrollmentStatus($data["sessionId"], self::ENROLLMENT_STATUS_PROCESSED);
            return $data["userId"];
        } catch (Exception $e) {
            $this->logger->error('Validation of enrollment secret failed', array('exception' => $e));
            throw $e;
        }
    }

    /**
     * Finalize the enrollment process.
     *
     * Invalidates $enrollmentSecret
     *
     * Call this after validateEnrollmentSecret
     * When successfull the enrollment state will be set to ENROLLMENT_STATUS_FINALIZED
     *
     * @param String The enrollment secret that was posted by the phone. This is the same secret used in the call to
     *               validateEnrollmentSecret()
     * @return bool true when finalize was successful, false otherwise
     *
     * Does not throw
     */
    public function finalizeEnrollment(string $enrollmentSecret): bool
    {
        try {
            $data = $this->_getStateValue(self::PREFIX_ENROLLMENT_SECRET, $enrollmentSecret);
            if (NULL === $data) {
                throw new RuntimeException('Enrollment secret not found');
            }
            if (is_array($data)) {
                // Enrollment is finalized, destroy our session data.
                $this->_unsetStateValue(self::PREFIX_ENROLLMENT_SECRET, $enrollmentSecret);
                $this->_setEnrollmentStatus($data["sessionId"], self::ENROLLMENT_STATUS_FINALIZED);
            } else {
                $this->logger->error(
                    'Enrollment status is not finalized, enrollmentsecret was not found in state storage. ' .
                    'Warning! the method will still return "true" as a result.'
                );
            }
            return true;
        } catch (Exception $e) {
            // Cleanup failed
            $this->logger->warning('finalizeEnrollment failed', array('exception' => $e));
        }
        return false;
    }

    /**
     * Authenticate a user.
     * This method should be called when the phone (tiqr client) posts a response to an
     * authentication challenge to the server. This method will validate the response and
     * returns one of the self::AUTH_RESULT_* codes to indicate success or error
     *
     * When the authentication was successful the user's session is marked as authenticated.
     * This essentially logs the user in. Use getauthenticateduser() and logout() with the
     * application's session sessionID to respectively get the authenticated user and clear
     * the authentication state.
     *
     * The default OCRA suite uses 6 digit response codes this makes the authentication vulnerable to a guessing attack
     * when the client has an unlimited amount of tries. It is important to limit the amount of times to allow a
     * AUTH_RESULT_INVALID_RESPONSE response. AUTH_RESULT_INVALID_RESPONSE counts as failed authentication attempt
     * (i.e. a wrong guess by the client). The other error results and exceptions mean that the response could
     * not be validated on the server and should therefore not reveal anything useful to the client.
     * The UserStorage class supports (temporarily) locking a user account. It is the responsibility of the application
     * to implement these measures
     *
     * @param String $userId The userid of the user that should be authenticated, as sent in the POST back by the tiqr
     *                       client. If $userId does not match the optional userId in startAuthenticationSession()
     *                       AUTH_RESULT_INVALID_USERID is returned
     * @param String $userSecret The OCRA user secret that the application previously stored for $userId using
     *                           e.g. a Tiqr_UserSecretStorage
     *                           Leave empty when using a OcraService that does not require a user secret
     * @param String $sessionKey The authentication session key that was returned by startAuthenticationSession()
     *                           If the session key cannot be found in the StateStorage AUTH_RESULT_INVALID_CHALLENGE
     *                           is returned
     * @param String $response   The response to the challenge that the tiqr client posted back to the server
     *
     * @return Int The result of the authentication. This is one of the AUTH_RESULT_* constants of the Tiqr_Server class.
     * @throws Exception when there was an error during the authentication process
     */
    public function authenticate(string $userId, string $userSecret, string $sessionKey, string $response): int
    {
        try {
            $state = $this->_getStateValue(self::PREFIX_CHALLENGE, $sessionKey);
            if (is_null($state)) {
                $this->logger->notice('The auth challenge could not be found in the state storage');
                return self::AUTH_RESULT_INVALID_CHALLENGE;
            }
        } catch (Exception $e) {
            $this->logger->error('Error looking up challenge in state storage', array('exception' => $e));
            throw $e;
        }

        $sessionId = $state["sessionId"] ?? NULL;   // Application's sessionId
        $challenge = $state["challenge"] ?? NULL;   // The challenge we sent to the Tiqr client
        if (!is_string($sessionId) || (!is_string($challenge)) ) {
            throw new RuntimeException('Invalid state for state storage');
        }

        // The user ID is optional, it is set when the application requested authentication of a specific userId
        // instead of letting the client decide
        $challengeUserId = $state["userId"] ?? NULL;

        // If the application requested a specific userId, verify that that is that userId that we're now authenticating
        if ($challengeUserId!==NULL && ($userId !== $challengeUserId)) {
            $this->logger->error(
                sprintf('Authentication failed: the requested userId "%s" does not match userId "%s" that is being authenticated',
                $challengeUserId, $userId)
            );
            return self::AUTH_RESULT_INVALID_USERID; // requested and actual userId do not match
        }

        try {
            $equal = $this->_ocraService->verifyResponse($response, $userId, $userSecret, $challenge, $sessionKey);
        } catch (Exception $e) {
            $this->logger->error(sprintf('Error verifying OCRA response for user "%s"', $userId), array('exception' => $e));
            throw $e;
        }

        if ($equal) {
            // Set application session as authenticated
            $this->_setStateValue(self::PREFIX_AUTHENTICATED, $sessionId, $userId, self::LOGIN_EXPIRE);
            $this->logger->notice(sprintf('Authenticated user "%s" in session "%s"', $userId, $sessionId));

            // Cleanup challenge
            // Future authentication attempts with this sessionKey will get a AUTH_RESULT_INVALID_CHALLENGE
            // This QR code / push notification cannot be used again
            // Cleaning up only after successful authentication enables the user to retry authentication after e.g. an
            // invalid response
            try {
                $this->_unsetStateValue(self::PREFIX_CHALLENGE, $sessionKey); // May throw
            } catch (Exception $e) {
                // Only log error
                $this->logger->warning('Could not delete authentication session key', array('error' => $e));
            }

            return self::AUTH_RESULT_AUTHENTICATED;
        }
        $this->logger->error('Authentication failed: invalid response');
        return self::AUTH_RESULT_INVALID_RESPONSE;
    }

    /**
     * Log the user out.
     * It is not an error is the $sessionId does not exists, or when the $sessionId has expired
     *
     * @param String $sessionId The application's session identifier (defaults
     *                          to the php session).
     *                          This is the application's sessionId that was provided to startAuthenticationSession()
     *
     * @throws Exception when there was an error communicating with the storage backed
     */
    public function logout(string $sessionId=""): void
    {
        if ($sessionId=="") {
            $sessionId = session_id(); 
        }
        
        $this->_unsetStateValue(self::PREFIX_AUTHENTICATED, $sessionId);
    }
    
    /**
     * Exchange a notificationToken for a deviceToken.
     * 
     * During enrollment, the phone will post a notificationAddress that can be 
     * used to send notifications. To actually send the notification, 
     * this address should be converted to the real device address.
     *
     * @param String $notificationType    The notification type.
     * @param String $notificationAddress The address that was stored during enrollment.
     *
     * @return String|bool The device address that can be used to send a notification.
     *                     false on error
     */
    public function translateNotificationAddress(string $notificationType, string $notificationAddress)
    {
        if ($notificationType == 'APNS' || $notificationType == 'FCM' || $notificationAddress == 'GCM') {
            return $this->_deviceStorage->getDeviceToken($notificationAddress);
        } else {
            return $notificationAddress;
        }
    }
    
    /**
     * Retrieve the currently logged in user.
     * @param String $sessionId The application's session identifier (defaults
     *                          to the php session).
     *                          This is the application's sessionId that was provided to startAuthenticationSession()
     * @return string|NULL The userId of the authenticated user,
     *                     NULL if no user is logged in
     *                     NULL if the user's login state could not be determined
     *
     * Does not throw
     */
    public function getAuthenticatedUser(string $sessionId=""): ?string
    {
        if ($sessionId=="") {
            $this->logger->debug('Using the PHP session id, as no session id was provided');
            $sessionId = session_id(); 
        }
        
        try {
            return $this->_getStateValue("authenticated_", $sessionId);
        }
        catch (Exception $e) {
            $this->logger->error('getAuthenticatedUser failed', array('exception'=>$e));
            return NULL;
        }
    }
    
    /**
     * Generate a authentication challenge URL
     * @param String $sessionKey The authentication sessionKey
     *
     * @return string AuthenticationURL
     * @throws Exception
     */
    protected function _getChallengeUrl(string $sessionKey): string
    {
        // Lookup the authentication session data and use this to generate the application specific
        // authentication URL
        // We probably just generated the challenge and stored it in the StateStorage
        // We can save a roundtrip to the storage backend here by reusing this information

        $state = $this->_getStateValue(self::PREFIX_CHALLENGE, $sessionKey);
        if (is_null($state)) {
            $this->logger->error(
                sprintf(
                'Cannot get session key "%s"',
                    $sessionKey
                )
            );
            throw new Exception('Cannot find sessionkey');
        }

        $userId = $state["userId"] ?? NULL;
        $challenge = $state["challenge"] ?? '';
        $spIdentifier = $state["spIdentifier"] ?? '';
        
        // Last bit is the spIdentifier
        return $this->_protocolAuth."://".(!is_null($userId)?urlencode($userId).'@':'').$this->getIdentifier()."/".$sessionKey."/".$challenge."/".urlencode($spIdentifier)."/".$this->_protocolVersion;
    }

    /**
     * Generate an enrollment string
     * @param String $metadataUrl The URL you provide to the phone to retrieve metadata.
     */
    protected function _getEnrollString(string $metadataUrl): string
    {
        return $this->_protocolEnroll."://".$metadataUrl;
    }

    /**
     * Generate a unique secure pseudo-random value to be used as session key in the
     * tiqr protocol. These keys are sent to the tiqr client during enrollment and authentication
     * And are used in the server as part of key for data in StateStorage
     * @return String The session key as HEX encoded string
     * @throws Exception When the key could not be generated
     */
    protected function _uniqueSessionKey(): string
    {

        return bin2hex( Tiqr_Random::randomBytes(self::SESSION_KEY_LENGTH_BYTES) );
    }
    
    /**
     * Internal function to set the enrollment status of a session.
     * @param String $sessionId The sessionId to set the status for
     * @param int $status The new enrollment status (one of the 
     *                    self::ENROLLMENT_STATUS_* constants)
     * @throws Exception when updating the status fails
     */
    protected function _setEnrollmentStatus(string $sessionId, int $status): void
    {
        if (($status < 1) || ($status > 6)) {
            // Must be one of the self::ENROLLMENT_STATUS_* constants
            throw new InvalidArgumentException('Invalid enrollment status');
        }
        $this->_setStateValue(self::PREFIX_ENROLLMENT_STATUS, $sessionId, $status, self::ENROLLMENT_EXPIRE);
    }

    /** Store a value in StateStorage
     * @param string $key_prefix
     * @param string $key
     * @param mixed $value
     * @param int $expire
     * @return void
     * @throws Exception
     *
     * @see Tiqr_StateStorage_StateStorageInterface::setValue()
     */
    protected function _setStateValue(string $key_prefix, string $key, $value, int $expire): void {
        $this->_stateStorage->setValue(
            $key_prefix . $this->_hashKey($key),
            $value,
            $expire
        );
    }

    /** Get a value from StateStorage
     * @param string $key_prefix
     * @param string $key
     * @return mixed
     * @throws Exception
     *
     * @see Tiqr_StateStorage_StateStorageInterface::getValue()
     */

    protected function _getStateValue(string $key_prefix, string $key) {
        return $this->_stateStorage->getValue(
            $key_prefix . $this->_hashKey($key)
        );
    }

    /** Remove a key and its value from StateStorage
     * @param string $key_prefix
     * @param string $key
     * @return void
     * @throws Exception
     *
     * @see Tiqr_StateStorage_StateStorageInterface::unsetValue()
     */
    protected function _unsetStateValue(string $key_prefix, string $key): void {
        $this->_stateStorage->unsetValue(
            $key_prefix . $this->_hashKey($key)
        );
    }

    /**
     * Create a stable hash of a $key. Used to improve the security of stored keys
     * @param string $key
     * @return string hashed $key
     */
    protected function _hashKey(string $key): string
    {
        return hash_hmac('sha256', $key, $this->_stateStorageSalt);
    }
}
