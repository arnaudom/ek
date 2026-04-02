<?php

namespace Drupal\ek_admin\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_admin\Service\TaskNotificationService;

/**
 * Base class for task forms with shared functionality.
 */
abstract class TaskFormBase extends FormBase {

  use AjaxFormHelperTrait;

  /**
   * Get notification options using bitmask values.
   *
   * @return array
   *   Array of notification options.
   */
  protected function getNotifyOptions(): array {
    return TaskNotificationService::getNotificationOptions();
  }

  /**
   * Get priority options.
   *
   * @return array
   *   Array of priority options.
   */
  protected function getPriorityOptions(): array {
    return [
      1 => $this->t('High'),
      2 => $this->t('Medium'),
      3 => $this->t('Low'),
    ];
  }

  /**
   * Parse notify bitmask to array of selected values.
   *
   * @param int $bitmask
   *   The bitmask value.
   *
   * @return array
   *   Array of selected bitmask values.
   */
  protected function parseNotifyBitmask(int $bitmask): array {
    $selected = [];
    $options = [
      TaskNotificationService::NOTIFY_DAILY,
      TaskNotificationService::NOTIFY_WEEKLY,
      TaskNotificationService::NOTIFY_MONTHLY,
      TaskNotificationService::NOTIFY_5_DAYS_BEFORE,
      TaskNotificationService::NOTIFY_3_DAYS_BEFORE,
      TaskNotificationService::NOTIFY_1_DAY_BEFORE,
      TaskNotificationService::NOTIFY_ON_OVERDUE,
      TaskNotificationService::NOTIFY_ON_COMPLETE,
    ];

    foreach ($options as $option) {
      if ($bitmask & $option) {
        $selected[$option] = $option;
      }
    }

    return $selected;
  }

  /**
   * Convert checkbox array back to bitmask.
   *
   * @param array $checkboxes
   *   The checkbox values from form.
   *
   * @return int
   *   The combined bitmask.
   */
  protected function checkboxesToBitmask(array $checkboxes): int {
    $bitmask = 0;
    foreach ($checkboxes as $value) {
      if (!empty($value) && is_numeric($value)) {
        $bitmask |= (int) $value; 
      }
    }
    return $bitmask;
  }

  /**
   * Format date from timestamp or date string.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return string|null
   *   The formatted date or NULL.
   */
  protected function formatDate($value): ?string {
    if (empty($value) || $value === '0000-00-00') {
      return NULL;
    }
    
    if (is_numeric($value)) {
      return date('Y-m-d', (int) $value);
    }
    
    return $value;
  }

  /**
   * Format notify_who UIDs to usernames.
   *
   * @param string|null $notifyWho
   *   The comma-separated UIDs.
   *
   * @return string
   *   The comma-separated usernames.
   */
  protected function formatNotifyWho(?string $notifyWho): string {
    if (empty($notifyWho)) {
      return '';
    }

    $uids = array_filter(explode(',', $notifyWho));
    $names = [];

    foreach ($uids as $uid) {
      $uid = trim($uid);
      if (!empty($uid)) {
        $account = \Drupal\user\Entity\User::load($uid);
        if ($account) {
          $names[] = $account->getAccountName();
        }
      }
    }

    return implode(',', $names);
  }

