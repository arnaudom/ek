<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Service\MessageRegistrationService.
 */

namespace Drupal\ek_messaging\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\ek_admin\Service\WebhookServiceInterface;
use Drupal\user\Entity\User;

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

    /**
     * @var \Drupal\ek_admin\Service\WebhookServiceInterface
     */
    protected $webhook;

    public function __construct(
        Connection $database,
        MessageEncryptionService $encryption,
        WebhookServiceInterface $webhook
    ) {
        $this->database = $database;
        $this->encryption = $encryption;
        $this->webhook = $webhook;
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

        // Init webhook for each recipient if webhook_data is provided.
        if (isset($data['webhook_data']) && is_array($data['webhook_data'])) {
            $this->initWebhooksForRecipients(
                (int) $data['uid'],
                $inbox ?? $data['to'],
                $data['webhook_data']
            );
        }

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

    /**
     * Queue webhooks for each active recipient of the message.
     *
     * @param int $from_uid
     *   The sender user ID.
     * @param string $inbox
     *   Comma-separated recipient UIDs (e.g. ",3,5,").
     * @param array $webhook_data
     *   Base payload data to include in each webhook.
     */
    protected function initWebhooksForRecipients(int $from_uid, string $inbox, array $webhook_data): void {
        $recipientUids = $this->parseRecipients($inbox);
        if (empty($recipientUids)) {
            return;
        }

        foreach (User::loadMultiple($recipientUids) as $account) {
            if ($account->isActive()) {
                $this->webhook->queueWebhook($account->id(), 'message_received', array_merge([
                    'route' => 'ek-messaging',
                    'from_uid' => $from_uid,
                ], $webhook_data));
            }
        }
    }

    /**
     * Extract numeric user IDs from a comma-separated inbox string.
     *
     * @param string $inbox
     *   Comma-separated UIDs, e.g. ",3,5,".
     *
     * @return int[]
     *   Array of unique integer UIDs.
     */
    protected function parseRecipients(string $inbox): array {
        $uids = array_filter(array_map('intval', explode(',', $inbox)));
        return array_unique(array_filter($uids));
    }
}