<?php

/*
@license New BSD License - See LICENSE file for details.
@copyright (C) 2022 SURF BV
*/

namespace TestServer;

use Mockery;
use Psr\Log\LoggerInterface;
use Tiqr_AutoLoader;
use Tiqr_OCRAWrapper;
use Tiqr_Service;
use Tiqr_UserStorage;

require_once __DIR__ . '/../library/tiqr/Tiqr/OATH/OCRA.php'; // For calculating OCRA responses

class TestServerController
{
    private $tiqrService;
    private $userStorage;
    private $userSecretStorage;
    private $host_url;
    private $apns_certificate_filename;
    private $apns_environment;
    private $logger;
    private $storageDir;

    private $supportedNotificationTypes = array(
        'GCM',
        'APNS',
        'FCM_DIRECT',
        'APNS_DIRECT'
    );

    /**
     * @param $host_url This is the URL by which the tiqr client can reach this server, including http(s):// and port.
     * E.g. 'http://my-laptop.local:8000'
     *
     * @param $authProtocol This is the app specific url for authentications of the tiqr client, without '://'
     * e.g. 'tiqrauth'. This must match what is configured in the tiqr client
     * @param $enrollProtocol This is the app specific url for enrolling user accounts in the tiqr client, without '://'
     * e.g. 'tiqrenroll'. This must match what is configured in the tiqr client
     *
     * @param string $token_exchange_url The URL of the tiqr token exchange server
     * @param string $token_exchange_appid The appid to use with the tiqr token exchange server
     *
     * @param string $apns_certificate_filename The filename of the file with PEM APNS signing certificate and private key
     * @param string $apns_environment The APNS environment to use. 'production' or 'sandbox'
     * @param string $firebase_apikey The Google firebase API key. Required for sending FCM push notifications
     * @param string $storage_dir Directory to use for tiqr state storage, user storage and user sercret storage
     */
    function __construct(LoggerInterface $logger, string $host_url, string $authProtocol, string $enrollProtocol, string $token_exchange_url, string $token_exchange_appid, string $apns_certificate_filename, string $apns_environment, string $firebase_apikey, string $storage_dir)
    {
        $this->storageDir = $storage_dir;
        $this->logger = $logger;
        $this->host_url = $host_url;
        $this->tiqrService = $this->createTiqrService($host_url, $authProtocol, $enrollProtocol, $token_exchange_url, $token_exchange_appid, $apns_certificate_filename, $apns_environment, $firebase_apikey);
        $this->userStorage = $this->createUserStorage();
        $this->userSecretStorage = $this->createUserSecretStorage();        
    }

    /**
     * @return string Directory path for storing the server's data
     */
    private function getStorageDir(): string
    {
        $storage_dir = $this->storageDir;
        if (!is_dir($storage_dir)) {
            if (false == mkdir($storage_dir)) {
                TestServerApp::error_exit(500, "Error creating storage directory: $storage_dir");
            }
        }

        return $storage_dir;
    }

    /**
     * @return Tiqr_Service
     */
    private function createTiqrService($host, $authProtocol, $enrollProtocol, $token_exchange_url, $token_exchange_appid, $apns_cert_filename, $apns_environment, $firebase_apikey)
    {
        // Derive the identifier from the host
        $identifier = parse_url($host, PHP_URL_HOST);
        $storage_dir = $this->getStorageDir();

        $tiqr_service = new Tiqr_Service(
            $this->logger,
            [
                'auth.protocol' => $authProtocol,
                'enroll.protocol' => $enrollProtocol,
                'ocra.suite' => 'OCRA-1:HOTP-SHA1-6:QH10-S',
                'identifier' => $identifier,
                'name' => "TestServerController $host",
                'logoUrl' => "$host/logoUrl",
                'infoUrl' => "$host/infoUrl",

                // 'phpqrcode.path'

                // APNS
                'apns.certificate' => $apns_cert_filename,
                'apns.environment' => $apns_environment,
                'apns.version' => 2,    // Use the new HTTP/2 based protocol

                // FCM
                'firebase.apikey' => $firebase_apikey,

                // Note: C2DM is no longer supported by google (https://developers.google.com/android/c2dm)
                'c2dm.username' => 'test_c2dm_username',
                'c2dm.password' => 'test_c2dm_password',
                'c2dm.application' => 'org.example.authenticator.test',

                // Session storage
                'statestorage' => array(
                    'type' => 'file',
                    'path' => $storage_dir,
                ),

                // Token exchange configuration
                'devicestorage' => array(
                    'type' => 'tokenexchange',
                    'url' => $token_exchange_url,
                    'appid' => $token_exchange_appid,
                ),

            ]
        );

        return $tiqr_service;
    }

