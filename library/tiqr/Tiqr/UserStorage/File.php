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
    protected $_path;

    /**
     * Create an instance
     * @param $config
     */
    public function __construct($config, LoggerInterface $logger, $secretconfig = array())
    {
        parent::__construct($config, $secretconfig, $logger);
        $this->logger = $logger;
        $this->_path = $config["path"];
    }

    /**
     * This function takes care of actually saving the user data to a JSON file.
     * @param String $userId
     * @param array $data
     */
    protected function _saveUser($userId, $data)
    {
        if (file_put_contents($this->getPath().$userId.".json", json_encode($data)) === false) {
            $this->logger->error('Unable to save the user to user storage (file storage)');
            return false;
        }
        return true;
    }
  
    /**
     * This function takes care of loading the user data from a JSON file.
     * @param String $userId
     * @return false if the data is not present, or an array containing the data.
     */
    protected function _loadUser($userId, $failIfNotFound = TRUE)
    {
        $fileName = $this->getPath().$userId.".json";

        $data = NULL;
        if (file_exists($fileName)) { 
            $data = json_decode(file_get_contents($this->getPath().$userId.".json"), true);
        }

        if ($data === NULL) {
            if ($failIfNotFound) {
                throw new Exception('Error loading data for user: ' . var_export($userId, TRUE));
            } else {
                $this->logger->error('Error loading data for user from user storage (file storage)');
                return false;
            }
        } else {
            return $data;
        }
    }

    /**
     * Delete user data (un-enroll).
     * @param String $userId
     */
    protected function _deleteUser($userId)
    {
        $filename = $this->getPath().$userId.".json";
        if (file_exists($filename)) {
            unlink($filename);
        } else {
            $this->logger->error('Unable to remove the user from user storage (file storage)');
        }
    }

    /**
     * Retrieve the path where the json files are stored.
     * @return String
     */
    public function getPath()
    {
         if (substr($this->_path, -1)!="/") return $this->_path."/";
         return $this->_path;
    }
    
}
