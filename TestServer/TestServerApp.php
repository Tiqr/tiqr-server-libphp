<?php

/*
@license New BSD License - See LICENSE file for details.
@copyright (C) 2022 SURF BV
*/

namespace TestServer;

use Exception;

abstract class App
{
    // Log error, return HTTP message and die
    abstract public static function error_exit(int $http_code, string $message);

    // Log
    abstract public static function log_info($message);

    abstract public static function log_warning($message);

    abstract public static function log_error($message);


    /** Translate $path to an absolute path
     * @param string $path path to existing file or directory. Can be an absolute path, or a path relative to $reference_path
     * @param string $referance_path if $path is a relative path, it is resolved relative to $reference_path
     * @return string | false the absolute path. false when $path (relative to $reference_path) does not exist
     */
    public static function realpath(string $path, string $reference_path='.')
    {
        $current_path = getcwd();
        if (false === $current_path) {
            return false;
        }
        if (! chdir($reference_path) ) {
            return false;
        }
        $absolute_path = \realpath($path);  // Returns false if $path is not found
        chdir($current_path);

        return $absolute_path;
    }

    // Get array with SERVER parameters
    abstract public function getSERVER(): array;

    // Get array with GET parameters
    abstract public function getGET(): array;

    // Get array with POST parameters
    abstract public function getPOST(): array;

    // Get HTTP request body
    abstract public function getBODY(): string;
}

class TestServerApp extends App
{
    private $SERVER = array();
    private $GET = array();
    private $POST = array();
    private $BODY = '';
    private $router;

    public final function __construct($router)
    {
        $this->SERVER = $_SERVER;
        $this->GET = $_GET;
        $this->POST = $_POST;
        $this->BODY = file_get_contents('php://input');

        if (!is_object($router) || !method_exists($router, 'Route')) {
            self::error_exit(500, 'TestServerApp : $router must be object with Route($app, $uri) method');
        }

        $this->router = $router;
    }

    function HandleHTTPRequest()
    {
        self::log_info("--== START ==--");
        $uri = $this->SERVER["REQUEST_URI"];
        $method = $this->SERVER["REQUEST_METHOD"];
        self::log_info("$method $uri");
        // Print HTTP headers from the request
        foreach ($this->SERVER as $k => $v) {
            if (strpos($k, "HTTP_") === 0) {
                // Transform back to HTTP header style
                $k = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                self::log_info("$k: $v");
            }
        }
        if ($method == 'POST') {
            self::log_info($this->BODY);
        }
        if (strlen($uri) == 0) {
            self::error_exit(500, 'Empty REQUEST_URI');
        }
        if ($uri[0] != '/') {
            self::error_exit(500, 'REQUEST_URI must start with "/"');
        }
        self::log_info('--');   // End of the HTTP dump

        $path = parse_url($uri, PHP_URL_PATH);

        try {
            $this->router->Route($this, $path);
        } catch (Exception $e) {
            self::error_exit(500, 'Exception: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString());
        }
    }


    static function error_exit(int $http_code, string $message): void
    {
        self::log_error($message);
        header("HTTP/1.1 $http_code");
        die(htmlentities($message));
    }

    static function log_info($message)
    {
        error_log("INFO: $message");
    }

    static function log_warning($message)
    {
        error_log("WARNING: $message");
    }

    static function log_error($message)
    {
        error_log("ERROR: $message");
    }

    /**
     * @return array
     */
    public function getSERVER(): array
    {
        return $this->SERVER;
    }

    /**
     * @return array
     */
    public function getGET(): array
    {
        return $this->GET;
    }

    /**
     * @return array
     */
    public function getPOST(): array
    {
        return $this->POST;
    }

    /**
     * @return string
     */
    public function getBODY(): string
    {
        return $this->BODY;
    }

}