    private function createUserStorage()
    {
        $storage_dir = $this->getStorageDir();
        $options = array(
            'type' => 'file',
            'path' => $storage_dir,
        );

        return $userStorage = Tiqr_UserStorage::getStorage(
            'file',
            $options,
            $this->logger
        );
    }

    private function createUserSecretStorage()
    {
        $storage_dir = $this->getStorageDir();
        $options = array(
            'type' => 'file',
            'path' => $storage_dir,
        );

        return $userStorage = \Tiqr_UserSecretStorage::getSecretStorage(
            'file',
            $this->logger,
            $options
        );
    }


    public function Route(App $app, string $path)
    {
        $view = new TestServerView();

        try {
            $app::log_info("host_url=$this->host_url");
            switch ($path) {
                case "/":   // Test server home page
                    $view->ShowRoot();
                    break;

                case "/list-users": // page showing currently enrolled user accounts
                    $this->list_users($app, $view);
                    break;

                // Enrollment
                case "/start-enrollment": // Show enroll page to user
                    $this->start_enrollment($app, $view);
                    break;
                case "/metadata":   // tiqr client gets metadata
                    $this->metadata($app);
                    break;
                case "/finish-enrollment": // tiqr client posts secret
                    $this->finish_enrollment($app);
                    break;

                // Render a QR code
                case "/qr": // used from start-enrollment and start_authenticate views
                    $this->qr($app);
                    break;

                // Serve test logo
                case "/logoUrl": // used by tiqr client to download logo, included in metadata
                    $this->logo($app);
                    break;
                // case "infoUrl": // used in metadata

                // Authentication
                case "/start-authenticate": // Show authenticate page to user
                    $this->start_authenticate($app, $view);
                    break;

                // Send push notification
                case "/send-push-notification":
                    $this->send_push_notification($app, $view);
                    break;

                case "/authentication": // tiqr client posts back response
                    $this->authentication($app);
                    break;

                default:
                    TestServerApp::error_exit(404, "Unknown route '$path'");
            }
        }
        catch (\Exception $e) {
            $app::log_error("Exception: " . $e->getMessage());
            $app::log_error($e);
            $view->Exception($path, $e);
        }
    }

    private function start_enrollment(App $app, TestServerView $view)
    {
        // The session ID is used for communicating enrollment status between this tiqr server and
        // the web browser displaying the enrollment interface. It is not used between the tiqr client and
        // this server. We do not use it.
        $session_id = 'session_id_' . time();
        $app::log_info("Created session $session_id");

        // The user_id to create. Get it from the request, if it is not there use a test user ID.
        $user_id = $app->getGET()['user_id'] ?? 'test-user-' . time();

        if ($this->userStorage->userExists($user_id)) {
            $app::log_warning("$user_id already exists");
        }

        $user_display_name = $user_id . '\'s display name';

        // Create enrollemnt key. The display name we set here is returned in the metadata generated by
        // getEnrollmentMetadata.
        // Note: we create the user in the userStorage later with a different display name so the displayname in the
        //       App differs from the user's displayname on the server.
        $enrollment_key = $this->tiqrService->startEnrollmentSession($user_id, $user_display_name, $session_id);
        $app::log_info("Started enrollment session $enrollment_key");
        $metadataUrl = $this->host_url . "/metadata";
        $enroll_string = $this->tiqrService->generateEnrollString("$metadataUrl?enrollment_key=$enrollment_key");
        $encoded_enroll_string = htmlentities(urlencode($enroll_string));
        $image_url = "/qr?code=" . $encoded_enroll_string;

        $view->StartEnrollment(htmlentities($enroll_string), $image_url);
    }

