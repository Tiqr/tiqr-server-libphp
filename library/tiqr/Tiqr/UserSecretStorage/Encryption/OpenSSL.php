<?php

/**
 * Class for encrypting/decrypting the user's secret with a symmetric key using PHP's openssl extension
 *
 * openssl is widely available, and is already a dependency of the tiqr library
 *
 * The intended purpose of this class is to encrypt the user's secret before storing it in the UserSecretStorage,
 * allowing you to store the encryption key and the encrypted secrets in different locations so that access to the
 * storage backed, or the backups thereof, does not allow access to the user's secrets.
 *
 *  Along with the encrypted user secret the IV, cipher and key_id are encoded in each ciphertext. This allows you to:
 *  - Change the cipher in the future and still decrypt key stored under the old cipher
 *  - Rotate the key in the future and still decrypt data stored under the old key
 *
 * The intended use is to allow you to update the encryption configuration, and recrypt the data with the new configuration
 * at a later time.
 *
 * SECURITY:
 * The default cipher is AES-128-CBC, which is a secure symmetric cipher that requires a 128-bit (16-byte) key and that
 * should be supported by any openssl version currently in use as it is supported in versions even before openssl 1.0.
 * AES-128-CBC is a good choice for the purpose stated above. However, it is not an authenticated cipher, and CBC is
 * vulnerable to padding oracle attacks. But assuming that the encrypted data is not controllable by an attacker at runtime,
 * this is not a problem.
 *
 * An alternative to AES-128-CBC that does offer authenticated encryption is AES-128-GCM.
 * GCM uses a shorter (96-bit (12-byte) vs 128-bit (16-byte)) IV than AES-128-CBC, and being counter mode cipher, is much
 * more vulnerable to an IV collision. Generally, when a large number of secrets is encrypted under the same key, the
 * probability of an IV collision increases. For a k-bits IV and N secrets the probability of a collision is approximately:
 *     p_collision = 1 - e^(-N^2 / 2*2^k)
 * For a 96-bit IV a 1/10^6 probability of a collision is reached at approximately 400,000,000,000 ( 4.0 * 10^11 )
 * encryptions and a 1/10^9 probability of a collision is reached at approximately 12,600,000,000 (1.3 * 10^10) encryptions.
 * A probability of collisions of 1/2^32 (=~ 1/10^10) (NIST 800-38D) is reached after approximately 6 * 10^9 encryptions.
 * So for practical purposes, it should not matter much.
 *
 * For simplicity, and considering that the encryption is intended to protect data at rest, we chose not to implement
 * additional measures to prevent (key, IV) collisions, like using a KDF to derive the encryption key.
 */
class Tiqr_UserSecretStorage_Encryption_OpenSSL implements Tiqr_UserSecretStorage_Encryption_Interface
{
    private $_cipher;
    private $_key_id;
    private $_keys;

    /* List the supported openssl cipher suites
    * tag: whether the cipher requires an authentication tag
    * key: the key length in bytes
    */

    private $_supportedCiphers = [
        'aes-128-cbc' => [ 'tag' => false, 'key' => 16 ],
        'aes-128-gcm' => [ 'tag' => true, 'key' => 16 ],
        'aes-192-cbc' => [ 'tag' => false, 'key' => 24 ],
        'aes-192-gcm' => [ 'tag' => true, 'key' => 24 ],
        'aes-256-cbc' => [ 'tag' => false, 'key' => 32 ],
        'aes-256-gcm' => [ 'tag' => true, 'key' => 32 ],
        'chacha20' => [ 'tag' => false, 'key' => 32 ],
        'camellia-128-cbc' => [ 'tag' => false, 'key' => 16 ],
        'camellia-192-cbc' => [ 'tag' => false, 'key' => 24 ],
        'camellia-256-cbc' => [ 'tag' => false, 'key' => 32 ],
        'aria-128-cbc' => [ 'tag' => false, 'key' => 16 ],
        'aria-128-gcm' => [ 'tag' => true, 'key' => 16 ],
        'aria-192-cbc' => [ 'tag' => false, 'key' => 24 ],
        'aria-192-gcm' => [ 'tag' => true, 'key' => 24 ],
        'aria-256-cbc' => [ 'tag' => false, 'key' => 32 ],
        'aria-256-gcm' => [ 'tag' => true, 'key' => 32 ],
    ];

