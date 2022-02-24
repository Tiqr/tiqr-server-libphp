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

require_once __DIR__ . '/TestServerApp.php';
require_once __DIR__ . '/TestServerController.php';
require_once __DIR__ . '/TestServerView.php';

// TODO: Update / set configuration. See readme
$config_filename = 'config';

$config_dir = __DIR__ . '/config/';

$config = array();
if (file_exists($config_dir . $config_filename)) {
    $config = json_decode(file_get_contents($config_dir . $config_filename), true);
}

$host_url = $config['host_url'] ?? 'http://localhost:8000';
$tiqrauth_protocol = $config['tiqrauth_protocol'] ?? 'tiqrauth';
$tiqrenroll_protocol = $config['tiqrenroll_protocol'] ?? 'tiqrenroll';

$test_server = new TestServerController($host_url, $tiqrauth_protocol, $tiqrenroll_protocol);
$app = new TestServerApp($test_server);
$app->HandleHTTPRequest();

return true;

