<?php

class CustomEncryptionClass implements Tiqr_UserSecretStorage_Encryption_Interface
{

    public function __construct(array $options = array())
    {
        // Check passing options work
        if (!isset($options['my_custom_option'])) {
            throw new RuntimeException("Missing option 'my_custom_option'");
        }
        if ($options['my_custom_option'] != 'my_custom_value') {
            throw new RuntimeException("Missing value for 'my_custom_option'");
        }
    }

    public function encrypt(string $data): string
    {
        return $data;
    }

    public function get_type(): string
    {
        return 'CustomEncryptionClass';
    }

    public function decrypt(string $data): string
    {
        return $data;
    }
}