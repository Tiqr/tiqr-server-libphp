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

/**
 * The implementation for the tiqr ocra service class.
 *
 * @author lineke
 *
 */
class Tiqr_OcraService_Tiqr extends Tiqr_OcraService_Abstract
{

    /**
     *  @see Tiqr_OcraService_Interface::verifyResponse()
     */
    public function verifyResponse(String $response, String $userId, String $userSecret, String $challenge, String $sessionInformation): bool
    {
        // Calculate the response. Because we have the same information as the client this should result in the same
        // response as the client calculated.
        try {
            $expected = OCRA::generateOCRA($this->_ocraSuite, $userSecret, "", $challenge, "", $sessionInformation, "");
        }
        catch (Exception $e) {
            $this->logger->warning(sprintf('Error calculating OCRA response for user "%s"', $userId), array('exception'=>$e));
            return false;
        }

        if (strlen($expected) != strlen($response)) {
            $this->logger->warning('verifyResponse: calculated and expected response have different lengths');
        }
        // Use constant time compare
        return $this->_ocraParser->constEqual($expected, $response);
    }
}
