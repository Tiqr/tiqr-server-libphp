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
For MySQL:

CREATE TABLE IF NOT EXISTS user (
    id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
    userid varchar(30) NOT NULL UNIQUE,
    displayname varchar(30) NOT NULL,
    secret varchar(128),        // Optional column, see Tiqr_UserSecretStorage_Pdo
    loginattempts integer,      // number of failed login attempts counting towards a permanent block
    tmpblocktimestamp BIGINT,   // 8-byte integer, holds unix timestamp of temporary block. 0=not temporary block
    tmpblockattempts integer,   // Number of failed login attempts counting towards a temporary block
    blocked tinyint(1),         // used as boolean: 0=not blocked. 1=blocked
    notificationtype varchar(10),
    notificationaddress varchar(64)
);

 *
 * In version 3.0 the format of the tmpblocktimestamp was changed from a datetime format to an integer.
 * Because it holds a unix timestamp a 64-bit (8-byte) integer. To upgrade the user table to the new format use:

ALTER TABLE user MODIFY tmpblocktimestamp BIGINT;

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

    private $_allowedStringColumns = ['displayname', 'notificationtype', 'notificationaddress'];
    private $_allowedIntColumns = ['loginattempts', 'tmpblockattempts', 'blocked', 'tmpblocktimestamp'];
    
    /**
     * Create an instance
     * @param array $config
     * @param array $secretconfig
     * @throws Exception
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);
        $this->tablename = $config['table'] ?? 'tiqruser';
        try {
            $this->handle = new PDO(
                $config['dsn'],
                $config['username'],
                $config['password'],
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch (PDOException $e) {
            $this->logger->error('Unable to establish a PDO connection.', array('exception'=>$e));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * @param string $columnName to query
     * @param string $userId os an existing user, throws when the user does not exist
     * @return string The string value of the column, returns string('') when the column is NULL
     * @throws RuntimeException | InvalidArgumentException
     * @throws ReadWriteException when there was a problem communicating with the backed
     */
    private function _getStringValue(string $columnName, string $userId): string
    {
        if ( !in_array($columnName, $this->_allowedStringColumns) ) {
            throw new InvalidArgumentException('Unsupported column name');
        }

        try {
            $sth = $this->handle->prepare('SELECT ' . $columnName . ' FROM ' . $this->tablename . ' WHERE userid = ?');
            $sth->execute(array($userId));
            $res=$sth->fetchColumn();
            if ($res === false) {
                // No result
                $this->logger->error(sprintf('No result getting "%s" for user "%s"', $columnName, $userId));
                throw new RuntimeException('User not found');
            }
            if ($res === NULL) {
                return '';  // Value unset
            }
            if (!is_string($res)) {
                $this->logger->error(sprintf('Expected string type while getting "%s" for user "%s"', $columnName, $userId));
                throw new RuntimeException('Unexpected return type');
            }
            return $res;
        }
        catch (Exception $e) {
            $this->logger->error('PDO error getting user', array('exception' => $e, 'userId' => $userId, 'columnName'=>$columnName));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * @param string $columnName to query
     * @param string $userId of an existing user, throws when the user does not exist
     * @return int The int value of the column, returns int(0) when the column is NULL
     * @throws RuntimeException | InvalidArgumentException
     * @throws ReadWriteException when there was a problem communicating with the backend
     *
     */
    private function _getIntValue(string $columnName, string $userId): int
    {
        if ( !in_array($columnName, $this->_allowedIntColumns) ) {
            throw new InvalidArgumentException('Unsupported column name');
        }

        try {
            $sth = $this->handle->prepare('SELECT ' . $columnName . ' FROM ' . $this->tablename . ' WHERE userid = ?');
            $sth->execute(array($userId));
            $res=$sth->fetchColumn();
            if ($res === false) {
                // No result
                $this->logger->error(sprintf('No result getting "%s" for user "%s"', $columnName, $userId));
                throw new RuntimeException('User not found');
            }
            if ($res === NULL) {
                return 0;  // Value unset
            }
            // Return type for integers depends on the PDO driver, can be string
            if (!is_numeric($res)) {
                $this->logger->error(sprintf('Expected int type while getting "%s" for user "%s"', $columnName, $userId));
                throw new RuntimeException('Unexpected return type');
            }
            return (int)$res;
        }
        catch (Exception $e) {
            $this->logger->error('PDO error getting user', array('exception' => $e, 'userId' => $userId, 'columnName'=>$columnName));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * @param string $columnName name of the column to set
     * @param string $userId of an existing user, throws when the user does not exist
     * @param string $value The value to set in $columnName
     * @throws RuntimeException | InvalidArgumentException
     * @throws ReadWriteException when there was a problem communicating with the backend
     */
    private function _setStringValue(string $columnName, string $userId, string $value): void
    {
        if ( !in_array($columnName, $this->_allowedStringColumns) ) {
            throw new InvalidArgumentException('Unsupported column name');
        }
        try {
            $sth = $this->handle->prepare('UPDATE ' . $this->tablename . ' SET ' . $columnName . ' = ? WHERE userid = ?');
            $sth->execute(array($value, $userId));
            if ($sth->rowCount() == 0) {
                // Required for mysql which only returns the number of rows that were actually updated
                if (!$this->userExists($userId)) {
                    throw new RuntimeException('User not found');
                }
            }
        }
        catch (Exception $e) {
            $this->logger->error('PDO error updating user', array('exception' => $e, 'userId' => $userId, 'columnName'=>$columnName));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * @param string $columnName name of the column to set
     * @param string $userId of an existing user, throws when the user does not exist
     * @param int $value The value to set in $columnName
     * @throws RuntimeException | InvalidArgumentException
     * @throws ReadWriteException when there was a problem communicating with the backend
     */
    private function _setIntValue(string $columnName, string $userId, int $value): void
    {
        if ( !in_array($columnName, $this->_allowedIntColumns) ) {
            throw new InvalidArgumentException('Unsupported column name');
        }
        try {
            $sth = $this->handle->prepare('UPDATE ' . $this->tablename . ' SET ' . $columnName . ' = ? WHERE userid = ?');
            $sth->execute(array($value, $userId));
            if ($sth->rowCount() == 0) {
                // Required for mysql which only returns the number of rows that were actually updated
                if (!$this->userExists($userId)) {
                    throw new RuntimeException('User not found');
                }
            }
        }
        catch (Exception $e) {
            $this->logger->error('PDO error updating user', array('exception' => $e, 'userId' => $userId, 'columnName'=>$columnName));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * @see Tiqr_UserStorage_Interface::createUser()
     */
    public function createUser(string $userId, string $displayName): void
    {
        if ($this->userExists($userId)) {
            throw new RuntimeException(sprintf('User "%s" already exists', $userId));
        }
        try {
            $sth = $this->handle->prepare("INSERT INTO ".$this->tablename." (displayname,userid) VALUES (?,?)");
            $sth->execute(array($displayName, $userId));
        }
        catch (Exception $e) {
            $this->logger->error(sprintf('Error creating user "%s"', $userId), array('exception'=>$e));
            throw new ReadWriteException('The user could not be saved in the user storage (PDO)');
        }
    }

    /**
     * @see Tiqr_UserStorage_Interface::userExists()
     */
    public function userExists(string $userId): bool
    {
        try {
            $sth = $this->handle->prepare("SELECT userid FROM ".$this->tablename." WHERE userid = ?");
            $sth->execute(array($userId));
            return (false !== $sth->fetchColumn());
        }
        catch (Exception $e) {
            $this->logger->error('PDO error checking user exists', array('exception'=>$e, 'userId'=>$userId));
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * @see Tiqr_UserStorage_Interface::getDisplayName()
     */
    public function getDisplayName(string $userId): string
    {
        return $this->_getStringValue('displayname', $userId);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getNotificationType()
     */
    public function getNotificationType(string $userId): string
    {
        return $this->_getStringValue('notificationtype', $userId);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getNotificationType()
     */
    public function setNotificationType(string $userId, string $type): void
    {
        $this->_setStringValue('notificationtype', $userId, $type);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getNotificationAddress()
     */
    public function getNotificationAddress(string $userId): string
    {
        return $this->_getStringValue('notificationaddress', $userId);
    }

    /**
     * @see Tiqr_UserStorage_Interface::setNotificationAddress()
     */
    public function setNotificationAddress(string $userId, string $address): void
    {
        $this->_setStringValue('notificationaddress', $userId, $address);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getLoginAttempts()
     */
    public function getLoginAttempts(string $userId): int
    {
        return $this->_getIntValue('loginattempts', $userId);
    }

    /**
     * @see Tiqr_UserStorage_Interface::setLoginAttempts()
     */
    public function setLoginAttempts(string $userId, int $amount): void
    {
        $this->_setIntValue('loginattempts', $userId, $amount);
    }

    /**
     * @see Tiqr_UserStorage_Interface::isBlocked()
     */
    public function isBlocked(string $userId, int $tempBlockDuration = 0): bool
    {
        // Check for blocked
        if ($this->_getIntValue('blocked', $userId) != 0) {
            return true;   // Blocked
        }

        if (0 == $tempBlockDuration) {
            return false;   // No check for temporary block
        }

        // Check for temporary block
        $timestamp = $this->getTemporaryBlockTimestamp($userId);
        // if no temporary block timestamp is set or if the temporary block is expired, return false
        if ( 0 == $timestamp || ($timestamp + $tempBlockDuration * 60) < time()) {
            return false;
        }
        return true;
    }

    /**
     * @see Tiqr_UserStorage_Interface::setBlocked()
     */
    public function setBlocked(string $userId, bool $blocked): void
    {
        $this->_setIntValue('blocked', $userId, ($blocked) ? 1 : 0);
    }

    /**
     * @see Tiqr_UserStorage_Interface::setTemporaryBlockAttempts()
     */
    public function setTemporaryBlockAttempts(string $userId, int $amount): void
    {
        $this->_setIntValue('tmpblockattempts', $userId, $amount);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getTemporaryBlockAttempts()
     */
    public function getTemporaryBlockAttempts(string $userId): int {
        return $this->_getIntValue('tmpblockattempts', $userId);
    }

    /**
     * @see Tiqr_UserStorage_Interface::setTemporaryBlockTimestamp()
     */
    public function setTemporaryBlockTimestamp(string $userId, int $timestamp): void
    {
        $this->_setIntValue('tmpblocktimestamp', $userId, $timestamp);
    }

    /**
     * @see Tiqr_UserStorage_Interface::getTemporaryBlockTimestamp()
     */
    public function getTemporaryBlockTimestamp(string $userId): int
    {
        return $this->_getIntValue('tmpblocktimestamp', $userId);
    }
}
