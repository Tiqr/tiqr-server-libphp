# tiqr-server-libphp
`Master` ![test-integration workflow](https://github.com/SURFnet/tiqr-server-libphp/actions/workflows/test-integration.yml/badge.svg?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/?branch=master)
<br>
`Develop` ![example workflow](https://github.com/SURFnet/tiqr-server-libphp/actions/workflows/test-integration.yml/badge.svg?branch=develop)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/?branch=develop)

A PHP library for implementing a Tiqr authentication server

# Introduction
This project is a PHP implementation of a library to implement a Tiqr server. This library is not a server by itself, instead it contains functionality that help Tiqr server implementations with several tasks. These tasks include:

1. Handling of authentications and registration flows of the Tiqr protocol (OCRA)
2. Storing of user authentication and user secret data
3. Sending of push notification (Firebase Cloud Messaging, Apple Push Notifications)
4. Storing of application state (for state persistency during registration and authentication workflows)

# Who should use this library?
Basically anyone who wants to implement a Tiqr server for their Tiqr client Apps (Android & iOS).

# History
A brief overview of notable points in time of this project

- 2010: This project wat initially created in 2010.
- 2012: The project was moved from a local SVN to GitHub
- 2014: The UserSecretStorage and the Ocra implementation was created
- 2015: GCM push message support was added, and several cleanup tasks where performed
- 2019: FCM push message support was added
- 2020: PHP 5 support was dropped
- 2022: Unit & integration test coverage was added
- 2022: Major refactoring of UserStorage and UserSecretStorage classes, addition of PSR Logging, removal of deprecated functionality

# Ecosystem
The tiqr-server-libphp uses external libraries sparingly. The most notable external dependency is the Zend Framework 1.
This framework is not used for any infrastructural support work like routing, command handling, logging, and so forth. 
The framework is currently only used to implement sending push notifications using the APNS protocol.

For creating QR codes, another external library is used. This is the Kairos PHPQRcode library. 

The rest of the library code is vanilla PHP code.

For testing purposes we use additional dev-dependencies. They include well know testing tools like PHPUnit and Mockery.

# Future strategy
- Having a robust test coverage on the code should have a high priority on every new feature created or bug fixed.
- Moving away from the long past Zend Framework is a long term goal, but is not a high priority now. Keeping the library working and increasing its 
  predictability is far more important. 
- New code must not depend on the Zend Framework 1

# Using the library
If you seek to implement a Tiqr server yourself, you can look at how this library is used by the Tiqr GSSP. Tiqr is an important second factor authentication method in the OpenConext Stepup ecosystem and this library is used by the [Tiqr GSSP](https://github.com/OpenConext/Stepup-tiqr). 

Another example for using this library is the [Tiqr TestServer](TestServer) that is included with the library

The API of the tiqr-server-libphp can be found in the `Tiqr` 'namespace' (`library/tiqr/Tiqr`). Notable classes found 
here are:

- `Tiqr_Autoloader` the homemade autoloader, used to load the external dependencies and the following services and storage backends 
- `Tiqr_Service` the main service class implementing the utility functions to handle user enrollement and authentication from a Tiqr Server.
- Factories for creating Tiqr_UserStorage and Tiqr_UserSecretStorage for different storage backends.

## Creating the Service
An example on how to configure, create and work with the `Service`.

### Config
To create the Tiqr Service you need to provide it with configuration options for the Tiqr_Service as well as the configuration for the Tiqr_StateStorage. The Tiqr_StateStorage is used to link the API calls from the tiqr client with the API calls from your tiqr server webinterface. You must select and configure the type you want to use – (e.g. pdo) corresponds with the class (e.g. type pdo will use Tiqr_StateStorage_PDO). 

The APNS and FCM configuration and the Token exchange configuration is required for sending Push Notifications to iOS and Android clients. These push notifications are an optional alternative way to start an authentication for a know user. When not using push notifications the user must always scan a QR code.

```php
// Options for Tiqr_Service
$options = [
    // General settings
    'auth.protocol' => 'tiqrauth',
    'enroll.protocol' => 'tiqrenroll',
    'ocra.suite' => 'OCRA-1:HOTP-SHA1-6:QH10-S',
    'identifier' => 'tiqr.example.com',
    'name' => "TestServerController https://tiqr.example.com",
    'logoUrl' => "https://tiqr.example.com/logoUrl",
    'infoUrl' => "https://tiqr.example.com/infoUrl",

    // APNS
    'apns.certificate' => 'files/apns.pem',
    'apns.environment' => 'production',

    // FCM
    'firebase.apikey' => 'your-secret-firebase-api-key',

    // Session storage
    'statestorage' => [
        'type' => 'pdo',
        'dsn' => 'mysql:host=localhost;dbname=state_store'
        'username' => 'state_rw'
        'password' => 'secret'
        'cleanup_probability' => 0.75
    ],

    // Token exchange configuration
    'devicestorage' => [
        'type' => 'tokenexchange',
        'url' => 'tx://tiqr.example.com',
        'appid' => 'app_id',
    ],
]
```

### Creation
Creating the Tiqr_Service is now as simple as creating a new instance with the configuration

```php
# Create the Tiqr_Service
$service = new Tiqr_Service($options)
```

### Example Usage
The service has 22 public methods that are used to enroll a new user, but also to run authentications. The purpose of 
this section is not to be an API documentation. But an example is shown on how the service methods behave.

The Tiqr servers purpose is to facilitate Tiqr authentications. In doing so communicating with the Tiqr app. Details
about this communication flow can be found in the Stepup-Tiqr documentation. Here you will find a communication diagram
for enrollment and authentication.

The following code examples show some of the concepts that are used during authentication.

```php
# 1. The name id (username) of the user is used to identify that specific user in Tiqr. 
#    In the case of Stepup-Tiqr (SAML based) we get the NameId from the SAML 2.0 AuthnRequest
#
# Example below is pseudocode you might write in your controller dealing with an authentication request
$nameId = $this->authenticationService->getNameId();

# The request id of the SAML AuthnRequest message, used to match the originating authentication request with the Tiqr authentication
$requestId = $this->authenticationService->getRequestId();
```

```php
# 2. Next you can do some verifications on the user, is it found in tiqr-server user storage?
#    Is it not locked out temporarily?
#
# Example below is pseudocode you might write in your controller dealing with an authentication request
$user = $this->userRepository->getUser($nameId);
if ($this->authenticationRateLimitService->isBlockedTemporarily($user)) {
    throw new Exception('You are locked out of the system');
}

$this->startAuthentication($nameId, $requestId)
public function startAuthentication($nameId, $requestId)
{
    # Authentication is started by providing the NameId and the PHP session id
    $sessionKey = $this->tiqrService->startAuthenticationSession($nameId, $this->session->getId());
    # The Service (Tiqr_Service) generates a session key which is stored in the state storage, but also returned to
    # persist in the Tiqr server implementation. 
    $this->session->set('sessionKey', $sessionKey);
    $this->storeRequestIdForNameId($sessionKey, $requestId);
    # Creates an authentication challenge URL. It links directly to the application 
    return $this->tiqrService->generateAuthURL($sessionKey);
}
```

```php
# 3. The tiqr server implementation now must wait for the Tiqr App to finalize its authentication with the user.
#    In the Stepup-Tiqr implementation, we do this by polling the tiqr server for the atuthentication status.
# Example below is pseudocode

# Javascript
function pollTiqrStatus() {
    getTiqrStatus()
    setTimeout(refresh, 5000);
}
pollTiqrStatus();

# In the PHP application:
$isAuthenticated = $this->tiqrService->getAuthenticatedUser($this->session->getId());
if ($isAuthenticated) {
    # Your controller can now go to the next action, maybe send back a successful SamlResponse, or signal otherwise
    # that the authentication succeeded. 
    return $successResponse;
}
# And deal with the non happy flow

if ($isExpired) {
    return $errorResponse;
}

if ($otherErrorConddition) {
    # ...
}
```

For more comprehensible examples on how to work with the Tiqr library, have a look at the Tiqr TestServer 
implementation. It can be found [here](./TestServer/README.md). Or have a look at a real world implementation on our
[Stepup-tiqr](https://github.com/OpenConext/Stepup-tiqr) project. A good entrypoint is the [TiqrServer](https://github.com/OpenConext/Stepup-tiqr/blob/develop/src/Tiqr/Legacy/TiqrService.php) 
and the [TiqrFactory](https://github.com/OpenConext/Stepup-tiqr/blob/develop/src/Tiqr/TiqrFactory.php).

# Logging
An effort was put into providing relevant logging to the library. Logging adheres to the PSR-3 logging standard.
Services, Repositories and other helper classes are configured with a LoggerInterface instance when they are 
instantiated. Your application should have a logging solution that can fit into that. Otherwise we suggest looking at
Monolog as a logging solution that is very flexible, and adheres to the PSR-3 standard.

In practice, when creating the Tiqr_Service, you need to inject your Logger in the constructor. The factory classes
also ask for a logger instance, for example: when creating a user secret storage.  

An example using Monolog (your framework will allow you to DI the logger into your own tiqr service implementation):

```php 
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a log channel
$logger = new Logger('name');
$logger->pushHandler(new StreamHandler('path/to/your.log', Logger::WARNING));

$this->tiqrService = new Tiqr_Service($logger, $options);
```

# Running tests
A growing set of unit tests can and should be used when developing the tiqr-server-libphp project.

How to run the tests:
```
composer install
composer test
```
