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

require_once("Tiqr/OATH/OCRAWrapper.php");
require_once("Tiqr/OATH/OCRAWrapper_v1.php");

/**
 * The implementation for the tiqr ocra service class.
 *
 * @author lineke
 *
 */
class Tiqr_OcraService_Tiqr extends Tiqr_OcraService_Abstract
{
    protected $_ocraSuite;
    protected $_ocraWrapper;
    protected $_ocraWrapper_v1;
    protected $_protocolVersion;

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
        $this->_ocraSuite = $config['ocra.suite'];
        $this->_protocolVersion = $config['protocolVersion'];
        $this->_ocraWrapper_v1 = new Tiqr_OCRAWrapper_v1($this->_ocraSuite);
        $this->_ocraWrapper = new Tiqr_OCRAWrapper($this->_ocraSuite);
        $this->logger = $logger;
    }

    /**
     * Get the correct protocol specific ocra wrapper
     *
     * @return Tiqr_OCRAWrapper|Tiqr_OCRAWrapper_v1
     */
    protected function _getProtocolSpecificOCRAWrapper()
    {
        if ($this->_protocolVersion < 2) {
            $this->logger->info('Using Tiqr_OCRAWrapper_v1 as protocol');
            return $this->_ocraWrapper_v1;
        } else {
            $this->logger->info('Using Tiqr_OCRAWrapper as protocol');
            return $this->_ocraWrapper;
        }
    }

    /**
     * Get the ocra challenge
     *
     * @return String The challenge
     */
    public function generateChallenge()
    {
        return $this->_ocraWrapper->generateChallenge();
    }

    /**
     * Verify the response
     *
     * @param string $response
     * @param string $userSecret
     * @param string $challenge
     * @param string $sessionKey
     *
     * @return boolean True if response matches, false otherwise
     */
    public function verifyResponseWithSecret($response, $userSecret, $challenge, $sessionKey)
    {
        $this->logger->info('Verify the response is correct');
        return $this->_getProtocolSpecificOCRAWrapper()->verifyResponse($response, $userSecret, $challenge, $sessionKey);
    }
}
