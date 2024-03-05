<?php

/*
@license New BSD License - See LICENSE file for details.
@copyright (C) 2022 SURF BV
*/

// Entry point of the test server
// To run with the php-cli build in webserver use:
//
// php -S <ip/host>:<port> app.php
//
// e.g.: php -S 0.0.0.0:8000 app.php

namespace TestServer;

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/TestServerApp.php';
require_once __DIR__ . '/TestServerController.php';
require_once __DIR__ . '/TestServerView.php';
require_once __DIR__ . '/TestServerPsrLogger.php';

# Get config filename and directory
$config_filename = 'config';
$config_dir = __DIR__ . '/config';

# Read configuration
$config = array();
if (file_exists($config_dir . '/' . $config_filename)) {
    $config = json_decode(file_get_contents($config_dir . '/' . $config_filename), true);
}

# Directory for storing session info and user data
$storage_dir = __DIR__ . '/storage/';

$host_url = $config['host_url'] ?? 'http://localhost:8000';
$tiqrauth_protocol = $config['tiqrauth_protocol'] ?? 'tiqrauth';
$tiqrenroll_protocol = $config['tiqrenroll_protocol'] ?? 'tiqrenroll';
$token_exchange_url = $config['token_exchange_url'] ?? 'https://tx.tiqr.org/tokenexchange/';
$token_exchange_appid = $config['token_exchange_appid'] ?? 'tiqr';
$apns_certificate_filename =  App::realpath($config['apns_certificate_filename'] ?? '', $config_dir);
$apns_environment =  $config['apns_environment'] ?? 'sandbox';
$firebase_projectId = $config['firebase_projectId'] ?? 'abc-1234';
$firebase_credentialsFile = App::realpath($config['firebase_credentialsFile'] ?? '',$config_dir);
$firebase_cacheTokens = $config['$firebase_cacheTokens'] ?? false;
$firebase_tokenCacheDir = $config['firebase_tokencachedir'] ?? $storage_dir;

$psr_logger = new TestServerPsrLogger();
$test_server = new TestServerController($psr_logger, $host_url, $tiqrauth_protocol, $tiqrenroll_protocol, $token_exchange_url, $token_exchange_appid, $apns_certificate_filename, $apns_environment, $firebase_projectId, $firebase_credentialsFile, $storage_dir, $firebase_cacheTokens, $firebase_tokenCacheDir);
$app = new TestServerApp($test_server);
$app->HandleHTTPRequest();

return true;

