<?php

use Psr\Log\LoggerInterface;

/**
 * Copyright 2022 SURF B.V.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

trait UserSecretStorageTrait
{
    /**
     * @var Tiqr_UserSecretStorage_Encryption_Interface
     */
    private $encryption;

    /**
     * @var array() of type_id (prefix) => Tiqr_UserSecretStorage_Encryption_Interface
     */

    private $decryption;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Get the user's secret
     * @param String $userId
     * @return String The user's secret
     * @throws Exception
     */
    public function getSecret(string $userId): string
    {
        $encryptedSecret = $this->getUserSecret($userId);
        $pos = strpos($encryptedSecret, ':');
        if ($pos === false) {
            // If the secret is not prefixed with the encryption type_id, it is assumed to be unencrypted.
            $this->logger->info("Secret for user '$userId' is not prefixed with the encryption type, assuming that it is not unencrypted");
            return $encryptedSecret;
        }

        $prefix = substr($encryptedSecret, 0, $pos);
        if ($prefix === $this->encryption->get_type()) {
            // Decrypt the secret if it is prefixed with the current encryption type
            // Remove the encryption type prefix before decrypting
           return $this->encryption->decrypt( substr($encryptedSecret, $pos+1) );
        }

        // Check the decryption array for the encryption type to see if there is an encryption
        // instance defined for it. If so, use that to decrypt the secret.
        if (isset($this->decryption[$prefix])) {
            return $this->decryption[$prefix]->decrypt( substr($encryptedSecret, $pos+1) );
        }

        $this->logger->error("Secret for user '$userId' is encrypted with unsupported encryption type '$prefix'");
        throw new RuntimeException("Secret for user '$userId' is encrypted with an unsupported encryption type");
    }

    /**
     * Store a secret for a user.
     * @param String $userId
     * @param String $secret
     * @throws Exception
     */
    public function setSecret(string $userId, string $secret): void
    {
        $encryptedSecret = $this->encryption->encrypt($secret);
        // Prefix the user secret with the encryption type
        $this->setUserSecret($userId, $this->encryption->get_type() . ':' . $encryptedSecret);
    }
}
