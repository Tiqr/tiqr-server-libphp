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

trait FileTrait
{
    /**
     * This function takes care of actually saving the user data to a JSON file.
     * @param String $userId
     * @param array $data
     */
    protected function _saveUser($userId, $data)
    {
        if (file_put_contents($this->getPath().$userId.".json", json_encode($data)) === false) {
            $this->logger->error('Unable to save the user to user storage (file storage)');
            return false;
        }
        return true;
    }

    /**
     * This function takes care of loading the user data from a JSON file.
     *
     * @param string $userId
     * @param boolean $failIfNotFound
     *
     * @return false if the data is not present, or an array containing the data.
     *
     * @throws Exception when the data can not be found and failIfNotFound is set to true
     */
    protected function _loadUser($userId, $failIfNotFound = TRUE)
    {
        $fileName = $this->getPath().$userId.".json";

        $data = NULL;
        if (file_exists($fileName)) {
            $data = json_decode(file_get_contents($this->getPath().$userId.".json"), true);
        }

        if ($data === NULL) {
            if ($failIfNotFound) {
                throw new Exception('Error loading data for user: ' . var_export($userId, TRUE));
            } else {
                $this->logger->error('Error loading data for user from user storage (file storage)');
                return false;
            }
        } else {
            return $data;
        }
    }

    /**
     * Retrieve the path where the json files are stored.
     * @return String
     */
    public function getPath()
    {
        if (substr($this->path, -1)!="/") return $this->path."/";
        return $this->path;
    }
}
