<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_UserSecretStorageTest extends TestCase
{
    /**
     * @dataProvider provideValidFactoryTypes
     */
    public function test_it_can_create_user_sercret_storage($type, $expectedInstanceOf)
    {
        $allOptions = [
            'path' => './',
            'dsn' => 'foobar:user:pass',
            'username'=> 'user',
            'password'=>'secret',
            'apiURL'=>'',
            'consumerKey' => 'key'
        ];
        $userSecretStorage = Tiqr_UserSecretStorage::getSecretStorage(
            $type,
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $allOptions
        );
        $this->assertInstanceOf($expectedInstanceOf, $userSecretStorage);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Interface::class, $userSecretStorage);
    }

    public function test_it_can_not_create_storage_by_fqn_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a UserSecretStorage instance of type: Fictional_Service_That_Was_Implements_UserSecretStorage.php");
        Tiqr_UserSecretStorage::getSecretStorage(
            'Fictional_Service_That_Was_Implements_UserSecretStorage.php',
            Mockery::mock(LoggerInterface::class),
            []
        );
    }

    public function provideValidFactoryTypes()
    {
        yield ['file', Tiqr_UserSecretStorage_File::class];
        yield ['pdo', Tiqr_UserSecretStorage_Pdo::class];
        yield ['oathserviceclient', Tiqr_UserSecretStorage_OathServiceClient::class];
    }
}
