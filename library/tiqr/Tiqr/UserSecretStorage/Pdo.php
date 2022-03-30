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
 * CREATE TABLE `tiqrusersecret` (`userid` varchar(10) PRIMARY KEY, `secret` varchar(100))
 * 
 */

use Psr\Log\LoggerInterface;

require_once 'Tiqr/UserStorage/Pdo.php';

/**
 * This user storage implementation implements a user secret storage using PDO.
 * It is usable for any database with a PDO driver
 * 
 * @author Patrick Honing <Patrick.Honing@han.nl>
 */
class Tiqr_UserSecretStorage_Pdo extends Tiqr_UserStorage_Pdo implements Tiqr_UserSecretStorage_Interface
{
    /**
     * Construct a user class
     *
     * @param array $config The configuration that a specific user class may use.
     */
    public function __construct($config, LoggerInterface $logger, $secretconfig = array())
    {
        $this->logger = $logger;
        $this->tablename = isset($config['table']) ? $config['table'] : 'tiqrusersecret';
        try {
            $this->handle = new PDO($config['dsn'],$config['username'],$config['password']);
        } catch (PDOException $e) {
            $this->logger->error(
                sprintf('Unable to establish a PDO connection. Error message from PDO: %s', $e->getMessage())
            );
        }
    }

    /**
     * Get the user's secret
     *
     * @param String $userId
     *
     * @return String The user's secret
     */
    public function getUserSecret($userId)
    {
        $sth = $this->handle->prepare("SELECT secret FROM ".$this->tablename." WHERE userid = ?");
        if($sth->execute(array($userId))) {
            return $sth->fetchColumn();
        }
        $this->logger->error('Unable to retrieve user secret from user secret storage (PDO)');
    }

    /**
     * Store a secret for a user.
     *
     * @param String $userId
     * @param String $secret
     */
    public function setUserSecret($userId, $secret)
    {
        if ($this->userExists($userId)) {
            $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET secret = ? WHERE userid = ?");
        } else {
            $sth = $this->handle->prepare("INSERT INTO ".$this->tablename." (secret,userid) VALUES (?,?)");
        }
        if (!$sth->execute(array($secret,$userId))) {
            $this->logger->error('Unable to persist user secret in user secret storage (PDO)');
        }
    }
}
