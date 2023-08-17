# tiqr-server-libphp
`Master` ![test-integration workflow](https://github.com/Tiqr/tiqr-server-libphp/actions/workflows/test-integration.yml/badge.svg?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Tiqr/tiqr-server-libphp/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Tiqr/tiqr-server-libphp/?branch=master)
<br>
`Develop` ![test-integration workflow](https://github.com/Tiqr/tiqr-server-libphp/actions/workflows/test-integration.yml/badge.svg?branch=develop)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Tiqr/tiqr-server-libphp/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/Tiqr/tiqr-server-libphp/?branch=develop)

A PHP library for implementing a Tiqr authentication server

The library includes a test server, see its [README](TestServer/README.md) for details.
Read the included [SECURITY.md](SECURITY.md) for security considerations when using this library.

# Introduction
This project is a PHP implementation of a library to implement a Tiqr server. This library is not a server by itself, instead it contains functionality that help Tiqr server implementations with several tasks. You need to write the code to provide the HTTP API for the Tiqr client, and to create the web interface that the user interacts with for enrolling and authentication. This library helps with a large part of this, including:

1. Handling of authentications and registration flows of the Tiqr protocol (OCRA)
2. Storing of user authentication and user secret data
3. Sending of push notification (Firebase Cloud Messaging, Apple Push Notifications)
4. Storing of application state (for state persistency during registration and authentication workflows)

When implementing Tiqr you will need to understand the Tiqr protocol. This is documented in the [Tiqr protocol documentation](https://tiqr.org/protocol/).

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
The tiqr-server-libphp uses external libraries sparingly. It uses libraries for sending push notifications and for generating QR code images.

The rest of the library code is vanilla PHP code.

For testing purposes we use additional dev-dependencies. They include well know testing tools like PHPUnit and Mockery.

# Future strategy
- Having a robust test coverage on the code should have a high priority on every new feature created or bug fixed.

# Using the library
If you seek to implement a Tiqr server yourself, you can look at how this library is used by the Tiqr GSSP. Tiqr is an important second factor authentication method in the OpenConext Stepup ecosystem and this library is used by the [Tiqr GSSP](https://github.com/OpenConext/Stepup-tiqr).

Another example for using this library is the [Tiqr TestServer](TestServer) that is included with the library

The API of the tiqr-server-libphp can be found in classes starting `Tiqr_` in `library/tiqr/Tiqr`. Notable classes found here are:

- [Tiqr_Service](library/tiqr/Tiqr/Service.php) the main service class implementing the utility functions to handle user enrollement and authentication from a Tiqr Server.
- Factories for creating [UserStorage.php](library/tiqr/Tiqr/UserStorage.php) and [UserSecretStorage.php](library/tiqr/Tiqr/UserSecretStorage.php) for different storage backends.

## Security
Please read the included [SECURITY.md](SECURITY.md) for important security considerations when using Tiqr and using this library. 

## Creating the Service
An example on how to configure, create and work with the Tiqr `Service`.

### Config
To create the Tiqr Service you need to provide it with configuration options for the [Tiqr_Service](library/tiqr/Tiqr/Service.php) as well as the configuration for the [Tiqr_StateStorage](library/tiqr/Tiqr/StateStorage.php). The `Tiqr_StateStorage` is used to link the API calls from the tiqr client with the API calls from your tiqr server webinterface. You must select and configure the type you want to use – (e.g. pdo) corresponds with the class (e.g. type pdo will use [Tiqr_StateStorage_PDO](library/tiqr/Tiqr/StateStorage/Pdo.php)).

The documentation of all the configuration options can be found in the [Tiqr_Service](library/tiqr/Tiqr/Service.php) class.

The APNS and FCM configuration and the Token exchange configuration is only required for sending Push Notifications to iOS and Android clients. These push notifications are an optional alternative way to start an authentication for a know user and require you to release your own app, under your own name. When not using push notifications the user must always scan a QR code.

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

    // APNS configuration, required for sending push notifications to iOS devices
    'apns.certificate' => 'files/apns.pem',
    'apns.environment' => 'production',

    // FCM configuration, required for sending push notifications to Android devices
    'firebase.apikey' => 'your-secret-firebase-api-key',

    // Session storage
    'statestorage' => [
        'type' => 'pdo',
        'dsn' => 'mysql:host=localhost;dbname=state_store'
        'username' => 'state_rw'
        'password' => 'secret'
        'cleanup_probability' => 0.75
    ],

    // Token exchange configuration (deprecated)
    'devicestorage' => [
        'type' => 'tokenexchange',
        'url' => 'tx://tiqr.example.com',
        'appid' => 'app_id',
    ],
]
```

### Autoloading and composer

Add the library to your project using composer. I.e.:

```
$ composer require tiqr/tiqr-server-libphp
```

and include the `vendor/autoload.php` generated by composer:

```php
<?php
# Include the composer autoloader
require_once 'vendor/autoload.php';
```

### Creation
Creating the `Tiqr_Service` is now as simple as creating a new instance with the configuration

```php
# Create the Tiqr_Service
$service = new Tiqr_Service($options)
```

### Example Usage
The service has 22 public methods that are used to enroll a new user, but also to run authentications. The purpose of this section is not to be an API documentation. But an example is shown on how the service methods behave.



For more comprehensible examples on how to work with the Tiqr library, have a look at the Tiqr TestServer implementation. It can be found [here](./TestServer/README.md). Or have a look at a real world implementation on our [Stepup-tiqr](https://github.com/OpenConext/Stepup-tiqr) project. A good entrypoint is the [TiqrServer](https://github.com/OpenConext/Stepup-tiqr/blob/develop/src/Tiqr/Legacy/TiqrService.php) and the [TiqrFactory](https://github.com/OpenConext/Stepup-tiqr/blob/develop/src/Tiqr/TiqrFactory.php).

# Logging
We put a lot of effort adding relevant logging to the library. Logging adheres to the PSR-3 logging standard. Services, Repositories and other helper classes in the library are configured with a LoggerInterface instance when they are instantiated. Your application should have a logging solution that can fit into that. Otherwise, we suggest looking at Monolog as a logging solution that is very flexible, and adheres to the PSR-3 standard.

In practice, when creating the Tiqr_Service, you need to inject your Logger in the constructor. The factory classes also ask for a logger instance, for example: when creating a user secret storage.  

An example using Monolog (your framework will allow you to DI the logger into your own tiqr service implementation):

```php 
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a log channel that logs alle messages of level DEBUG and above to a file
$logger = new Logger('name');
$logger->pushHandler(new StreamHandler('path/to/your.log', Logger::DEBUG));

$this->tiqrService = new Tiqr_Service($logger, $options);
```

# Running tests
A growing set of unit tests can and should be used when developing the tiqr-server-libphp project.

To run all te QA tests:
```
composer install
composer test
```

After `composer install`, you can run the individual tests from the `/qa/ci/` directory. E.g. to run phpunit tests only:
```
./ci/qa/phpunit 
```
