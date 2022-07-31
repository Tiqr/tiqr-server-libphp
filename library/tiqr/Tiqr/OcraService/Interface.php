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

/**
 * The interface that defines what a ocra service class should implement.
 *
 * The interface defines the generation of the ocra challenge.
 *
 * @author lineke
 *
 */
interface Tiqr_OcraService_Interface
{
    /**
     * Construct a ocra service class
     *
     * @param array $config The configuration that a specific user class may use
     * @throws Exception
     */
    public function __construct(array $config, LoggerInterface $logger);

    /**
     * Generate a random OCRA challenge question (Q)
     *
     * @return string The challenge
     * @throws Exception
     */
    public function generateChallenge(): string;

    /**
     * Verify the OCRA response (provided by the client)
     *
     * The challenge and sessionInformation were sent to the tiqr client, and the tiqr client used the userSecret to
     * calculate the response.
     * To verify the response, we need the same information. The OCRA suite is also part of the calculation. It is
     * assumed that the client and OcraService implementation agreed on the OCRA suit to be used.
     *
     * The userSecret may not be available, i.e. when using the OathServiceClient. In that case the implementation
     * can use the $userId as a reference to lookup the user's secret
     *
     * Note: the counter (C), password (P) and timestamp (T) inputs to the OCRA algorithm are not used
     *
     * @param string $response The response calculated by the Tiqr client to verify
     * @param string $userId The tiqr user ID associated with the userSecret. The userId is not part of the OCRA
     *                       calculation. An implementation may use it to lookup the userSecret
     * @param string $userSecret The OCRA secret to use to verify the response, if available
     *                           HEX encoded string
     * @param string $challenge The OCRA challenge question (Q) that was given to the tiqr client,
     *                          HEX encoded string
     * @param string $sessionInformation The OCRA session information (S) that was given to the tiqr client,
     *                                   HEX encoded string
     *
     * @return boolean True if response is correct, false otherwise
     * @throws Exception
     */
    public function verifyResponse(String $response, String $userId, String $userSecret, String $challenge, String $sessionInformation): bool;
}
