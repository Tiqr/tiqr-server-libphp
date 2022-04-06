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
 * @author Peter Verhage <peter@egeniq.com>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2012 SURFnet BV
 */

use Psr\Log\LoggerInterface;

require_once 'Tiqr/UserStorage/Interface.php';

/**
 * Abstract base implementation for user storage. 
 *
 * Adds built-in support for encryption of the user secret.
 * 
 * @author peter
 */
abstract class Tiqr_UserStorage_Abstract implements Tiqr_UserStorage_Interface
{
    protected $logger;

    public function __construct($config, LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns additional attributes for the user.
     *
     * @param string $userId User identifier.
     *
     * @return array additional user attributes
     */
    public function getAdditionalAttributes($userId) 
    {
        return array();
    }
}
