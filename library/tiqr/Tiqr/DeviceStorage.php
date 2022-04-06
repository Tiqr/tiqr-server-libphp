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
require_once("Tiqr/DeviceStorage/Abstract.php");

use Psr\Log\LoggerInterface;

/**
 * A utility class to create an instance of a specific type of storage that 
 * can retrieve deviceTokens.
 * @author ivo
 */
class Tiqr_DeviceStorage
{
    /**
     * Return a storage instance of a certain type.
     * Supported types are dummy: stores the device token 
     * @param String $type The type of storage to return. Supported are:
     *                     - dummy: treats a notificationToken as a deviceToken
     *                     - tokenexchange: uses a tokenexchange service to 
     *                       exchange notificationTokens for deviceTokens. 
     * @param array $options Options for the device storage. See the
     *                       documentation in DeviceStorage/ for specific 
     *                       config options per type.
     * @param LoggerInterface $logger
     * @throws Exception An exception if an unknown storage is requested.
     */
    public static function getStorage($type="dummy", $options=array(), LoggerInterface $logger)
    {
        switch ($type) {
            case "dummy":
                require_once("Tiqr/DeviceStorage/Dummy.php");
                $instance = new Tiqr_DeviceStorage_Dummy($options, $logger);
                $instance->init();
                return $instance;
            case "tokenexchange":
                require_once("Tiqr/DeviceStorage/TokenExchange.php");
                $instance = new Tiqr_DeviceStorage_TokenExchange($options, $logger);
                $instance->init();
                return $instance;
        }

        throw new RuntimeException(sprintf('Unable to create a DeviceStorage instance of type: %s', $type));
    }
}
