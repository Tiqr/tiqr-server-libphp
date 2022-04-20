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

require_once 'Tiqr/UserStorage/FileTrait.php';
require_once 'Tiqr/UserSecretStorage/UserSecretStorageTrait.php';

/**
 * This user storage implementation implements a simple user's secret storage using json files.
 * This is mostly for demonstration and development purposes. In a production environment
 * please supply your own implementation that hosts the data in your user database OR
 * in a secure (e.g. hardware encrypted) storage.
 * @author ivo
 */
class Tiqr_UserSecretStorage_File implements Tiqr_UserSecretStorage_Interface
{
    use UserSecretStorageTrait;
    use FileTrait;

    private $userSecretStorage;

    private $logger;

    private $path;

    public function __construct(
        Tiqr_UserSecretStorage_Encryption_Interface $encryption,
        string $path,
        LoggerInterface $logger
    ) {
        // See UserSecretStorageTrait
        $this->encryption = $encryption;
        $this->logger = $logger;
        $this->path = $path;
    }

    /**
     * Get the user's secret
     *
     * @param String $userId
     *
     * @return String The user's secret
     */
    private function getUserSecret($userId)
    {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["secret"])) {
                return $data["secret"];
            }
        }
        $this->logger->notice('Unable to retrieve the secret (user not found). In user secret storage (file)');
        return NULL;
    }

    /**
     * Store a secret for a user
     *
     * @param String $userId
     * @param String $secret
     */
    private function setUserSecret($userId, $secret)
    {
        $data = $this->_loadUser($userId, false);
        $data["secret"] = $secret;
        $this->_saveUser($userId, $data);
    }
}
