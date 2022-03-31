<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_DeviceStorageTest extends TestCase
{
    /**
     * @dataProvider provideValidFactoryTypes
     */
    public function test_it_can_create_user_sercret_storage($type, $expectedInstanceOf)
    {
        $ocraService = Tiqr_DeviceStorage::getStorage(
            $type,
            [],
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing()
        );
        $this->assertInstanceOf($expectedInstanceOf, $ocraService);
        $this->assertInstanceOf(Tiqr_DeviceStorage_Abstract::class, $ocraService);
    }

    public function test_it_can_not_create_storage_by_fqn_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a DeviceStorage instance of type: Fictional_Service_That_Was_Implements_DeviceStorage.php");
        Tiqr_DeviceStorage::getStorage(
            'Fictional_Service_That_Was_Implements_DeviceStorage.php',
            [],
            Mockery::mock(LoggerInterface::class)
        );
    }

    public function provideValidFactoryTypes()
    {
        yield ['dummy', Tiqr_DeviceStorage_Dummy::class];
        yield ['tokenexchange', Tiqr_DeviceStorage_TokenExchange::class];
    }
}
