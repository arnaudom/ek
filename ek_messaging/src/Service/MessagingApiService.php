<?php

namespace Drupal\ek_messaging\Service;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Business logic for messaging API endpoints.
 */
class MessagingApiService {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \Drupal\ek_messaging\Service\MessageRegistrationService
   */
  protected $messageRegistration;

  /**
   * @var \Drupal\ek_messaging\Service\MessageEncryptionService
   */
  protected $encryption;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a MessagingApiService object.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    MessageRegistrationService $message_registration,
    MessageEncryptionService $encryption,
    MailManagerInterface $mail_manager,
  ) {
    $this->logger = $logger_factory->get('ek_messaging');
    $this->messageRegistration = $message_registration;
    $this->encryption = $encryption;
    $this->mailManager = $mail_manager;
  }

  /**
   * Returns inbox messages (decrypted) for a user.
   */
  public function getInbox(int $uid, ?int $since = NULL, int $limit = 100, int $offset = 0): array {
    $limit = min(max($limit, 1), 200);
    $offset = max($offset, 0);

    $query = $this->getExternalDb()->select('ek_messaging', 'm');
    $query->fields('m', ['id', 'stamp', 'from_uid', 'to', 'status', 'subject', 'priority']);
    $query->innerJoin('ek_messaging_text', 't', 'm.id = t.id');
    $query->fields('t', ['text', 'format']);
    $query->condition('m.inbox', '%,' . $uid . ',%', 'LIKE');

    if (!is_null($since) && $since > 0) {
      $query->condition('m.stamp', $since, '>=');
    }

    $query->orderBy('m.stamp', 'DESC');
    $query->range($offset, $limit);

    $rows = $query->execute()->fetchAll();
    if (empty($rows)) {
      return [];
    }

    $from_uids = [];
    foreach ($rows as $row) {
      $from_uids[] = (int) $row->from_uid;
    }
    $from_uids = array_values(array_unique($from_uids));

    $senders = [];
    foreach (User::loadMultiple($from_uids) as $user) {
      $senders[(int) $user->id()] = $user->getDisplayName();
    }

    $items = [];
    foreach ($rows as $row) {
      $status = isset($row->status) ? (string) $row->status : '';
      $readers = array_filter(explode(',', $status));
      $is_read = in_array((string) $uid, $readers, TRUE);

      try {
        $body = $this->encryption->decrypt((string) $row->text);
      }
      catch (\RuntimeException $e) {
        $this->logger->error('Failed to decrypt message @id via API: @msg', [
          '@id' => (int) $row->id,
          '@msg' => $e->getMessage(),
        ]);
        $body = (string) $this->t('[Message body could not be decrypted.]');
      }

      $items[] = [
        'id' => (int) $row->id,
        'stamp' => (int) $row->stamp,
        'datetime' => date('c', (int) $row->stamp),
        'from_uid' => (int) $row->from_uid,
        'from_name' => $senders[(int) $row->from_uid] ?? '',
        'subject' => (string) $row->subject,
        'priority' => (int) $row->priority,
        'is_read' => $is_read,
        'format' => (string) $row->format,
        'message' => $body,
      ];
    }

    return $items;
  }

  /**
   * Sends a new message or reply from authenticated user.
   */
  public function postMessage(int $from_uid, string $from_mail, array $payload): array {
    $to_uids = $this->resolveRecipientUids($payload);
    if (empty($to_uids)) {
      throw new \InvalidArgumentException('At least one valid recipient is required.');
    }

    $priority = isset($payload['priority']) ? (int) $payload['priority'] : 2;
    if (!in_array($priority, [1, 2, 3], TRUE)) {
      $priority = 2;
    }

    $subject = isset($payload['subject']) ? Xss::filter((string) $payload['subject']) : '';
    if ($subject === '') {
      throw new \InvalidArgumentException('subject is required.');
    }

    if ($priority === 1 && stripos($subject, '[urgent]') === FALSE) {
      $subject = '[urgent] ' . $subject;
    }

    $body = isset($payload['message']) ? (string) $payload['message'] : '';
    if (trim($body) === '') {
      throw new \InvalidArgumentException('message is required.');
    }

    $format = isset($payload['format']) ? (string) $payload['format'] : 'restricted_html';
    $inbox = ',' . implode(',', $to_uids) . ',';

    $message_id = $this->messageRegistration->register([
      'uid' => $from_uid,
      'to' => $inbox,
      'to_group' => 0,
      'type' => 2,
      'status' => '',
      'inbox' => $inbox,
      'archive' => '',
      'subject' => $subject,
      'body' => $body,
      'format' => $format,
      'priority' => $priority,
    ]);

    $send_email_copy = !empty($payload['send_email']) && (int) $payload['send_email'] === 1;
    $email_errors = $this->sendEmailAlerts($message_id, $to_uids, $subject, $body, $priority, $from_mail, $send_email_copy);

    return [
      'message_id' => $message_id,
      'to_uids' => $to_uids,
      'email_alert_sent' => empty($email_errors),
      'email_errors' => $email_errors,
    ];
  }

  /**
   * Resolves recipients from payload. Supports explicit recipients and replies.
   */
  protected function resolveRecipientUids(array $payload): array {
    $uids = [];

    if (!empty($payload['to_uids']) && is_array($payload['to_uids'])) {
      foreach ($payload['to_uids'] as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) {
          $uids[] = $uid;
        }
      }
    }
    elseif (!empty($payload['to']) && is_string($payload['to'])) {
      foreach (explode(',', $payload['to']) as $uid) {
        $uid = (int) trim($uid);
        if ($uid > 0) {
          $uids[] = $uid;
        }
      }
    }

    if (empty($uids) && !empty($payload['reply_to'])) {
      $reply_id = (int) $payload['reply_to'];
      if ($reply_id > 0) {
        $row = $this->getExternalDb()->select('ek_messaging', 'm')
          ->fields('m', ['from_uid'])
          ->condition('id', $reply_id)
          ->execute()
          ->fetchObject();
        if ($row && (int) $row->from_uid > 0) {
          $uids[] = (int) $row->from_uid;
        }
      }
    }

    $uids = array_values(array_unique($uids));
    if (empty($uids)) {
      return [];
    }

    $users = User::loadMultiple($uids);
    $valid = [];
    foreach ($users as $user) {
      if ($user->isActive()) {
        $valid[] = (int) $user->id();
      }
    }

    return $valid;
  }

  /**
   * Sends email alert for new message.
   */
  protected function sendEmailAlerts(
    int $message_id,
    array $to_uids,
    string $subject,
    string $body,
    int $priority,
    string $from_mail,
    bool $send_email_copy,
  ): array {
    $link = Url::fromRoute('ek_messaging_read', ['id' => $message_id], [])->toString();
    $url = Url::fromRoute('user.login', [], [
      'absolute' => TRUE,
      'query' => ['destination' => $link],
    ])->toString();
    $open = "<a href='" . $url . "'>" . (string) $this->t('open') . "</a>";

    if ($send_email_copy) {
      $params = [
        'subject' => $subject,
        'body' => $open . '<hr>' . $body,
        'from' => $from_mail,
        'priority' => $priority,
        'link' => 0,
        'url' => $url,
      ];
    }
    else {
      $params = [
        'subject' => (string) $this->t('You have a new message'),
        'body' => $open,
        'from' => $from_mail,
        'priority' => $priority,
        'link' => 1,
        'url' => $url,
      ];
    }

    $errors = [];
    foreach (User::loadMultiple($to_uids) as $account) {
      if (!$account->isActive()) {
        continue;
      }

      $send = $this->mailManager->mail(
        'ek_messaging',
        'ek_message',
        $account->getEmail(),
        $account->getPreferredLangcode(),
        $params,
        $from_mail,
        TRUE,
      );

      if (empty($send['result'])) {
        $errors[] = $account->getEmail();
      }
    }

    return $errors;
  }

  /**
   * Returns external DB connection.
   */
  protected function getExternalDb() {
    return Database::getConnection('external_db', 'external_db');
  }

}
