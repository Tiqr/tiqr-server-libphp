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

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns additional attributes for the user.
     *
     * @param string $userId User identifier.
     * @return array additional user attributes
     */
    public function getAdditionalAttributes(string $userId): array
    {
        return array();
    }
}
