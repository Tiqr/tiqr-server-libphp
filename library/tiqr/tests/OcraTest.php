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

require_once 'tiqr_autoloader.inc';

class OcraTest extends TestCase
{
    /**
     * @dataProvider onewayChallengeResponseDataProvider
     * @dataProvider tiqrTestDataProvider
     * @dataProvider truncationDataProvider
     */
    public function testOcraRFCTestVectors($expected, $suite, $key, $counter, $challenge, $password, $session, $time) {
        $result = OCRA::generateOCRA($suite, $key, $counter, $challenge, $password, $session, $time);
        $this->assertSame($expected, $result);
    }

    public function onewayChallengeResponseDataProvider()
    {
        // Test vectors from the RFC test suite
        // C.1. One-Way Challenge Response

        // The standard 20-byte secret key, as HEX string
        $key20 = '3132333435363738393031323334353637383930';
        // The standard 32-byte secret key, as HEX string
        $key32 = '3132333435363738393031323334353637383930313233343536373839303132';
        $key64 = '31323334353637383930313233343536373839303132333435363738393031323334353637383930313233343536373839303132333435363738393031323334';

        // PIN (1234) SHA1 hash value:
        $pass1234 = "7110eda4d09e062aa5e4a390b0a572ac0d2c0220";

        return [
            // [ result, suite, key, counter, challenge, password, session, time ]
            [ '237653', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('0', 8)), '', '', '' ],
            [ '243178', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('1', 8)), '', '', '' ],
            [ '653583', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('2', 8)), '', '', '' ],
            [ '740991', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('3', 8)), '', '', '' ],
            [ '608993', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('4', 8)), '', '', '' ],
            [ '388898', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('5', 8)), '', '', '' ],
            [ '816933', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('6', 8)), '', '', '' ],
            [ '224598', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('7', 8)), '', '', '' ],
            [ '750600', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('8', 8)), '', '', '' ],
            [ '294470', 'OCRA-1:HOTP-SHA1-6:QN08', $key20, '', dechex(str_repeat('9', 8)), '', '', '' ],

            [ '65347737', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '0', dechex('12345678'), $pass1234, '', '' ],
            [ '86775851', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '1', dechex('12345678'), $pass1234, '', '' ],
            [ '78192410', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '2', dechex('12345678'), $pass1234, '', '' ],
            [ '71565254', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '3', dechex('12345678'), $pass1234, '', '' ],
            [ '10104329', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '4', dechex('12345678'), $pass1234, '', '' ],
            [ '65983500', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '5', dechex('12345678'), $pass1234, '', '' ],
            [ '70069104', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '6', dechex('12345678'), $pass1234, '', '' ],
            [ '91771096', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '7', dechex('12345678'), $pass1234, '', '' ],
            [ '75011558', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '8', dechex('12345678'), $pass1234, '', '' ],
            [ '08522129', 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', $key32, '9', dechex('12345678'), $pass1234, '', '' ],

            [ '83238735', 'OCRA-1:HOTP-SHA256-8:QN08-PSHA1', $key32, '', dechex(str_repeat('0', 8)), $pass1234, '', '' ],
            [ '01501458', 'OCRA-1:HOTP-SHA256-8:QN08-PSHA1', $key32, '', dechex(str_repeat('1', 8)), $pass1234, '', '' ],
            [ '17957585', 'OCRA-1:HOTP-SHA256-8:QN08-PSHA1', $key32, '', dechex(str_repeat('2', 8)), $pass1234, '', '' ],
            [ '86776967', 'OCRA-1:HOTP-SHA256-8:QN08-PSHA1', $key32, '', dechex(str_repeat('3', 8)), $pass1234, '', '' ],
            [ '86807031', 'OCRA-1:HOTP-SHA256-8:QN08-PSHA1', $key32, '', dechex(str_repeat('4', 8)), $pass1234, '', '' ],

            [ '07016083', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00000', dechex(str_repeat('0', 8)), '', '', '' ],
            [ '63947962', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00001', dechex(str_repeat('1', 8)), '', '', '' ],
            [ '70123924', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00002', dechex(str_repeat('2', 8)), '', '', '' ],
            [ '25341727', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00003', dechex(str_repeat('3', 8)), '', '', '' ],
            [ '33203315', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00004', dechex(str_repeat('4', 8)), '', '', '' ],
            [ '34205738', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00005', dechex(str_repeat('5', 8)), '', '', '' ],
            [ '44343969', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00006', dechex(str_repeat('6', 8)), '', '', '' ],
            [ '51946085', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00007', dechex(str_repeat('7', 8)), '', '', '' ],
            [ '20403879', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00008', dechex(str_repeat('8', 8)), '', '', '' ],
            [ '31409299', 'OCRA-1:HOTP-SHA512-8:C-QN08', $key64, '00009', dechex(str_repeat('9', 8)), '', '', '' ],

            [ '95209754', 'OCRA-1:HOTP-SHA512-8:QN08-T1M', $key64, '', dechex(str_repeat('0', 8)), '', '', "132d0b6" ],
            [ '55907591', 'OCRA-1:HOTP-SHA512-8:QN08-T1M', $key64, '', dechex(str_repeat('1', 8)), '', '', "132d0b6" ],
            [ '22048402', 'OCRA-1:HOTP-SHA512-8:QN08-T1M', $key64, '', dechex(str_repeat('2', 8)), '', '', "132d0b6" ],
            [ '24218844', 'OCRA-1:HOTP-SHA512-8:QN08-T1M', $key64, '', dechex(str_repeat('3', 8)), '', '', "132d0b6" ],
            [ '36209546', 'OCRA-1:HOTP-SHA512-8:QN08-T1M', $key64, '', dechex(str_repeat('4', 8)), '', '', "132d0b6" ],
        ];
    }

    public function tiqrTestDataProvider()
    {
        // Test vectors for OCRA suites as used by tiqr with a 32 byte key and with
        // 32 byte and 16 byte session data
        // First for the default tiqr suite - non RFC: OCRA-1:HOTP-SHA1-6:QH10-S
        // Then for the RFC equivalent suite: OCRA-1:HOTP-SHA1-6:QH10-S064
        // Because the suite is part of the HMAC calculation different OCRA suites with the same input
        // yield different results.
        $key32 = '3132333435363738393031323334353637383930313233343536373839303132';
        return [
            // [ result, suite, key, counter, challenge, password, session, time ]
            ['525367', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['401066', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '1111111111', '', '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f', ''],
            ['453922', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '2222222222', '', '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f', ''],
            ['067878', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '3333333333', '', '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f', ''],
            ['032766', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '4444444444', '', '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f', ''],
            ['200519', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '5555555555', '', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf', ''],
            ['783447', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '6666666666', '', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', ''],
            ['952010', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '7777777777', '', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', ''],
            ['679025', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '8888888888', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['063810', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '9999999999', '', '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f', ''],
            ['705436', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'aaaaaaaaaa', '', '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f', ''],
            ['022194', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'bbbbbbbbbb', '', '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f', ''],
            ['447988', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'cccccccccc', '', '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f', ''],
            ['982346', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'dddddddddd', '', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf', ''],
            ['627589', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'eeeeeeeeee', '', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', ''],
            ['570983', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'ffffffffff', '', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', ''],

            ['539871', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f', ''],
            ['048093', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '1111111111', '', '101112131415161718191a1b1c1d1e1f', ''],
            ['967283', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '2222222222', '', '202122232425262728292a2b2c2d2e2f', ''],
            ['743385', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '3333333333', '', '303132333435363738393a3b3c3d3e3f', ''],
            ['940475', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '4444444444', '', '404142434445464748494a4b4c4d4e4f', ''],
            ['966039', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '5555555555', '', '505152535455565758595a5b5c5d5e5f', ''],
            ['640518', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '6666666666', '', '606162636465666768696a6b6c6d6e6f', ''],
            ['121159', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '7777777777', '', '707172737475767778797a7b7c7d7e7f', ''],
            ['282283', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '8888888888', '', '808182838485868788898a8b8c8d8e8f', ''],
            ['726279', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '9999999999', '', '909192939495969798999a9b9c9d9e9f', ''],
            ['086966', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'aaaaaaaaaa', '', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeaf', ''],
            ['690560', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'bbbbbbbbbb', '', 'b0b1b2b3b4b5b6b7b8b9babbbcbdbebf', ''],
            ['191243', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'cccccccccc', '', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecf', ''],
            ['566704', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'dddddddddd', '', 'd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', ''],
            ['088754', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'eeeeeeeeee', '', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeef', ''],
            ['349241', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', 'ffffffffff', '', 'f0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', ''],

            ['174452', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['554036', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '1111111111', '', '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f', ''],
            ['468209', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '2222222222', '', '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f', ''],
            ['445556', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '3333333333', '', '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f', ''],
            ['407436', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '4444444444', '', '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f', ''],
            ['645826', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '5555555555', '', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf', ''],
            ['485668', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '6666666666', '', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', ''],
            ['246775', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '7777777777', '', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', ''],
            ['242998', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '8888888888', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['597774', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '9999999999', '', '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f', ''],
            ['165023', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'aaaaaaaaaa', '', '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f', ''],
            ['940705', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'bbbbbbbbbb', '', '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f', ''],
            ['780450', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'cccccccccc', '', '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f', ''],
            ['261967', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'dddddddddd', '', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf', ''],
            ['638400', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'eeeeeeeeee', '', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', ''],
            ['464175', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'ffffffffff', '', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', ''],

            ['699726', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f', ''],
            ['443731', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '1111111111', '', '101112131415161718191a1b1c1d1e1f', ''],
            ['197950', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '2222222222', '', '202122232425262728292a2b2c2d2e2f', ''],
            ['958600', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '3333333333', '', '303132333435363738393a3b3c3d3e3f', ''],
            ['937539', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '4444444444', '', '404142434445464748494a4b4c4d4e4f', ''],
            ['440635', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '5555555555', '', '505152535455565758595a5b5c5d5e5f', ''],
            ['159268', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '6666666666', '', '606162636465666768696a6b6c6d6e6f', ''],
            ['757353', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '7777777777', '', '707172737475767778797a7b7c7d7e7f', ''],
            ['575961', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '8888888888', '', '808182838485868788898a8b8c8d8e8f', ''],
            ['644239', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', '9999999999', '', '909192939495969798999a9b9c9d9e9f', ''],
            ['992011', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'aaaaaaaaaa', '', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeaf', ''],
            ['722254', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'bbbbbbbbbb', '', 'b0b1b2b3b4b5b6b7b8b9babbbcbdbebf', ''],
            ['843312', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'cccccccccc', '', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecf', ''],
            ['822323', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'dddddddddd', '', 'd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', ''],
            ['688792', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'eeeeeeeeee', '', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeef', ''],
            ['918325', 'OCRA-1:HOTP-SHA1-6:QH10-S064', $key32, '', 'ffffffffff', '', 'f0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', ''],
        ];
    }

    public function truncationDataProvider()
    {
        // Test all allowed truncation lengths that are allowed by the spec (0, 4-10)
        $key32 = '3132333435363738393031323334353637383930313233343536373839303132';
        return [
            // [ result, suite, key, counter, challenge, password, session, time ]
            ['fb2c8815e0858fda61334e840e47b90e20f4b32f', 'OCRA-1:HOTP-SHA1-0:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['1160', 'OCRA-1:HOTP-SHA1-4:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['18118', 'OCRA-1:HOTP-SHA1-5:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['525367', 'OCRA-1:HOTP-SHA1-6:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['3234167', 'OCRA-1:HOTP-SHA1-7:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['75158174', 'OCRA-1:HOTP-SHA1-8:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['117807965', 'OCRA-1:HOTP-SHA1-9:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
            ['1612833570', 'OCRA-1:HOTP-SHA1-10:QH10-S', $key32, '', '0000000000', '', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', ''],
        ];
    }

    /**
     * @dataProvider ocraInvalidInputDataProvider
     */
    public function testOcraInputValidation($message, $suite, $key, $counter, $challenge, $password, $session, $time)
    {
        $this->expectExceptionObject(new InvalidArgumentException($message));
        OCRA::generateOCRA($suite, $key, $counter, $challenge, $password, $session, $time);
        $this->assertFalse(true, "Expected InvalidArgumentException with message: $message");
    }

    public function ocraInvalidInputDataProvider() {
        $invalid_hex = 'DEADWRONG0';
        $tiqr_default_suite='OCRA-1:HOTP-SHA1-6:QH10-S';

        // Test OCRA algorithm inputs:
        // - That are non hex
        // - That are too long. Max length is parameter and suite dependent. We test max length using a string of length
        //   (maxlength in bytes+1) * 2

        return [
            // Invalid key
            [ "Parameter 'key' contains non hex digits", $tiqr_default_suite, $invalid_hex, '', '', '', '', ''],

            // Invalid counter, max length is 8 bytes
            [ "Parameter 'counter' contains non hex digits", 'OCRA-1:HOTP-SHA512-8:C-QN08', '', $invalid_hex, '', '', '', ''],
            [ "Parameter 'counter' too long", 'OCRA-1:HOTP-SHA512-8:C-QN08', '', str_repeat('0', 18), '', '', '', ''],

            // Invalid challenge question, max length is 256 bytes
            [ "Parameter 'question' contains non hex digits", $tiqr_default_suite, '', '', $invalid_hex, '', '', ''],
            [ "Parameter 'question' too long", $tiqr_default_suite, '', '', str_repeat('0', 258), '', '', ''],

            // Invalid password, max length of PSHA1 is 20 bytes
            [ "Parameter 'password' contains non hex digits", 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', '', '', '', $invalid_hex, '', ''],
            [ "Parameter 'password' too long", 'OCRA-1:HOTP-SHA256-8:C-QN08-PSHA1', '', '', '', str_repeat('0', 42), '', ''],

            // Invalid session, max length of S is 64 bytes
            [ "Parameter 'sessionInformation' contains non hex digits", $tiqr_default_suite, '', '', '', '', $invalid_hex, ''],
            [ "Parameter 'sessionInformation' too long", $tiqr_default_suite, '', '', '', '', str_repeat('0', 130), ''],

            // Invalid time, max length of time is 8 bytes
            [ "Parameter 'timeStamp' contains non hex digits", 'OCRA-1:HOTP-SHA512-8:QN08-T1M', '', '', '', '', '', $invalid_hex],
            [ "Parameter 'timeStamp' too long", 'OCRA-1:HOTP-SHA512-8:QN08-T1M', '', '', '', '', '', str_repeat('0', 18)],

            // Unsupported OCRA CryptoFunctions

            // Algo is invalid
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA2-6:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:SHA1-6:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:SHA256-6:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:TOTP-SHA1-6:QH10-S', '', '', '', '', '', ''],

            // Invalid truncation length
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-1:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-2:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-3:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-11:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-123:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-A:QH10-S', '', '', '', '', '', ''],
            [ "Unsupported OCRA CryptoFunction", 'OCRA-1:HOTP-SHA1-6.0:QH10-S', '', '', '', '', '', ''],
        ];
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

    /**
     * @dataProvider HexToBinTestProvider
     */
    function testHexToBin($expected, string $hex, int $maxBytes, string $parameterName) {
        // Use reflection to access the private static function OCRA::_hexStr2Bytes(string $hex, int $maxBytes, string $parameterName) : string

        $classOCRA = new ReflectionClass('OCRA');
        $_hexStr2Bytes = $classOCRA->getMethod("_hexStr2Bytes");
        $_hexStr2Bytes->setAccessible(true);

        if ( $expected instanceof Exception ) {
            $this->expectExceptionObject($expected);
            $_hexStr2Bytes->invokeArgs(NULL, array($hex, $maxBytes, $parameterName));
        }
        else {
            $this->assertSame($expected, $_hexStr2Bytes->invokeArgs(NULL, array($hex, $maxBytes, $parameterName)));
        }
    }

    // For testHexToBin
    public function HexToBinTestProvider(): array
    {
        return [
            // [ expected return value or exception, hex, maxBytes, parameterName]
            'empty string => empty result' => ['', '', 0, 'dummy'],
            'empty string => empty result - for any maxBytes value' => ['', '', 10, 'dummy'],
            'nul byte' => ["\0", '00', 1, 'dummy'],
            '16 hex bytes - lower case' => ["\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f", '000102030405060708090a0b0c0d0e0f', 16, ''],
            '16 hex bytes - mixed case' => ["\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\xaa\xbb\xcc\xdd\xee\xff", '00010203040506070809aAbBcCdDeEfF', 16, ''],
            'upper case' => ["The quick brown fox...", '54686520717569636B2062726F776E20666F782E2E2E', 40, ''],
            'invalid hex digits' => [new InvalidArgumentException("Parameter 'p' contains non hex digits"), 'not hex', 100, 'p'],
            'odd number of hex digits' => [new InvalidArgumentException("Parameter 'q' contains odd number of hex digits"), '123', 100, 'q'],
            'too long' => [new InvalidArgumentException("Parameter 'someValue' too long"), '1122334455', 4, 'someValue'],
        ];
    }

}
