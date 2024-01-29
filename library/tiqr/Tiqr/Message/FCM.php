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

/**
 * Android Cloud To Device Messaging message.
 * @author peter
 */
class Tiqr_Message_FCM extends Tiqr_Message_Abstract
{
    /**
     * Send message.
     *
     * @throws Tiqr_Message_Exception_AuthFailure
     * @throws Tiqr_Message_Exception_SendFailure
     * @throws \Google\Exception
     */
    public function send()
    {
        $options = $this->getOptions();
        $projectId = $options['firebase.projectId'];
        $credentialsFile = $options['firebase.credentialsFile'];

        $translatedAddress = $this->getAddress();
        $alertText = $this->getText();
        $url = $this->getCustomProperty('challenge');

        $this->_sendFirebase($translatedAddress, $alertText, $url, $projectId, $credentialsFile);
    }

    /**
     * @throws \Google\Exception
     */
    private function getGoogleAccessToken($credentialsFile){
        $client = new \Google_Client();
        $client->setAuthConfig($credentialsFile);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $token = $client->getAccessToken();
        return $token['access_token'];
    }

    /**
     * Send a message to a device using the firebase API key.
     *
     * @param $deviceToken string device ID
     * @param $alert string alert message
     * @param $challenge string tiqr challenge url
     * @param $projectId string the id of the firebase project
     * @param $credentialsFile string The location of the firebase secret json
     * @param $retry boolean is this a 2nd attempt
     * @throws Tiqr_Message_Exception_SendFailure
     * @throws \Google\Exception
     */
    private function _sendFirebase(string $deviceToken, string $alert, string $challenge, string $projectId, string $credentialsFile, bool $retry=false)
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

        $headers = array(
            'Authorization: Bearer ' . $this->getGoogleAccessToken($credentialsFile),
            'Content-Type: application/json',
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        $errors = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $remoteip = curl_getinfo($ch,CURLINFO_PRIMARY_IP);
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
          $this->_sendFirebase($deviceToken, $alert, $challenge, $projectId, $credentialsFile, true);
          return;
        }

        if ($statusCode !== 200) {
            throw new Tiqr_Message_Exception_SendFailure(sprintf('Invalid status code : %s. Server : %s. Response : "%s".', $statusCode, $remoteip, $result), true);
        }

        // handle errors, ignoring registration_id's
        $response = json_decode($result, true);
        foreach ($response['results'] as $k => $v) {
            if (isset($v['error'])) {
                throw new Tiqr_Message_Exception_SendFailure("Error in FCM response: " . $v['error'], true);
            }
        }
    }
}
