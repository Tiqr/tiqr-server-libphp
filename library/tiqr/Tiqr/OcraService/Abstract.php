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

abstract class Tiqr_OcraService_Abstract implements Tiqr_OcraService_Interface
{
    /** @var OATH_OCRAParser */
    protected $_ocraParser;
    /** @var String */
    protected $_ocraSuite;
    /** @var LoggerInterface  */
    protected $logger;

    /**
     * @throws Exception
     */
    public function __construct(array $config, LoggerInterface $logger) {
        $this->logger = $logger;

        // Set the OCRA suite
        $this->_ocraSuite = $config['ocra.suite'] ?? 'OCRA-1:HOTP-SHA1-6:QH10-S';   // Use tiqr server default suite
        $this->_ocraParser = new OATH_OCRAParser($this->_ocraSuite);
    }

    /**
     * Generate a challenge string based on an ocraSuite
     * @return String An OCRA challenge that matches the specification of
     *         the ocraSuite.
     * @throws Exception
     */
    public function generateChallenge(): string
    {
        return $this->_ocraParser->generateChallenge();
    }

}