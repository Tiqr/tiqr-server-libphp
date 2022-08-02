<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_DeviceStorageTest extends TestCase
{
    /**
     * @dataProvider provideValidFactoryTypes
     */
    public function test_it_can_create_device_storage($type, $expectedInstanceOf)
    {
        $deviceStorage = Tiqr_DeviceStorage::getStorage(
            $type,
            [],
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing()
        );
        $this->assertInstanceOf($expectedInstanceOf, $deviceStorage);
        $this->assertInstanceOf(Tiqr_DeviceStorage_Abstract::class, $deviceStorage);
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