    // Generate a png image QR code with whatever string is given in the code HTTP request parameter.
    private function qr(App $app): void
    {
        header('Content-type: image/png');

        $code = $app->getGET()['code'] ?? '';
        if (strlen($code) == 0) {
            // http://<server>/qr?code=<the string to encode>
            $app::error_exit(404, "qr: 'code' request parameter not set");
        }
        $this->tiqrService->generateQR($code);
    }

    // In response to scanning the enrollment QR code the tiqr client makes a GET request to the URL in
    // the QR code.
    // This URL is exactly what we put in the QR code we generated in start_enrollment() :
    //   <host_url>/metadata?enrollment_key=<enrollment_key>
    private function metadata(App $app)
    {
        // The enrollment key must be there, it binds the user's browser session to the request coming from
        // the user's tiqr client
        $enrollment_key = $app->getGET()['enrollment_key'] ?? '';
        if (strlen($enrollment_key) == 0) {
            // http://<server>/metadata?enrollment_key=<the enrollment key from the QR code>
            $app::error_exit(404, "metadata: 'enrollment_key' request parameter not set");
        }
        // Generate an enrollment secret to add to the metadata URL
        $enrollment_secret = $this->tiqrService->getEnrollmentSecret($enrollment_key);
        $app::log_info("Created enrollment secret $enrollment_secret for enrollment key $enrollment_key");

        // Note: The enrollment_secret must be added manually to the enrollment URL.
        // This makes the process of generating the enrollment URL more complex, but gives
        // the application full control over how the URL is formatted

        // Add enrollment_secret to the $enrollment_url
        $enrollment_url = $this->host_url . "/finish-enrollment?enrollment_secret=$enrollment_secret";
        $authentication_url = $this->host_url . '/authentication';
        // Get the enrollment data
        $enrollment_metadata = $this->tiqrService->getEnrollmentMetadata($enrollment_key, $authentication_url, $enrollment_url);
        if (false == $enrollment_metadata) {
            // Happens when calling metadata with the same enrollment key a second time
            $app::error_exit(500, 'Error getting enrollment metadata');
        }
        // Print the generated enrollment data in a more readable form
        foreach ($enrollment_metadata as $key1 => $value1) {
            if (is_array($value1)) {
                foreach ($value1 as $key2 => $value2) {
                    $app::log_info("Metadata: $key1/$key2=$value2");
                }
            }
            else {
                $app::log_info("Metadata: $key1=$value1");
            }
        }
        // The enrollment metadata must be returned to the client as JSON
        $enrollment_metadata_json = json_encode($enrollment_metadata, JSON_UNESCAPED_SLASHES);
        $app::log_info("Return: $enrollment_metadata_json");
        header("content-type: application/json");
        echo $enrollment_metadata_json;
    }

