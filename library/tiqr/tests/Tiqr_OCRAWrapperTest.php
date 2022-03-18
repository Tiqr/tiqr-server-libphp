<?php

require_once 'tiqr_autoloader.inc';

// OCRAWrapper does not work with Tiqr_AutoLoader
$tiqrLibraryDir = __DIR__ . '/../Tiqr/';
require_once($tiqrLibraryDir.'OATH/OCRAWrapper.php');
require_once($tiqrLibraryDir.'OATH/OCRAWrapper_v1.php');

use PHPUnit\Framework\TestCase;

class Tiqr_OCRAWrapperTest extends TestCase
{
    const DEFAULT_OCRA_SUITE = 'OCRA-1:HOTP-SHA1-6:QH10-S';
    // OCRA-1      : OCRA (RFC6287) protocol version 1
    // HOTP-SHA1-6 : CryptoFunction (this is the RFC's default). HOTP (RFC4226) with SHA-1 truncated to 6 digits
    // QH10-S      : DataInput: challenge 10 hex digits - session key length not specified => default = 64 bytes

    public function testCreate() {
        $this->assertInstanceOf( Tiqr_OCRAWrapper_v1::class, new Tiqr_OCRAWrapper_v1( self::DEFAULT_OCRA_SUITE));   // Old
        $this->assertInstanceOf( Tiqr_OCRAWrapper::class, new Tiqr_OCRAWrapper( self::DEFAULT_OCRA_SUITE) );        // New
    }

    // The old OCRA implementation
    public function testOCRAWrapper_v1() {
        $secret=str_repeat('00', 32);   // Tiqr uses 64 hex digit length keys (=32 bytes)
        $ocra=new Tiqr_OCRAWrapper_v1( self::DEFAULT_OCRA_SUITE);   // Old implementation
        $session_key = $ocra->generateSessionKey(); // 32 digits - 16 bytes
        $challenge = $ocra->generateChallenge();
        self::assertEquals(10, strlen($challenge));
        $response=$ocra->calculateResponse($secret, $challenge, $session_key);
        // Will ocassionaly output 1 digit resposes
        self::assertTrue( strlen($response) > 1 );
        self::assertTrue($ocra->verifyResponse($response, $secret, $challenge, $session_key));
    }

    // The new OCRA implementation
    public function testOCRAWrapper() {
        $secret=str_repeat('00', 32);   // Tiqr uses 64 hex digit length keys (=32 bytes)
        $ocra=new Tiqr_OCRAWrapper( self::DEFAULT_OCRA_SUITE);  // New implementation
        $session_key = $ocra->generateSessionKey();  // 64 digits - 32 bytes
        $challenge = $ocra->generateChallenge();    // 10 hex digits - 5 bytes
        self::assertEquals(10, strlen($challenge));
        $response=$ocra->calculateResponse($secret, $challenge, $session_key);
        // Response length can occasionally be 5
        self::assertTrue( (strlen($response) == 5) ||  (strlen($response) == 6) );
        self::assertTrue($ocra->verifyResponse($response, $secret, $challenge, $session_key));
    }

    public function  testOCRAvectors() {
        $ocra = new Tiqr_OCRAWrapper(self::DEFAULT_OCRA_SUITE);
        $key32 = '3132333435363738393031323334353637383930313233343536373839303132';
        $testvectors = array(
            [$key32, '0000000000', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', '525367'],
            [$key32, '1111111111', '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f', '401066'],
            [$key32, '2222222222', '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f', '453922'],
            [$key32, '3333333333', '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f', '067878'],
            [$key32, '4444444444', '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f', '032766'],
            [$key32, '5555555555', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf', '200519'],
            [$key32, '6666666666', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', '783447'],
            [$key32, '7777777777', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', '952010'],
            [$key32, '8888888888', '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', '679025'],
            [$key32, '9999999999', '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f', '063810'],
            [$key32, 'aaaaaaaaaa', '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f', '705436'],
            [$key32, 'bbbbbbbbbb', '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f', '022194'],
            [$key32, 'cccccccccc', '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f', '447988'],
            [$key32, 'dddddddddd', 'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf', '982346'],
            [$key32, 'eeeeeeeeee', 'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf', '627589'],
            [$key32, 'ffffffffff', 'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff', '570983'],
        );
        foreach ($testvectors as $v) {
            $this->assertEquals($v[3], $ocra->calculateResponse($v[0], $v[1], $v[2]));
            $this->assertTrue($ocra->verifyResponse($v[3], $v[0], $v[1], $v[2]));
        }
    }

    public function  testOCRAversionDifferences() {
        $ocra1=new Tiqr_OCRAWrapper_v1( self::DEFAULT_OCRA_SUITE);   // Old implementations
        $ocra2=new Tiqr_OCRAWrapper( self::DEFAULT_OCRA_SUITE);   // Old implementations

        $v1_v2_mismatch = 0;
        $v1_length = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0);
        $v2_length = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0);
        $nr_tests = 50000;
        for ($i=0;$i<$nr_tests;$i++) {
            $secret = $ocra2->generateSessionKey(); // 32 random bytes

            $session_key = $ocra1->generateSessionKey(); // 32 digits - 16 bytes
            $challenge = $ocra1->generateChallenge();   // 10 hex digit challenge

            $response1 = $ocra1->calculateResponse($secret, $challenge, $session_key);
            $v1_length[strlen($response1)]++;
            $response2 = $ocra2->calculateResponse($secret, $challenge, $session_key);
            $v2_length[strlen($response2)]++;
            if ( (int)$response1 != (int)$response2 ) {
                $v1_v2_mismatch++;
                echo "v1: $response1 != v2: $response2\n";
                echo "SECRET=$secret  Q=$challenge  S=$session_key\n";
            }
        }
        // With truncate bug fixed, there must be no more mismatches
        $max_mismatch = 0;
        echo "Max allowed mismatches=$max_mismatch\n";
        $this->assertTrue($v1_v2_mismatch <= $max_mismatch, "Found $v1_v2_mismatch per $nr_tests. That is more than $max_mismatch");

        echo "V1 length counts: ".implode(", ", $v1_length)."\n";
        echo "V2 length counts: ".implode(", ", $v2_length)."\n";
    }


}
