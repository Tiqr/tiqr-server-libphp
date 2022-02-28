<?php

require_once 'tiqr_autoloader.inc';

use PHPUnit\Framework\TestCase;

class Tiqr_UserStorageTest extends TestCase
{
    private function makeTempDir() {
        $t=tempnam(sys_get_temp_dir(),'Tiqr_UserSecretStorage_FileTest');
        unlink($t);
        mkdir($t);
        return $t;
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
        $userStorage = \Tiqr_UserStorage::getStorage(
            'file',
            $options,
            $secretoptions
        );
        $this->assertInstanceOf(Tiqr_UserStorage_File::class, $userStorage);

        $this->assertFalse( $userStorage->userExists( 'user1' ) );

        // Create a user in the storage
        $this->assertTrue( $userStorage->createUser('user1', 'User1 display name') );
        // Read back displayname
        $this->assertEquals( 'User1 display name', $userStorage->getDisplayName('user1') );

        // Check state of new user
        $this->assertTrue( $userStorage->userExists( 'user1' ) );
        $this->assertEquals( 0, $userStorage->getLoginAttempts( 'user1' ) );
        $this->assertEquals( 0, $userStorage->getTemporaryBlockAttempts( 'user1' ) );
        $this->assertFalse( $userStorage->isBlocked( 'user1', false ) );
        $this->assertFalse($userStorage->getTemporaryBlockTimestamp('user1') );
        $this->assertEquals( '', $userStorage->getSecret( 'user1' ) );
        $this->assertEquals( '', $userStorage->getNotificationType( 'user1' ) );
        $this->assertEquals( '', $userStorage->getNotificationAddress( 'user1' ) );

        // user secret
        $userStorage->setSecret('user1', 'a-secret');
        $this->assertEquals( 'a-secret', $userStorage->getSecret( 'user1' ) );

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
        $this->assertTrue( $userStorage->isBlocked('user1', false ) );

        // Temporary block
        $now_min_5_minutes = time() - 5*60;
        $five_minutes_ago = date("Y-m-d H:i:s", $now_min_5_minutes);
        $userStorage->setTemporaryBlockTimestamp( 'user1', $five_minutes_ago);
        $this->assertEquals( $five_minutes_ago, $userStorage->getTemporaryBlockTimestamp( 'user1' ) );

        // duration is in minutes
        $this->assertFalse( $userStorage->isBlocked('user1', 4) );  // 5 min ago, so block expired
        $this->assertTrue( $userStorage->isBlocked('user1', 10) );  // Block did not expire yet
    }
}