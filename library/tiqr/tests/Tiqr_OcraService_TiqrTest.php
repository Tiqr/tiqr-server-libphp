<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_OcraService_TiqrTest extends TestCase
{
    static function getService(string $ocraSuite): Tiqr_OcraService_Tiqr
    {
        return new Tiqr_OcraService_Tiqr(
            array('ocra.suite' => $ocraSuite),
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing()
        );
    }

    public function testRandomVerify() {
        $suite = \Tiqr_Service::DEFAULT_OCRA_SUITE;
        $secret=str_repeat('00', 32);   // Tiqr uses 64 hex digit length keys (=32 bytes)
        $ocra=$this->getService($suite);
        $session_key = '0001020304050607080910111213141516';
        $challenge = $ocra->generateChallenge();    // 10 hex digits - 5 bytes
        self::assertEquals(10, strlen($challenge));
        $response=OCRA::generateOCRA($suite, $secret, '', $challenge, '', $session_key,'');
        self::assertTrue( (strlen($response) == 6) );
        self::assertTrue($ocra->verifyResponse($response, 'dummy-user', $secret, $challenge, $session_key));
    }

    public function  testOCRAvectors()
    {
        $suite = \Tiqr_Service::DEFAULT_OCRA_SUITE;
        $ocra = $this->getService($suite);
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
        $i = 0;
        foreach ($testvectors as $v) {
            $this->assertTrue($ocra->verifyResponse($v[3], "vector-$i", $v[0], $v[1], $v[2]));
            $i++;
        }
    }
}
