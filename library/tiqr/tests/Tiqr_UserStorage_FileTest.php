<?php

require_once 'tiqr_autoloader.inc';

use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_UserStorage_FileTest extends TestCase
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

    public function test_create_user_storage()
    {
        $userStorage = $this->buildUserStorage();
        $this->assertInstanceOf(Tiqr_UserStorage_Interface::class, $userStorage);
    }

    public function test_user_secret_storage_is_not_part_of_user_storage()
    {
        // For refactoring: user secret storage should not be on the user storage anymore
        $this->assertClassNotHasAttribute('_userSecretStorage', Tiqr_UserStorage_File::class);
    }

    public function test_get_secret_is_not_part_of_user_storage()
    {
        $userStorage = $this->buildUserStorage();
        $this->expectError();
        $this->expectErrorMessage('Call to undefined method Tiqr_UserStorage_File::getSecret()');
        $userStorage->getSecret('UserId');
    }

    public function test_set_secret_is_not_part_of_user_storage()
    {
        $userStorage = $this->buildUserStorage();
        $this->expectError();
        $this->expectErrorMessage('Call to undefined method Tiqr_UserStorage_File::setSecret()');
        $userStorage->setSecret('UserId', 'Secret');
    }

    private function buildUserStorage()
    {
        $config = [
            'type' => 'file',
            'path' => $this->targetPath
        ];
        return new Tiqr_UserStorage_File($config, $this->logger, $config);
    }
}
