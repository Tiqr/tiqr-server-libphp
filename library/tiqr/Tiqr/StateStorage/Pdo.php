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
 *
 *
 * Use a database table for storing the tiqr state (session) information
 * There is a cleanup query that runs with a default probability of 10% each time
 * Tiqr_StateStorage_StateStorageInterface::setValue() is used to cleanup expired keys and prevent the table
 * from growing indefinitely.
 * The default 10% probability is good for test setups, but is unnecessary high for production use. For production use,
 * with high number of authentications and registrations, a much lower value should be used to prevent unnecessary
 * loading the database with DELETE queries. Note that the cleanup is only there to prevent indefinite growth of the
 * table, expired keys are never returned, whether they exist in the database or not.
 *
 * The goal is to prevent on the one hand running the cleanup to often with the chance of running multiple queries at
 * the same time, while on the other hand hardly ever running the cleanup leading to an unnecessary large table and query
 * execution time when it does run.
 *
 * The following information can be used for choosing a suitable cleanup_probability:
 * - A successful authentication creates two keys, i.e. two calls to setValue()
 * - A successful enrollment creates three keys, i.e. three calls to setValue()
 * Add to that the keys created by the application that uses the library (e.g. Stepup-Tiqr creates one key).
 *
 * A good starting point is using the typical peak number of authentications per hour and setting the cleanup_probability
 * so that it would on average run once during such an hour. Since the number of authentications >> number of enrollments
 * A good starting value for cleanup_probability would be:
 *
 *  1 / ( [number of authentications per hour] * ( 2 + [ keys used by application] ) )
 *
 * E.g. for 10000 authentications per hour, with one key created by the application:
 *      cleanup_probability = 1 / (10000 (2 + 1)) = 0.00003
 *
 *
 * Create SQL table (MySQL):

 CREATE TABLE IF NOT EXISTS tiqrstate (
    `key` varchar(255) PRIMARY KEY,
    expire BIGINT,
    `value` text
);

CREATE INDEX IF NOT EXISTS index_tiqrstate_expire ON tiqrstate (expire);

 * @see Tiqr_StateStorage::getStorage()
 * @see Tiqr_StateStorage_StateStorageInterface
 *
 * Supported options:
 * table               : The name of the table in the database
 * dsn                 : The dsn, see the PDO interface documentation
 * username            : The database username
 * password            : The database password
 * cleanup_probability : The probability that the cleanup of expired keys is executed. Optional, defaults to 0.1
 *                       Specify the desired probability as a float. E.g. 0.01 = 1%; 0.001 = 0.1%
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
                sprintf("Deleted %d expired keys", $deletedRows)
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
        // REPLACE INTO is mysql dialect. Supported by sqlite as well.
        // This does:
        // INSERT INTO tablename (`value`,`expire`,`key`) VALUES (?,?,?)
        //   ON CONFLICT UPDATE tablename SET `value`=?, `expire`=? WHERE `key`=?
        // in pgsql "ON CONFLICT" is "ON DUPLICATE KEY"

        $sth = $this->handle->prepare("REPLACE INTO ".$this->tablename." (`value`,`expire`,`key`) VALUES (?,?,?)");

        // $expire == 0 means never expire
        if ($expire != 0) {
            $expire+=time();    // Store unix timestamp after which the key expires
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

    /**
     * @see Tiqr_HealthCheck_Interface::healthCheck()
     */
    public function healthCheck(string &$statusMessage = ''): bool
    {
        try {
            // Retrieve a random row from the table, this checks that the table exists and is readable
            $sth = $this->handle->prepare('SELECT `value`, `key`, `expire` FROM ' . $this->tablename . ' LIMIT 1');
            $sth->execute();
        }
        catch (Exception $e) {
            $statusMessage = sprintf('Error performing health check on PDO StateStorage: %s', $e->getMessage());
            return false;
        }
        return true;
    }
}
