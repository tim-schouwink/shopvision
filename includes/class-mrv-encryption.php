<?php
/**
 * Encryption helper - Securely encrypt/decrypt sensitive data like API keys
 *
 * Uses AES-256-CBC encryption with WordPress AUTH_KEY and AUTH_SALT as the key.
 * This ensures the encrypted data is unique per WordPress installation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Encryption {

    /**
     * Encryption method
     */
    private const METHOD = 'aes-256-cbc';

    /**
     * Prefix to identify encrypted values
     */
    private const ENCRYPTED_PREFIX = 'mrv_enc_v1:';

    /**
     * Encrypt a value
     *
     * @param string $value Plain text value to encrypt
     * @return string Encrypted value (base64 encoded with prefix)
     */
    public static function encrypt(string $value): string {
        if (empty($value)) {
            return '';
        }

        // Don't double-encrypt
        if (self::is_encrypted($value)) {
            return $value;
        }

        $key = self::get_encryption_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt(
            $value,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            // Fallback: return original value if encryption fails
            return $value;
        }

        // Combine IV and encrypted data, then base64 encode
        $combined = $iv . $encrypted;

        return self::ENCRYPTED_PREFIX . base64_encode($combined);
    }

    /**
     * Decrypt a value
     *
     * @param string $value Encrypted value (base64 encoded with prefix)
     * @return string Decrypted plain text value
     */
    public static function decrypt(string $value): string {
        if (empty($value)) {
            return '';
        }

        // If not encrypted (legacy plain text), return as-is
        if (!self::is_encrypted($value)) {
            return $value;
        }

        // Remove prefix
        $value = substr($value, strlen(self::ENCRYPTED_PREFIX));

        // Decode base64
        $combined = base64_decode($value);
        if ($combined === false) {
            return '';
        }

        $key = self::get_encryption_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);

        // Extract IV and encrypted data
        $iv = substr($combined, 0, $iv_length);
        $encrypted = substr($combined, $iv_length);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return '';
        }

        return $decrypted;
    }

    /**
     * Check if a value is encrypted
     *
     * @param string $value Value to check
     * @return bool True if encrypted
     */
    public static function is_encrypted(string $value): string {
        return strpos($value, self::ENCRYPTED_PREFIX) === 0;
    }

    /**
     * Get the encryption key derived from WordPress salts
     *
     * @return string 32-byte encryption key
     */
    private static function get_encryption_key(): string {
        // Use WordPress AUTH_KEY and AUTH_SALT for maximum entropy
        $key_material = '';

        if (defined('AUTH_KEY')) {
            $key_material .= AUTH_KEY;
        }
        if (defined('AUTH_SALT')) {
            $key_material .= AUTH_SALT;
        }

        // Fallback if salts are not defined (shouldn't happen in production)
        if (empty($key_material)) {
            $key_material = ABSPATH . DB_NAME . DB_USER;
        }

        // Create a 32-byte key using SHA-256
        return hash('sha256', $key_material, true);
    }

    /**
     * Migrate a plain text option to encrypted
     *
     * @param string $option_name The option name to migrate
     * @return bool True if migration was performed
     */
    public static function migrate_option(string $option_name): bool {
        $value = get_option($option_name, '');

        if (empty($value)) {
            return false;
        }

        // Already encrypted
        if (self::is_encrypted($value)) {
            return false;
        }

        // Encrypt and save
        $encrypted = self::encrypt($value);
        update_option($option_name, $encrypted);

        return true;
    }
}
