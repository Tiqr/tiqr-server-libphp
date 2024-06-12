<?php

use Psr\Log\LoggerInterface;

abstract class Tiqr_UserSecretStorage_Abstract implements Tiqr_UserSecretStorage_Interface, Tiqr_HealthCheck_Interface
{
    protected LoggerInterface $logger;
    private Tiqr_UserSecretStorage_Encryption_Interface $encryption;

    /**
     * @var array() of type_id (prefix) => Tiqr_UserSecretStorage_Encryption_Interface
     */
    private array $decryption;

    public function __construct(LoggerInterface $logger, Tiqr_UserSecretStorage_Encryption_Interface $encryption, array $decryption = array())
    {
        $this->logger = $logger;
        $this->encryption = $encryption;
        $this->decryption = $decryption;
    }

    /**
     * Get the user's secret
     * @param String $userId
     * @return String The user's secret
     * @throws Exception
     */
    abstract protected function getUserSecret(string $userId): string;

    /**
     * Set the user's secret
     *
     * @param String $userId
     * @param String $secret The user's secret
     * @throws Exception
     */
    abstract protected function setUserSecret(string $userId, string $secret): void;


    /**
     * Get the user's secret
     * @param String $userId
     * @return String The user's secret
     * @throws Exception
     */
    public function getSecret(string $userId): string
    {
        $encryptedSecret = $this->getUserSecret($userId);
        $pos = strpos($encryptedSecret, ':');
        if ($pos === false) {
            // If the secret is not prefixed with the encryption type_id, it is assumed to be unencrypted.
            $this->logger->info("Secret for user '$userId' is not prefixed with the encryption type, assuming that it is not encrypted");
            return $encryptedSecret;
        }

        $prefix = substr($encryptedSecret, 0, $pos);
        if ($prefix === $this->encryption->get_type()) {
            // Decrypt the secret if it is prefixed with the current encryption type
            // Remove the encryption type prefix before decrypting
            return $this->encryption->decrypt( substr($encryptedSecret, $pos+1) );
        }

        // Check the decryption array for the encryption type to see if there is an encryption
        // instance defined for it. If so, use that to decrypt the secret.
        if (isset($this->decryption[$prefix])) {
            return $this->decryption[$prefix]->decrypt( substr($encryptedSecret, $pos+1) );
        }

        $this->logger->error("Secret for user '$userId' is encrypted with unsupported encryption type '$prefix'");
        throw new RuntimeException("Secret for user '$userId' is encrypted with an unsupported encryption type");
    }

    /**
     * Store a secret for a user.
     * @param String $userId
     * @param String $secret
     * @throws Exception
     */
    public function setSecret(string $userId, string $secret): void
    {
        $encryptedSecret = $this->encryption->encrypt($secret);
        // Prefix the user secret with the encryption type
        $this->setUserSecret($userId, $this->encryption->get_type() . ':' . $encryptedSecret);
    }

    /**
     * @see Tiqr_HealthCheck_Interface::healthCheck()
     */
    public function healthCheck(string &$statusMessage = ''): bool
    {
        return true;    // Health check is always successful when not implemented
    }
}
