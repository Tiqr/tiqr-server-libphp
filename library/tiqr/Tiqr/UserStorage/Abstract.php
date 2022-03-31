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

require_once 'Tiqr/UserStorage/Interface.php';
require_once 'Tiqr/UserStorage/Encryption.php';
require_once 'Tiqr/UserSecretStorage.php';

/**
 * Abstract base implementation for user storage. 
 *
 * Adds built-in support for encryption of the user secret.
 * 
 * @author peter
 */
abstract class Tiqr_UserStorage_Abstract implements Tiqr_UserStorage_Interface
{
    protected $_encryption;

    protected $_userSecretStorage;

    protected $logger;

    public function __construct($config, LoggerInterface $logger, $secretconfig = array())
    {
        $this->logger = $logger;
        $type = isset($config['encryption']['type']) ? $config['encryption']['type'] : 'dummy';
        $options = isset($config['encryption']) ? $config['encryption'] : array();
        $this->_encryption = Tiqr_UserStorage_Encryption::getEncryption($logger, $type, $options);

        if (count($secretconfig)) {
            $this->_userSecretStorage = Tiqr_UserSecretStorage::getSecretStorage($secretconfig['type'], $logger, $secretconfig);
        } else {
            $this->_userSecretStorage = Tiqr_UserSecretStorage::getSecretStorage($config['type'], $logger, $config);
        }
    }

    /**
     * Returns the encryption instance.
     */
    protected function _getEncryption()
    {
        return $this->_encryption;
    }

    /**
     * Get the user's secret
     * @param String $userId
     * @return String The user's secret
     */
    protected function _getEncryptedSecret($userId)
    {
        return $this->_userSecretStorage->getUserSecret($userId);
    }

    /**
     * Store a secret for a user.
     * @param String $userId
     * @param String $secret
     */
    protected function _setEncryptedSecret($userId, $secret)
    {
        $this->_userSecretStorage->setUserSecret($userId, $secret);
    }

    /**
     * Get the user's secret
     * @param String $userId
     * @return String The user's secret
     */
    public final function getSecret($userId)
    {
        $encryptedSecret = $this->_getEncryptedSecret($userId);
        return $this->_getEncryption()->decrypt($encryptedSecret);
    }

    /**
     * Store a secret for a user.
     * @param String $userId
     * @param String $secret
     */
    public final function setSecret($userId, $secret)
    {
        $encryptedSecret = $this->_getEncryption()->encrypt($secret);
        $this->_setEncryptedSecret($userId, $encryptedSecret);
    }

    /**
     * Returns additional attributes for the user.
     *
     * @param string $userId User identifier.
     *
     * @return array additional user attributes
     */
    public function getAdditionalAttributes($userId) 
    {
        return array();
    }
}
