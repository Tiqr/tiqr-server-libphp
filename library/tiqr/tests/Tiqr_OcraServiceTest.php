<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_OcraServiceTest extends TestCase
{
    /**
     * @dataProvider provideValidFactoryTypes
     */
    public function test_it_can_create_ocra_service($type, $expectedInstanceOf)
    {
        $allOptions = [
            'ocra.suite' => 'OCRA-1:HOTP-SHA1-6:QH10-S',
            'apiURL' => '',
            'consumerKey' => '',
        ];
        $ocraService = Tiqr_OcraService::getOcraService(
            $type,
            $allOptions,
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing()
        );
        $this->assertInstanceOf($expectedInstanceOf, $ocraService);
        $this->assertInstanceOf(Tiqr_OcraService_Interface::class, $ocraService);
    }

    public function test_it_can_create_ocra_service_with_defaults()
    {
        $allOptions = [
            'ocra.suite' => 'OCRA-1:HOTP-SHA1-6:QH10-S',
            'apiURL' => '',
            'consumerKey' => '',
        ];
        $ocraService = Tiqr_OcraService::getOcraService();
        $this->assertEquals(false, $ocraService->verifyResponse('', '', '', '', ''));   // Tests null logger
        $this->assertInstanceOf(Tiqr_OcraService_Tiqr::class, $ocraService);
        $this->assertInstanceOf(Tiqr_OcraService_Interface::class, $ocraService);
    }

    public function test_it_can_not_create_storage_by_fqn_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a OcraService instance of type: Fictional_Service_That_Was_Implements_OcraService.php");
        Tiqr_OcraService::getOcraService(
            'Fictional_Service_That_Was_Implements_OcraService.php',
            [],
            Mockery::mock(LoggerInterface::class)
        );
    }

    /**
     * @dataProvider provideOcraServices
     */
    public function test_session_and_challenge_generation($ocraService) {
        $challenge = $ocraService->generateChallenge();    // 10 hex digits - 5 bytes
        self::assertEquals(10, strlen($challenge));
    }

    public function provideOcraServices()
    {
        yield [ Tiqr_OcraService::getOcraService(
            'tiqr',
            array(),
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing()
        ) ];
        yield [ Tiqr_OcraService::getOcraService(
            'oathserviceclient',
            array(
                'apiURL' => '',
                'consumerKey' => ''),
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing() )
        ];
    }


    public function provideValidFactoryTypes()
    {
        yield ['tiqr', Tiqr_OcraService_Tiqr::class];
        yield ['oathserviceclient', Tiqr_OcraService_OathServiceClient::class];
    }
}
