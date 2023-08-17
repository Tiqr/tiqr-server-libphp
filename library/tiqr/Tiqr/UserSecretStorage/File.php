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
 * This user storage implementation implements a simple user's secret storage using json files.
 * This is mostly for demonstration and development purposes. In a production environment
 * please supply your own implementation that hosts the data in your user database OR
 * in a secure (e.g. hardware encrypted) storage.
 * @author ivo
 *
 * @see Tiqr_UserSecretStorage::getSecretStorage()
 * @see Tiqr_UserSecretStorage_Interface
 *
 * Supported options:
 * path : Path to the directory where the user data is stored
 *

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
     * @throws Exception
     */
    private function getUserSecret(string $userId): string
    {
        if ($data = $this->_loadUser($userId)) {
            if (isset($data["secret"])) {
                return $data["secret"];
            }
        }
        $this->logger->error(sprintf('User or user secret not found in secret storage (file) for user "%s"', $userId));
        throw new RuntimeException('User or user secret not found in secret storage (File)');
    }

    /**
     * Store a secret for a user
     *
     * @param String $userId
     * @param String $secret
     * @throws Exception
     */
    private function setUserSecret(string $userId, string $secret): void
    {
        $data=array();
        if ($this->_userExists($userId)) {
            $data = $this->_loadUser($userId);
        }
        $data["secret"] = $secret;
        $this->_saveUser($userId, $data);
    }
}
