# tiqr-server-libphp
`Main` ![test-integration workflow](https://github.com/Tiqr/tiqr-server-libphp/actions/workflows/test-integration.yml/badge.svg?branch=main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Tiqr/tiqr-server-libphp/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/Tiqr/tiqr-server-libphp/?branch=master)
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

- 2010: This project was initially created in 2010
- 2012: The project was moved from a local SVN to GitHub
- 2014: The UserSecretStorage and the Ocra implementation was created
- 2015: GCM push message support was added, and several cleanup tasks where performed
- 2019: FCM push message support was added
- 2020: PHP 5 support was dropped
- 2022: Unit & integration test coverage was added, added TestServer
- 2022: 3.3: Major refactoring of UserStorage and UserSecretStorage classes, addition of PSR Logging, removal of deprecated functionality, security hardening
- 2023: 4.0: Switch to composer autoloader, add PHP 8 support, remove APNS v1 and Zend library dependency
- 2024: 4.1: Update FCM to use HTTP v1 API for google PN's, add openssl encryption type for UserSecretStorage

# Ecosystem
The tiqr-server-libphp uses external libraries sparingly. It uses libraries for sending push notifications and for generating QR code images.

The rest of the library code is vanilla PHP code.

For testing purposes we use additional dev-dependencies. They include well know testing tools like PHPUnit and Mockery.

# TestServer
The library includes a [Tiqr TestServer](TestServer) that is aimed at developers and testers of tiqr clients. See the [TestServer README](TestServer/README.md) for more information.

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
    
    //FCM configuration, required for sending push notifications to Android devices
    'firebase.projectId' => '12345-abcde',
    'firebase.credentialsFile' => 'google.json',    

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

[See this instructions](FCM.md) for generating the `firebase.projectId` and `firebase.credentialsFile`

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

# UserStorage and UserSecretStorage

The [UserStorage.php](library/tiqr/Tiqr/UserStorage.php) and [UserSecretStorage.php](library/tiqr/Tiqr/UserSecretStorage.php) are used to store Tiqr user account data (UserStorage) and to store the user's OCRA secret (UserSecretStorage). The use of both classes is optional as you provide Tiqr_Service() with the userid and secret, these can come from anywhere. The 'file' type is more suitable for testing and development. The 'pdo' type is intended for production use.

The UserSecretStorage supports encrypting the user secret using e.g. an AES key. You can also provide your own encrpytion implementation. See [UserSecretStorage.php](library/tiqr/Tiqr/UserSecretStorage.php) for more information.

## Example UserStorage and UserSecretStorage usage

Example using a single mysql 'user' table for user and user secret storage, the user's secret is stored encrypted using an AES key. 

### Create a user storage table in MySQL

```mysql
CREATE TABLE IF NOT EXISTS user (
  id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
  userid varchar(30) NOT NULL UNIQUE,
  displayname varchar(30) NOT NULL,
  secret varchar(128),
  loginattempts integer,
  tmpblocktimestamp BIGINT,
  tmpblockattempts integer,
  blocked tinyint(1),
  notificationtype varchar(10),
  notificationaddress varchar(64)
);
```

### Create and configure the UserStorage and UserSecretStorage classes

```php

$user_storage = Tiqr_UserStorage::getUserStorage(
    'pdo',
    $logger,
    array(
        'dsn' => 'mysql:host=mysql.example.com;dbname=tiqr',
        'username' => 'tiqr_rw',
        'password' => 'secret',
        'table' => 'user'
    )
);

$secret_storage = Tiqr_UserSecretStorage::getSecretStorage(
    'pdo',
    $logger,
    array(
        'dsn' => 'mysql:host=mysql.example.com;dbname=tiqr',
        'username' => 'tiqr_rw',
        'password' => 'secret',
        'table' => 'user',
        
        // Encrypt the secret using an AES key
        'encryption' => [
            'type' => 'openssl',
            'cipher' => 'aes-256-cbc', // Cypher to use for encryption
            'key_id' => 'key_2024',    // ID of the key to use for encryption
            'keys' => [
                'key_2024' => '0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f20', // Key used for encryption
                'key_2015' => '303132333435363738393a3b3c3d3e3f303132333435363738393a3b3c3d3e3f', // A (old) key that can be used for decryption
            ],
        ],
    )
);

$user_storage->createUser('jdoe', 'John Doe');  // Create user with id 'jdoe' and displayname 'John Doe'. 'jdoe' is the user's unique identifier.
$secret_storage->setSecret('jdoe', '4B7AD80B70FC758C99EFDD7E93932EEE43B9378A1AE5E26098B912C2ECA91828'); // Set the user's secret
// Set some other data that is associated with the user
$user_storage->setNotificationType('jdoe', 'APNS');
$user_storage->setNotificationAddress('jdoe', '251afb4304140542c15252e4a07c4211b441ece5');
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
