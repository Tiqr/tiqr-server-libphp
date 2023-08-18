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
 * @author Peter Verhage <peter@egeniq.com>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2012 SURFnet BV
 */

use Psr\Log\LoggerInterface;

/**
 * Interface for encrypting/decrypting the user secret.
 * 
 * @author peter
 */
interface Tiqr_UserSecretStorage_Encryption_Interface
{
    /**
     * Construct an encryption instance.
     *
     * @param array $config The configuration
     * @throws RuntimeException
     */
    public function __construct(array $config);
    
    /**
     * Encrypts the given data. 
     *
     * @param string $data Data to encrypt
     * @return string encrypted data
     * @throws RuntimeException
     */
    public function encrypt(string $data) : string;
    
    /**
      * Decrypts the given data.
     *
     * @param string $data Data to decrypt
     * @return string decrypted data
     * @throws RuntimeException
     */
    public function decrypt(string $data) : string;
}
