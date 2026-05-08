<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Service\WebhookServiceInterface.
 */

namespace Drupal\ek_admin\Service;

/**
 * Interface for WebhookService.
 */
interface WebhookServiceInterface {

    /**
     * Queue a webhook event if the target UID matches configured settings.
     *
     * @param int $uid
     *   The target user ID (e.g., message recipient).
     * @param string $event
     *   The event identifier (e.g., 'message_received').
     * @param array $data
     *   The payload data to include in the webhook.
     *
     * @return bool
     *   TRUE if the webhook was queued, FALSE otherwise.
     */
    public function queueWebhook(int $uid, string $event, array $data): bool;

    /**
     * Process a single queued webhook item by sending the HTTP request.
     *
     * @param array $item
     *   Queue item data containing 'event' and 'payload'.
     * @param string|null $url
     *   The webhook URL. If NULL, will be fetched from GlobalSettings.
     * @param string|null $secret
     *   The HMAC secret key. If NULL, will be fetched from GlobalSettings.
     *
     * @return bool
     *   TRUE on success, FALSE on failure.
     */
    public function processWebhook(array $item, ?string $url = null, ?string $secret = null): bool;

}