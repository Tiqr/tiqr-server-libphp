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
 * @copyright (C) 2010-2011 SURFnet BV
 */


/**
 * @internal includes
 */
require_once("Tiqr/StateStorage/Abstract.php");

use Psr\Log\LoggerInterface;

/**
 * Utility class to create specific StateStorage instances.
 * StateStorage is used to store temporary information used during 
 * enrollment and authentication sessions.
 * @author ivo
 *
 */
class Tiqr_StateStorage
{
    /**
     * Get a storage of a certain type (default: 'file')
     * @param String $type The type of storage to create. Supported
     *                     types are 'file', 'pdo' and 'memcache'.
     * @param array $options The options to pass to the storage
     *                       instance. See the documentation
     *                       in the StateStorage/ subdirectory for
     *                       options per type.
     * @throws RuntimeException If an unknown type is requested.
     * @throws RuntimeException When the options configuration array misses a required parameter
     *
     */
    public static function getStorage($type="file", $options=array(), LoggerInterface $logger)
    {
        switch ($type) {
            case "file":
                require_once("Tiqr/StateStorage/File.php");
                if (!array_key_exists('path', $options)) {
                    throw new RuntimeException('The path is missing in the StateStorage configuration');
                }
                $instance = new Tiqr_StateStorage_File($options['path'], $logger);
                $instance->init();
                return $instance;
            case "memcache":
                require_once("Tiqr/StateStorage/Memcache.php");
                $instance = new Tiqr_StateStorage_Memcache($options, $logger);
                $instance->init();
                return $instance;
            case "pdo":
                require_once("Tiqr/StateStorage/Pdo.php");

                $requiredOptions = ['table', 'dsn', 'username', 'password'];
                foreach ($requiredOptions as $requirement) {
                    if (!array_key_exists($requirement, $options)) {
                        throw new RuntimeException(
                            sprintf(
                                'Please configure the "%s" configuration option for the PDO state storage',
                                $requirement
                            )
                        );
                    }
                }

                $pdoInstance = new PDO(
                    $options['dsn'],
                    $options['username'],
                    $options['password'],
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                );
                // Set a hard-coded default for the probability the expired state is removed
                // 0.1 translates to a 10% chance the garbage collection is executed
                $cleanupProbability = 0.1;
                if (array_key_exists('cleanup_probability', $options) && is_numeric($options['cleanup_probability'])) {
                    $cleanupProbability = $options['cleanup_probability'];
                }

                $tablename = $options['table'];
                $instance = new Tiqr_StateStorage_Pdo($pdoInstance, $logger, $tablename, $cleanupProbability);
                $instance->init();
                return $instance;

        }

        throw new RuntimeException(sprintf('Unable to create a StateStorage instance of type: %s', $type));
    }
}
