<?php

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
    private $encryption;

    /**
     * Get the user's secret
     * @param String $userId
     * @return String The user's secret
     */
    public function getSecret(string $userId): string
    {
        $encryptedSecret = $this->getUserSecret($userId);
        return $this->encryption->decrypt($encryptedSecret);
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
        $this->setUserSecret($userId, $encryptedSecret);
    }
}
