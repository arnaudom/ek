<?php

namespace Drupal\ek_sales\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Form\TaskFormBase;
use Drupal\ek_admin\Service\TaskNotificationService;

/**
 * Provides a form to record and edit sales task alerts.
 */
class Tasks extends TaskFormBase {

  /**
   * The task notification service.
   *
   * @var \Drupal\ek_admin\Service\TaskNotificationService
   */
  protected $taskNotificationService;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_sales_task';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->taskNotificationService = $container->get('ek_admin.task_notification');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $data = NULL, $doc = NULL, $param = NULL) {
    
    // Initialize default data object
    if (!$data || !is_object($data)) {
      $data = (object) [
        'id' => NULL,
        'serial' => '',
        'event' => NULL,
        'uid' => NULL,
        'task' => NULL,
        'start' => NULL,
        'end' => NULL,
        'completion_rate' => 0,
        'notify' => 0,
        'notify_who' => NULL,
        'color' => '#80ff80',
        'priority' => 2,
      ];
    }

    // Get read settings
    $read = [];
    if (($param['owner'] ?? TRUE) === FALSE && ($param['delete'] ?? TRUE) === FALSE) {
      $read = $this->getReadSettings(FALSE, FALSE);
    }

    // Header
    $form['edit_doc'] = [
      '#type' => 'item',
      '#markup' => '<h2>' . $this->t('@doc ref. @p', [
        '@doc' => ucfirst($doc),
        '@p' => $data->serial,
      ]) . '</h2>',
    ];

    // Hidden fields
    $form['for_serial'] = [
      '#type' => 'hidden',
      '#value' => $data->serial,
    ];

    $form['for_doc'] = [
      '#type' => 'hidden',
      '#value' => strtolower($doc),
    ];

    $form['destination'] = [
      '#type' => 'hidden',
      '#value' => $param['destination'] ?? '',
    ];

    // task id from sales task table
    $form['for_id'] = [
        '#type' => 'hidden',
        '#value' => $param['for_id'],
    ];

    // Build common form elements
    $this->buildCommonElements($form, $form_state, $data, $read);

    // Sales-specific autocomplete library
    $form['notify_who']['#attached']['library'] = [
      'ek_admin/ek_admin.users_autocomplete',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateCommon($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(array $form, FormStateInterface $form_state): ?string {
    if ($form_state->getTriggeringElement()['#id'] === 'task-record') {
      return $form_state->getValue('destination');
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $docType = $form_state->getValue('for_doc');
    
    $tableMap = [
      'invoice' => 'ek_sales_invoice_tasks',
      'purchase' => 'ek_sales_purchase_tasks',
    ];
    
    $table = $tableMap[$docType] ?? NULL;
    if (!$table) {
      return;
    }

    $database = Database::getConnection('external_db', 'external_db');
    $taskId = $form_state->getValue('for_id');
    $wasComplete = FALSE;

    // Check if task was already complete
    if ($taskId) {
      $previousRate = $database->select($table, 't')
        ->fields('t', ['completion_rate'])
        ->condition('id', $taskId)
        ->execute()
        ->fetchField();
      $wasComplete = ((int) $previousRate >= 100);
    }

    if ($form_state->getValue('delete') == 1) {
      $database->delete($table)
        ->condition('id', $taskId)
        ->execute();
      $update = TRUE;
    }
    else {
      $fields = $this->buildFieldArray($form_state);
      $fields['serial'] = $form_state->getValue('for_serial');
      $fields['weight'] = 0;

      if ($taskId) {
        $update = $database->update($table)
          ->fields($fields)
          ->condition('id', $taskId)
          ->execute();

        // Check if task just became complete - send notification
        $nowComplete = ((int) $fields['completion_rate'] >= 100);
        if ($nowComplete && !$wasComplete) {
          $this->sendCompletionNotification($table, $taskId, $docType);
        }
      }
      else {
        $update = $database->insert($table)
          ->fields($fields)
          ->execute();
      }
    }

    if ($update) {
      Cache::invalidateTags(['sales_task']);
    }
  }

  /**
   * Send completion notification.
   *
   * @param string $table
   *   The table name.
   * @param int $taskId
   *   The task ID.
   * @param string $taskType
   *   The task type.
   */
  protected function sendCompletionNotification(string $table, int $taskId, string $taskType): void {
    $mailItems = $this->taskNotificationService->buildCompletionNotification(
      $table,
      $taskId,
      $taskType
    );

    if (!empty($mailItems)) {
      $queue = \Drupal::queue('ek_email_queue');
      $queue->createQueue();
      
      foreach ($mailItems as $params) {
        $u = \Drupal\user\Entity\User::load($params['uid']);
        if ($u && $u->getEmail()) {
          $params['options']['name'] = $u->getAccountName();
          $params['options']['subject'] = $params['subject'];
          $params['options']['link'] = $params['link'];
          $params['options']['serial'] = $params['serial'];
          $params['options']['task'] = $params['task'];
          $params['options']['end'] = $params['end'];
          $params['options']['alert'] = $params['alert'];
          $params['options']['assign_name'] = '';
          
          if ($assign = \Drupal\user\Entity\User::load($params['assign'])) {
            $params['options']['assign_name'] = $assign->getAccountName();
          }

          $data = [
            'module' => 'ek_admin',
            'key' => 'tasks',
            'params' => $params,
            'email' => $u->getEmail(),
            'lang' => $u->getPreferredLangcode(),
          ];
          $queue->createItem($data);
        }
      }
    }
  }

}