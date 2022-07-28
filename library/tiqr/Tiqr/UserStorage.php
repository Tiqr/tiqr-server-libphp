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
 * Class implementing a factory to retrieve user data.
 *
 * @author ivo
 */
class Tiqr_UserStorage
{
    /**
     * Get a storage of a certain type (default: 'file')
     *
     * @param String $type The type of storage to create. Supported
     *                     types are 'file', 'pdo' or the full class name of a custom solution.
     * @param array $options The options to pass to the storage
     *                       instance. See the documentation
     *                       in the UserStorage/ subdirectory for
     *                       options per type.
     * @param LoggerInterface $logger
     *
     * @return Tiqr_UserStorage_Interface
     *
     * @throws Exception An exception if an unknown user storage is requested.
     */
    public static function getStorage(string $type="file", array $options=array(), LoggerInterface $logger): Tiqr_UserStorage_Interface
    {
        switch ($type) {
            case "file":
                require_once("Tiqr/UserStorage/File.php");
                return new Tiqr_UserStorage_File($options, $logger);
            case "pdo":
                require_once("Tiqr/UserStorage/Pdo.php");
                return new Tiqr_UserStorage_Pdo($options, $logger);
        }

        throw new RuntimeException(sprintf('Unable to create a UserStorage instance of type: %s', $type));
    }
}