  /**
   * Build common form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $data
   *   The task data object.
   * @param array $read
   *   Array of read-only field settings.
   */
  protected function buildCommonElements(array &$form, FormStateInterface $form_state, object $data, array $read = []): void {
    
    // Delete checkbox (only when editing)
    if (!empty($data->id)) {
      
      $form['delete'] = [
        '#type' => 'checkbox',
        '#title' => $read['delete_description'] ?? $this->t('Delete this task'),
        '#attributes' => isset($read['delete']) ? ['disabled' => $read['delete']] : NULL,
      ];

      // Completion rate slider
      $rate = $data->completion_rate ?? 0;
      $form['completion_rate'] = [
        '#type' => 'range',
        '#min' => 0,
        '#max' => 100,
        '#required' => TRUE,
        '#default_value' => $rate,
        '#title' => $this->t('Completion rate'),
        '#states' => [
          'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
        ],
        '#ajax' => [
          'callback' => [$this, 'getRate'],
          'wrapper' => 'rate',
          'method' => 'replace',
          'event' => 'change',
        ],
        '#prefix' => "<div class='container-inline'>",
      ];

      $form['rate'] = [
        '#type' => 'item',
        '#markup' => $rate . ' %',
        '#prefix' => "<div id='rate'>",
        '#suffix' => '</div></div>',
      ];
    }
    else {
      $form['completion_rate'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
    }

    // Event name
    $form['event'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event name'),
      '#size' => 30,
      '#maxlength' => 100,
      '#default_value' => $data->event ?? NULL,
      '#attributes' => isset($read['event']) ? ['readonly' => $read['event']] : NULL,
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Assigned user
    $form['uid'] = [
      '#type' => 'select',
      '#size' => 1,
      '#options' => \Drupal\ek_admin\Access\AccessCheck::listUsers(),
      '#required' => TRUE,
      '#default_value' => $data->uid ?? NULL,
      '#title' => $this->t('Assigned to'),
      '#disabled' => $read['uid'] ?? FALSE,
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Task description
    $form['task'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('Task description'),
      '#required' => TRUE,
      '#default_value' => $data->task ?? NULL,
      '#attributes' => isset($read['task']) ? ['readonly' => $read['task']] : NULL,
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Date range
    $form['start'] = [
      '#type' => 'date',
      '#size' => 12,
      '#required' => TRUE,
      '#default_value' => $this->formatDate($data->start ?? NULL) ?? date('Y-m-d'),
      '#title' => $this->t('Starting'),
      '#prefix' => "<div class='container-inline'>",
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    $form['end'] = [
      '#type' => 'date',
      '#size' => 12,
      '#default_value' => $this->formatDate($data->end ?? NULL),
      '#title' => $this->t('Ending'),
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Priority
    $form['priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#options' => $this->getPriorityOptions(),
      '#default_value' => $data->priority ?? 2,
      '#disabled' => $read['priority'] ?? FALSE,
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Color picker
    $form['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $data->color ?? '#80ff80',
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Notification options (CHECKBOXES - bitmask approach)
    $currentNotify = $this->parseNotifyBitmask((int) ($data->notify ?? 0));


    // Notification recipients
    $form['notify_who'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notification recipients'),
      '#rows' => 2,
      '#id' => 'edit-notify-who',
      '#attributes' => [
        'placeholder' => $this->t('Enter user names separated by comma (autocomplete enabled).'),
      ],
      '#default_value' => $this->formatNotifyWho($data->notify_who ?? NULL),
      '#disabled' => $read['notify_who'] ?? FALSE,
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];
    
    $form['notify'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Notification schedule'),
      '#description' => $this->t('Select all that apply. Combine recurring reminders with deadline warnings.'),
      '#options' => $this->getNotifyOptions(),
      '#default_value' => $currentNotify,
      '#disabled' => $read['notify'] ?? FALSE,
      '#states' => [
        'visible' => [':input[name="delete"]' => ['checked' => FALSE]],
      ],
    ];

    // Deadline notice
    $form['deadline_notice'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      '#states' => [
        'visible' => [
          ':input[name="delete"]' => ['checked' => FALSE],
          ':input[name="end"]' => ['value' => ''],
          [
            [':input[name="notify[8]"]' => ['checked' => TRUE]],
            'or',
            [':input[name="notify[16]"]' => ['checked' => TRUE]],
            'or',
            [':input[name="notify[32]"]' => ['checked' => TRUE]],
            'or',
            [':input[name="notify[64]"]' => ['checked' => TRUE]],
          ],
        ],
      ],
    ];
    $form['deadline_notice']['message'] = [
      '#markup' => $this->t('⚠️ Deadline warnings require an end date to be set.'),
    ];

    // Actions
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['record'] = [
      '#type' => 'submit',
      '#id' => 'task-record',
      '#value' => $this->t('Record'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
  }

  /**
   * Rate callback for AJAX.
   */
  public function getRate(array &$form, FormStateInterface $form_state) {
    $form['rate']['#markup'] = $form_state->getValue('completion_rate') . ' %';
    return $form['rate'];
  }

  /**
   * Common form validation.
   */
  protected function validateCommon(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('delete') == 1) {
      return;
    }

    // Validate notification recipients
    $notifyWho = $form_state->getValue('notify_who');
    if (!empty($notifyWho)) {
      $users = explode(',', $notifyWho);
      $errors = [];
      $validUids = [];

      foreach ($users as $username) {
        $username = trim($username);
        if (empty($username)) {
          continue;
        }

        $query = Database::getConnection()->select('users_field_data', 'u');
        $query->fields('u', ['uid']);
        $query->condition('name', $username);
        $uid = $query->execute()->fetchField();

        if (empty($uid)) {
          $errors[] = $username;
        }
        else {
          $validUids[] = $uid;
        }
      }

      if (!empty($errors)) {
        $form_state->setErrorByName(
          'notify_who',
          $this->t('Invalid user(s): @users', ['@users' => implode(', ', $errors)])
        );
      }
      else {
        $form_state->setValue('notify_who', implode(',', $validUids));
      }
    }

    // Validate deadline requirement for deadline-based notifications
    $notify = $form_state->getValue('notify');
    if (is_array($notify)) {
      $deadlineFlags = [
        TaskNotificationService::NOTIFY_5_DAYS_BEFORE,
        TaskNotificationService::NOTIFY_3_DAYS_BEFORE,
        TaskNotificationService::NOTIFY_1_DAY_BEFORE,
        TaskNotificationService::NOTIFY_ON_OVERDUE,
      ];

      $hasDeadlineNotification = FALSE;
      foreach ($deadlineFlags as $flag) {
        if (!empty($notify[$flag])) {
          $hasDeadlineNotification = TRUE;
          break;
        }
      }

      if ($hasDeadlineNotification && empty($form_state->getValue('end'))) {
        $form_state->setErrorByName(
          'end',
          $this->t('You need an end date for deadline-based notifications.')
        );
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $redirectUrl = $this->getRedirectUrl($form, $form_state);
    if (!$redirectUrl) {
      throw new \Exception('No destination provided by form');
    }

    $response = new AjaxResponse();
    return $response->addCommand(new RedirectCommand($redirectUrl));
  }

  /**
   * Get the redirect URL.
   */
  abstract protected function getRedirectUrl(array $form, FormStateInterface $form_state): ?string;

  /**
   * Build common field array for database.
   */
  protected function buildFieldArray(FormStateInterface $form_state): array {
    $notifyWho = $form_state->getValue('notify_who');
    $notify = $form_state->getValue('notify');
    
    // Convert checkboxes to bitmask
    $notifyBitmask = is_array($notify) ? $this->checkboxesToBitmask($notify) : 0;

    return [
      'event' => Xss::filter($form_state->getValue('event')),
      'uid' => $form_state->getValue('uid'),
      'task' => Xss::filter($form_state->getValue('task')),
      'start' => strtotime($form_state->getValue('start')),
      'end' => $form_state->getValue('end') ? strtotime($form_state->getValue('end')) : NULL,
      'completion_rate' => $form_state->getValue('completion_rate') ?: 0,
      'notify' => $notifyBitmask,
      'notify_who' => $notifyWho ? rtrim($notifyWho, ',') : NULL,
      'color' => $form_state->getValue('color'),
      'priority' => $form_state->getValue('priority') ?? 2,
    ];
  }

  /**
   * Get read-only settings based on permissions.
   */
  protected function getReadSettings(bool $isOwner, bool $canDelete): array {
    $read = ['delete_description' => $this->t('Delete this task')];

    if ($isOwner && !$canDelete) {
      $read = [
        'delete_description' => $this->t('You need permission to delete this task'),
        'delete' => 'disable',
        'event' => 'readonly',
        'uid' => TRUE,
        'task' => 'readonly',
        'start' => 'readonly',
        'end' => 'readonly',
        'notify' => FALSE,
        'notify_who' => FALSE,
      ];
    }
    elseif (!$isOwner && !$canDelete) {
      $read = [
        'delete_description' => $this->t('You need permission to delete this task'),
        'delete' => 'disable',
        'event' => 'readonly',
        'uid' => TRUE,
        'task' => 'readonly',
        'start' => 'readonly',
        'end' => 'readonly',
        'notify' => TRUE,
        'notify_who' => TRUE,
      ];
    }

    return $read;
  }

}