<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Service\MessageEncryptionService.
 */

namespace Drupal\ek_messaging\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Provides AES-256-GCM encryption/decryption for message bodies.
 *
 * Key management strategy
 * -----------------------
 * The encryption key is stored in Drupal's configuration system under
 * ek_messaging.settings:encryption_key.  That config object should be
 * exported to the site's config/sync directory so it is kept out of the
 * database.  For production sites the recommended approach is to store the
 * key value in the environment and inject it via $config overrides in
 * settings.php:
 *
 *   $config['ek_messaging.settings']['encryption_key'] = getenv('EK_MSG_KEY');
 *
 * This keeps the key out of both the codebase and the database.  The key
 * must be a 32-byte (256-bit) value encoded as a base64 string.
 *
 * Algorithm: AES-256-GCM
 *   - Authenticated encryption: guarantees both confidentiality and integrity.
 *   - A unique random 12-byte IV (nonce) is generated for every message.
 *   - The 16-byte authentication tag is stored alongside the ciphertext.
 *   - Stored format: base64( iv[12] . tag[16] . ciphertext )
 */
class MessageEncryptionService {

    const CIPHER     = 'aes-256-gcm';
    const IV_LENGTH  = 12;   // 96-bit nonce – recommended for GCM
    const TAG_LENGTH = 16;   // 128-bit authentication tag

    /**
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var string|null  Raw 32-byte key (lazy-loaded).
     */
    private $rawKey = null;

    public function __construct(
        LoggerChannelFactoryInterface $logger_factory
    ) {
        $this->logger = $logger_factory->get('ek_messaging');
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Encrypts a plaintext message body.
     *
     * @param string $plaintext
     *   The raw message body (may contain HTML).
     *
     * @return string
     *   Base64-encoded ciphertext envelope, or the original plaintext if
     *   encryption is disabled / key is missing (with a logged warning).
     *
     * @throws \RuntimeException
     *   When OpenSSL encryption fails unexpectedly.
     */
    public function encrypt(string $plaintext): string {
        $key = $this->getKey();
        if ($key === null) {
            $this->logger->warning('ek_messaging: encryption key not configured - message stored in plaintext.');
            return $plaintext;
        }

        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('ek_messaging: openssl_encrypt() failed: ' . openssl_error_string());
        }

        // Pack IV + tag + ciphertext then base64-encode for safe DB storage.
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts a ciphertext envelope previously produced by ::encrypt().
     *
     * @param string $stored
     *   The value as retrieved from the database.
     *
     * @return string
     *   Decrypted plaintext, or the original value if it does not look like
     *   an encrypted envelope (backwards-compatible with legacy plaintext rows).
     *
     * @throws \RuntimeException
     *   When decryption or authentication fails (tampered data).
     */
    public function decrypt(string $stored): string {
        $key = $this->getKey();
        if ($key === null) {
            return $stored;
        }

        // Detect legacy plaintext rows – they won't be valid base64 payloads
        // of the minimum expected length.
        $raw = base64_decode($stored, true);
        $minLen = self::IV_LENGTH + self::TAG_LENGTH + 1;
        if ($raw === false || strlen($raw) < $minLen) {
            // Treat as unencrypted legacy data.
            return $stored;
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('ek_messaging: decryption failed - data may be tampered or the key has changed.');
        }

        return $plaintext;
    }

    /**
     * Returns TRUE when an encryption key is available.
     */
    public function isEnabled(): bool {
        return $this->getKey() !== null;
    }

    /**
     * Generates a new cryptographically-strong key and returns it as base64.
     * Useful for the settings form "Generate key" button.
     */
    public static function generateKey(): string {
        return base64_encode(random_bytes(32));
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the raw 32-byte key, or NULL if none is configured.
     */
    protected function getKey(): ?string {
        /** @var \Drupal\key\KeyRepositoryInterface $keyRepo */
        $keyRepo = \Drupal::service('key.repository');
        $keyEntity = $keyRepo->getKey('ek_messaging_key');

        if ($keyEntity === null) {
            $this->logger->warning('ek_messaging: Key "ek_messaging_key" not found in Key module – messages stored in plaintext.');
            return null;
        }

        $raw = $keyEntity->getKeyValue();

        if ($raw === false || strlen($raw) !== 32) {
            $this->logger->error('ek_messaging: Key "ek_messaging_key" must be exactly 32 raw bytes (AES-256). Check your Key module configuration.');
            return null;
        }

        $this->rawKey = $raw;
        return $this->rawKey;
    }
}