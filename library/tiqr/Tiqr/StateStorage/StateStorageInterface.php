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

interface Tiqr_StateStorage_StateStorageInterface
{
    /**
     * Store a $key and $value in the statestorage.
     * $key is the handle to the stored value.
     * If $key already exists, the existing value is overwritten
     *
     * @param String $key The key identifying the data
     * @param mixed $value The data to store in state storage
     * @param int $expire The expiration (in seconds) of the data, 0 means never expire
     *                    Note that depending on the backend used a key may be removed before the indicated expiry
     * @throws ReadWriteException
     * @throws Exception
     */
    public function setValue(string $key, $value, int $expire=0): void;

    /**
     * Remove $key from the state storage
     * It is not an error to remove a key that does not exist
     *
     * @param String $key The key identifying the data to be removed.
     * @throws ReadWriteException when there was en error communicating with the backend
     * @throws Exception
     */
    public function unsetValue(string $key): void;

    /**
     * Retrieve the value previously stored using setValue for $key.
     * @param String $key The key identifying the data to be retrieved.
     * @return mixed|null The data associated with the key,
     *                    returns NULL when the key cannot be found
     *                    MAY also NULL when there was an error communicating with the storage backend, depending on
     *                    the storage backend used
     *                    Depending on the backend used a key could have been removed
     *
     * @throws Exception
     * @throws ReadWriteException When there was en error communicating with the backend
     */
    public function getValue(string $key);

    /**
     * Called to initialize the storage engine
     * @return void
     * @throws RuntimeException
     */
    public function init(): void;
}