    /**
     * Construct an encryption instance.
     *
     * Supported options in $config:
     *   cipher: The openssl cipher suite to use for encryption. This must be a symmetric cipher suite supported by your
     *           version of openssl. The default is AES-128-CBC. Another good option would be AES-128-GCM
     *           The name of the cipher suite is case insensitive.
     *   key_id: The key id to use for encryption. This is used to identify the key to use for encryption.
     *           ked_id is case insensitive.
     *   keys: An array of key_id => key_value of keys to use for encryption and decryption. The key_value is the
     *         hex encoded value of the symmetric encryption key. The key value must be the correct length for the cipher.
     *         E.g. for a 128-bit key, the key_value must be 32 hex characters long.
     *         The key with key_id must appear in this array, additional encryption key can be added to this array to
     *         allow these keys to be used for decryption.
     *         A key_id must not contain the character ':' as this is used as a separator in the ciphertext components.
     *
     * @param array $config The configuration
     * @throws RuntimeException
     */
    public function __construct(Array $config)
    {
        $this->_cipher = strtolower($config['cipher'] ?? 'aes-128-cbc');

        // Check if the cipher is supported by openssl, match case-insensitive because openssl_get_cipher_methods returns
        // the cipher names in different casings, depending on the openssl version
        $opensslSupportedCiphers = array_map('strtolower', openssl_get_cipher_methods());
        if (!in_array($this->_cipher, $opensslSupportedCiphers)) {
            throw new RuntimeException("Cipher '{$this->_cipher}' is not supported by your version of openssl");
        }
        if (!isset($this->_supportedCiphers[$this->_cipher])) {
            throw new RuntimeException("Cipher '{$this->_cipher}' is not supported by this version of the tiqr library");
        }

        $this->_key_id = strtolower($config['key_id'] ?? 'default');
        if (strpos($this->_key_id, ':') !== false) {
            throw new RuntimeException("Key id '{$this->_key_id}' contains invalid character ':'");
        }

        $this->_keys = $config['keys'] ?? [];
    }
    
    /**
     * Encrypts the given data. 
     *
     * @param String $data Data to encrypt.
     * @return string encrypted data
     * @throws RuntimeException
     */
    public function encrypt(string $data) : string
    {
        $iv_length = openssl_cipher_iv_length($this->_cipher);
        // All the supported ciphers use an IV >= 12
        if (($iv_length === false) || ($iv_length < 12)) {
            throw new RuntimeException("Failed to get IV length for cipher '{$this->_cipher}'");
        }
        $iv = Tiqr_Random::randomBytes($iv_length);
        if (!isset($this->_keys[$this->_key_id])) {
            throw new RuntimeException("No key configured for key_id '{$this->_key_id}'");
        }
        @$key = hex2bin($this->_keys[$this->_key_id]);
        if ($key === false) {
            throw new RuntimeException("Error decoding key with key_id '{$this->_key_id}'");
        }

        // If the passphrase (key) to openssl_encrypt is shorter than expected, it is silently padded with NUL characters;
        // if the passphrase is longer than expected, it is silently truncated.
        // openssl_cipher_key_length() requires PHP >= 8.2, so we use a lookup table instead
        // A longer key is not a problem, but could indicate a configuration error
        $key_length = $this->_supportedCiphers[$this->_cipher]['key'];
        if (strlen($key) != $key_length) {
            throw new RuntimeException("Invalid length of key with key_id '{$this->_key_id}' used with cipher '{$this->_cipher}', expected {$key_length} bytes, got " . strlen($key) . " bytes");
        }

        // openssl_encrypt returns the ciphertext as a base64 encoded string, so we don't need to encode it again
        // The tag is returned as a binary string, but only if the cipher requires a tag
        $tag='';
        if ($this->_supportedCiphers[$this->_cipher]['tag']) {
            $encrypted = openssl_encrypt($data, $this->_cipher, $key, 0, $iv, $tag, '', 16);
        } else {
            $encrypted = openssl_encrypt($data, $this->_cipher, $key, 0, $iv);
        }
        if ($encrypted === false) {
            throw new RuntimeException("Error encrypting data");
        }
        $tag = $this->_supportedCiphers[$this->_cipher]['tag'] ? $tag : '';
        // Return the encoded ciphertext, including the IV, tag and cipher
        // <cipher>:<key_id>:iv<>:<tag>:<ciphertext>
        $encoded = $this->_cipher . ":" . $this->_key_id . ":" . base64_encode($iv) . ":" . base64_encode($tag) . ":" . $encrypted;

        return $encoded;
    }
    
    /**
      * Decrypts the given data.
     *
     * @param string $data Data to decrypt.
     * @return string decrypted data
     * @throws RuntimeException
     */
    public function decrypt(string $data) : string
    {
        // Split the encoded data into its components
        // <cipher>:<key_id>:<iv>:<tag>:<ciphertext>
        $split_data = explode(':', $data);
        if (count($split_data) != 5) {
            throw new RuntimeException("Invalid ciphertext format");
        }

        // Cipher
        $cipher = strtolower($split_data[0]);
        $supportedCiphers = array_map('strtolower', openssl_get_cipher_methods());
        if (!in_array($cipher, $supportedCiphers)) {
            throw new RuntimeException("Cipher '$cipher' is not supported by your version of openssl");
        }

        // Key id
        $key_id = strtolower($split_data[1]);
        if (!isset($this->_keys[$key_id])) {
            throw new RuntimeException("No key configured for key_id '$key_id'");
        }
        @$key = hex2bin($this->_keys[$key_id]);
        if ($key === false) {
            throw new RuntimeException("Error decoding key with key_id '$key_id'");
        }

        // IV
        $iv = base64_decode($split_data[2],true);
        if ($iv === false) {
            throw new RuntimeException("Error decoding IV");
        }

        // Tag
        $tag = base64_decode($split_data[3],true);
        if ($tag === false) {
            throw new RuntimeException("Error decoding tag");
        }
        $ciphertext = $split_data[4];

        $plaintext=openssl_decrypt($ciphertext, $cipher, $key, 0, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException("Error decrypting data");
        }

        return $plaintext;
    }

    public function get_type() : string
    {
        return 'openssl';
    }
}
