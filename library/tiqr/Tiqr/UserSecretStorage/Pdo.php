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
 *
 * You can create separate tables for Tiqr_UserSecretStorage_Pdo and Tiqr_UserStorage_Pdo
 * You can also combine the two tables by adding a "secret" column to the user storage table
 * @see Tiqr_UserStorage_Pdo
 *
 * Mysql Create statement usersecret table

CREATE TABLE IF NOT EXISTS usersecret (
    id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
    userid varchar(30) NOT NULL UNIQUE,
    secret varchar(128),
);
 *
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
     * @param PDO $handle
     */
    public function __construct(
        Tiqr_UserSecretStorage_Encryption_Interface $encryption,
        LoggerInterface $logger,
        PDO $handle,
        string $tableName
    ) {
        $this->encryption = $encryption;
        $this->logger = $logger;
        $this->handle = $handle;
        $this->tableName = $tableName;
    }

    /**
     * @see Tiqr_UserSecretStorage_Interface::userExists()
     *
     * Note: duplicate of Tiqr_UserStorage_Pdo::userExists()
     */
    public function userExists(string $userId): bool
    {
        try {
            $sth = $this->handle->prepare('SELECT userid FROM ' . $this->tableName . ' WHERE userid = ?');
            $sth->execute(array($userId));
            return (false !== $sth->fetchColumn());
        }
        catch (Exception $e) {
            $this->logger->error('PDO error checking user exists', array('exception'=>$e, 'userId'=>$userId));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * Get the user's secret
     *
     * @param String $userId
     * @return string
     * @throws Exception
     */
    private function getUserSecret(string $userId): string
    {
        try {
            $sth = $this->handle->prepare('SELECT secret FROM ' . $this->tableName . ' WHERE userid = ?');
            $sth->execute(array($userId));
            $res=$sth->fetchColumn();
            if ($res === false) {
                // No result
                $this->logger->error(sprintf('No result getting secret for user "%s"', $userId));
                throw new RuntimeException('User not found');
            }
        }
        catch (Exception $e) {
            $this->logger->error('PDO error getting user', array('exception' => $e, 'userId' => $userId));
            throw ReadWriteException::fromOriginalException($e);
        }

        if (!is_string($res)) {
            $this->logger->error(sprintf('No secret found for user "%s"', $userId));
            throw new RuntimeException('Secret not found');
        }
        return $res;
    }

    /**
     *
     * @throws Exception
     */
    private function setUserSecret(string $userId, string $secret): void
    {
        // UserSecretStorage can be used in a separate table. In this case the table has its own userid column
        // This means that when a user has been created using in the UserStorage, it does not exists in the
        // UserSecretStorage so userExists will be false and we need to use an INSERT query.

        // It is also possible to use one table for both the UserStorage and the UserSecretStorage, in that case the
        // userid column is shared between the UserStorage and UserSecretStorage and the user must first have been created
        // in the UserStorage because:
        // - UserStorage_Pdo::create() no longer supports overwriting an existing user
        // - The INSERT will fail when displayname has a NOT NULL constraint
        try {
            if ($this->userExists($userId)) {
                $sth = $this->handle->prepare('UPDATE ' . $this->tableName . ' SET secret = ? WHERE userid = ?');
            } else {
                $sth = $this->handle->prepare('INSERT INTO ' . $this->tableName . ' (secret,userid) VALUES (?,?)');
            }
            $sth->execute(array($secret, $userId));
        }
        catch (Exception $e) {
            $this->logger->error(
                sprintf('Unable to persist user secret for user "%s" in user secret storage (PDO)', $userId),
                array('exception'=>$e)
            );
            throw ReadWriteException::fromOriginalException($e);
        }
    }
}
