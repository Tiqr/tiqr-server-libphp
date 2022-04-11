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
     * Store a value with a certain key in the statestorage.
     * @param String $key The key identifying the data
     * @param mixed $value The data to store in state storage
     * @param int $expire The expiration (in seconds) of the data
     * @throws ReadWriteException
     */
    public function setValue($key, $value, $expire=0);

    /**
     * Remove a value from the state storage
     * @param String $key The key identifying the data to be removed.
     * @throws ReadWriteException
     */
    public function unsetValue($key);

    /**
     * Retrieve the data for a certain key.
     * @param String $key The key identifying the data to be retrieved.
     * @return mixed The data associated with the key
     */
    public function getValue($key);

    /**
     * Called to initialize the storage engine
     * @return void
     */
    public function init();
}
