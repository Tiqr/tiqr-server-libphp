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
 * @author Ivo Jansch <ivo@egeniq.com>
 *
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2010-2012 SURFnet BV
 */

use Psr\Log\LoggerInterface;

require_once('Tiqr/API/Client.php');

/**
 * The implementation for the oathservice ocra service class.
 *
 * @author lineke
 *
 */
class Tiqr_OcraService_OathServiceClient extends Tiqr_OcraService_Abstract
{
    protected $_apiClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Construct a ocra service class
     *
     * @param array $config The configuration that a specific user class may use.
     */
    public function __construct($config, LoggerInterface $logger)
    {
        $this->_apiClient = new Tiqr_API_Client();
        $this->_apiClient->setBaseURL($config['apiURL']);
        $this->_apiClient->setConsumerKey($config['consumerKey']);
        $this->logger = $logger;
    }

    /**
     * Get the ocra challenge
     *
     * @return string|null The challenge or null when no challende was returned form the Oath service
     */
    public function generateChallenge()
    {
        $result = $this->_apiClient->call('/oath/challenge/ocra');
        if ($result->code == '200') {
            $this->logger->notice(
                sprintf(
                    'Challenge api call returned status code %s and response body: %s.',
                    $result->code,
                    $result->body
                )
            );
            return $result->body;
        }
        $this->logger->error('The call to /oath/challenge/ocra did not yield a challenge.');
        return null;
    }

    /**
     * Verify the response
     *
     * @param string $response
     * @param string $userId
     * @param string $challenge
     * @param string $sessionKey
     *
     * @return boolean True if response matches, false otherwise
     */
    public function verifyResponseWithUserId($response, $userId, $challenge, $sessionKey)
    {
        try {
            $result = $this->_apiClient->call('/oath/validate/ocra?response='.urlencode($response).'&challenge='.urlencode($challenge).'&userId='.urlencode($userId).'&sessionKey='.urlencode($sessionKey));
            $this->logger->notice(
                sprintf(
                    'Verify response api call returned status code %s and response body: %s.',
                    $result->code,
                    $result->body
                )
            );
            return true;
        } catch (Exception $e) {
            $this->logger->error(
                sprintf(
                    'Calling of verifyResponseWithUserId failed with message: "%s"',
                    $e->getMessage()
                )
            );
            return false;
        }
    }

    /**
     * Returns which method name to use to verify the response (verifyResponseWithSecret or verifyResponseWithUserId)
     *
     * @return string
     */
    public function getVerificationMethodName()
    {
        return 'verifyResponseWithUserId';
    }
}
