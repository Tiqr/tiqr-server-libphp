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
     * Get a secret storage of a certain type (default: 'file')
     *
     * @param String $type The type of storage to create. Supported
     *                     types are 'file', 'pdo' or 'oathservice'.
     * @param array $options The options to pass to the storage
     *                       instance. See the documentation
     *                       in the UserSecretStorage/ subdirectory for
     *                       options per type.
     *
     * @return Tiqr_UserSecretStorage_Interface
     */
    public static function getSecretStorage(string $type = "file", LoggerInterface $logger, array $options = [])
    {
        // If not provided in config, we fall back to dummy (no) encryption
        $encryptionType = isset($config['encryption']['type']) ? $config['encryption']['type'] : 'dummy';
        // If the encryption configuration is not configured, we fall back to an empty encryption configuration
        $encryptionOptions = isset($config['encryption']) ? $config['encryption'] : [];
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

                $tableName = isset($options['table']) ? $options['table'] : 'tiqrusersecret';
                $dsn = $options['dsn'];
                $userName = $options['username'];
                $password = $options['password'];

                require_once("Tiqr/UserSecretStorage/Pdo.php");
                return new Tiqr_UserSecretStorage_Pdo($encryption, $logger, $dsn, $userName, $password, $tableName);
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
