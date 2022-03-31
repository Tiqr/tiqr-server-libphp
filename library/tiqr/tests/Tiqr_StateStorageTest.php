<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_StateStorageTest extends TestCase
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }

    private function makeTempDir() {
        $t=tempnam(sys_get_temp_dir(),'Tiqr_StateStorageTest');
        unlink($t);
        mkdir($t);
        return $t;
    }

    function testCreateStateStorage() {
        // Invalid type
        $this->expectException(Exception::class);
        Tiqr_StateStorage::getStorage("nonexistent", array(), $this->logger);
        $this->expectException(Exception::class);
    }

    function testStateStorage_File() {
        // No config, always writes to /tmp
        $ss=Tiqr_StateStorage::getStorage("file", array(), $this->logger);
        $this->assertInstanceOf(Tiqr_StateStorage_File::class, $ss);

        $this->stateTests($ss);
    }

    function testStateStorage_Pdo() {
        $tmpDir = $this->makeTempDir();
        $dsn = 'sqlite:' . $tmpDir . '/state.sq3';
        // Create test database
        $pdo = new PDO(
            $dsn,
            null,
            null,
            array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,)
        );
        $this->assertTrue(
            0 === $pdo->exec( <<<SQL
                CREATE TABLE state (
                    key varchar(255) PRIMARY KEY,
                    expire int,
                    value text
                );
SQL
            ) );
        $options=array(
            'table' => 'state',
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
            'cleanup_probability' => 0.6
        );
        $ss=Tiqr_StateStorage::getStorage("pdo", $options, $this->logger);
        $this->assertInstanceOf(Tiqr_StateStorage_Pdo::class, $ss);

        $this->stateTests($ss);
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

    private function stateTests(Tiqr_StateStorage_Abstract $ss) {
        $ss->unsetValue("nonexistent_key");

        // Gettng nonexistent value returns NULL
        $this->assertEquals(NULL,  $ss->getValue("nonexistent_key"));

        // Empty key allowed in Pdo and File, but Pdo fails silently
        $this->assertEquals('', $ss->setValue('', 'empty', 0));
        // Test it was written
        if ($ss instanceof Tiqr_StateStorage_File) {
            $this->assertEquals('empty', $ss->getValue(''));
        }
        elseif ($ss instanceof Tiqr_StateStorage_Pdo) {
            $this->assertEquals(NULL, $ss->getValue(''));   // PDO won't return empty key
        }
        else {
            throw new LogicException("Don't know how to test this type");
        }

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
}
