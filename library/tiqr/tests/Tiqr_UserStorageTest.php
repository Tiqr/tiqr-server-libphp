<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Tiqr_UserStorageTest extends TestCase
{
    private function makeTempDir() {
        $t=tempnam(sys_get_temp_dir(),'Tiqr_UserSecretStorage_FileTest');
        unlink($t);
        mkdir($t);
        return $t;
    }

    // Used by Pdo and File
    function userStorageTests(Tiqr_UserStorage_Abstract $userStorage) {
        $this->assertTrue( $userStorage->healthCheck() );
        $this->assertFalse( $userStorage->userExists( 'user1' ) );

        // Create a user in the storage
        $userStorage->createUser('user1', 'User1 display name');
        // Read back displayname
        $this->assertEquals( 'User1 display name', $userStorage->getDisplayName('user1') );

        // Check state of new user
        $this->assertTrue( false ==! $userStorage->userExists( 'user1' ) );
        $this->assertEquals( 0, $userStorage->getLoginAttempts( 'user1' ) );
        $this->assertEquals( 0, $userStorage->getTemporaryBlockAttempts( 'user1' ) );
        $this->assertFalse( $userStorage->isBlocked( 'user1', 0 ) );
        $this->assertFalse( $userStorage->isBlocked( 'user1', 60 ) );   // Check with temp block duration of 60 minutes
        $this->assertEquals( 0, $userStorage->getTemporaryBlockTimestamp('user1') );
        $this->assertEquals( '', $userStorage->getNotificationType( 'user1' ) );
        $this->assertEquals( '', $userStorage->getNotificationAddress( 'user1' ) );

        // Creating a user that already exists is an error
        try {
            $userStorage->createUser('user1', 'User1 display name');
            $this->fail('Expected exception');
        }
        catch (Exception $e) {}

        // notification type
        $userStorage->setNotificationType('user1', 'NOTIFICATION_TYPE');
        $this->assertEquals( 'NOTIFICATION_TYPE', $userStorage->getNotificationType( 'user1' ) );

        // notification address
        $userStorage->setNotificationType('user1', 'NOTIFICATION_ADDRESS');
        $this->assertEquals( 'NOTIFICATION_ADDRESS', $userStorage->getNotificationType( 'user1' ) );

        // Login attempts
        $userStorage->setLoginAttempts('user1', 3 );
        $this->assertEquals( 3, $userStorage->getLoginAttempts( 'user1' ) );

        // Temporary block attempts
        $userStorage->setTemporaryBlockAttempts('user1', 2 );
        $this->assertEquals( 2, $userStorage->getTemporaryBlockAttempts( 'user1' ) );

        // Block user
        $userStorage->setBlocked('user1', true );
        $this->assertTrue( $userStorage->isBlocked('user1', 0 ) ); // 0 means: do not take temp block status into account

        // Unblock user
        $userStorage->setBlocked('user1', false );

        // Temporary block user
        $now_min_5_minutes = time() - 5*60;
        $userStorage->setTemporaryBlockTimestamp( 'user1', $now_min_5_minutes);
        $this->assertEquals( $now_min_5_minutes, $userStorage->getTemporaryBlockTimestamp( 'user1' ) );

        // duration is in minutes
        $this->assertFalse( $userStorage->isBlocked('user1', 4) );  // 5 min ago, so temp block expired
        $this->assertTrue( $userStorage->isBlocked('user1', 10) );  // Block did not expire yet

        // Test ignoring temp block
        $this->assertFalse( $userStorage->isBlocked('user1', 0 ) ); // 0 means: do not take temp block status into account

        // Block user and assert user is still block, even when temp block expired
        $userStorage->setBlocked('user1', true );
        $this->assertTrue( $userStorage->isBlocked('user1', 4 ) );  // 5 min ago, so temp block expired
    }

    function testUserStorage_File() {
        $tmpDir = $this->makeTempDir();
        $options=array(
            'path' => $tmpDir
        );
        $secretoptions=array(
            'type' => 'file',
            'path' => $tmpDir
        );
        $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

        $userStorage = Tiqr_UserStorage::getStorage(
            'file',
            $options,
            $logger
        );
        $this->assertInstanceOf(Tiqr_UserStorage_File::class, $userStorage);

        // Run user storage tests
        $this->userStorageTests($userStorage);
    }

    public function test_it_can_not_create_ldap_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a UserStorage instance of type: ldap");
        Tiqr_UserStorage::getStorage(
            'ldap',
            [],
            Mockery::mock(LoggerInterface::class)
        );
    }

    public function test_it_can_not_create_storage_by_fqn_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a UserStorage instance of type: Fictional_Service_That_Was_Implements_StateStorage.php");
        Tiqr_UserStorage::getStorage(
            'Fictional_Service_That_Was_Implements_StateStorage.php',
            [],
            Mockery::mock(LoggerInterface::class)
        );
    }

    // Test PDO user and secret storage in one table
    function testUserStorage_Pdo_combined() {
        $tmpDir = $this->makeTempDir();
        $dsn = 'sqlite:' . $tmpDir . '/user.sq3';
        // Create test database
        $pdo = new PDO(
            $dsn,
            null,
            null,
            array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,)
        );
        $this->assertTrue(
            0 === $pdo->exec( <<<SQL
                                            CREATE TABLE user (
                                                id INTEGER PRIMARY KEY,
                                                userid VARCHAR (30) NOT NULL UNIQUE,
                                                displayname VARCHAR (30) NOT NULL,
                                                secret VARCHAR (128),
                                                loginattempts INTEGER,
                                                tmpblocktimestamp INTEGER,
                                                tmpblockattempts INTEGER,
                                                blocked INTEGER,
                                                notificationtype VARCHAR(10),
                                                notificationaddress VARCHAR(256)
                                            );
SQL
        ) );

        $options=array(
            'table' => 'user',  // Optional
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
        );
        $secretoptions=array(
            'type' => 'pdo',
            'table' => 'user',  // Optional
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
        );
        $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $userStorage = Tiqr_UserStorage::getStorage(
            'pdo',
            $options,
            $logger
        );
        $this->assertInstanceOf(Tiqr_UserStorage_Pdo::class, $userStorage);

        // Run user storage tests
        $this->userStorageTests($userStorage);
    }

    // Test PDO user and secret storage in one table
    function testUserStorage_Pdo_split() {
        $tmpDir = $this->makeTempDir();
        $dsn = 'sqlite:' . $tmpDir . '/user.sq3';
        // Create test database
        $pdo = new PDO(
            $dsn,
            null,
            null,
            array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,)
        );
        $this->assertTrue(
            0 === $pdo->exec( <<<SQL
                                            CREATE TABLE user (
                                                id INTEGER PRIMARY KEY,
                                                userid VARCHAR (30) NOT NULL UNIQUE,
                                                displayname VARCHAR (30) NOT NULL,
                                                loginattempts INTEGER,
                                                tmpblocktimestamp INTEGER,
                                                tmpblockattempts INTEGER,
                                                blocked INTEGER,
                                                notificationtype VARCHAR(10),
                                                notificationaddress VARCHAR(256)
                                            );
SQL
            ) );
        $this->assertTrue(
            0 === $pdo->exec( <<<SQL
                                            CREATE TABLE secret (
                                                userid VARCHAR (30) NOT NULL UNIQUE,
                                                secret VARCHAR (128)
                                            );
SQL
            ) );
        $options=array(
            'table' => 'user',  // Optional
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
        );
        $secretoptions=array(
            'type' => 'pdo',
            'table' => 'secret',  // Optional
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
        );
        $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $userStorage = Tiqr_UserStorage::getStorage(
            'pdo',
            $options,
            $logger
        );
        $this->assertInstanceOf(Tiqr_UserStorage_Pdo::class, $userStorage);

        // Run user storage tests
        $this->userStorageTests($userStorage);
    }

    function test_pdo_healthcheck_fails_when_table_does_not_exist()
    {
        $tmpDir = $this->makeTempDir();
        $dsn = 'sqlite:' . $tmpDir . '/user.sq3';
        // Create test database
        $pdo = new PDO(
            $dsn,
            null,
            null,
            array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,)
        );

        $options=array(
            'table' => 'does_not_exists',  // Optional
            'dsn' => $dsn,
            'username' => null,
            'password' => null,
        );
        $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $userStorage = Tiqr_UserStorage::getStorage(
            'pdo',
            $options,
            $logger
        );

        $status = '';
        $this->assertFalse( $userStorage->healthCheck($status) );
        $this->assertStringContainsString('Error reading from UserStorage_PDO', $status);
    }
}
