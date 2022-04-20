<?php

require_once 'tiqr_autoloader.inc';

use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_StateStoragePdoTest extends TestCase
{
    /**
     * @var Tiqr_StateStorage_Pdo
     */
    private $stateStorage;

    /**
     * @var MockInterface|PDO
     */
    private $pdoInstance;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private function makeTempDir() {
        $tempName = tempnam(sys_get_temp_dir(),'Tiqr_StateStorageTest');
        unlink($tempName);
        mkdir($tempName);
        return $tempName;
    }

    private function setUpDatabase(string $dsn)
    {
        // Create test database
        $pdo = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE state (key varchar(255) PRIMARY KEY, expire int, value text);");
    }

    protected function setUp(): void
    {
        $targetPath = $this->makeTempDir();
        $dsn = 'sqlite:' . $targetPath . '/state.sq3';
        $this->setUpDatabase($dsn);

        $pdoInstance = new PDO($dsn, null, null);
        $this->pdoInstance = m::mock($pdoInstance);
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $this->stateStorage = new Tiqr_StateStorage_Pdo($this->pdoInstance, $this->logger,'state', 1);
        $this->assertInstanceOf(Tiqr_StateStorage_Pdo::class, $this->stateStorage);

    }

    function test_called_clean_expired() {
        // Here be dragons: first call to the PDO's prepare statement must be a DELETE query
        // (clearing the expired entries)
        $this->pdoInstance
            ->shouldReceive('prepare')
            ->once()
            ->withArgs(function($query) {
                $queryType = substr($query, 0,6);
                // This assertion focusses on the Delete statement, let the others through without checking
                if ($queryType === 'DELETE') {
                    $this->assertStringContainsString('DELETE FROM state', $query);
                    $this->assertStringContainsString('WHERE `expire` < ? AND NOT `expire` = 0', $query);
                    return true;
                }
                $this->fail('The first call to the prepare PDO method should be with a DELETE statement');
            })->andReturn(m::mock(PDOStatement::class)->shouldIgnoreMissing());
        // The other prepare statements we don't care about, but they must be
        // declared to prevent expectation errors. Covering them in the expectation above
        // raises side effects, as it becomes impossible to tell in what order the prepare
        // statement is called.
        $statement = m::mock(PDOStatement::class)->shouldIgnoreMissing();
        $this->pdoInstance
            ->shouldReceive('prepare')
            ->andReturn($statement);
        $statement
            ->shouldReceive('execute')
            ->andReturn(true);

        $this->stateStorage->setValue('key', 'data', 1);
    }

    /**
     * @dataProvider provideIncorrectCleanupProbabilityValues
     */
    public function test_input_validation_for_cleanup_probability($incorrectValue)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The probability for removing the expired state should be expressed in a floating point value between 0 and 1.');
        new Tiqr_StateStorage_Pdo(m::mock(PDO::class), $this->logger, 'tablename', $incorrectValue);
    }

    public function provideIncorrectCleanupProbabilityValues()
    {
        return [
            'value too low' => [-1],
            'value too high' => [1.001],
        ];
    }
}