    // After receiving the enrollment metadata the tiqr client generates a secret key and posts
    // it to the enrollment URL we specified in the metadata together with the information required for sending
    // it push notification and the user's language preference.
    private function finish_enrollment(App $app)
    {
        $enrollment_secret = $app->getGET()['enrollment_secret'] ?? '';
        if (strlen($enrollment_secret) == 0) {
            // http://<server>/finish_enrollment?enrollment_secret=<the enrollment secret from the metadata>
            $app::error_exit(404, "enrollment: 'enrollment_secret' request parameter not set");
        }
        // Validate the enrollment secret we were sent. In return we get the userid back that we set in
        // start_enrollment using startEnrollmentSession
        $userid = $this->tiqrService->validateEnrollmentSecret($enrollment_secret);
        if (false === $userid) {
            $app::error_exit(404, "Invalid enrollment_secret");
        }
        $app::log_info("userid: $userid");

        $secret = $app->getPOST()['secret'] ?? '';
        if (strlen($secret) == 0) {
            $app::error_exit(404, "Missing secret is POST");
        }
        // This is the hex encoded value of the authentication secret that the tiqr client
        // generated
        $app::log_info("secret: $secret");

        $language = $app->getPOST()['language'] ?? '';
        if (strlen($language) == 0) {
            $app::log_warning("No language in POST");
        }
        // The iso language code e.g. "nl-NL"
        $app::log_info("language: $language");

        $notificationType = $app->getPOST()['notificationType'] ?? '';
        if (strlen($notificationType) == 0) {
            $app::log_warning("No notificationType in POST");
        }
        // The notification message type (APNS, GCM, FCM ...)
        $app::log_info("notificationType: $notificationType");

        if (! in_array($notificationType, $this->supportedNotificationTypes)) {
            $app->log_warning("Unsupported notification type: $notificationType");
        }

        $notificationAddress = $app->getPOST()['notificationAddress'] ?? '';
        if (strlen($notificationAddress) == 0) {
            $app::log_warning("No notificationAddress in POST");
        }
        // This is the notification address that the Tiqr Client got from the token exchange (e.g. tx.tiqr.org)
        $app::log_info("notificationAddress: $notificationAddress");

        $version = $app->getPOST()['version'] ?? '';
        if (strlen($version) == 0) {
            $app::log_warning("No version in POST");
        }
        // ?
        $app::log_info("version: $version");

        $operation = $app->getPOST()['operation'] ?? '';
        if (strlen($operation) == 0) {
            $app::log_warning("No operation in POST");
        }
        // Must be "register"
        $app::log_info("operation: $operation");
        if ($operation != 'register') {
            $app::error_exit(404, "Invalid operation: '$operation'. Expected 'register'");
        }

        // Get User-Agent HTTP header
        $user_agent = urldecode($_SERVER['HTTP_USER_AGENT'] ?? '');
        $app::log_info("User-Agent: $user_agent");

        // Create the user. Use the display name to store the version the client POSTed and the user-agent it sent
        // in this POST request's header.
        $this->userStorage->createUser($userid, "$version | $user_agent");
        $app::log_info("Created user $userid");

        // Set the user secret
        $this->userSecretStorage->setSecret($userid, $secret);
        $app::log_info("Secret for $userid was stored");

        // Store notification type and the notification address that the client sent us
        $this->userStorage->setNotificationType($userid, $notificationType);
        $this->userStorage->setNotificationAddress($userid, $notificationAddress);

        // Finalize the enrollemnt
        $this->tiqrService->finalizeEnrollment($enrollment_secret);
        $app::log_info("Enrollment was finalized");

        // Must return "OK" to the tiqr client after a successful enrollment
        echo "OK";
    }

