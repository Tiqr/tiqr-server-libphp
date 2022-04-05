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

require_once("Tiqr/OcraService/Abstract.php");

use Psr\Log\LoggerInterface;

/**
 * Class implementing a factory to retrieve the ocra service to use
 *
 * @author lineke
 */
class Tiqr_OcraService
{
    /**
     * Get a ocra service of a certain type (default: 'tiqr')
     *
     * @param String $type The type of ocra service to create. Supported
     *                     types are 'tiqr' or 'oathservice'.
     * @param array $options The options to pass to the ocra service
     *                       instance.
     *
     * @return Tiqr_OcraService_Interface
     * @throws Exception An exception if an unknown orca service type is requested.
     */
    public static function getOcraService($type="tiqr", $options=array(), LoggerInterface $logger)
    {
        switch ($type) {
            case "tiqr":
                require_once("Tiqr/OcraService/Tiqr.php");
                return new Tiqr_OcraService_Tiqr($options, $logger);
            case "oathserviceclient":
                require_once("Tiqr/OcraService/OathServiceClient.php");
                return new Tiqr_OcraService_OathServiceClient($options, $logger);
        }
        throw new RuntimeException(sprintf('Unable to create a OcraService instance of type: %s', $type));
    }
}
