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

require_once 'Tiqr/UserSecretStorage/UserSecretStorageTrait.php';

use Psr\Log\LoggerInterface;

/**
 * This user storage implementation implements a user secret storage using PDO.
 * It is usable for any database with a PDO driver
 * 
 * @author Patrick Honing <Patrick.Honing@han.nl>
 */
class Tiqr_UserSecretStorage_Pdo implements Tiqr_UserSecretStorage_Interface
{
    use UserSecretStorageTrait;

    private $tableName;

    private $handle;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Tiqr_UserSecretStorage_Encryption_Interface $encryption
     * @param LoggerInterface $logger
     * @param string $dsn
     * @param string $userName
     * @param string $password
     * @param string $tableName
     */
    public function __construct(
        Tiqr_UserSecretStorage_Encryption_Interface $encryption,
        LoggerInterface $logger,
        string $dsn,
        string $userName,
        string $password,
        string $tableName = 'tiqrusersecret'
    ) {
        $this->encryption = $encryption;
        $this->logger = $logger;
        $this->tableName = $tableName;
        try {
            $this->handle = new PDO($dsn, $userName, $password);
        } catch (PDOException $e) {
            $this->logger->error(
                sprintf('Unable to establish a PDO connection. Error message from PDO: %s', $e->getMessage())
            );
        }
    }
    private function userExists($userId)
    {
        $sth = $this->handle->prepare("SELECT userid FROM ".$this->tableName." WHERE userid = ?");
        $sth->execute(array($userId));
        $result = $sth->fetchColumn();
        if ($result !== false) {
            return true;
        }
        $this->logger->debug('Unable fot find user in user secret storage (PDO)');
        return false;
    }

    /**
     * Get the user's secret
     *
     * @param String $userId
     *
     * @return mixed: null|string
     */
    private function getUserSecret($userId)
    {
        $sth = $this->handle->prepare("SELECT secret FROM ".$this->tableName." WHERE userid = ?");
        if($sth->execute(array($userId))) {
            $secret = $sth->fetchColumn();
            if ($secret !== false) {
                return $secret;
            }
        }
        $this->logger->notice('Unable to retrieve user secret from user secret storage (PDO)');
    }

    /**
     * Store a secret for a user.
     *
     * @param String $userId
     * @param String $secret
     */
    private function setUserSecret($userId, $secret)
    {
        if ($this->userExists($userId)) {
            $sth = $this->handle->prepare("UPDATE ".$this->tableName." SET secret = ? WHERE userid = ?");
        } else {
            $sth = $this->handle->prepare("INSERT INTO ".$this->tableName." (secret,userid) VALUES (?,?)");
        }
        $result = $sth->execute(array($secret,$userId));
        if (!$result) {
            $this->logger->error('Unable to persist user secret in user secret storage (PDO)');
        }
    }
}
