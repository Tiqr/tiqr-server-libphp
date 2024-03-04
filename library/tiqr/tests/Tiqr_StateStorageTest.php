<?php

require_once 'tiqr_autoloader.inc';

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_StateStorageTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /**
     * @var LoggerInterface
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }

    private function makeTempDir()
    {
        $t=tempnam(sys_get_temp_dir(),'Tiqr_StateStorageTest');
        unlink($t);
        mkdir($t);
        return $t;
    }

    public function testStateStorage_File()
    {
        // No config, always writes to /tmp
        $ss = Tiqr_StateStorage::getStorage("file", ['path' => '/tmp'], $this->logger);
        $this->assertInstanceOf(Tiqr_StateStorage_File::class, $ss);

        $this->stateTests($ss);
    }

    public function test_it_can_not_create_ldap_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a StateStorage instance of type: ldap");
        Tiqr_StateStorage::getStorage("ldap", array(), $this->logger);
    }

    public function test_it_can_not_create_storage_by_fqn_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a StateStorage instance of type: Fictional_Service_That_Was_Implements_StateStorage.php");
        Tiqr_StateStorage::getStorage("Fictional_Service_That_Was_Implements_StateStorage.php", array(), $this->logger);
    }

    public function testStateStorage_Pdo()
    {
        $stateStorage = $this->createStateStorage();
        $this->assertInstanceOf(Tiqr_StateStorage_Pdo::class, $stateStorage);

        $this->stateTests($stateStorage);
    }

    public function test_unsetting_a_non_existing_key_does_not_result_in_error()
    {
        $stateStorage = $this->createStateStorage();
        $this->logger->shouldReceive('info')->once();
        try {
            $stateStorage->unsetValue('i-do-not-exist');
            $this->fail('Expected Exception');
        }
        catch (Exception $e) {}
    }

    /**
     * @dataProvider provideInvalidPdoConfigurationOptions
     */
    public function test_pdo_requires_certain_configration_options($invalidOptions)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Please configure the ".*" configuration option for the PDO state storage$/');
        Tiqr_StateStorage::getStorage("pdo", $invalidOptions, $this->logger);
    }

    public function provideInvalidPdoConfigurationOptions()
    {
        return [
            'missing table' => [['dsn' => 'foobar', 'username' => 'user', 'password' => 'secret']],
            'missing dsn' => [['table'=> 'table', 'username' => 'user', 'password' => 'secret']],
            'missing username' => [['dsn' => 'foobar', 'table'=> 'table', 'password' => 'secret']],
            'missing password' => [['dsn' => 'foobar', 'table'=> 'table', 'username' => 'user']],
            'missing multiple' => [['username' => 'user']],
            'missing everything' => [[]],
            'missing everything, but has invalid options' => [['user' => 'user', 'pw' => 'secret']],
        ];
    }

    private function stateTests(Tiqr_StateStorage_StateStorageInterface $ss)
    {
        // Getting nonexistent value returns NULL
        $this->assertEquals(NULL,  $ss->getValue("nonexistent_key"));

        // Empty key not allowed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty key not allowed');
        $ss->setValue('', 'empty key', 0);

        // Test update
        $ss->setValue('update_key', 'first-value', 2);
        $this->assertEquals('first-value', $ss->getValue('update_key'));  // Must exist
        $ss->setValue('update_key', 'second-value', 0);    // Update all fields

        // Test unsset
        $ss->setValue('set-key-1', 'set-value-1', 0);
        $this->assertEquals('set-value-1', $ss->getValue('set-key-1'));  // Must exist
        $ss->setValue('set-key-2', 'set-value-2', 60);
        $this->assertEquals('set-value-2', $ss->getValue('set-key-2'));  // Must exist
        $ss->unsetValue('set-key-1');
        $ss->unsetValue('set-key-2');
        $this->assertEquals(NULL, $ss->getValue('set-key-1'));  // Must not xist
        $this->assertEquals(NULL, $ss->getValue('set-key-2'));  // Must not xist

        // Test expiry
        $ss->setValue('long-expiry-key', 'long-expiry-value', 60 * 5);  // Expiry in 5 minutes
        $this->assertEquals('long-expiry-value', $ss->getValue('long-expiry-key'));  // Must exist
        $short_expiry_time=2;
        $endtime = time() + $short_expiry_time + 1;
        $ss->setValue('two-second-expiry-key', 'key_value-2', $short_expiry_time);  // Expiry in seconds
        $this->assertEquals('key_value-2', $ss->getValue('two-second-expiry-key'));  // Must still exist

        while (time() < $endtime) {
            $ss->getValue('two-second-expiry-key'); // Likely to trigger GC, depending on loop count
        }

        $this->assertEquals(NULL, $ss->getValue('two-second-expiry-key'));  // Must not exist

        // Check that keys with longer expiry still exist
        $this->assertEquals('long-expiry-value', $ss->getValue('long-expiry-key'));  // Must still exist
        $this->assertEquals('second-value', $ss->getValue('update_key'));  // Must still exist because we set it to never expire
    }

    private function createStateStorage(): Tiqr_StateStorage_Pdo
    {
        $tmpDir = $this->makeTempDir();
        $dsn = 'sqlite:' . $tmpDir . '/state.sq3';
        // Create test database
        $pdo = new PDO(
            $dsn,
            null,
            null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,]
        );
        $pdo->exec("CREATE TABLE state (key varchar(255) PRIMARY KEY, expire int, value text)");

        $options = [
            'table' => 'state',
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
            'cleanup_probability' => 0.6,
        ];
        return Tiqr_StateStorage::getStorage("pdo", $options, $this->logger);
    }
}
