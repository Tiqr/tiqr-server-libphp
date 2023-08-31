<?php

require_once 'tiqr_autoloader.inc';

require_once 'CustomEncryptionClass.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class Tiqr_UserSecretStorage_EncryptionTest extends TestCase
{
    public function test_it_can_create_dummy_encryption()
    {
        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            'dummy'
        );

        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Plain::class, $userSecretStorage_Encryption);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Interface::class, $userSecretStorage_Encryption);
    }

    public function test_it_can_create_plain_encryption()
    {
        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            'plain'
        );

        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Plain::class, $userSecretStorage_Encryption);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Interface::class, $userSecretStorage_Encryption);
    }

    public function test_error_when_invalid_type()
    {
        $this->expectExceptionMessage("Class 'type_that_does_not_exist' not found");

        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            'type_that_does_not_exist'
        );
    }

    public function test_it_can_create_custom_encryption()
    {
        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            CustomEncryptionClass::class,
            array('my_custom_option' => 'my_custom_value')
        );

        $this->assertInstanceOf(CustomEncryptionClass::class, $userSecretStorage_Encryption);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Interface::class, $userSecretStorage_Encryption);
    }

    public function test_it_can_create_openssl_encryption()
    {
        $userSecretStorage_Encryption=Tiqr_UserSecretStorage_Encryption::getEncryption(
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            'openssl',
            array('cipher' => 'aes-128-cbc', 'key_id' => 'test_key', 'keys' => array('test_key' => '0102030405060708090a0b0c0d0e0f10'))
        );

        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_OpenSSL::class, $userSecretStorage_Encryption);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Interface::class, $userSecretStorage_Encryption);
    }
}
