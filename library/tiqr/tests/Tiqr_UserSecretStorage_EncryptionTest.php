<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class CustomEncryptionClass_1 implements Tiqr_UserSecretStorage_Encryption_Interface
{
    public function __construct(array $options = array())
    {
        if (!isset($options['my_custom_option'])) {
            throw new RuntimeException("Missing option 'my_custom_option'");
        }
        if ($options['my_custom_option'] != 'my_custom_value') {
            throw new RuntimeException("Missing value for 'my_custom_option'");
        }
    }

    public function encrypt(string $data): string
    {
        return $data;
    }

    public function decrypt(string $data): string
    {
        return $data;
    }

    public function get_type() : string
    {
        return 'CustomEncryptionClass_1';
    }
}

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
            CustomEncryptionClass_1::class,
            array('my_custom_option' => 'my_custom_value')
        );

        $this->assertInstanceOf(CustomEncryptionClass_1::class, $userSecretStorage_Encryption);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Encryption_Interface::class, $userSecretStorage_Encryption);
    }
}
