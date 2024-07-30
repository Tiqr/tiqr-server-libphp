<?php

namespace TestServer;

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Log\AbstractLogger;

class TestServerPsrLogger extends AbstractLogger
{
    private $logFile;
    private $logid;
    public function __construct(string $logFile = '')
    {
        // Parent has no __construct

        // Set logid to a unique value
        $this->logid = substr(uniqid(), -6);

        $highwater = 500;   // lines
        $lowwater = 400;    // lines

        // Keep the size of the logfile between $lowwater and $highwater lines by truncating it to $lowwater lines when
        // it exceeds $highwater lines by removing the oldest (first) lines.
        $this->logFile = $logFile;
        if ((strlen($this->logFile) != 0) && file_exists($this->logFile)) {
            $lines = file($this->logFile);
            if (count($lines) > $highwater) {
                $lines = array_slice($lines, -$lowwater);
                file_put_contents($this->logFile, $lines);
            }
        }
    }
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        error_log(">$level $message");

        if (strlen($this->logFile) == 0) {
            return;
        }
        // Write the $message to our log file
        $logline = date('Y-m-d H:i:s') . " [$this->logid] $level $message\n";
        file_put_contents($this->logFile, $logline, FILE_APPEND);
    }
}