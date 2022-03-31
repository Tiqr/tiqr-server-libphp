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
 * @author Patrick Honing <Patrick.Honing@han.nl>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2012 SURFnet BV
 * 
 * Create SQL table (MySQL):
 * CREATE TABLE `tiqruser` (`userid` varchar(10) PRIMARY KEY, `displayname` varchar(45),`blocked` int,`loginattempts` int,
 * `tmpblockattempts` int,`tmpblocktimestamp` varchar(45) default NULL,`notificationtype` varchar(10),`notificationaddress` varchar(45))
 * 
 */

use Psr\Log\LoggerInterface;


/**
 * This user storage implementation implements a user storage using PDO.
 * It is usable for any database with a PDO driver
 * 
 * @author Patrick Honing <Patrick.Honing@han.nl>
 */
class Tiqr_UserStorage_Pdo extends Tiqr_UserStorage_Abstract
{
    protected $handle = null;
    protected $tablename;
    
    /**
     * Create an instance
     * @param array $config
     * @param array $secretconfig
     */
    public function __construct($config, LoggerInterface $logger, $secretconfig = array())
    {
        parent::__construct($config, $logger, $secretconfig);
        $this->tablename = isset($config['table']) ? $config['table'] : 'tiqruser';
        try {
            $this->handle = new PDO($config['dsn'],$config['username'],$config['password']);
        } catch (PDOException $e) {
            $this->logger->error(
                sprintf('Unable to establish a PDO connection. Error message from PDO: %s', $e->getMessage())
            );
        }
    }

    public function createUser($userId, $displayName)
    {
        if ($this->userExists($userId)) {
            $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET displayname = ? WHERE userid = ?");
        } else {
            $sth = $this->handle->prepare("INSERT INTO ".$this->tablename." (displayname,userid) VALUES (?,?)");
        }
        if ($sth->execute(array($displayName,$userId))){
            return $this->userExists($userId);
        }
        $this->logger->error('The user could not be saved in the user storage (PDO)');
    }

    /**
     * Be carefull! This method does not return an expected boolean value. Instead it returns:
     * - void (if user was not found)
     * - userid (if user was found)
     *
     * @param $userId
     * @return mixed
     */
    public function userExists($userId)
    {
        $sth = $this->handle->prepare("SELECT userid FROM ".$this->tablename." WHERE userid = ?");
        if ($sth->execute(array($userId))) {
            return $sth->fetchColumn();
        }
        $this->logger->error('Unable fot find user in user storage (PDO)');
    }
    
    public function getDisplayName($userId)
    {
        $sth = $this->handle->prepare("SELECT displayname FROM ".$this->tablename." WHERE userid = ?");
        if ($sth->execute(array($userId))) {
            return $sth->fetchColumn();
        }
        $this->logger->error('Retrieving the users display name failed in the user storage (PDO)');
    }

    public function getNotificationType($userId)
    {
        $sth = $this->handle->prepare("SELECT notificationtype FROM ".$this->tablename." WHERE userid = ?");
        if ($sth->execute(array($userId))) {
            return $sth->fetchColumn();
        }
        $this->logger->error('Unable to retrieve notification type from user storage (PDO)');
    }
    
    public function setNotificationType($userId, $type)
    {
        $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET notificationtype = ? WHERE userid = ?");
        if (!$sth->execute(array($type,$userId))) {
            $this->logger->error('Unable to set the notification type in user storage for a given user (PDO)');
        }
    }
    
    public function getNotificationAddress($userId)
    {
        $sth = $this->handle->prepare("SELECT notificationaddress FROM ".$this->tablename." WHERE userid = ?");
        if ($sth->execute(array($userId))) {
            return $sth->fetchColumn();
        }
        $this->logger->error('Unable to retrieve notification address from user storage (PDO)');
    }
    
    public function setNotificationAddress($userId, $address)
    {
        $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET notificationaddress = ?  WHERE userid = ?");
        if (!$sth->execute(array($address,$userId))) {
            $this->logger->error('Unable to set the notification address in user storage for a given user (PDO)');
        }
    }
    
    public function getLoginAttempts($userId)
    {
        $sth = $this->handle->prepare("SELECT loginattempts FROM ".$this->tablename." WHERE userid = ?");
        if ($sth->execute(array($userId))) {
            return $sth->fetchColumn();
        }
        $this->logger->error('Unable to retrieve login attempts from user storage (PDO)');
    }
    
    public function setLoginAttempts($userId, $amount)
    {
        $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET loginattempts = ? WHERE userid = ?");
        if (!$sth->execute(array($amount,$userId))) {
            $this->logger->error('Unable to set login attempts in user storage for a given user (PDO)');
        }
    }
    
    public function isBlocked($userId, $duration)
    {
        if ($this->userExists($userId)) {
            $sth = $this->handle->prepare("SELECT blocked FROM ".$this->tablename." WHERE userid = ?");
            $sth->execute(array($userId));
            $blocked = ($sth->fetchColumn() == 1);
            $timestamp = $this->getTemporaryBlockTimestamp($userId);
            // if not blocked or block is expired, return false
            if (!$blocked || (false !== $timestamp && false != $duration && (strtotime($timestamp) + $duration * 60) < time())) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }
    
    public function setBlocked($userId, $blocked)
    {
        $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET blocked = ? WHERE userid = ?");
        $isBlocked = ($blocked) ? "1" : "0";
        if (!$sth->execute([$isBlocked, $userId])) {
            $this->logger->error('Unable to block the user in the user storage (PDO)');
        }
    }
    
    public function setTemporaryBlockAttempts($userId, $amount) {
        $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET tmpblockattempts = ? WHERE userid = ?");
        if (!$sth->execute(array($amount,$userId))) {
            $this->logger->error('Unable to set temp login attempts in user storage for a given user (PDO)');
        }
    }

    /**
     * @param $userId
     * @return int
     * @throws RuntimeException when the query fails, a runtime exception is raised. As continuing execution from that
     *                          point would either throw an error, or return 0
     */
    public function getTemporaryBlockAttempts($userId) {
        if ($this->userExists($userId)) {
            $sth = $this->handle->prepare("SELECT tmpblockattempts FROM ".$this->tablename." WHERE userid = ?");
            if (!$sth->execute(array($userId))) {
                throw new RuntimeException('Unable to get temp login attempts in user storage for a given user (PDO)');
            }
            return (int) $sth->fetchColumn();
        }
        return 0;
    }
    
    public function setTemporaryBlockTimestamp($userId, $timestamp)
    {
        $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET tmpblocktimestamp = ? WHERE userid = ?");
        if (!$sth->execute(array($timestamp,$userId))) {
            $this->logger->error('Unable to update temp lock timestamp in user storage for a given user (PDO)');
        }
    }
            
    public function getTemporaryBlockTimestamp($userId)
    {
        if ($this->userExists($userId)) {
            $sth = $this->handle->prepare("SELECT tmpblocktimestamp FROM ".$this->tablename." WHERE userid = ?");
            $sth->execute(array($userId));
            $timestamp = $sth->fetchColumn(); 
            if (null !== $timestamp) {
                return $timestamp;
            } else {
                $this->logger->info('No temp lock timestamp found in user storage for a given user (PDO)');
            }
        }
        return false;
    }
    
}
