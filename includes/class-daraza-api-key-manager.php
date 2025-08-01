<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Daraza_API_Key_Manager
{
    private const ENCRYPTION_METHOD = 'aes-256-gcm'; // Use GCM mode for better security
    private const KEY_OPTION_NAME = 'daraza_api_key_encrypted';
    private const KEY_VERSION_OPTION = 'daraza_api_key_version';
    private const KEY_EXPIRY_OPTION = 'daraza_api_key_expiry';
    private const KEY_ROTATION_DAYS = 90; // Rotate keys every 90 days
    private const KEY_LENGTH_MIN = 32;
    private const KEY_LENGTH_MAX = 128;

    /**
     * Get the encryption key
     */
    private static function get_encryption_key()
    {
        $key = wp_salt('auth');
        return substr(hash('sha256', $key, true), 0, 32);
    }

    /**
     * Get the initialization vector
     */
    private static function get_iv()
    {
        $iv = wp_salt('secure_auth');
        return substr(hash('sha256', $iv, true), 0, 16);
    }

    /**
     * Encrypt the API key
     */
    public static function encrypt_api_key($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        // Validate key before encryption
        if (!self::validate_api_key($api_key)) {
            return false;
        }

        $key = self::get_encryption_key();
        $iv = self::get_iv();

        // Use GCM mode for better security
        $tag = '';
        $encrypted = openssl_encrypt(
            $api_key,
            self::ENCRYPTION_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            return false;
        }

        // Combine IV, encrypted data, and tag
        $combined = $iv . $tag . $encrypted;
        return base64_encode($combined);
    }

    /**
     * Decrypt the API key
     */
    public static function decrypt_api_key($encrypted_api_key)
    {
        if (empty($encrypted_api_key)) {
            return false;
        }

        $combined = base64_decode($encrypted_api_key);
        if ($combined === false) {
            return false;
        }

        // Extract IV, tag, and encrypted data
        $iv = substr($combined, 0, 16);
        $tag = substr($combined, 16, 16);
        $encrypted = substr($combined, 32);

        $key = self::get_encryption_key();

        $decrypted = openssl_decrypt(
            $encrypted,
            self::ENCRYPTION_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? $decrypted : false;
    }

    /**
     * Save the API key with encryption
     */
    public static function save_api_key($api_key)
    {
        if (!self::validate_api_key($api_key)) {
            return new WP_Error('invalid_api_key', __('Invalid API key format.', 'daraza-payments'));
        }

        $encrypted = self::encrypt_api_key($api_key);
        if ($encrypted === false) {
            return new WP_Error('encryption_failed', __('Failed to encrypt API key.', 'daraza-payments'));
        }

        // Save encrypted key
        $result = update_option(self::KEY_OPTION_NAME, $encrypted);
        if (!$result) {
            return new WP_Error('save_failed', __('Failed to save API key.', 'daraza-payments'));
        }

        // Update version and expiry
        $version = get_option(self::KEY_VERSION_OPTION, 0) + 1;
        update_option(self::KEY_VERSION_OPTION, $version);
        update_option(self::KEY_EXPIRY_OPTION, time() + (self::KEY_ROTATION_DAYS * DAY_IN_SECONDS));

        // Log the key update (without exposing the key)
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('Daraza API key updated', ['source' => 'daraza-payments']);
        }

        return true;
    }

    /**
     * Get the API key (decrypted)
     */
    public static function get_api_key()
    {
        $encrypted = get_option(self::KEY_OPTION_NAME);
        if (empty($encrypted)) {
            return false;
        }

        return self::decrypt_api_key($encrypted);
    }

    /**
     * Validate API key format
     */
    public static function validate_api_key($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        // Check length
        if (strlen($api_key) < self::KEY_LENGTH_MIN || strlen($api_key) > self::KEY_LENGTH_MAX) {
            return false;
        }

        // Basic format validation - alphanumeric and some special characters
        if (!preg_match('/^[a-zA-Z0-9\-_]{' . self::KEY_LENGTH_MIN . ',' . self::KEY_LENGTH_MAX . '}$/', $api_key)) {
            return false;
        }

        // Additional validation can be added here
        // For example, checking the key against a test endpoint

        return true;
    }

    /**
     * Check if API key needs rotation
     */
    public static function needs_rotation()
    {
        $expiry = get_option(self::KEY_EXPIRY_OPTION);
        if (empty($expiry)) {
            return true;
        }

        // Check if key is expired or will expire in the next 7 days
        return time() >= ($expiry - (7 * DAY_IN_SECONDS));
    }

    /**
     * Get API key metadata
     */
    public static function get_key_metadata()
    {
        return [
            'version' => get_option(self::KEY_VERSION_OPTION, 0),
            'expiry' => get_option(self::KEY_EXPIRY_OPTION, 0),
            'needs_rotation' => self::needs_rotation(),
            'days_until_expiry' => self::get_days_until_expiry(),
            'is_configured' => !empty(self::get_api_key())
        ];
    }

    /**
     * Get days until API key expiry
     */
    private static function get_days_until_expiry()
    {
        $expiry = get_option(self::KEY_EXPIRY_OPTION);
        if (empty($expiry)) {
            return 0;
        }

        $days = ceil(($expiry - time()) / DAY_IN_SECONDS);
        return max(0, $days);
    }

    /**
     * Delete the API key
     */
    public static function delete_api_key()
    {
        delete_option(self::KEY_OPTION_NAME);
        delete_option(self::KEY_VERSION_OPTION);
        delete_option(self::KEY_EXPIRY_OPTION);

        // Log the deletion
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('Daraza API key deleted', ['source' => 'daraza-payments']);
        }

        return true;
    }

    /**
     * Test API key validity
     */
    public static function test_api_key($api_key = null)
    {
        if ($api_key === null) {
            $api_key = self::get_api_key();
        }

        if (empty($api_key)) {
            return false;
        }

        // Basic format validation
        if (!self::validate_api_key($api_key)) {
            return false;
        }

        // You could add a test API call here to verify the key works
        // For now, we'll just return true if the format is valid
        return true;
    }
}
