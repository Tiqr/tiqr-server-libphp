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
     * @param LoggerInterface $logger The logger to use.
     * @param array $options The options to pass to the storage instance.
     *                       This contains the configuration options for the UserSecretStorage
     *                       specified in the $type parameter.
     *                       See the documentation in the UserSecretStorage subdirectory for options
     *                       per type.
     *
     *                       For the file and pdo UserSecretStorage types the encryption and decryption options
     *                       are used to encrypt the secrets. These are ignored when the oathserviceclient
     *                       is used.
     *
     *                       encryption:
     *                       The $options array can contain an 'encryption' key that specifies
     *                       the encryption method and configuration to use for encrypting the secrets.
     *                       The encryption method is specified in the 'type' key. Available types are:
     *                       - 'plain' : (default) no encryption
     *                       - 'dummy' : alias for 'plain'
     *                       - A custom encryption class can be used by specifying the class name. The custom encryption
     *                         class must implement the Tiqr_UserSecretStorage_Encryption_Interface
     *                       The encryption options are documented in the UserSecretStorage/Encryption/
     *                       subdirectory.
     *
     *                       decryption:
     *                       The $options array can contain a 'decryption' kay that lists additional
     *                       encryption methods and their configuration to use for decrypting the secrets only.
     *                       The format is slightly different from the encryption configuration to allow
     *                       multiple decryption methods to be specified.
     *                       The decryption configuration is optional. If not specified it defaults to the
     *                       'plain' encryption method.
     *                       Note that all decryption methods specified in the 'decryption' configuration
     *                       must have a unique type as returned by the encryption method's
     *                       Tiqr_UserSecretStorage_Encryption_Interface::get_type() implementation
     *
     * The $options array has the following structure:
     * array(
     *    // UserSecretStorage configuration
     *    '<option_1_for_usersecretstorage>' => '<value>',
     *    '<option_2_for_usersecretstorage>' => '<value>',
     *
     *     // Encryption configuration
     *     // This configuration is used for both encryption and decryption
     *     // If not provided in config, we fall back to dummy/plain (no) encryption
     *     // <encryption_type> is the type of encryption or a custom encryption class name
     *    'encryption' => array(
     *        'type' => '<encryption_type>',
     *        '<option_1_for_encryption>' => '<value>',
     *        '<option_2_for_encryption>' => '<value>',
     *     ),
     *
     *     // Additional decryption method configuration
     *     // This configuration is only used for decryption, the encryption
     *     // configuration is also used for decryption and does not need to be repeated here.
     *     // <encryption_type_1> and <encryption_type_2> is the type of encryption or a custom encryption
     *     // class name.
     *     'decryption' => array(
     *         '<encryption_type_1>' => array(
     *             '<option_1_for_encryption_1>' => '<value>',
     *             '<option_2_for_encryption_1>' => '<value>',
     *         ),
     *         '<encryption_type_2>' => array(
     *              '<option_1_for_encryption_2>' => '<value>',
     *              '<option_2_for_encryption_2>' => '<value>',
     *         ),
     *     )
     * );
 *
     *
     * @return Tiqr_UserSecretStorage_Interface
     * @throws RuntimeException If an unknown type is requested.
     * @throws RuntimeException When the options configuration array misses a required parameter
     */
    public static function getSecretStorage(string $type, LoggerInterface $logger, array $options): Tiqr_UserSecretStorage_Interface
    {
        $encryption = null;
        $decryption = [];
        if ( $type != 'oathserviceclient') {
            // Create encryption instance
            // If not provided in config, we fall back to dummy/plain (no) encryption
            $encryptionType = $options['encryption']['type'] ?? 'plain';
            // If the encryption configuration is not configured, we fall back to an empty encryption configuration
            $encryptionOptions = $options['encryption'] ?? [];
            $encryption = Tiqr_UserSecretStorage_Encryption::getEncryption($logger, $encryptionType, $encryptionOptions);

            // Create decryption instance(s)
            // If not provided in config, we fall back to dummy/plain (no) encryption
            $decryptionOptions = $options['decryption'] ?? ['plain' => []];
            foreach ($decryptionOptions as $decryptionType => $decryptionConfig) {
                $decryption[$decryptionType] = Tiqr_UserSecretStorage_Encryption::getEncryption($logger, $decryptionType, $decryptionConfig);
            }
        }

        switch ($type) {
            case "file":
                if (!array_key_exists('path', $options)) {
                    throw new RuntimeException('The path is missing in the UserSecretStorage configuration');
                }
                $path = $options['path'];
                return new Tiqr_UserSecretStorage_File($encryption, $path, $logger, $decryption);
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
                return new Tiqr_UserSecretStorage_Pdo($encryption, $logger, $handle, $tableName, $decryption);

            case "oathserviceclient":
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
