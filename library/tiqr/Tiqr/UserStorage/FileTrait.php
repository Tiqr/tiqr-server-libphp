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
     * @param string $userId
     * @param array $data
     * @throws ReadWriteException
     */
    protected function _saveUser(string $userId, array $data): void
    {
        if (file_put_contents($this->getPath().$userId.".json", json_encode($data)) === false) {
            throw new ReadWriteException('Unable to save the user to user storage (file storage)');
        }
    }

    /**
     * @param string $userId
     * @return bool true when the user exists, false otherwise
     *
     * Does not throw
     */
    protected function _userExists(string $userId): bool {
        $fileName = $this->getPath().$userId.".json";

        return file_exists($fileName);
    }

    /**
     * This function takes care of loading the user data from a JSON file.
     *
     * @param string $userId
     *
     * @return array containing the user data on success.
     *
     * @throws Exception when the data can not be found
     */
    protected function _loadUser(string $userId): array
    {
        $fileName = $this->getPath().$userId.".json";

        $data = NULL;
        if (file_exists($fileName)) {
            $data = json_decode(file_get_contents($this->getPath().$userId.".json"), true);
        }

        if ($data === NULL) {
            $this->logger->error(sprintf('Error loading user data (File) for user "%s"', $userId));
            throw new RuntimeException('Error loading user data (File)');
        }

        return $data;
    }

    /**
     * Retrieve the path where the json files are stored.
     * @return String
     */
    public function getPath(): string
    {
        if (substr($this->path, -1)!="/") return $this->path."/";
        return $this->path;
    }

    /**
     * @see Tiqr_HealthCheck_Interface::healthCheck()
     */
    public function healthCheck(string &$statusMessage = ''): bool
    {
        if (!is_dir($this->path)) {
            $statusMessage = "FileStorage: Path does not exist";
            return false;
        }
        // Check if the path is writable
        if (!is_writable($this->path)) {
            $statusMessage = "FileStorage: Path is not writable";
            return false;
        }

        return true;
    }
}
