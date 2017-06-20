<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\DeleteTaskForm.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to delete task.
 */
class DeleteTaskForm extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
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
                        ->query($query, array('id' => $id))->fetchField();

        $url = Url::fromRoute('ek_projects_view', array('id' => $pid), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@url" >Project</a>', array('@url' => $url)),
        );


        $query = "SELECT * FROM {ek_project_tasks} "
                . " WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();

        $access = ProjectData::validate_access($pid);
        $perm = \Drupal::currentUser()->hasPermission('delete_project_task');

        $form['edit_item'] = array(
            '#type' => 'item',
            '#markup' => t('Task for project : @p', array('@p' => $data->pcode)),
        );
        $form['edit_item2'] = array(
            '#type' => 'item2',
            '#markup' => t('Description : @p', array('@p' => $data->task)),
        );

        $del = 1;
        if (!$access) {
            $del = 0;
            $message = t('You are not authorized to delete this task from this project');
        } elseif (!$perm) {
            $del = 0;
            $message = t('You are not editor. the task cannot be deleted.');
        }


        if ($del != 0) {

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );
            $form['for_pid'] = array(
                '#type' => 'hidden',
                '#value' => $pid,
            );
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('Are you sure you want to delete this task ?'),
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete'),
            );
        } else {

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $message,
            );
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

        $query = "SELECT task FROM {ek_project_tasks} WHERE id=:id";
        $task = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchField();

        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_tasks')
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();




        if ($delete) {
            $query = "SELECT name from {users_field_data} WHERE uid=:u";
            $name = db_query($query, array(':u' => \Drupal::currentUser()->id()))->fetchField();
            $param = serialize(
                    array(
                        'id' => $form_state->getValue('for_pid'),
                        'field' => t('Task deleted by') . ": " . $name,
                        'value' => $task
                    )
            );
            ProjectData::notify_user($param);
            drupal_set_message(t('The task has been deleted'), 'status');
            $form_state->setRedirect('ek_projects_view', array('id' => $pid));
        }
    }

}
