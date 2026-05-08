<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Plugin\QueueWorker\WebhookQueueWorker.
 */

namespace Drupal\ek_admin\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ek_admin\Service\WebhookServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\RequeueException;

/**
 * Processes webhook items via cron.
 *
 * @QueueWorker(
 *   id = "ek_admin_webhook",
 *   title = @Translation("Process webhooks"),
 *   cron = {"time" = 60}
 * )
 */
class WebhookQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

    /**
     * @var \Drupal\ek_admin\Service\WebhookServiceInterface
     */
    protected $webhookService;

    /**
     * Constructs a WebhookQueueWorker object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\ek_admin\Service\WebhookServiceInterface $webhook_service
     *   The webhook service.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, WebhookServiceInterface $webhook_service) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->webhookService = $webhook_service;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('ek_admin.webhook')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem($data) {
        if (!is_array($data)) {
            return;
        }

        // Validate required fields.
        if (empty($data['event']) || !isset($data['payload'])) {
            return;
        }

        // URL and secret are NOT passed here — they are fetched from
        // GlobalSettings inside WebhookService::processWebhook() at processing
        // time, so the secret never persists in the queue database table.
        $success = $this->webhookService->processWebhook($data);

        // If processing failed, throw RequeueException to retry later.
        if (!$success) {
            throw new RequeueException('Webhook processing failed; will retry on next cron run.');
        }
    }

}