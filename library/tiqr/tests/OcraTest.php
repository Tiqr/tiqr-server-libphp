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
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Tiqr/OATH/OCRA.php';

class ArrayTest extends TestCase
{

    public function decimalToHex($decimalChallenge)
    {
        return dechex($decimalChallenge);
    }

    public function testPlainChallengeResponse()
    {

        $result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QN08",
                                     "3132333435363738393031323334353637383930",
                                     "",
                                     $this->decimalToHex("00000000"),
                                     "",
                                     "",
                                     "");

        $this->assertEquals("237653", $result);

        $result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QN08",
                                     "3132333435363738393031323334353637383930",
                                     "",
                                     $this->decimalToHex("77777777"),
                                     "",
                                     "",
                                     "");

        $this->assertEquals("224598", $result);
    }

    public function testChallengeResponseWithSession()
    {
        $result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QN08-S",
                                     "3132333435363738393031323334353637383930",
                                     "",
                                     $this->decimalToHex("77777777"),
                                     "",
                                     "ABCDEFABCDEF",
                                     "");

        $this->assertEquals("675831", $result);
    }

    public function testTruncate() {
        // Test RFC4226 truncate algorithm
        // Use SHA-1 with an integer value to generate the strings to truncate
        $tests=array(
//          sha => expected result
            6 => 37787,
            23 => 24262,
            114 => 5582,
            203 => 3164,
            480 => 385,
            1784 => 313,
            5001 => 65,
            8012 => 12,
            9536 => 41702,
            22264 => 16058,
            35619 => 3,
            71453 => 5,
            50979 => 16854,
            68359 => 54266,
            113274 => 7068,
        );
        foreach ($tests as $i => $expected) {
            $hash=sha1($i);
            $t=OCRA::_oath_truncate($hash, 6);
            $this->assertIsString($t);
            $this->assertTrue(strlen($t) == 6);

            $this->assertEquals( $expected, (int)$t, "$i : $hash : t=$t");
        }

        // String consisting of "0000...000" must result in 0
        $this->assertEquals( 0, (int)OCRA::_oath_truncate(str_repeat("00", 20), 6) );
    }

}
