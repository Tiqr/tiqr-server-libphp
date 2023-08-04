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

/**
 * This user storage implementation implements a simple user storage using json files.
 * This is mostly for demonstration and development purposes. In a production environment
 * please supply your own implementation that hosts the data in your user database OR
 * in a secure (e.g. hardware encrypted) storage.
 * @author ivo
 *
 * Note: This implementation does not employ locking or transactions, and is not safe when the same user is updated
 *       concurrently
 */
abstract class Tiqr_UserStorage_GenericStore extends Tiqr_UserStorage_Abstract
{

    /** Return data of user $userId
     * @param string $userId
     * @return array
     * @throws Exception
     */
    abstract protected function _loadUser(string $userId): array;

    /** Write data of $userId
     * @param string $userId
     * @param array $data
     * @throws Exception
     */
    abstract protected function _saveUser(string $userId, array $data): void;

    /**
     * @param string $userId
     * @return bool trues when user exists, false otherwise
     * @throws Exception
     */
    abstract protected function _userExists(string $userId): bool;


    /**
     * @see Tiqr_UserStorage_Interface::createUser()
     */
    public function createUser(string $userId, string $displayName) : void
    {
        $user = array("userId"=>$userId,
                      "displayName"=>$displayName);
        $this->_saveUser($userId, $user);
    }

    /**
     * @see Tiqr_UserStorage_Interface::userExists()
     */
    public function userExists(string $userId): bool
    {
        return $this->_userExists($userId);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getDisplayName()
     */
    public function getDisplayName(string $userId): string
    {
        if ($data = $this->_loadUser($userId)) {
            return $data["displayName"];
        }
        return '';
    }

    /**
     * @see Tiqr_UserStorage_Interface::getNotificationType()
     */
    public function getNotificationType(string $userId): string
    {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["notificationType"])) {
               return $data["notificationType"];
            }
        }
        return '';
    }

    /**
     * @see Tiqr_UserStorage_Interface::setNotificationType()
     */
    public function setNotificationType(string $userId, string $type): void
    {
        $data = $this->_loadUser($userId);
        $data["notificationType"] = $type;
        $this->_saveUser($userId, $data);
    }    
    
    /**
     * @see Tiqr_UserStorage_Interface::getNotificationAddress()
     */
    public function getNotificationAddress(string $userId): string
    {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["notificationAddress"])) {
               return $data["notificationAddress"];
            }
        }
        $this->logger->info('Unable to find notification address for user');
        return '';
    }

    /**
     * @see Tiqr_UserStorage_Interface::setNotificationAddress()
     */
    public function setNotificationAddress(string $userId, string $address): void
    {
        $data = $this->_loadUser($userId);
        $data["notificationAddress"] = $address;
        $this->_saveUser($userId, $data);
    } 

    /**
     * @see Tiqr_UserStorage_Interface::getLoginAttempts()
     */
    public function getLoginAttempts(string $userId): int
    {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["loginattempts"])) {
                return $data["loginattempts"];
            }
        }
        return 0;
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::setLoginAttempts()
     */
    public function setLoginAttempts(string $userId, int $amount): void
    {
        $data = $this->_loadUser($userId);
        $data["loginattempts"] = $amount;
        $this->_saveUser($userId, $data);
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::isBlocked()
     */
    public function isBlocked(string $userId, int $tempBlockDuration = 0): bool
    {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["blocked"]) && $data["blocked"]) {
                return true;
            }

            // Check temporary block
            $timestamp = $this->getTemporaryBlockTimestamp($userId);
            if (0 == $timestamp || 0 == $tempBlockDuration) {
                return false; // No temp block timestamp set or no tempBlockDuration provided
            }

            if ($timestamp + $tempBlockDuration * 60 < time()) {
                return false;   // Temp block expired
            }
        }
        return true;    // Blocked by temp block
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::setTemporaryBlockAttempts()
     */
    public function setTemporaryBlockAttempts(string $userId, int $amount): void {
        $data = $this->_loadUser($userId);
        $data["temporaryBlockAttempts"] = $amount;
        $this->_saveUser($userId, $data);
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::getTemporaryBlockAttempts()
     */
    public function getTemporaryBlockAttempts(string $userId): int {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["temporaryBlockAttempts"])) {
                return $data["temporaryBlockAttempts"];
            }
        }
        return 0;
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::setTemporaryBlockTimestamp()
     */
    public function setTemporaryBlockTimestamp(string $userId, int $timestamp): void {
        $data = $this->_loadUser($userId);
        $data["temporaryBlockTimestamp"] = $timestamp;
        $this->_saveUser($userId, $data);
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::getTemporaryBlockTimestamp()
     */
    public function getTemporaryBlockTimestamp(string $userId): int {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["temporaryBlockTimestamp"])) {
                return $data["temporaryBlockTimestamp"];
            }
        }
        return 0;
    }
    
    /**
     * @see Tiqr_UserStorage_Interface::block()
     */
    public function setBlocked(string $userId, bool $blocked): void
    {
        $data = $this->_loadUser($userId);
        $data["blocked"] = $blocked;
        $this->_saveUser($userId, $data);
    }
    
}
