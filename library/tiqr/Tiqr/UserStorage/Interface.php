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

use Psr\Log\LoggerInterface;

/**
 * The interface that defines what a user class should implement. 
 * This interface can be used to adapt the module to a custom user backend. 
 * 
 * The interface defines the type of data that a user storage should support
 * to be able to house the data required for the tiqr authentication.
 * 
 * The implementing classes are only required to implement the necessary
 * getters and setters and should not worry about the actual meaning of this 
 * data. Tiqr supplies the data for storage in your backend and retrieves
 * it when necessary.
 * 
 * @author ivo
 *
 */
interface Tiqr_UserStorage_Interface
{
    /**
     * Construct a user class
     * @param array $config The configuration that a specific user class may use.
     * @param LoggerInterface $logger
     */
    public function __construct(array $config, LoggerInterface $logger);
    
    /**
     * Store a new user with a certain displayName.
     * @param String $userId A unique ID of the new user, throws exception when a user with this ID already exists
     * @param String $displayName
     * @throws Exception
     */
    public function createUser(string $userId, string $displayName): void;
    
    /**
     * Check if a user exists
     * @param String $userId
     * @return boolean true if the user exists, false otherwise
     * @throws Exception
     *
     */
    public function userExists(string $userId): bool;
    
    /**
     * Get the display name of a user.
     * @param String $userId
     * @return String the display name of this user, empty string when no displayname is set
     * @throws Exception
     */
    public function getDisplayName(string $userId): string;

    /**
     * Get the type of device notifications a user supports 
     * @param String $userId
     * @return String The notification type, empty string when no notification type is set
     * @throws Exception
     */
    public function getNotificationType(string $userId): string;
    
    /**
     * Set the notification type of a user.
     * @param String $userId
     * @param String $type
     * @throws Exception
     */
    public function setNotificationType(string $userId, string $type): void;
    
    /**
     * get the notification address of a user's device. 
     * @param String $userId
     * @return String The notification address, empty string when no notification address is set
     * @throws Exception
     */
    public function getNotificationAddress(string $userId): string;
    
    /**
     * Set the notification address of a user's device
     * @param String $userId
     * @param String $address
     * @throws Exception
     */
    public function setNotificationAddress(string $userId, string $address): void;
    
    /**
     * Get the amount of unsuccessful login attempts previously set using getLoginAttempts
     * @param string $userId
     * @return int the number of unsuccessful login attempts
     * @throws Exception
     */
    public function getLoginAttempts(string $userId): int;
    
    /**
     * Set the amount of unsuccessful login attempts.
     * @param String $userId
     * @param int $amount
     * @throws Exception
     */
    public function setLoginAttempts(string $userId, int $amount): void;
    
    /**
     * Check if the user's account is blocked
     * A block can be permanent or temporary
     * @param string $userId
     * @param int $duration Duration of a temporary block in minutes, when set to 0 temporary blocks are not taken into
     *            account
     * @return bool true if the user's account is permanently blocked or if the user's account was temporary blocked in
     *              the past $tempBlockDuration minutes
     * @throws Exception
     *
     * Note that the $tempBlockDuration is specified in MINUTES
     */
    public function isBlocked(string $userId, int $tempBlockDuration=0): bool;
    
    /**
     * Block or unblock the user account.
     * @param string $userId
     * @param bool $blocked true to block, false to unblock
     * @throws Exception
     */
    public function setBlocked(string $userId, bool $blocked): void;
    
    /**
     * Set the number of temporary block attempts
     * @param string $userId
     * @param int $amount
     * @throws Exception
     */
    public function setTemporaryBlockAttempts(string $userId, int $amount): void;
    
    /**
     * Get the number of temporary block attempts previously set with setTemporaryBlockAttempts()
     * @param string $userId
     * @return The number of temporary block attempts
     * @throws Exception
     */
    public function getTemporaryBlockAttempts(string $userId): int;
    
    /**
     * Set the timestamp for the temporary block
     * @param string $userId
     * @param string $timestamp unix timestamp of the block (time_t)
     *               0 means no temporary block timestamp is set
     * @throws Exception
     */
    public function setTemporaryBlockTimestamp(string $userId, int $timestamp): void;
    
    /**
     * Get the temporary block timestamp
     * @param string $userId
     * @return int unix timestamp of the block previously set with setTemporaryBlockTimestamp()
     *             0 means no temporary block timestamp is set
     * @throws Exception
     */
    public function getTemporaryBlockTimestamp(string $userId): int;

    /**
     * Returns additional attributes for the given user.
     *
     * @param string $userId User identifier.
     * @return array additional user attributes
     * @throws Exception
     */
    public function getAdditionalAttributes(string $userId): array;
}
