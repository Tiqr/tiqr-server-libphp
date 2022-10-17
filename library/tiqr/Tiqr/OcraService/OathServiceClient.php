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
    /** @var Tiqr_API_Client */
    protected $_apiClient;

    /**
     * Construct a OCRA service that uses the Tiqr_API_Client to use an OCRA KeyServer
     *
     * @param array $config The configuration that a specific user class may use.
     * @throws Exception
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);

        if (!isset($config['apiURL'])) {
            throw new RuntimeException('Missing apiURL in config for oathserviceclient');
        }
        if (!isset($config['consumerKey'])) {
            throw new RuntimeException('Missing consumerKey in config for oathserviceclient');
        }

        $this->_apiClient = new Tiqr_API_Client();
        $this->_apiClient->setBaseURL($config['apiURL']);
        $this->_apiClient->setConsumerKey($config['consumerKey']);
    }

    // Use the implementation in the abstract class to generate the challenge locally
    // public function generateChallenge(): string

    // Use the implementation in the abstract class to generate the session key (i.e. session information) locally
    // public function generateSessionKey(): string

    // Use a remote server to verify the response
    public function verifyResponse(string $response, string $userId, string $userSecret, string $challenge, string $sessionInformation): bool
    {
        try {
            $result = $this->_apiClient->call('/oath/validate/ocra?response='.urlencode($response).'&challenge='.urlencode($challenge).'&userId='.urlencode($userId).'&sessionKey='.urlencode($sessionInformation));
            $this->logger->notice(
                sprintf(
                    'Verify response api call returned status code %s and response body: %s.',
                    $result->code,
                    $result->body
                )
            );
            // Tiqr_API_Client::call throws when it gets a non HTTP 2xx response
            return true;
        } catch (Exception $e) {
            $this->logger->error(
                sprintf(
                    'verifyResponse for user "%s" failed',
                    $userId
                ),
                array( 'exception' => $e)
            );
            return false;
        }
    }

}
