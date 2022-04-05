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
 * @author Lineke Kerckhoffs-Willems <lineke@egeniq.com>
 *
 * @package tiqr
 *
 * @license New BSD License - See LICENSE file for details.
 *
 * @copyright (C) 2014 SURFnet BV
 */

use Psr\Log\LoggerInterface;

require_once('Tiqr/API/Client.php');

/**
 * OATHService storage for user's secret
 */
class Tiqr_UserSecretStorage_OathServiceClient implements Tiqr_UserSecretStorage_Interface
{
    private $client;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Tiqr_API_Client $client, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->client = $client;
    }

    /**
     * Get the user's secret
     * Not implemented because the idea of the oathservice is that secrets cannot be retrieved
     *
     * @param String $userId
     *
     * @return string The user's secret
     */
    public function getSecret($userId)
    {
        $this->logger->notice('Calling getUserSecret on the OathServiceClient is not implemented');
        return null;
    }

    /**
     * Store a secret for a user
     *
     * Note that this storage engine does not use the encryption mechnism that PDO and File storage do implement. This
     * is taken care of by the OathService itself.
     *
     * @param string $userId
     * @param string $secret
     * @throws Exception
     */
    public function setSecret($userId, $secret)
    {
        $this->logger->info('Storing the user secret on the OathServiceClient (api call)');
        $this->client->call('/secrets/'.urlencode($userId), 'POST', array('secret' => $secret));
    }
}
