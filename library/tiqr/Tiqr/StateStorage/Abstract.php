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

use Psr\Log\LoggerInterface;

/**
 * The Abstract base class for all StateStorage implementations
 * StateStorages are meant to store the information that is used in the
 * enrollment and authentication processes, or to keep track of whether
 * a user is logged in.
 * @author ivo
 *
 */
abstract class Tiqr_StateStorage_Abstract implements Tiqr_StateStorage_StateStorageInterface
{
    /**
     * The options for the storage. Derived classes can access this
     * to retrieve options configured for the state storage.
     * @var array
     */
    protected $_options = array();

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * An initializer that will be called directly after instantiating
     * the storage. Derived classes can override this to perform 
     * initialization of the storage.
     * 
     * Note: this method is ont abstract since not every derived class
     * will want to implement this.
     */
    public function init(): void
    {
        
    }

    /**
     * The constructor to construct a state storage instance. Should not be
     * called directly, use the Tiqr_StateStorage factory to construct
     * a state storage instance of a certain type.
     * @param array $options An array of options for the state storage
     */
    public function __construct($options, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->_options = $options;
    }
}
