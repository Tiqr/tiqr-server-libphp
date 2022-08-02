<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_UserSecretStorage_PdoTest extends TestCase
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $dsn;

    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|PDO
     */
    private $pdoInstance;

    protected function setUp(): void
    {
        $targetPath = $this->makeTempDir();
        $this->dsn = 'sqlite:' . $targetPath . '/state.sq3';
        $this->setUpDatabase($this->dsn);
        $pdoInstance = new PDO($this->dsn, 'root', 'secret');
        $this->pdoInstance = Mockery::mock($pdoInstance);
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
        $this->assertClassNotHasAttribute('_userSecretStorage', Tiqr_UserStorage_Pdo::class);
    }

    public function test_exception_expected_when_set_secret_fails()
    {
        $store = $this->buildUserSecretStorage();

        $selectStatement = Mockery::mock(PDOStatement::class);
        $selectStatement->shouldReceive('execute')->andReturn(true);
        $selectStatement->shouldReceive('fetchColumn')->andReturn('user1');

        $updateStatement = Mockery::mock(PDOStatement::class);
        $updateStatement->shouldReceive('execute')->andReturnUsing(
            function () { throw new ReadWriteException(); }
        );

        $this->pdoInstance
            ->shouldReceive('prepare')
            ->with('SELECT userid FROM tiqrusersecret WHERE userid = ?')
            ->andReturn($selectStatement);


        $this->pdoInstance
            ->shouldReceive('prepare')
            ->with('UPDATE tiqrusersecret SET secret = ? WHERE userid = ?')
            ->andReturn($updateStatement);

        $this->expectException(ReadWriteException::class);
        $store->setSecret('UserId', 'My Secret');
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
        $this->expectErrorMessage("Call to private method Tiqr_UserSecretStorage_Pdo::getUserSecret() from context 'Tiqr_UserSecretStorage_PdoTest'");
        $store->getUserSecret('UserId');
    }

    public function test_deprecated_setSecret_method_is_not_available()
    {
        $store = $this->buildUserSecretStorage();
        $this->expectError();
        $this->expectErrorMessage("Call to private method Tiqr_UserSecretStorage_Pdo::setUserSecret() from context 'Tiqr_UserSecretStorage_PdoTest'");
        $store->setUserSecret('UserId', 'My Secret');
    }

    private function buildUserSecretStorage(): Tiqr_UserSecretStorage_Pdo
    {
        return new Tiqr_UserSecretStorage_Pdo(
            new Tiqr_UserSecretStorage_Encryption_Dummy([]),
            $this->logger,
            $this->pdoInstance,
            'tiqrusersecret'
        );
    }

    private function makeTempDir() {
        $tempName = tempnam(sys_get_temp_dir(),'Tiqr_UserSecretStorageTest');
        unlink($tempName);
        mkdir($tempName);
        return $tempName;
    }

    private function setUpDatabase(string $dsn)
    {
        // Create test database
        $pdo = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE tiqrusersecret (userid varchar(255) PRIMARY KEY, secret varchar(255));");
    }
}
