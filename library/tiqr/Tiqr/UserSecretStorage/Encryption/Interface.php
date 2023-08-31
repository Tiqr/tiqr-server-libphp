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
     * Encrypts the given data. Throws an exception if the data cannot be encrypted.
     *
     * @param string $data Data to encrypt
     * @return string encrypted data
     * @throws RuntimeException
     */
    public function encrypt(string $data) : string;
    
    /**
     * Decrypts the given data. May throw an exception if the data cannot be decrypted
     *
     * @param string $data Data to decrypt
     * @return string decrypted data
     * @throws RuntimeException
     */
    public function decrypt(string $data) : string;

    /**
     * Get a string that identifies the encryption implementation
     * It is recommended to use a short lowercase string that identifies the encryption implementation that is used.
     * The type is stored as part of the encrypted secret to allow the correct encryption implementation
     * to be selected to decrypt a secret at runtime.
     *
     * It must not contain any spaces or special characters.
     *
     * @return string The type of encryption
     * @throws RuntimeException
     */
    public function get_type() : string;
}
