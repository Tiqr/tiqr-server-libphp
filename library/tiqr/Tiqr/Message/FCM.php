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
 * @copyright (C) 2010-2015 SURFnet BV
 */


/** @internal base includes */
require_once('Tiqr/Message/Abstract.php');

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
     */
    public function send()
    {
        $options = $this->getOptions();
        $apiKey = $options['firebase.apikey'];

        $translatedAddress = $this->getAddress();
        $alertText = $this->getText();
        $url = $this->getCustomProperty('challenge');

        $this->_sendFirebase($translatedAddress, $alertText, $url, $apiKey);
    }

    /**
     * Send a message to a device using the firebase API key.
     *
     * @param $deviceToken string device ID
     * @param $alert string alert message
     * @param $challenge string tiqr challenge url
     * @param $apiKey string api key for firebase
     * @param $retry boolean is this a 2nd attempt
     * @param Tiqr_Message_Exception $gcmException
     *
     * @throws Tiqr_Message_Exception_SendFailure
     */
    private function _sendFirebase($deviceToken, $alert, $challenge, $apiKey, $retry=false)
    {
        $msg = array(
            'challenge' => $challenge,
            'text'      => $alert,
        );

        $fields = array(
            'registration_ids' => array($deviceToken),
            'data' => $msg,
            'time_to_live' => 300,
        );

        $headers = array(
            'Authorization: key=' . $apiKey,
            'Content-Type: application/json',
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        $errors = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            throw new Tiqr_Message_Exception_SendFailure("Server unavailable", true);
        }

        if (!empty($errors)) {
            throw new Tiqr_Message_Exception_SendFailure("Http error occurred: ". $errors, true);
        }

        // Wait and retry once in case of a 502 Bad Gateway error
        if (($statusCode == 502) && !($retry)) {
          sleep(2);
          $this->_sendFirebase($deviceToken, $alert, $challenge, $apiKey, true);
          return;
        }

        if ($statusCode !== 200) {
            throw new Tiqr_Message_Exception_SendFailure("Invalid status code : '".$statusCode."'. Response: ".$result, true);
        }

        // handle errors, ignoring registration_id's
        $response = json_decode($result, true);
        foreach ($response['results'] as $k => $v) {
            if (isset($v['error'])) {
                throw new Tiqr_Message_Exception_SendFailure("Error in GCM response: " . $v['error'], true);
            }
        }
    }
}
