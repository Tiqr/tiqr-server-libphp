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
 * Dummy encryption class, returns the data as is.
 * 
 * @author peter
 */
class Tiqr_UserSecretStorage_Encryption_Dummy implements Tiqr_UserSecretStorage_Encryption_Interface
{
    /**
     * Construct an encryption instance.
     *
     * @param Array $config The configuration that a specific configuration class may use.
     *
     */
    public function __construct(array $config)
    {
    }
    
    /**
     * Encrypts the given data. 
     *
     * @param String $data Data to encrypt.
     *
     * @return String encrypted data
     */
    public function encrypt(string $data) : string
    {
        return $data;
    }
    
    /**
      * Decrypts the given data.
     *
     * @param String $data Data to decrypt.
     *
     * @return String decrypted data
     */
    public function decrypt(string $data) : string
    {
        return $data;
    }
}
