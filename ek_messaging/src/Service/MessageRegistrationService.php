<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Service\MessageRegistrationService.
 */

namespace Drupal\ek_messaging\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;

/**
 * Service replacement for the procedural ek_message_register() helper.
 *
 * Usage (from a class that is dependency-injected):
 * @code
 *   $id = $this->messageRegistration->register([...]);
 * @endcode
 *
 * Usage from procedural code (module hooks, legacy callers):
 * @code
 *   $id = \Drupal::service('ek_messaging.message_registration')->register([...]);
 * @endcode
 */
class MessageRegistrationService {

    /**
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * @var \Drupal\ek_messaging\Service\MessageEncryptionService
     */
    protected $encryption;

    public function __construct(
        Connection $database,
        MessageEncryptionService $encryption
    ) {
        $this->database = $database;
        $this->encryption = $encryption;
    }

    /**
     * Inserts a new message into the database, encrypting the body.
     *
     * @param array $data
     *   Keys:
     *   - uid        (int)    Sender user ID.
     *   - to         (string) Comma-separated recipient UIDs, e.g. ",3,5,".
     *   - to_group   (int)    Group target (0 = none).
     *   - type       (int)    Message type.
     *   - status     (string) Comma-separated UIDs who have read the message.
     *   - inbox      (string) Comma-separated UIDs whose inbox contains it.
     *   - archive    (string) Comma-separated UIDs who archived it.
     *   - subject    (string) Already-sanitised subject line.
     *   - body       (string) Serialized or raw message body HTML.
     *   - format     (string) Text format machine name.
     *   - priority   (int)    1=high, 2=normal, 3=low.
     *
     * @return int
     *   The new message ID.
     */
    public function register(array $data): int {
        // Determine the plain-text body from whatever was passed in.
        // Legacy callers pass serialize($value); newer callers may pass the
        // raw string directly.  We normalise to a plain string here.
        $body = $this->resolveBody($data['body']);

        // Encrypt before storage.
        $encryptedBody = $this->encryption->encrypt($body);

        $connection = $this->getExternalDb();

        // Insert envelope row.
        $messageId = (int) $connection->insert('ek_messaging')
            ->fields([
                'stamp'    => time(),
                'from_uid' => (int) $data['uid'],
                'to'       => $data['to'],
                'to_group' => (int) $data['to_group'],
                'type'     => (int) $data['type'],
                'status'   => $data['status'] ?? '',
                'inbox'    => $data['inbox'] ?? $data['to'],
                'archive'  => $data['archive'] ?? '',
                'subject'  => $data['subject'],
                'priority' => (int) ($data['priority'] ?? 2),
            ])
            ->execute();

        // Insert body row.
        $connection->insert('ek_messaging_text')
            ->fields([
                'id'     => $messageId,
                'text'   => $encryptedBody,
                'format' => $data['format'] ?? 'basic_html',
            ])
            ->execute();

        // Invalidate inbox caches.
        Cache::invalidateTags(['ek_message_inbox']);
        Cache::invalidateTags(['config:system.menu.tools']);

        return $messageId;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the body to a plain string regardless of how it was supplied.
     */
    protected function resolveBody($body): string {
        if (!is_string($body)) {
            return (string) $body;
        }
        // Legacy code serialised the value before passing it in; unserialize
        // if the string looks like a PHP serialised value.
        if (str_starts_with($body, 's:') || str_starts_with($body, 'a:')) {
            $unserialized = @unserialize($body);
            if ($unserialized !== false) {
                return (string) $unserialized;
            }
        }
        return $body;
    }

    /**
     * Returns the external DB connection.
     */
    protected function getExternalDb(): Connection {
        return \Drupal\Core\Database\Database::getConnection('external_db', 'external_db');
    }
}