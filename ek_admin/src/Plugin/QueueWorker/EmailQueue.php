<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Plugin\QueueWorker\EmailQueue.
 */
namespace Drupal\ek_admin\Plugin\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes Tasks for ek_admin.
 * Use central queue manager for other modules
 *
 * @QueueWorker(
 *   id = "ek_email_queue",
 *   title = @Translation("ek_admin email queue"),
 *   cron = {"time" = 60}
 * )
 */
class EmailQueue extends QueueWorkerBase {
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $params = $data;
    $mailManager->mail($data['module'], $data['key'], $data['email'], $data['lang'], $data['params'] , $send = TRUE);
  }
}