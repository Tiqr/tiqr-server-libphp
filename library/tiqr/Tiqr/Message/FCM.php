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
 * @author Joost van Dijk <joost.vandijk@surfnet.nl>
 *
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2024 SURF BV
 */
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;

/**
 * Android Cloud To Device Messaging message.
 *
 * @author peter
 */
class Tiqr_Message_FCM extends Tiqr_Message_Abstract
{
    /**
     * Send message.
     *
     * @throws Tiqr_Message_Exception_SendFailure
     * @throws \Google\Exception
     */
    public function send()
    {
        $options = $this->getOptions();
        $projectId = $options['firebase.projectId'];
        $credentialsFile = $options['firebase.credentialsFile'];
        $cacheTokens = $options['firebase.cacheTokens'] ?? false;
        $tokenCacheDir = $options['firebase.tokenCacheDir'] ?? __DIR__;
        $translatedAddress = $this->getAddress();
        $alertText = $this->getText();
        $url = $this->getCustomProperty('challenge');

        $this->_sendFirebase($translatedAddress, $alertText, $url, $projectId, $credentialsFile, $cacheTokens, $tokenCacheDir);
    }

    /**
     * @throws \Google\Exception
     * @throws Tiqr_Message_Exception_SendFailure
     */
    private function getGoogleAccessToken($credentialsFile, $cacheTokens, $tokenCacheDir )
    {
        $client = new \Google_Client();
        // Try to add a file based cache for accesstokens, if configured
        if ($cacheTokens) {
            //set up the cache
            $filesystemAdapter = new Local($tokenCacheDir);
            $filesystem = new Filesystem($filesystemAdapter);
            $pool = new FilesystemCachePool($filesystem);

            //set up a callback to log token refresh
            $tokenCallback = function ($cacheKey, $accessToken) {
                $this->logger->debug(sprintf('New access token received at cache key %s', $cacheKey));
            };
            $client->setTokenCallback($tokenCallback);
            $client->setCache($pool);
        } else {
            $this->logger->warning("Cache for oAuth tokens is disabled");
        }
        $client->setAuthConfig($credentialsFile);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $token = $client->getAccessToken();
        return $token['access_token'];
    }

    /**
     * Send a message to a device using the firebase API key.
     *
     * @param  $deviceToken     string device ID
     * @param  $alert           string alert message
     * @param  $challenge       string tiqr challenge url
     * @param  $projectId       string the id of the firebase project
     * @param  $credentialsFile string The location of the firebase secret json
     * @param  $cacheTokens     bool Enable caching the accesstokens for accessing the Google API
     * @param  $tokenCacheDir   string Location for storing the accesstoken cache
     * @param  $retry           boolean is this a 2nd attempt
     * @throws Tiqr_Message_Exception_SendFailure
     */
    private function _sendFirebase(string $deviceToken, string $alert, string $challenge, string $projectId, string $credentialsFile, bool $cacheTokens, string $tokenCacheDir, bool $retry=false)
    {
        $apiurl = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

        $fields = [
            'message' => [
                'token' => $deviceToken,
                'data' => [
                    'challenge' => $challenge,
                    'text'      => $alert,
                ],
                "android" => [
                    "ttl" => "300s",
                ],
            ],
        ];

        try {
            $headers = array(
                'Authorization: Bearer ' . $this->getGoogleAccessToken($credentialsFile, $cacheTokens, $tokenCacheDir),
                'Content-Type: application/json',
            );
        } catch (\Google\Exception $e) {
            throw new Tiqr_Message_Exception_SendFailure(sprintf("Error getting Goosle access token : %s", $e->getMessage()), true);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        $errors = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $remoteip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($result === false) {
            throw new Tiqr_Message_Exception_SendFailure("Server unavailable", true);
        }

        if (!empty($errors)) {
            throw new Tiqr_Message_Exception_SendFailure("Http error occurred: ". $errors, true);
        }

        // Wait and retry once in case of a 502 Bad Gateway error
        if ($statusCode === 502 && !($retry)) {
            sleep(2);
            $this->_sendFirebase($deviceToken, $alert, $challenge, $projectId, $credentialsFile,  $cacheTokens,  $tokenCacheDir, true);
            return;
        }

        if ($statusCode !== 200) {
            throw new Tiqr_Message_Exception_SendFailure(sprintf('Invalid status code : %s. Server : %s. Response : "%s".', $statusCode, $remoteip, $result), true);
        }

        // handle errors, ignoring registration_id's
        $response = json_decode($result, true);
        foreach ($response as $k => $v) {
            if ($k=="error") {
                throw new Tiqr_Message_Exception_SendFailure(sprintf("Error in FCM response: %s", $result), true);
            }
        }
    }
}
