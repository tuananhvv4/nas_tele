<?php
/**
 * Encryption Helper for Sensitive Data
 * Uses AES-256-CBC encryption
 */

// Encryption key - CHANGE THIS to a random 32-character string
define('ENCRYPTION_KEY', 'your-secret-key-change-this-32ch');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Encrypt a string
 */
function encryptPassword($plaintext) {
    if (empty($plaintext)) {
        return '';
    }
    
    $key = hash('sha256', ENCRYPTION_KEY);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    
    $encrypted = openssl_encrypt($plaintext, ENCRYPTION_METHOD, $key, 0, $iv);
    
    // Combine IV and encrypted data
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a string
 */
function decryptPassword($encrypted) {
    if (empty($encrypted)) {
        return '';
    }
    
    $key = hash('sha256', ENCRYPTION_KEY);
    $data = base64_decode($encrypted);
    
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $iv_length);
    $encrypted_data = substr($data, $iv_length);
    
    return openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, $key, 0, $iv);
}
