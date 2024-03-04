<?php

namespace TestServer;

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Log\AbstractLogger;

class TestServerPsrLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        error_log(">$level $message");
    }
}