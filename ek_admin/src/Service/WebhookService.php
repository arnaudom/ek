<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Service\WebhookService.
 */

namespace Drupal\ek_admin\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\ek_admin\GlobalSettings;

/**
 * Queue-based webhook service for triggering external webhooks on events.
 *
 * Usage:
 *   $webhook = \Drupal::service('ek_admin.webhook');
 *   $webhook->queueWebhook(2, 'message_received', ['message_id' => 123]);
 */
class WebhookService implements WebhookServiceInterface {

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
    protected $queueFactory;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
    protected $httpClient;

    /**
     * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
     */
    protected $loggerFactory;

  /**
   * The queue name for webhook items.
   */
    const QUEUE_NAME = 'ek_admin_webhook';

  /**
   * Constructs a WebhookService object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
    public function __construct(
        QueueFactory $queue_factory,
        ClientInterface $http_client,
        LoggerChannelFactoryInterface $logger_factory
    ) {
        $this->queueFactory = $queue_factory;
        $this->httpClient = $http_client;
        $this->loggerFactory = $logger_factory;
    }

  /**
   * {@inheritdoc}
   */
    public function queueWebhook(int $uid, string $event, array $data): bool {
        // Load global settings (coid=1 is the default settings row)
        $settings = new GlobalSettings();

        // Check if webhook is enabled
        if (!$settings->get('webhook_enabled')) {
            return FALSE;
        }

        // Check if the target UID matches configured UIDs
        $configuredUids = $settings->get('webhook_uids');
        if (empty($configuredUids)) {
            return FALSE;
        }

        $allowedUids = array_map('trim', explode(',', $configuredUids));
        $allowedUids = array_filter($allowedUids, 'is_numeric');

        if (!in_array((string) $uid, $allowedUids)) {
            return FALSE;
        }

        $url = $settings->get('webhook_url');
        $secret = $settings->get('webhook_secret');

        if (empty($url) || empty($secret)) {
            return FALSE;
        }

        // Queue the webhook item — secret and URL are fetched at processing
        // time by the queue worker, so they never persist in the queue table.
        $item = [
            'event' => $event,
            'payload' => $data,
            'created' => time(),
        ];

        $queue = $this->queueFactory->get(self::QUEUE_NAME);
        $queue->createItem($item);

        $this->loggerFactory->get('ek_admin_webhook')
            ->info('Webhook queued: event=@event, uid=@uid', [
                '@event' => $event,
                '@uid' => $uid,
            ]);

        return TRUE;
    }

    /**
     * {@inheritdoc}
     */
    public function processWebhook(array $item, ?string $url = null, ?string $secret = null): bool {
        // Fetch URL and secret from GlobalSettings if not provided by caller.
        if ($url === null || $secret === null) {
            $settings = new GlobalSettings();
            $url ??= $settings->get('webhook_url');
            $secret ??= $settings->get('webhook_secret');
        }

        if (empty($url) || empty($secret)) {
            $this->loggerFactory->get('ek_admin_webhook')
                ->error('Cannot process webhook: missing URL or secret for event @event', [
                    '@event' => $item['event'] ?? 'unknown',
                ]);
            return FALSE;
        }

        try {
            $payloadJson = json_encode($item['payload']);
            $url = isset($item['route']) ? $url .  "/" . $item['route']: $url;
            $signature = hash_hmac('sha256', $payloadJson, $secret);

            $response = $this->httpClient->post($url, [
                'json' => [
                    'event' => $item['event'],
                    'payload' => $item['payload'],
                ],
                'headers' => [
                    'X-Hub-Signature' => 'sha256=' . $signature,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->loggerFactory->get('ek_admin_webhook')
                    ->info('Webhook sent successfully: event=@event, status=@status', [
                        '@event' => $item['event'],
                        '@status' => $statusCode,
                    ]);
                return TRUE;
            }
            else {
                $this->loggerFactory->get('ek_admin_webhook')
                    ->warning('Webhook returned non-2xx status: event=@event, status=@status', [
                        '@event' => $item['event'],
                        '@status' => $statusCode,
                    ]);
                return FALSE;
            }
        }
        catch (RequestException $e) {
            $this->loggerFactory->get('ek_admin_webhook')
                ->error('Webhook request failed: event=@event, error=@error', [
                    '@event' => $item['event'],
                    '@error' => $e->getMessage(),
                ]);
            return FALSE;
        }
    }

}