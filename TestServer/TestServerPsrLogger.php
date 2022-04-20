<?php

namespace TestServer;

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Log\AbstractLogger;

class TestServerPsrLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        error_log(">$level $message");
    }
}