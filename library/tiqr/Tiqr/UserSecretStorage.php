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
    public static function getSecretStorage($type="file", LoggerInterface $logger, $options=array())
    {
        switch ($type) {
            case "file":
                require_once("Tiqr/UserSecretStorage/File.php");
                return new Tiqr_UserSecretStorage_File($options, $logger);
                break;
            case "pdo":
                require_once("Tiqr/UserSecretStorage/Pdo.php");
                return new Tiqr_UserSecretStorage_Pdo($options, $logger);
            case "oathserviceclient":
                require_once("Tiqr/UserSecretStorage/OathServiceClient.php");
                return new Tiqr_UserSecretStorage_OathServiceClient($options, $logger);
        }
        throw new RuntimeException(sprintf('Unable to create a UserSecretStorage instance of type: %s', $type));
    }
}
