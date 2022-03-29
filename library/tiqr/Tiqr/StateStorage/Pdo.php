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

    public function __construct(PDO $pdoInstance, string $tablename, int $cleanupProbability)
    {
        if ($cleanupProbability < 1 || $cleanupProbability > 1000) {
            throw new RuntimeException('The probability for removing the expired state should be expressed in a value between 1 and 1000.');
        }
        $this->cleanupProbability = $cleanupProbability;
        $this->tablename = $tablename;
        $this->handle = $pdoInstance;
    }

    private function keyExists($key)
    {
        $sth = $this->handle->prepare("SELECT `key` FROM ".$this->tablename." WHERE `key` = ?");
        $sth->execute(array($key));
        return $sth->fetchColumn();
    }

    private function cleanExpired() {
        $sth = $this->handle->prepare("DELETE FROM ".$this->tablename." WHERE `expire` < ? AND NOT `expire` = 0");
        $sth->execute(array(time()));
    }
    
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::setValue()
     */
    public function setValue($key, $value, $expire=0)
    {
        if (rand(0, 1000) < $this->cleanupProbability) {
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
        $sth->execute(array(serialize($value),$expire,$key));
    }
        
    /**
     * (non-PHPdoc)
     * @see library/tiqr/Tiqr/StateStorage/Tiqr_StateStorage_Abstract::unsetValue()
     */
    public function unsetValue($key)
    {
        $sth = $this->handle->prepare("DELETE FROM ".$this->tablename." WHERE `key` = ?");
        $sth->execute(array($key));
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
                return NULL;
            }
            if (false === $sth->execute(array($key, time())) ) {
                return NULL;
            }
            $result = $sth->fetchColumn();
            return  unserialize($result);
        }
        return NULL;
    }
}
