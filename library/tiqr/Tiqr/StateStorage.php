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
     *                     types are 'file' and 'memcache'.
     * @param array $options The options to pass to the storage
     *                       instance. See the documentation
     *                       in the StateStorage/ subdirectory for
     *                       options per type.
     * @throws Exception If an unknown type is requested.
     */
    public static function getStorage($type="file", $options=array())
    {
        switch ($type) {
            case "file":
                require_once("Tiqr/StateStorage/File.php");
                $instance = new Tiqr_StateStorage_File($options);
                break;
            case "memcache":
                require_once("Tiqr/StateStorage/Memcache.php");
                $instance = new Tiqr_StateStorage_Memcache($options);
                break;
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

                $pdoInstance = new PDO($options['dsn'],$options['username'],$options['password']);
                // Set a hard-coded default for the probability the expired state is removed
                // 10 translates to: expired entries are removed 10 in every 1000 write actions
                $cleanupProbability = 10;
                if (array_key_exists('cleanup_probability', $options) && is_int($options['cleanup_probability'])) {
                    $cleanupProbability = $options['cleanup_probability'];
                }

                $tablename = $options['table'];
                $instance = new Tiqr_StateStorage_Pdo($pdoInstance, $tablename, $cleanupProbability);
                break;
            default:
                if (!isset($type)) {
                    throw new Exception('Class name not set');
                } elseif (!class_exists($type)) {
                    throw new Exception('Class not found: ' . var_export($type, TRUE));
                } elseif (!is_subclass_of($type, 'Tiqr_StateStorage_Abstract')) {
                    throw new Exception('Class ' . $type . ' not subclass of Tiqr_StateStorage_Abstract');
                }
                $instance = new $type($options);
        }

        $instance->init();
        return $instance;
    }
}
