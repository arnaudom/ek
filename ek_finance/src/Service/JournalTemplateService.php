<?php

namespace Drupal\ek_finance\Service;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides persistence and loading for journal entry templates.
 */
class JournalTemplateService {
  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a JournalTemplateService object.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Returns the user/company template options for the form selector.
   */
  public function getOptions($coid = NULL, $uid = NULL) {
    $options = ['' => $this->t('- Custom -')];

    if (empty($coid)) {
      return $options;
    }

    $query = Database::getConnection('external_db', 'external_db')
      ->select('ek_journal_template', 't')
      ->fields('t', ['id', 'name', 'currency', 'fx_rate'])
      ->condition('coid', $coid, '=')
      ->orderBy('name', 'ASC');

    $result = $query->execute();
    while ($record = $result->fetchAssoc()) {
      $label = $record['name'];
      if (!empty($record['currency'])) {
        $label .= ' (' . $record['currency'];
        /*if (!empty($record['fx_rate'])) {
          $label .= ' / ' . $record['fx_rate'];
        }*/
        $label .= ')';
      }
      $options[$record['id']] = $label;
    }

    return $options;
  }

  /**
   * Loads one journal template by id.
   */
  public function load($id, $coid = NULL) {
    if (empty($id)) {
      return NULL;
    }

    $query = Database::getConnection('external_db', 'external_db')
      ->select('ek_journal_template', 't')
      ->fields('t')
      ->condition('id', $id, '=');

    if (!empty($coid)) {
      $query->condition('coid', $coid, '=');
    }

    return $query->execute()->fetchAssoc() ?: NULL;
  }

  /**
   * Saves a journal template.
   */
  public function save(array $data) {
    $connection = Database::getConnection('external_db', 'external_db');
    $now = gmdate('Y-m-d H:i:s');

    $record = [
      'uid' => !empty($data['uid']) ? (int) $data['uid'] : (int) $this->currentUser->id(),
      'coid' => !empty($data['coid']) ? (int) $data['coid'] : 0,
      'name' => trim((string) ($data['name'] ?? '')),
      'description' => trim((string) ($data['description'] ?? '')),
      'currency' => trim((string) ($data['currency'] ?? '')),
      'fx_rate' => trim((string) ($data['fx_rate'] ?? '')),
      'pattern' => trim((string) ($data['pattern'] ?? 'bank_transfer')),
      'payload' => $data['payload'] ?? '[]',
      'created' => $data['created'] ?? $now,
      'changed' => $now,
    ];

    if (empty($record['name'])) {
      return FALSE;
    }

    if (!empty($data['id'])) {
      $connection->update('ek_journal_template')
        ->fields([
          'uid' => $record['uid'],
          'coid' => $record['coid'],
          'name' => $record['name'],
          'description' => $record['description'],
          'currency' => $record['currency'],
          'fx_rate' => $record['fx_rate'],
          'pattern' => $record['pattern'],
          'payload' => $record['payload'],
          'changed' => $record['changed'],
        ])
        ->condition('id', (int) $data['id'], '=')
        ->execute();
      return (int) $data['id'];
    }

    $inserted = $connection->insert('ek_journal_template')
      ->fields($record)
      ->execute();

    return (int) $inserted;
  }

  /**
   * Decodes serialized payload values.
   */
  public function decodePayload($payload) {
    if (empty($payload)) {
      return [];
    }

    if (is_array($payload)) {
      return $payload;
    }

    $decoded = json_decode($payload, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

}