    private function logo(App $app)
    {
        // Source: https://nl.wikipedia.org/wiki/Bestand:Philips_PM5544.svg
        $name = __DIR__ . '/Philips_PM5544.jpg';
        $fp = fopen($name, 'rb');
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($name));
        fpassthru($fp);
        fclose($fp);
    }

    function list_users(App $app, TestServerView $view)
    {
        $storageDir = $this->getStorageDir();
        $users = array();
        foreach (scandir($storageDir) as $filename) {
            if (substr($filename, -5, 5) == '.json') {
                $user = json_decode(file_get_contents($storageDir . '/' . $filename), true);
                if (($user != NULL) && ($user['secret'])) {
                    foreach ($user as $k => $v) {
                        $user[$k] = htmlentities($v);
                    }
                    $users[] = $user;
                }
            }
        }
        $view->ListUsers($users);
    }

    private function start_authenticate(App $app, TestServerView $view)
    {
        $session_id = 'session_id_' . time();
        $app::log_info("Created session $session_id");

        // The user_id to authenticate. Get it from the request, if it is not there use an empty user ID
        // Both scenario's are support by tiqr:
        // 1. No user-id in the authentication url. This is a login scenario. The tiqr client selects the user id
        // 2. Specify the user-is in the authentication url. This is the step-up scenario. The tiqr server specifies
        //    the user-id

        // Get optional user ID
        $user_id = $app->getGET()['user_id'] ?? '';
        if (strlen($user_id) > 0) {
            $app::log_info("Authenticating user '$user_id'");

            if (!$this->userStorage->userExists($user_id)) {
                $app::log_warning("'$user_id' is not known on the server");
            }
        }


        // Start authentication session
        $session_key = $this->tiqrService->startAuthenticationSession($user_id, $session_id);
        $app::log_info('Started authentication session');
        $app::log_info("session_key=$session_key");

        // Get authentication URL for the tiqr client (to put in the QR code)
        $authentication_URL = $this->tiqrService->generateAuthURL($session_key);
        $app::log_info('Started authentication URL');
        $app::log_info("authentication_url=$authentication_URL");

        $image_url = "/qr?code=" . htmlentities(urlencode($authentication_URL));

        $response = '';
        if (strlen($user_id) > 0) {
            // Calculate response
            $app::log_info("Calculating response for $user_id");
            $secret = $this->userSecretStorage->getSecret($user_id);
            $app::log_info("secret=$secret");

            $challenge='';
            // Parse the authentication URL to get the challenge question
            if ( (strpos($authentication_URL, 'https://') === 0) || (strpos($authentication_URL, 'http://')) ) {
                // New style URL, get the value of the "q" query parameter
                parse_str(parse_url($authentication_URL, PHP_URL_QUERY), $result);
                $challenge=$result['q'];
            }
            else {  // Old style URL, parameters are separated by slashes
                $exploded = explode('/', $authentication_URL);
                $challenge = $exploded[4];   // 10 digit hex challenge
            }
            $app::log_info("challenge=$challenge");
            $response=\OCRA::generateOCRA('OCRA-1:HOTP-SHA1-6:QH10-S', $secret, '', $challenge, '', $session_key, '');
            $app::log_info("response=$response");
        }

        $view->StartAuthenticate(htmlentities($authentication_URL), $image_url, $user_id, $response, $session_key);
    }


    private function send_push_notification(App $app, TestServerView $view) {
        $user_id = $app->getGET()['user_id'] ?? '';
        if (strlen($user_id) == 0) {
            $app::error_exit(404, "Missing user_id in POST");
        }
        $app->log_info("user_id = $user_id");

        $session_key = $app->getGET()['session_key'] ?? '';
        if (strlen($session_key) == 0) {
            $app::error_exit(404, "Missing session_key in POST");
        }
        $app->log_info("session_key = $session_key");

        // Get Notification address and type from userid
        $notificationType=$this->userStorage->getNotificationType($user_id);
        $app->log_info("notificationType = $notificationType");

        if (! in_array($notificationType, $this->supportedNotificationTypes)) {
            $app->log_warning("Unsupported notification type: $notificationType");
        }

        $notificationAddress=$this->userStorage->getNotificationAddress($user_id);

        // Use tiqr tokenexchange to translate the notification address to the device's push notification address
        // translateNotificationAddress does not translate the new APNS_DIRECT and FCM_DIRECT notificationType, it
        // only translates APNS, GCM and FCM. For any other types it returns the unmodified $notificationAddress
        $deviceNotificationAddress = $this->tiqrService->translateNotificationAddress($notificationType, $notificationAddress);
        $app->log_info("deviceNotificationAddress (from token exchange) = $deviceNotificationAddress");
        
        // Note that the current Tiqr app returns notification type 'APNS' or 'GCM'.
        // The Google Cloud Messaging (GCM) API - implemented in the Tiqr_Message_GCM class - is deprecated and has
        // been replaced by Firebase Cloud Messaging (FCM). See: https://developers.google.com/cloud-messaging
        // So even though the tiqr app returns GCM we actually use FCM implemented by Tiqr_Message_FCM
        // sendAuthNotification() accepts GCM, FCM_DIRECT and knows to use Tiqr_Message_FCM instead. For both APNS and
        // APNS_DIRECT Tiqr_Message_APNS will be used.
        $app->log_info("Sending push notification using $notificationType to $deviceNotificationAddress");
        $this->tiqrService->sendAuthNotification($session_key, $notificationType, $deviceNotificationAddress);
        $app->log_info("Push notification sent");

        $view->PushResult("Sent $notificationType to $deviceNotificationAddress");
    }


    private function authentication(App $app)
    {
        // This should be the session key from the authentication URL that we generated
        $sessionKey = $app->getPOST()['sessionKey'] ?? '';
        if (strlen($sessionKey) == 0) {
            $app::error_exit(404, "Missing sessionKey is POST");
        }
        $app::log_info("sessionKey: $sessionKey");

        // The userId the client authenticated
        $userId = $app->getPOST()['userId'] ?? '';
        if (strlen($userId) == 0) {
            $app::error_exit(404, "Missing $userId is POST");
        }
        $app::log_info("userId: $userId");

        // Get version from POST
        $version = $app->getPOST()['version'] ?? '';
        if (strlen($version) == 0) {
            $app::log_warning("No version in POST");
        }
        // ?
        $app::log_info("version: $version");

        // Get operation from POST
        $operation = $app->getPOST()['operation'] ?? '';
        if (strlen($operation) == 0) {
            $app::log_warning("No operation in POST");
        }
        // Must be "login"
        $app::log_info("operation: $operation");
        if ($operation != 'login') {
            $app::error_exit(404, "Invalid operation: '$operation'. Expected 'login'");
        }

        // Get response from POST
        $response = $app->getPOST()['response'] ?? '';
        if (strlen($response) == 0) {
            $app::log_warning("No response in POST");
        }
        $app::log_info("response: $response");

        $language = $app->getPOST()['language'] ?? '';
        if (strlen($language) == 0) {
            $app::log_warning("No language in POST");
        }
        // The iso language code e.g. "nl-NL"
        $app::log_info("language: $language");

        $notificationType = $app->getPOST()['notificationType'] ?? '';
        if (strlen($notificationType) == 0) {
            $app::log_warning("No notificationType in POST");
        }
        // The notification message type (APNS, GCM, FCM ...)
        $app::log_info("notificationType: $notificationType");

        if (! in_array($notificationType, $this->supportedNotificationTypes)) {
            $app->log_warning("Unsupported notification type: $notificationType");
        }

        $notificationAddress = $app->getPOST()['notificationAddress'] ?? '';
        if (strlen($notificationAddress) == 0) {
            $app::log_warning("No notificationAddress in POST");
        }
        // This is the notification address that the Tiqr Client got from the token exchange (e.g. tx.tiqr.org)
        $app::log_info("notificationAddress: $notificationAddress");

        // Note: a production tiqr server will now update the notification type and notification address in the
        // user storage, we do not. We only log when they are different

        $notificationType_from_userStorage = $this->userStorage->getNotificationType($userId);
        $notificationAddress_from_userStorage = $this->userStorage->getNotificationAddress($userId);
        if ($notificationAddress != $notificationAddress_from_userStorage) {
            $app::log_warning("Client sent different notification address. client=$notificationAddress, server=$notificationAddress_from_userStorage");
        }
        if ($notificationType != $notificationType_from_userStorage) {
            $app::log_warning("Client sent different notification type. client=$notificationType, server=$notificationType_from_userStorage");
        }

        // Get User-Agent HTTP header
        $user_agent = urldecode($_SERVER['HTTP_USER_AGENT'] ?? '');
        $app::log_info("User-Agent: $user_agent");

        $result = 'ERROR'; // Result for the tiqr Client
        // 'OK', 'INVALID_CHALLENGE', 'INVALID_REQUEST', 'INVALID_RESPONSE', 'INVALID_USER'

        if (!$this->userStorage->userExists($userId)) {
            $app::log_error("Unknown user: $userId ");
            $result = 'INVALID_USER';
        }

        // Lookup the secret of the user by ID
        $userSecret = $this->userSecretStorage->getSecret($userId);   // Assume this works
        $app::log_info("userSercret=$userSecret");

        $app::log_info("Authenticating user");
        $result = $this->tiqrService->authenticate($userId, $userSecret, $sessionKey, $response);
        $resultStr = 'ERROR';
        switch ($result) {
            case Tiqr_Service::AUTH_RESULT_INVALID_REQUEST:
                $resultStr = "INVALID_REQUEST";
                break;
            case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
                $resultStr = "OK";
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE:
                $resultStr = "INVALID_RESPONSE";
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE:
                $resultStr = "INVALID_CHALLENGE";
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_USERID:
                $resultStr = "INVALID_USER";
                break;
        }

        if ($result === Tiqr_Service::AUTH_RESULT_AUTHENTICATED) {
            try {
                if ($notificationAddress != $notificationAddress_from_userStorage) {
                    $this->userStorage->setNotificationAddress($userId, $notificationAddress);
                    log_info("Updated notification address");
                }
                if ($notificationType != $notificationType_from_userStorage) {
                    $this->userStorage->setNotificationType($userId, $notificationType);
                    log_info("Updated notification type");
                }
            }
            catch (\Exception $e) {
                $app::log_warning('Updating push notification information failed');
                $app::log_warning($e);
            }
        }

        $app::log_info("Returning authentication result '$resultStr'");
        echo $resultStr;
    }
}
