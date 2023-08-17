<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_UserSecretStorage_FileTest extends TestCase
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $targetPath;

    private function makeTempDir() {
        $tempName = tempnam(sys_get_temp_dir(),'Tiqr_UserStorageTest');
        unlink($tempName);
        mkdir($tempName);
        return $tempName;
    }

    protected function setUp(): void
    {
        $this->targetPath = $this->makeTempDir();
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }

    public function test_create_user_secret_storage()
    {
        $userStorage = $this->buildUserSecretStorage();
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Interface::class, $userStorage);
    }

    public function test_user_secret_storage_is_not_part_of_user_storage()
    {
        // For refactoring: user secret storage should not be on the user storage anymore
        $this->assertClassNotHasAttribute('_userSecretStorage', Tiqr_UserStorage_File::class);
    }

    public function test_it_can_store_and_retrieve_an_user_secret()
    {
        $store = $this->buildUserSecretStorage();
        $store->setSecret('user-id-1', 'my-secret');
        $secret = $store->getSecret('user-id-1');
        $this->assertEquals('my-secret', $secret);
    }

    public function test_deprecated_getSecret_method_is_not_available()
    {
        $store = $this->buildUserSecretStorage();
        $this->expectError();
        $this->expectErrorMessage("Call to private method Tiqr_UserSecretStorage_File::getUserSecret()");
        $this->expectErrorMessage("Tiqr_UserSecretStorage_FileTest");
        $store->getUserSecret('UserId');
    }

    public function test_deprecated_setSecret_method_is_not_available()
    {
        $store = $this->buildUserSecretStorage();
        $this->expectError();
        $this->expectErrorMessage("Call to private method Tiqr_UserSecretStorage_File::setUserSecret()");
        $this->expectErrorMessage("Tiqr_UserSecretStorage_FileTest");
        $store->setUserSecret('UserId', 'My Secret');
    }

    private function buildUserSecretStorage(): Tiqr_UserSecretStorage_File
    {
        return new Tiqr_UserSecretStorage_File(new Tiqr_UserSecretStorage_Encryption_Dummy([]), $this->targetPath, $this->logger);
    }
}
