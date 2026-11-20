<?php

namespace Drupal\ek_projects\Form;

use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\Service\ProjectService;

/**
 * Provides a form to delete task.
 */
class DeleteTaskForm extends FormBase {


  protected $moduleHandler;
  protected $projectService;

  /**
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandler $module_handler, ProjectService $projectService) {
    $this->moduleHandler = $module_handler;
    $this->projectService = $projectService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
              $container->get('module_handler'),
              $container->get('project.service')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_project_task_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $query = "SELECT p.id FROM {ek_project} p "
                . "LEFT JOIN {ek_project_tasks} t "
                . "ON p.pcode=t.pcode WHERE t.id=:id";
    $pid = Database::getConnection('external_db', 'external_db')
      ->query($query, ['id' => $id])->fetchField();

    $url = Url::fromRoute('ek_projects_view', ['id' => $pid], [])->toString();
    $form['back'] = [
      '#type' => 'item',
      '#markup' => $this->t('<a href="@url" >Project</a>', ['@url' => $url]),
    ];

    $query = "SELECT * FROM {ek_project_tasks} "
                . " WHERE id=:id";
    $data = Database::getConnection('external_db', 'external_db')
      ->query($query, [':id' => $id])
      ->fetchObject();

    $access = $this->projectService->validate_access($pid);
    $perm = \Drupal::currentUser()->hasPermission('delete_project_task');
    $projectCode = (string) ($data->pcode ?? '');

    $form['edit_item'] = [
      '#type' => 'item',
      '#markup' => $this->t('Task for project : @p', ['@p' => $projectCode]),
    ];
    $form['edit_item2'] = [
      '#type' => 'item',
      '#markup' => $this->t('Description : @p', ['@p' => $data->task ?? '']),
    ];

    $del = 1;
    if (!$access) {
      $del = 0;
      $message = $this->t('You are not authorized to delete this task from this project');
    }
    elseif (!$perm) {
      $del = 0;
      $message = $this->t('You are not editor. the task cannot be deleted.');
    }

    if ($del != 0) {
      $form['for_id'] = [
        '#type' => 'hidden',
        '#value' => $id,
      ];
      $form['for_pid'] = [
        '#type' => 'hidden',
        '#value' => $pid,
      ];
      $form['alert'] = [
        '#type' => 'item',
        '#markup' => $this->t('Are you sure you want to delete this task ?'),
      ];

      $form['actions']['record'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
      ];
    }
    else {
      $form['alert'] = [
        '#type' => 'item',
        '#markup' => $message,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $database = Database::getConnection('external_db', 'external_db');
    $query = "SELECT task, pcode FROM {ek_project_tasks} WHERE id=:id";
    $taskData = $database
      ->query($query, [':id' => $form_state->getValue('for_id')])
      ->fetchObject();

    $delete = $database
      ->delete('ek_project_tasks')
      ->condition('id', $form_state->getValue('for_id'))
      ->execute();

    if ($delete) {
      $acc = User::load(\Drupal::currentUser()->id());
      $name = $acc ? $acc->getAccountName() : '';

      $param = serialize(
                [
                  'id' => $form_state->getValue('for_pid'),
                  'pcode' => (string) ($taskData->pcode ?? ''),
                  'field' => $this->t('Task deleted by') . ": " . $name,
                  'value' => (string) ($taskData->task ?? ''),
                ]
        );
      $this->projectService->notify_user($param);
      \Drupal::messenger()->addStatus(t('The task has been deleted'));
      $form_state->setRedirect('ek_projects_view', ['id' => $form_state->getValue('for_pid')]);
    }
  }

}
