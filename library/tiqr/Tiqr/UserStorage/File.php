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

require_once 'Tiqr/UserStorage/FileTrait.php';
require_once 'Tiqr/UserStorage/GenericStore.php';

/**
 * This user storage implementation implements a simple user storage using json files.
 * This is mostly for demonstration and development purposes. In a production environment
 * please supply your own implementation that hosts the data in your user database OR
 * in a secure (e.g. hardware encrypted) storage.
 * @author ivo
 */
class Tiqr_UserStorage_File extends Tiqr_UserStorage_GenericStore
{
    use FileTrait;

    protected $path;

    /**
     * Create an instance
     * @param array $config
     * @param LoggerInterface $logger
     */
    public function __construct($config, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);
        $this->path = $config["path"];
    }
}
