<?php

namespace Drupal\ek_projects\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Form\TaskFormBase;
use Drupal\ek_projects\Service\ProjectService;
use Drupal\ek_admin\Service\TaskNotificationService;

/**
 * Provides a form to record and edit project tasks.
 */
class TaskProject extends TaskFormBase {

  /**
   * The project service.
   *
   * @var \Drupal\ek_projects\Service\ProjectService
   */
  protected $projectService;

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
    return 'ek_task_project';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->projectService = $container->get('project.service');
    $instance->taskNotificationService = $container->get('ek_admin.task_notification');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $param = NULL) {
    
    // Initialize default data object
    if (!isset($param['data']) || !is_object($param['data'])) {
      $param['data'] = (object) [
        'id' => NULL,
        'pcode' => $param['pcode'] ?? '',
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
    if ($param['edit'] ?? FALSE) {
      $read = $this->getReadSettings(
        $param['owner'] ?? FALSE,
        $param['delete'] ?? FALSE
      );
    }

    // Header
    $form['edit_task_project'] = [
      '#type' => 'item',
      '#markup' => $this->t('Project ref. @p', ['@p' => $param['data']->pcode]),
    ];

    // Hidden fields
    $form['for_pcode'] = [
      '#type' => 'hidden',
      '#value' => $param['data']->pcode,
    ];

    $form['for_pid'] = [
      '#type' => 'hidden',
      '#value' => $param['pid'],
    ];

    // Build common form elements
    $this->buildCommonElements($form, $form_state, $param['data'], $read);

    // Project-specific autocomplete library
    $form['notify_who']['#attached']['library'] = [
      'ek_projects/ek_projects_autocomplete',
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
      return '/projects/project/' . $form_state->getValue('for_pid') . '?s2=true';
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $database = Database::getConnection('external_db', 'external_db');
    $taskId = $form_state->getValue('for_id');
    $wasComplete = FALSE;

    // Check if task was already complete
    if ($taskId) {
      $previousRate = $database->select('ek_project_tasks', 't')
        ->fields('t', ['completion_rate'])
        ->condition('id', $taskId)
        ->execute()
        ->fetchField();
      $wasComplete = ((int) $previousRate >= 100);
    }

    if ($form_state->getValue('delete') == 1) {
      $database->delete('ek_project_tasks')
        ->condition('id', $taskId)
        ->execute();
    }
    else {
      $fields = $this->buildFieldArray($form_state);
      $fields['pcode'] = $form_state->getValue('for_pcode');
      $fields['weight'] = 0;

      if ($taskId) {
        $database->update('ek_project_tasks')
          ->fields($fields)
          ->condition('id', $taskId)
          ->execute();

        // Check if task just became complete - send notification
        $nowComplete = ((int) $fields['completion_rate'] >= 100);
        if ($nowComplete && !$wasComplete) {
          $this->sendCompletionNotification($taskId, 'project');
        }
      }
      else {
        $database->insert('ek_project_tasks')
          ->fields($fields)
          ->execute();
      }

      // Notify about task edit
      $acc = \Drupal\user\Entity\User::load($form_state->getValue('uid'));
      $name = $acc ? $acc->getAccountName() : '';
      
      $param = serialize([
        'pcode' => $form_state->getValue('for_pcode'),
        'id' => $form_state->getValue('for_pid'),
        'field' => $this->t('Task edited for') . ': ' . $name,
        'value' => Xss::filter($form_state->getValue('task')),
      ]);
      $this->projectService->notify_user($param);
    }

    Cache::invalidateTags(['project_task_block']);
  }

  /**
   * Send completion notification.
   *
   * @param int $taskId
   *   The task ID.
   * @param string $taskType
   *   The task type.
   */
  protected function sendCompletionNotification(int $taskId, string $taskType): void {
    $mailItems = $this->taskNotificationService->buildCompletionNotification(
      'ek_project_tasks',
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