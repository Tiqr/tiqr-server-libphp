<?php
/**
 * This file is part of the tiqr project.
 * 
 * The tiqr project aims to provide an open implementation for 
 * authentication using mobile devices. It was initiated by 
 * SURFnet and developed by Egeniq.
 *
 * More information: http://www.tiqr.org
 *
 * @author Ivo Jansch <ivo@egeniq.com>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2012 SURFnet BV
 */

use Psr\Log\LoggerInterface;

/**
 * Class implementing a factory to retrieve user secrets.
 *
 * @author lineke
 */
class Tiqr_UserSecretStorage
{
    /**
     * Get a secret storage of a certain type
     *
     * @param String $type The type of storage to create. Supported
     *                     types are 'file', 'pdo' or 'oathservice'.
     * @param array $options The options to pass to the storage
     *                       instance. See the documentation
     *                       in the UserSecretStorage/ subdirectory for
     *                       options per type.
     *
     * @return Tiqr_UserSecretStorage_Interface
     * @throws RuntimeException If an unknown type is requested.
     * @throws RuntimeException When the options configuration array misses a required parameter
     */
    public static function getSecretStorage(string $type, LoggerInterface $logger, array $options): Tiqr_UserSecretStorage_Interface
    {
        // If not provided in config, we fall back to dummy (no) encryption
        $encryptionType = $config['encryption']['type'] ?? 'dummy';
        // If the encryption configuration is not configured, we fall back to an empty encryption configuration
        $encryptionOptions = $config['encryption'] ?? [];
        $encryption = Tiqr_UserStorage_Encryption::getEncryption($logger, $encryptionType, $encryptionOptions);

        switch ($type) {
            case "file":
                require_once("Tiqr/UserSecretStorage/File.php");
                if (!array_key_exists('path', $options)) {
                    throw new RuntimeException('The path is missing in the UserSecretStorage configuration');
                }
                $path = $options['path'];
                return new Tiqr_UserSecretStorage_File($encryption, $path, $logger);
            case "pdo":
                // Input validation on the required configuration options
                if (!array_key_exists('dsn', $options)) {
                    throw new RuntimeException('The dsn is missing in the UserSecretStorage configuration');
                }
                if (!array_key_exists('username', $options)) {
                    throw new RuntimeException('The username is missing in the UserSecretStorage configuration');
                }
                if (!array_key_exists('password', $options)) {
                    throw new RuntimeException('The password is missing in the UserSecretStorage configuration');
                }

                $tableName = $options['table'] ?? 'tiqrusersecret';
                $dsn = $options['dsn'];
                $userName = $options['username'];
                $password = $options['password'];

                try {
                    $handle = new PDO($dsn, $userName, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION) );
                } catch (PDOException $e) {
                    $logger->error(
                        sprintf('Unable to establish a PDO connection. Error message from PDO: %s', $e->getMessage())
                    );
                    throw ReadWriteException::fromOriginalException($e);
                }

                require_once("Tiqr/UserSecretStorage/Pdo.php");
                return new Tiqr_UserSecretStorage_Pdo($encryption, $logger, $handle, $tableName);
            case "oathserviceclient":
                require_once("Tiqr/UserSecretStorage/OathServiceClient.php");
                if (!array_key_exists('apiURL', $options)) {
                    throw new RuntimeException('The apiURL is missing in the UserSecretStorage configuration');
                }
                if (!array_key_exists('consumerKey', $options)) {
                    throw new RuntimeException('The consumerKey is missing in the UserSecretStorage configuration');
                }

                $apiClient = new Tiqr_API_Client();
                $apiClient->setBaseURL($options['apiURL']);
                $apiClient->setConsumerKey($options['consumerKey']);
                return new Tiqr_UserSecretStorage_OathServiceClient($apiClient, $logger);
        }
        throw new RuntimeException(sprintf('Unable to create a UserSecretStorage instance of type: %s', $type));
    }
}
