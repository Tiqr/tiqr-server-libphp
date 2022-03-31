# tiqr-server-libphp
`Master` [![Build Status master](https://app.travis-ci.com/SURFnet/tiqr-server-libphp.svg?branch=master)](https://app.travis-ci.com/SURFnet/tiqr-server-libphp)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/?branch=master)<br>
`Develop` [![Build Status develop](https://app.travis-ci.com/SURFnet/tiqr-server-libphp.svg?branch=develop)](https://app.travis-ci.com/SURFnet/tiqr-server-libphp)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/SURFnet/tiqr-server-libphp/?branch=develop)

A PHP library for implementing a Tiqr authentication server

# Introduction
This project is a PHP implementation of a Tiqr server. This server is more of a library that helps Tiqr server backends
(called Tiqr-GSSP in our eco-system) with several tasks. These tasks include:

1. Handling of authentications and registration flows using suitable and safe authentication methods (HOTP, Ocra)
2. Storing of user authentication and user secret data
3. Sending of push notification (Google Cloud Messaging, Firebase Cloud Messaging, Apple Push Notifications and Cloud 
   to Device Messaging)
4. Storing of application state (for state persistency during registration and authentication workflows)

# Who should use this library?
Basically anyone who wants to implement a Tiqr server for their Tiqr Apps (Android & iOS). As Tiqr is tightly integrated
into the OpenConext StepUp ecosystem, we chose to implement the Tiqr server into our [Tiqr GSSP](https://github.com/OpenConext/Stepup-tiqr)
If you seek to implement a Tiqr backend yourself, you can look to that project for pointers. 

# History
A brief overview of notable points in time of this project

- 2010: This project wat initially created in 2010.
- 2012: The project was moved from a local SVN to GitHub
- 2014: The UserSecretStorage and the Ocra implementation was created
- 2015: GCM push message support was added, and several cleanup tasks where performed
- 2019: FCM push message support was added
- 2020: PHP 5 support was dropped
- 2022: Unit & integration test coverage was added

# Ecosystem
The tiqr-server-libphp uses external libraries sparingly. The most notable external dependency is the Zend Framework 1.
This framework is used primarily for removing complexity in certain parts of the library. It is not used for any 
infrastructural support work like routing, command handling, logging, and so forth. Where the framework is used is:

- Implementing push notifications for the following protocols: APNS, C2DM and GCM
- UserSecret storage supports LDAP for a storage engine. The LDAP implementation is ZF1 based.
- User storage supports a similar LDAP solution

For creating QR codes, another external library is used. This is the Kairos PHPQRcode library. 

The rest of the library code is vanilla PHP code.

For testing purposes we use additional dev-dependencies. They include well know testing tools like PHPUnit and Mockery.

# Future strategy
- Having a robust test coverage on the code should have a high priority on every new feature created or bug fixed.
- Moving away from the long past Zend Framework is not a high priority, keeping the library working and increasing its 
  predictability is far more important. 
- New code must not depend on the Zend Framework 1

# Using the library
Examples on how to work with the library can be found in the aforementioned Tiqr GSSP. But some examples are included 
here to get a basic understanding of the capabilities of the library.

The API of the tiqr-server-libphp can be found in the `Tiqr` 'namespace' (`library/tiqr/Tiqr`). Notable classes found 
here are:

- `Autoloader` the homemade autoloader, used to load the external dependencies and the following services and storage backends 
- `Service` the main service class used to interact with the underlying features of the library
- `Factories` the `UserStorage`, `UserSecretStorage` and `StateStorage` are creating the specified storage backend based 
   on the way you configured Tiqr

## Creating the Service
An example on how to configure, create and work with the `Service`.

### Config
To create the Tiqr Service, we encourage you to create a factory in your consuming code base. In order to create the 
service, you will first need to create a Tiqr server configuration. A brief example is listed below. Note that this
example is taken from a Symfony parameters file. This YAML is later transformed to a PHP Array. But for readability the
YAML format is retained in this example:

```php
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
    // User storage
    'userstorage' => [
        'type' => 'dummy',
    ],
    
    'usersecretstorage' => [
        'type' => 'dummy',
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

# Running tests
A growing set of unit tests can and should be used when developing the tiqr-server-libphp project.

How to run the tests:
```
composer install
vendor/bin/phpunit
```
