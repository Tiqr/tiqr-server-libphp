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

require_once 'Zend/Mobile/Push/Gcm.php';
require_once 'Zend/Mobile/Push/Message/Gcm.php';

/**
 * Android Cloud To Device Messaging message.
 * @author peter
 */
class Tiqr_Message_GCM extends Tiqr_Message_Abstract
{
    private static $_services = array();
    
    /**
     * Factory method for returning a GCM service instance for the given 
     * configuration options.
     *
     * @param array $options configuration options
     *
     * @return Zend_Mobile_Push_Gcm service instance
     *
     * @throws Tiqr_Message_Exception_AuthFailure
     */
    private static function _getService($options)
    {
        $apikey      = $options['gcm.apikey'];
        $application = $options['gcm.application'];

        $key = "{apikey}@{$application}";

        if (!isset(self::$_services[$key])) {
            $service = new Zend_Mobile_Push_Gcm();
            $service->setApiKey($apikey);
            self::$_services[$key] = $service;
        }
        
        return self::$_services[$key];
    }
    
    /**
     * Send message.
     *
     * @throws Tiqr_Message_Exception_AuthFailure
     * @throws Tiqr_Message_Exception_SendFailure
     */
    public function send()
    {
        $service = self::_getService($this->getOptions());
        
        $data = $this->getCustomProperties();
        $data['text'] = $this->getText();

        $message = new Zend_Mobile_Push_Message_Gcm();
        $message->addToken($this->getAddress());
        $message->setId($this->getId()); // TODO: GCM equivalent needed?
        $message->setData($data);

        try {
            $response = $service->send($message);
        } catch (Zend_Http_Client_Exception $e) {
            throw new Tiqr_Message_Exception_SendFailure("HTTP client error", true, $e);
        } catch (Zend_Mobile_Push_Exception_ServerUnavailable $e) {
            throw new Tiqr_Message_Exception_SendFailure("Server unavailable", true, $e);
        } catch (Zend_Mobile_Push_Exception_InvalidAuthToken $e) {
            throw new Tiqr_Message_Exception_AuthFailure("Invalid token", $e);
        } catch (Zend_Mobile_Push_Exception_InvalidPayload $e) {
            throw new Tiqr_Message_Exception_SendFailure("Payload too large", $e);
        } catch (Zend_Mobile_Push_Exception $e) {
            throw new Tiqr_Message_Exception_SendFailure("General send error", false, $e);
        }

        // handle errors, ignoring registration_id's
        $error = null;
        foreach ($response->getResults() as $k => $v) {
            if (isset($v['error']) && $v['error'] === "MismatchSenderId") {
                throw new Tiqr_Message_Exception_MismatchSenderId("MismatchSenderId", true);
            }
            if (isset($v['error']) && is_null($error)) {
                $error = $v['error'];
            }
        }

        if ($error != null) {
            throw new Tiqr_Message_Exception_SendFailure("Error in GCM response: " . $error, true);
        }

    }
}
