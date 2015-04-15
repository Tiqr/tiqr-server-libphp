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
 * @author Peter Verhage <peter@egeniq.com>
 * 
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2011 SURFnet BV
 */


/** @internal base includes */
require_once('Tiqr/Message/Abstract.php');

//require_once('Zend/Gdata/ClientLogin.php');
require_once 'Zend/Mobile/Push/Gcm.php';
require_once 'Zend/Mobile/Push/Message/Gcm.php';

/**
 * Android Cloud To Device Messaging message.
 * @author peter
 */
class Tiqr_Message_C2DM extends Tiqr_Message_Abstract
{
    private static $_services = array();
    
    /**
     * Factory method for returning a C2DM service instance for the given 
     * configuration options.
     *
     * @param array $options configuration options
     *
     * @return Zend_Service_Google_C2dm service instance
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
     * @throws Tiqr_Message_Exception_InvalidDevice     
     */
    public function send()
    {
        $service = self::_getService($this->getOptions());
        
        $data = $this->getCustomProperties();
        $data['text'] = $this->getText();

        $message = new Zend_Mobile_Push_Message_Gcm();
        $message->addToken($this->getAddress());
        $message->setId($this->getId()); // TODO: needed?
        $message->setData($data);

        try {
            $response = $service->send($message);
        } catch (Zend_Htpp_Client_Exception $e) {
            throw new Tiqr_Message_Exception_SendFailure("HTTP client error", true, $e);
        } catch (Zend_Mobile_Push_Exception_ServerUnavailable $e) {
            throw new Tiqr_Message_Exception_SendFailure("Server unavailable", true, $e);
        } catch (Zend_Mobile_Push_Exception_InvalidAuthToken $e) {
            throw new Tiqr_Message_Exception_InvalidDevice("Invalid token", $e);
        } catch (Zend_Mobile_Push_Exception_InvalidPayload $e) {
            throw new Tiqr_Message_Exception_InvalidDevice("Payload too large", $e);
        } catch (Zend_Mobile_Push_Exception $e) {
            throw new Tiqr_Message_Exception_SendFailure("General send error", false, $e);
        }

        // handle all errors and registration_id's
        foreach ($response->getResults() as $k => $v) {
            if (isset($v['registration_id'])) {
                error_log("$k has a new registration id of: " . $v['registration_id']);
            }
            if (isset($v['error'])) {
                error_log("$k had an error of: " . $v['error']);
            }
            if (isset($v['message_id'])) { // success
            }
        }

    }
}
