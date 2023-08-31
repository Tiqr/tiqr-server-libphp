<?php

require_once 'tiqr_autoloader.inc';

require_once 'CustomEncryptionClass.inc';

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class Tiqr_UserSecretStorageTest extends TestCase
{
    private $temp_dir;

    public function setUp(): void
    {
        parent::setUp();
        $this->temp_dir = sys_get_temp_dir() . '/tiqr_user_secret_storage_test'.bin2hex(random_bytes(8));
        mkdir($this->temp_dir);
    }

    /**
     * @dataProvider provideValidFactoryTypes
     */
    public function test_it_can_create_user_sercret_storage($type, $expectedInstanceOf)
    {
        $allOptions = [
            'path' => $this->temp_dir,
            'dsn' => "sqlite:/{$this->temp_dir}/db.sqlite",
            'username'=> 'user',
            'password'=>'secret',
            'apiURL'=>'',
            'consumerKey' => 'key'
        ];
        $userSecretStorage = Tiqr_UserSecretStorage::getSecretStorage(
            $type,
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $allOptions
        );
        $this->assertInstanceOf($expectedInstanceOf, $userSecretStorage);
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Interface::class, $userSecretStorage);
    }

    /**
     * Setup a user secret storage instance of file or pdo type in a temporary directory
     * During a test, the user secret storage instance can be recreated with the same type and options, and it will return
     * the same data
     * @param string $type: file or pdo
     * @param array $encryption_options: encryption/decryption options
     * @param array $raw_user_secret: raw user secret data, array(userid=>secret), this is used to initialize the storage
     *                                directly, without using the setSecret() method so it bypasses the encryption of the
     *                                secret and the prefixing of the secret with the encryption type
     *
     * @return Tiqr_UserSecretStorage_Interface
     */
    private function setup_userSecretStorage(string $type, array $encryption_options = array(), array $raw_user_secret = array()) : Tiqr_UserSecretStorage_Interface
    {
        switch ($type) {
            case 'pdo':
                $encryption_options['dsn'] = "sqlite://{$this->temp_dir}/db.sqlite";
                $encryption_options['username'] = 'user';
                $encryption_options['password'] = 'secret';

                $pdo = new PDO($encryption_options['dsn'],
                    null,
                    null,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                // If required, init schema
                $pdo->exec("CREATE TABLE IF NOT EXISTS tiqrusersecret (userid varchar(255) PRIMARY KEY, secret varchar(255));");

                foreach ($raw_user_secret as $user => $secret) {
                    $s = $pdo->prepare("REPLACE INTO tiqrusersecret (userid, secret) VALUES (:user, :secret);");
                    $s->bindParam(':user', $user);
                    $s->bindParam(':secret', $secret);
                    $s->execute();
                }
                break;

            case 'file':
                $encryption_options['path'] = $this->temp_dir;

                foreach ($raw_user_secret as $user => $secret) {
                    $filename = $this->temp_dir . '/' . $user;
                    file_put_contents($filename . '.json', json_encode(['secret' => $secret]));
                }
                break;

            default:
                throw new RuntimeException("Unknown type: $type");
        }

        return Tiqr_UserSecretStorage::getSecretStorage(
            $type,
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $encryption_options
        );
    }

    /**
     * @dataProvider provideUserSecretStorageTypes
     */
    public function test_it_can_use_user_secret_storage_with_openssl_encryption(string $type)
    {
        // Test UserSecretStorage with openssl encryption options
        $options_enc = [
                'encryption' => [
                    'type' => 'openssl',
                    'cipher' => 'aes-128-cbc',
                    'key_id' => 'test_key',
                    'keys' => [
                        'test_key' => '0102030405060708090a0b0c0d0e0f10'
                    ]
                ]
            ];
        $userSecretStorage_enc = $this->setup_userSecretStorage($type, $options_enc);

        // Create user & secret
        $user='test-user-001';
        $userSecret='3132333435363738393a3b3c3d3e3f404142434445464748494A4B4C4D4E4F50';
        $userSecretStorage_enc->setSecret($user, $userSecret);
        // Read the secret back
        $this->assertEquals($userSecret, $userSecretStorage_enc->getSecret($user));

        // Read the secret back, using the wrong key, this must fail
        // Because we use CBC, the decryption may still succeed, but the decrypted result will be wrong.
        // Whether this happens depends on the IV because that is the only random part in this encryption
        $options_enc['encryption']['keys']['test_key'] = 'fa11fa11fa11fa11fa11fa11fa11fa11';
        $userSecretStorage_enc = $this->setup_userSecretStorage($type, $options_enc);
        $decrypted = 'failed';
        try {
            $decrypted = $userSecretStorage_enc->getSecret($user);
        } catch (Exception $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
            $this->assertEquals('Error decrypting data', $e->getMessage());
        }
        $this->assertNotEquals($userSecret, $decrypted);


        // Decrypt the same secret again, but this time using the decryption array to
        // specify the openssl encryption instance
        $options_dec = [
                'encryption' => [
                    'type' => 'dummy'
                ],

                'decryption' => [
                    'CustomEncryptionClass' => [
                        'my_custom_option' => 'my_custom_value'
                    ],
                    'openssl' => [
                        'cipher' => 'aes-128-cbc',
                        'key_id' => 'test_key',
                        'keys' => [
                            'test_key' => '0102030405060708090a0b0c0d0e0f10'
                        ]
                    ]
                ]
            ];

        $userSecretStorage_dec = $this->setup_userSecretStorage($type, $options_dec);
        $this->assertEquals($userSecret, $userSecretStorage_dec->getSecret($user));


        // Check that we cannot decrypt when we don't specify openssl encryption/decryption options
        $userSecretStorage_none = $this->setup_userSecretStorage($type, array());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Secret for user '$user' is encrypted with an unsupported encryption type");
        $userSecretStorage_none->getSecret($user);
    }

    /**
     * @dataProvider provideUserSecretStorageTypes
     */
    public function test_reading_secret_without_prefix(string $type)
    {
        $user='test-user-002';
        $secret1='404142434445464748494A4B4C4D4E4F503132333435363738393a3b3c3d3e3f';
        $userSecretStorage = $this->setup_userSecretStorage($type, array(), array($user => $secret1));

        // Read the secret back, because the secret is not prefixed with the encryption type, it is assumed to be unencrypted
        // and is returned as-is
        $this->assertEquals($secret1, $userSecretStorage->getSecret($user));

        // Overwrite secret, and check that we can read it back
        $secret2='3132333435363738393a3b3c3d3e3f404142434445464748494A4B4C4D4E4F50';
        $userSecretStorage->setSecret($user, $secret2);
        $this->assertEquals($secret2, $userSecretStorage->getSecret($user));
    }

    public function provideUserSecretStorageTypes()
    {
        yield 'file' => ['file'];
        yield 'pdo' => ['pdo'];
    }

    public function test_it_can_not_create_storage_by_fqn_storage()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to create a UserSecretStorage instance of type: Fictional_Service_That_Was_Implements_UserSecretStorage.php");
        Tiqr_UserSecretStorage::getSecretStorage(
            'Fictional_Service_That_Was_Implements_UserSecretStorage.php',
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            []
        );
    }

    public function test_it_input_validates_the_configuration_options_for_file()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The path is missing in the UserSecretStorage configuration");
        Tiqr_UserSecretStorage::getSecretStorage(
            'file',
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            []
        );
    }

    public function test_it_can_create_custom_encryption_class()
    {
            $options = array(
                // Encryption configuration
                'encryption' => array(
                    'type' => CustomEncryptionClass::class,
                    'my_custom_option' => 'my_custom_value'
                ),

                // UserSecretStorage configuration
                'path' => $this->temp_dir,
            );
        $userSecretStorage=Tiqr_UserSecretStorage::getSecretStorage(
            'file',
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $options
        );
        $this->assertInstanceOf(Tiqr_UserSecretStorage_Interface::class, $userSecretStorage);
    }

    /**
     * @dataProvider provideInvalidPdoOptions
     */
    public function test_it_input_validates_the_configuration_options_for_pdo($missing, $parameters)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf("The %s is missing in the UserSecretStorage configuration", $missing));
        Tiqr_UserSecretStorage::getSecretStorage(
            'pdo',
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $parameters
        );
    }

    /**
     * @dataProvider provideInvalidOathOptions
     */
    public function test_it_input_validates_the_configuration_options_for_oathserviceclient($missing, $parameters)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf("The %s is missing in the UserSecretStorage configuration", $missing));
        Tiqr_UserSecretStorage::getSecretStorage(
            'oathserviceclient',
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
            $parameters
        );
    }

    public function provideValidFactoryTypes()
    {
        yield ['file', Tiqr_UserSecretStorage_File::class];
        yield ['pdo', Tiqr_UserSecretStorage_Pdo::class];
        yield ['oathserviceclient', Tiqr_UserSecretStorage_OathServiceClient::class];
    }

    public function provideInvalidPdoOptions()
    {
        yield ['dsn', ['username' => 'username', 'password' => 'secret']];
        yield ['username', ['password' => 'secret', 'dsn' => 'sqlite:/foobar.example.com/test.sqlite']];
        yield ['password', ['username' => 'username', 'dsn' => 'sqlite:/foobar.example.com/test.sqlite']];
    }

    public function provideInvalidOathOptions()
    {
        yield ['apiURL', ['consumerKey' => 'secret']];
        yield ['consumerKey', ['apiURL' => 'https://api.example.com']];
    }
}
