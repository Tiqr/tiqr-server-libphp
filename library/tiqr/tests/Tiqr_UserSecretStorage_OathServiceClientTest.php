<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_UserSecretStorage_OathServiceClientTest extends TestCase
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $apiClient;

    protected function setUp(): void
    {
        $this->apiClient = Mockery::mock(Tiqr_API_Client::class);
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }

    public function test_create_user_secret_storage()
    {
        $userStorage = $this->buildUserSecretStorage();
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Interface::class, $userStorage);
    }

    public function test_it_can_store_and_retrieve_an_user_secret()
    {
        $store = $this->buildUserSecretStorage();
        $this->apiClient->shouldReceive('call')->with(
            '/secrets/user-id-1',
            'POST',
            ['secret' => 'my-secret']
        );

        $store->setSecret('user-id-1', 'my-secret');
        // Retrieving of the user secret is not supported
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Calling getUserSecret on the OathServiceClient is not supported");
        $store->getSecret('user-id-1');
    }

    private function buildUserSecretStorage(): Tiqr_UserSecretStorage_OathServiceClient
    {
        return new Tiqr_UserSecretStorage_OathServiceClient(
            $this->apiClient,
            $this->logger
        );
    }
}
