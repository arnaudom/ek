<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Update\EncryptExistingMessages.
 */

namespace Drupal\ek_messaging\Update;

use Drupal\Core\Database\Database;

/**
 * Batch operation: encrypt all existing plaintext messages in ek_messaging_text.
 *
 * Detection of plaintext vs encrypted rows
 * -----------------------------------------
 * An encrypted envelope always starts with a base64-encoded block whose
 * decoded length is at least IV(12) + TAG(16) + 1 = 29 bytes, i.e. the
 * base64 string is at least 40 characters long AND decodes to valid bytes.
 * Plaintext HTML will virtually never satisfy those constraints, so we
 * attempt to detect and skip already-encrypted rows gracefully.
 *
 * This batch can be run multiple times safely.
 */
class EncryptExistingMessages {

    /**
     * Batch process callback.
     *
     * @param array &$context
     *   Batch context.
     */
    public static function batchProcess(array &$context): void {
        $db = Database::getConnection('external_db', 'external_db');
        /** @var \Drupal\ek_messaging\Service\MessageEncryptionService $encryption */
        $encryption = \Drupal::service('ek_messaging.encryption');

        if (!$encryption->isEnabled()) {
            $context['results']['skipped_no_key'] = true;
            $context['finished'] = 1;
            return;
        }

        // Initialise sandbox on first call.
        if (!isset($context['sandbox']['max'])) {
            $context['sandbox']['max']     = (int) $db->select('ek_messaging_text', 't')
                ->countQuery()->execute()->fetchField();
            $context['sandbox']['current'] = 0;
            $context['results']['encrypted'] = 0;
            $context['results']['skipped']   = 0;
            $context['results']['errors']    = 0;

            if ($context['sandbox']['max'] === 0) {
                $context['finished'] = 1;
                return;
            }
        }

        // Process 50 rows per batch chunk.
        $limit  = 50;
        $rows   = $db->select('ek_messaging_text', 't')
            ->fields('t', ['id', 'text'])
            ->orderBy('t.id', 'ASC')
            ->range($context['sandbox']['current'], $limit)
            ->execute();

        foreach ($rows as $row) {
            $context['sandbox']['current']++;

            // Skip rows that look already-encrypted.
            if ($row->text == null || static::looksEncrypted($row->text)) {
                $context['results']['skipped']++;
                continue;
            }

            try {
                $encrypted = $encryption->encrypt($row->text);
                $db->update('ek_messaging_text')
                    ->condition('id', $row->id)
                    ->fields(['text' => $encrypted])
                    ->execute();
                $context['results']['encrypted']++;
            }
            catch (\Throwable $e) {
                $context['results']['errors']++;
                \Drupal::logger('ek_messaging')->error(
                    'Failed to encrypt message id @id: @msg',
                    ['@id' => $row->id, '@msg' => $e->getMessage()]
                );
            }
        }

        $context['message'] = t(
            'Processed @cur of @max messages…',
            ['@cur' => $context['sandbox']['current'], '@max' => $context['sandbox']['max']]
        );

        $context['finished'] = $context['sandbox']['current'] / $context['sandbox']['max'];
    }

    /**
     * Batch finished callback.
     */
    public static function batchFinished(bool $success, array $results, array $operations): void {
        if (!$success) {
            \Drupal::messenger()->addError(t('Batch did not complete successfully.'));
            return;
        }
        if (!empty($results['skipped_no_key'])) {
            \Drupal::messenger()->addError(
                t('Migration aborted: no encryption key is configured. Configure a key first.')
            );
            return;
        }
        \Drupal::messenger()->addStatus(t(
            'Encryption migration complete. Encrypted: @enc | Already encrypted (skipped): @skip | Errors: @err',
            [
                '@enc'  => $results['encrypted'] ?? 0,
                '@skip' => $results['skipped']   ?? 0,
                '@err'  => $results['errors']    ?? 0,
            ]
        ));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Heuristic: returns TRUE if the stored value looks like an AES-256-GCM
     * envelope (base64 of at least 29 bytes).
     */
    protected static function looksEncrypted(string $value): bool {
        if (strlen($value) < 40) {
            return false;
        }
        $raw = base64_decode($value, true);
        // Minimum: 12 (IV) + 16 (tag) + 1 (ciphertext byte) = 29 bytes
        return $raw !== false && strlen($raw) >= 29;
    }
}