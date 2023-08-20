<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class Tiqr_UserSecretStorage_Encryption_OpenSSLTest extends TestCase
{
    public function test_it_can_create_openssl_encryption()
    {
        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            'openssl'
        );

        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_OpenSSL::class, $userSecretStorage_Encryption);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Interface::class, $userSecretStorage_Encryption);
    }

    /**
     * @dataProvider provide_invalid_configuration_options
     */
    public function test_invalid_configuration_options($options, $expectedError)
    {
        $this->expectErrorMessage($expectedError);

        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            'openssl',
            $options
        );

    }

    public function provide_invalid_configuration_options()
    {
        yield [ ['cipher' => 'invalid'], "Cipher 'invalid' is not supported by your version of openssl" ];
        yield [ ['cipher' => 'aes-128-ecb'], "Cipher 'aes-128-ecb' is not supported by this version of the tiqr library" ];
        yield [ ['key_id' => 'invalid:key'], "Key id 'invalid:key' contains invalid character ':'" ];
    }

    /**
     * @dataProvider provide_cipher_options
     */
    public function test_encrypt_decrypt($options)
    {
        $enc=new Tiqr_UserSecretStorage_Encryption_OpenSSL(
            $options
        );
        $testData='test plaintext data';
        $encrypted = $enc->encrypt($testData);
        $this->assertIsString($encrypted);

        $decrypted = $enc->decrypt($encrypted);
        $this->assertEquals($testData, $decrypted);
    }

    public function provide_cipher_options()
    {
        // supportedCiphers is a private static property of Tiqr_UserSecretStorage_Encryption_OpenSSL
        // make it public to access it
        $enc=new Tiqr_UserSecretStorage_Encryption_OpenSSL(['cipher' => 'aes-128-cbc', 'keys' => array('default' => '0102030405060708090a0b0c0d0e0f10')]);
        $reflection = new ReflectionClass($enc);
        $property = $reflection->getProperty('_supportedCiphers');
        $property->setAccessible(true);
        $supportedCiphers=$property->getValue($enc);

        $testKey='0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f20';
        $opensslSupportedCiphers = array_map('strtolower', openssl_get_cipher_methods());

        foreach ($supportedCiphers as $cipher=>$cipherInfo) {
            if (!in_array($cipher, $opensslSupportedCiphers)) {
                continue;   // Skip ciphers that are not supported by the current openssl, we can't test them
            }
            yield [ array('cipher' => $cipher, 'keys' => array('default' => substr($testKey, 0, $cipherInfo['key']*2)) ) ];
            yield [ array('cipher' => strtoupper($cipher), 'keys' => array('default' => substr($testKey, 0, $cipherInfo['key']*2)) ) ];
        }
    }

    /**
     * @dataProvider provide_invalid_encryption_options
     */
    public function test_invalid_encryption_options($options, $expectedError)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedError);

        $enc=new Tiqr_UserSecretStorage_Encryption_OpenSSL($options);
        $testData='test plaintext data';
        $enc->encrypt($testData);
    }

    public function provide_invalid_encryption_options()
    {
        yield [
            array('cipher' => 'aes-128-cbc', 'key_id'=>'key1', 'keys' => array('default' => '0102030405060708090a0b0c0d0e0f10')),
            "No key configured for key_id 'key1'"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'key_id'=>'key1', 'keys' => array('key1' => '0102030405060708090a0b0c0d0e0f')),
            "Invalid length of key with key_id 'key1' used with cipher 'aes-128-cbc', expected 16 bytes, got 15 bytes"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'key_id'=>'key1', 'keys' => array('key1' => '0102030405060708090a0b0c0d0e0f1011')),
            "Invalid length of key with key_id 'key1' used with cipher 'aes-128-cbc', expected 16 bytes, got 17 bytes"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'key_id'=>'key1', 'keys' => array('key1' => '0102030405060708090a0b0c0d0e0fxx')),
            "Error decoding key with key_id 'key1'"
        ];
    }

    /**
     * @dataProvider provide_decrypt_testverctors
     */
    public function test_decrypt($cipher, $key, $iv, $tag, $ciphertext, $plaintext) {
        $enc=new Tiqr_UserSecretStorage_Encryption_OpenSSL(
            array('cipher' => 'aes-128-cbc', 'keys' => array('test_key' => $key))
        );

        // <cipher>:<key_id>:<iv>:<tag>:<ciphertext>
        $encrypted=$cipher.':test_key:'.base64_encode(hex2bin($iv)).':'.base64_encode(hex2bin($tag)).':'.base64_encode(hex2bin($ciphertext));

        $decrypted = $enc->decrypt($encrypted);
        $this->assertEquals($plaintext, bin2hex($decrypted));
    }

    public function provide_decrypt_testverctors() {
        // cipher, key, iv, tag, ciphertext, plaintext
        // AES GCM test vector from http://csrc.nist.gov/groups/ST/toolkit/BCM/documents/proposedmodes/gcm/gcm-spec.pdf
        yield [
            'aes-128-gcm',
            'feffe9928665731c6d6a8f9467308308', // key
            'cafebabefacedbaddecaf888', // iv
            '4d5c2af327cd64a62cf35abd2ba6fab4', // tag
            '42831ec2217774244b7221b784d0d49ce3aa212f2c02a4e035c17e2329aca12e21d514b25466931c7d8f6a5aac84aa051ba30b396a0aac973d58e091473f5985', // ciphertext
            'd9313225f88406e5a55909c5aff5269a86a7a9531534f7da2e4c303d8a318a721c3c0c95956809532fcf0e2449a6b525b16aedf5aa0de657ba637b391aafd255'  // plaintext
        ];

        yield [
            'aes-128-cbc',
            '000102030405060708090A0B0C0D0E0F',
            '101112131415161718191A1B1C1D1E1F',
            '', // tag
            'a1031d42c29c4d605ab8a9863dfac2ec1195bb123b4eb393c5c10248dda384b1',
            bin2hex('plaintext testdata')
        ];
    }

    /**
     * @dataProvider provide_invalid_encrypted_data
     */
    public function test_decryption_error($options, $encrypted, $expectedError) {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedError);

        $enc=new Tiqr_UserSecretStorage_Encryption_OpenSSL($options);
        $enc->decrypt($encrypted);
    }

    public function provide_invalid_encrypted_data() {
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:default:EBESExQVFhcYGRobHB0eHw==::oQMdQsKcTWBauKmGPfrC7BGVuxI7TrOTxcECSN2jhLE=:',
            "Invalid ciphertext format"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:default:EBESExQVFhcYGRobHB0eHw==:oQMdQsKcTWBauKmGPfrC7BGVuxI7TrOTxcECSN2jhLE=',
            "Invalid ciphertext format"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:invalid:EBESExQVFhcYGRobHB0eHw==::oQMdQsKcTWBauKmGPfrC7BGVuxI7TrOTxcECSN2jhLE=',
            "No key configured for key_id 'invalid"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:default:EBESExQV_hcYGRobHB0eHw==::oQMdQsKcTWBauKmGPfrC7BGVuxI7TrOTxcECSN2jhLE=',
            "Error decoding IV"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:default:EBESExQVFhcYGRobHB0eHw==:_:xxxdQsKcTWBauKmGPfrC7BGVuxI7TrOTxcECSN2jhLE=',
            "Error decoding tag"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:default:EBESExQVFhcYGRobHB0eHw==::oQMdQsKcTWBauKmGPfr_7BGVuxI7TrOTxcECSN2jhLE=',
            "Error decrypting data"
        ];
        yield [
            array('cipher' => 'aes-128-cbc', 'keys' => array('default' => '000102030405060708090A0B0C0D0E0F' )),
            'aes-128-cbc:default:EBESExQVFhcYGRobHB0eHw==::xxxdQsKcTWBauKmGPfrC7BGVuxI7TrOTxcECSN2jhLE=',
            "Error decrypting data"
        ];
    }


}
