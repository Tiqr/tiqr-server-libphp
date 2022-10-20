<?php

use Psr\Log\LoggerInterface;

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
 * 
 * Create SQL table (MySQL):

 CREATE TABLE IF NOT EXISTS tiqrstate (
    `key` varchar(255) PRIMARY KEY,
    expire BIGINT,
    `value` text
);

CREATE INDEX IF NOT EXISTS index_tiqrstate_expire ON tiqrstate (expire);
 */


class Tiqr_StateStorage_Pdo extends Tiqr_StateStorage_Abstract
{
    /**
     * @var PDO
     */
    protected $handle;

    /**
     * @var string
     */
    private $tablename;

    /**
     * @var int
     */
    private $cleanupProbability;

    /**
     * @param PDO $pdoInstance The PDO instance where all state storage operations are performed on
     * @param LoggerInterface
     * @param string $tablename The tablename that is used for storing and retrieving the state storage
     * @param float $cleanupProbability The probability the expired state storage items are removed on a 'setValue' call. Example usage: 0 = never, 0.5 = 50% chance, 1 = always
     *
     * @throws RuntimeException when an invalid cleanupProbability is configured
     */
    public function __construct(PDO $pdoInstance, LoggerInterface $logger, string $tablename, float $cleanupProbability)
    {
        if ($cleanupProbability < 0 || $cleanupProbability > 1) {
            throw new RuntimeException('The probability for removing the expired state should be expressed in a floating point value between 0 and 1.');
        }
        $this->cleanupProbability = $cleanupProbability;
        $this->tablename = $tablename;
        $this->handle = $pdoInstance;
        $this->logger = $logger;
    }

    /**
     * @param string $key to lookup
     * @return bool true when $key is found, false when the key does not exist
     * @throws ReadWriteException
     */
    private function keyExists(string $key): bool
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }
        try {
            $sth = $this->handle->prepare('SELECT `key` FROM ' . $this->tablename . ' WHERE `key` = ?');
            $sth->execute(array($key));
            return $sth->fetchColumn() !== false;
        }
        catch (Exception $e) {
            $this->logger->error(
                sprintf('Error checking for key "%s" in PDO StateStorage', $key),
                array('exception' => $e)
            );
            throw ReadWriteException::fromOriginalException($e);
        }
    }

    /**
     * Remove expired keys
     * This is a maintenance task that should be periodically run
     * Does not throw
     */
    private function cleanExpired(): void {
        try {
            $sth = $this->handle->prepare("DELETE FROM " . $this->tablename . " WHERE `expire` < ? AND NOT `expire` = 0");
            $sth->execute(array(time()));
            $deletedRows=$sth->rowCount();
            $this->logger->notice(
                sprintf("Deleted %i expired keys", $deletedRows)
            );
        }
        catch (Exception $e) {
            $this->logger->error(
                sprintf("Deleting expired keys failed: %s", $e->getMessage()),
                array('exception', $e)
            );
        }
    }
    
    /**
     * @see Tiqr_StateStorage_StateStorageInterface::setValue()
     */
    public function setValue(string $key, $value, int $expire=0): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }
        if (((float) rand() /(float) getrandmax()) < $this->cleanupProbability) {
            $this->cleanExpired();
        }
        if ($this->keyExists($key)) {
            $sth = $this->handle->prepare("UPDATE ".$this->tablename." SET `value` = ?, `expire` = ? WHERE `key` = ?");
        } else {
            $sth = $this->handle->prepare("INSERT INTO ".$this->tablename." (`value`,`expire`,`key`) VALUES (?,?,?)");
        }
        // $expire == 0 means never expire
        if ($expire != 0) {
            $expire+=time();    // Store unix timestamp after which the expires
        }
        try {
            $sth->execute(array(serialize($value), $expire, $key));
        }
        catch (Exception $e) {
            $this->logger->error(
                sprintf('Unable to store key "%s" in PDO StateStorage', $key),
                array('exception' => $e)
            );
            throw ReadWriteException::fromOriginalException($e);
        }
    }
        
    /**
     * @see Tiqr_StateStorage_StateStorageInterface::unsetValue()
     */
    public function unsetValue($key): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }
        try {
            $sth = $this->handle->prepare("DELETE FROM " . $this->tablename . " WHERE `key` = ?");
            $sth->execute(array($key));
        }
        catch (Exception $e) {
            $this->logger->error(
                sprintf('Error deleting key "%s" from PDO StateStorage', $key),
                array('exception' => $e)
            );
            throw ReadWriteException::fromOriginalException($e);
        }

        if ($sth->rowCount() === 0) {
            // Key did not exist, this is not an error
            $this->logger->info(
                sprintf('unsetValue: key "%s" not found in PDO StateStorage', $key
                )
            );
        }
    }
    
    /**
     * @see Tiqr_StateStorage_StateStorageInterface::getValue()
     */
    public function getValue(string $key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Empty key not allowed');
        }

        try {
            $sth = $this->handle->prepare('SELECT `value` FROM ' . $this->tablename . ' WHERE `key` = ? AND (`expire` >= ? OR `expire` = 0)');
            $sth->execute(array($key, time()));
        }
        catch (Exception $e) {
            $this->logger->error(
                sprintf('Error getting value for key "%s" from PDO StateStorage', $key),
                array('exception' => $e)
            );
            throw ReadWriteException::fromOriginalException($e);
        }
        $result = $sth->fetchColumn();
        if (false === $result) {
            // Occurs normally
            $this->logger->info(sprintf('getValue: Key "%s" not found in PDO StateStorage', $key));
            return NULL;    // Key not found
        }
        $result=unserialize($result, array('allowed_classes' => false));
        if (false === $result) {
            throw new RuntimeException(sprintf('getValue: unserialize error for key "%s" in PDO StateStorage', $key));
        }

        return $result;
    }

}
