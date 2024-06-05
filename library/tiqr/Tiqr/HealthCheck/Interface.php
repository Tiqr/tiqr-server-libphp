<?php

interface Tiqr_HealthCheck_Interface
{
    /**
     * Do a health check
     *
     * @param string &$statusMessage: a message that describes the status of the health check.
     *
     * This message is set by the health check when it fails to provide more information about the failure.
     * This message should be a short string that can be displayed to a user or logged.
     * It must not contain any sensitive information.
     *
     * @return bool: true when the healthcheck is successful, false otherwise
     *
     * A class that does not implement a health check always returns true
     */
    public function healthCheck(string &$statusMessage = ''): bool;
}
