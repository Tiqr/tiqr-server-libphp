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
 * @copyright (C) 2010-2011 SURFnet BV
 */


/**
 * A class implementing secure random number generation.
 *
 * @author ivo
 *
 */
class Tiqr_Random
{
    /**
     * Generate $length cryptographically secure pseudo-random bytes
     * Throws when requested number of bytes cannot be generated
     *
     * @param int $length the number of bytes to generate.
     * @return string containing $length cryptographically secure pseudo-random bytes
     *
     * @throws Exception, Error, TypeError
     */
    public static function randomBytes($length)
    {
        // Get $length cryptographically secure pseudo-random bytes
        $rnd=\random_bytes($length);

        if (strlen($rnd) != $length) {
            throw new Exception("random_bytes did not return the requested number of bytes");
        }

        return $rnd;
    }

}