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
 * CREATE TABLE `tiqrstate` (`key` varchar(255) PRIMARY KEY,`expire` int,`value` text);
 * 
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

    private function keyExists($key)
    {
        $sth = $this->handle->prepare("SELECT `key` FROM ".$this->tablename." WHERE `key` = ?");
        if ($sth->execute(array($key))) {
            return $sth->fetchColumn();
        }
        $this->logger->info('The state storage key could not be found in the database');
    }

    private function cleanExpired() {
        $sth = $this->handle->prepare("DELETE FROM ".$this->tablename." WHERE `expire` < ? AND NOT `expire` = 0");
        $result = $sth->execute(array(time()));
        if (!$result || $sth->rowCount() === 0){
            // No exception is thrown here. The application can continue with expired state for now.
            $this->logger->error('Unable to remove expired keys from the pdo state storage');
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::setValue()
     */
    public function setValue($key, $value, $expire=0)
    {
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
        if (!$sth->execute(array(serialize($value),$expire,$key))) {
            throw new ReadWriteException(sprintf('Unable to store "%s" state to the PDO', $key));
        }
    }
        
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::unsetValue()
     */
    public function unsetValue($key)
    {
        if ($this->keyExists($key)) {
            $sth = $this->handle->prepare("DELETE FROM " . $this->tablename . " WHERE `key` = ?");
            $result = $sth->execute(array($key));
            if (!$result || $sth->rowCount() === 0) {
                throw new ReadWriteException(
                    sprintf(
                        'Unable to unlink the "%s" value from state storage, key not found on pdo',
                        $key
                    )
                );
            }
            return;
        }
        $this->logger->info(sprintf('Unable to unlink the "%s" value from state storage, key does not exist on pdo', $key));
    }
    
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::getValue()
     */
    public function getValue($key)
    {
        if ($this->keyExists($key)) {
            $sth = $this->handle->prepare("SELECT `value` FROM ".$this->tablename." WHERE `key` = ? AND (`expire` >= ? OR `expire` = 0)");
            if (false === $sth) {
                $this->logger->error('Unable to prepare the get key statement');
                return NULL;
            }
            if (false === $sth->execute(array($key, time())) ) {
                $this->logger->error('Unable to get key from the pdo state storage');
                return NULL;
            }
            $result = $sth->fetchColumn();
            return  unserialize($result);
        }
        $this->logger->info('Unable to find key in the pdo state storage');
        return NULL;
    }
}
