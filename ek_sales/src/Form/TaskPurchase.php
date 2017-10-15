<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\TaskPurchase.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to reecord and edit purchase task alerts.
 */
class TaskPurchase extends FormBase {

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
        return 'ek_sales_task_purchase';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $access = AccessCheck::GetCompanyByUser();

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_purchase', 'p');
        $query->leftJoin('ek_sales_purchase_tasks', 't', 'p.serial=t.serial');
        $or1 = db_or();
        $or1->condition('p.head', $access, 'IN');
        $or1->condition('p.allocation', $access, 'IN');

        $data = $query
                ->fields('t')
                ->fields('p', array('serial'))
                ->condition($or1)
                ->condition('p.id', $id, '=')
                ->execute()
                ->fetchObject();


        if ($data) {

            $form['edit_purchase'] = array(
                '#type' => 'item',
                '#markup' => t('Purchase ref. @p', array('@p' => $data->serial)),
            );


            $form['for_serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $data->id,
            );

            if ($data->id) {
                $form['delete'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('Delete this task'),
                );
            }

            $form['event'] = array(
                '#type' => 'textfield',
                '#title' => $this->t('Event name'),
                '#size' => 25,
                '#maxlength' => 100,
                '#default_value' => $data->event,
            );

            $user = db_query('SELECT uid,name from {users_field_data} WHERE uid > :u AND status=:s', array(':u' => 0, ':s' => 1))
                    ->fetchAllKeyed();

            $form['uid'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $user,
                '#required' => TRUE,
                '#default_value' => isset($data->uid) ? $data->uid : NULL,
                '#title' => $this->t('Assigned to'),
                    //'#attributes' => array('style' => array('width:200px;white-space:nowrap')),
            );

            $form['task'] = array(
                '#type' => 'textfield',
                '#title' => $this->t('Task description'),
                '#size' => 25,
                '#maxlength' => 150,
                '#default_value' => isset($data->task) ? $data->task : NULL,
            );


            $form['start'] = array(
                '#type' => 'date',
                '#id' => 'edit-from',
                '#size' => 12,
                '#required' => TRUE,
                '#default_value' => isset($data->start) ? date('Y-m-d', $data->start) : date('Y-m-d'),
                '#title' => $this->t('Starting'),
                '#prefix' => "<div class='container-inline'>",
            );


            $form['end'] = array(
                '#type' => 'date',
                '#id' => 'edit-to',
                '#size' => 12,
                '#default_value' => isset($data->end) ? date('Y-m-d', $data->end) : NULL,
                '#title' => $this->t('ending'),
                '#suffix' => '</div>',
            );

            $form['color'] = array(
                '#type' => 'color',
                '#title' => $this->t('Color'),
                '#default_value' => isset($data->color) ? $data->color : '#80ff80',
            );
            
            $notify = array(
                '0' => t('Never'),
                '1' => t('Weekly'),
                '2' => t('5 days before deadline'),
                '3' => t('3 days before dealine'),
                '4' => t('1 day before dealine'),
                '5' => t('Daily'),
                '6' => t('Monthly')
            );

            $form['notify'] = array(
                '#type' => 'select',
                '#title' => $this->t('Notification period'),
                '#options' => $notify,
                '#default_value' => $data->notify,
            );

            if ($data->notify_who != NULL) {
                $who = explode(',', $data->notify_who);
                $list = '';

                foreach ($who as $w) {
                    if (trim($w) != NULL) {

                        $query = "SELECT name from {users_field_data} WHERE uid=:u";
                        $name = db_query($query, array(':u' => $w))->FetchField();
                        $list .= $name . ',';
                    }
                }
            } else {
                $list = '';
            }


            $form['notify_who'] = array(
                '#type' => 'textarea',
                '#title' => $this->t('Notification recipients'),
                '#rows' => 2,
                '#attributes' => array('placeholder' => t('enter users names separated by comma (autocomplete enabled).')),
                '#default_value' => $list,
                '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
            );


            if (isset($data->id)) {

                $rate = array(
                    '0' => 0,
                    '10' => 10,
                    '20' => 20,
                    '30' => 30,
                    '40' => 40,
                    '50' => 50,
                    '60' => 60,
                    '70' => 70,
                    '80' => 80,
                    '90' => 90,
                    '100' => 100,
                );

                $form['rate'] = array(
                    '#type' => 'select',
                    '#title' => $this->t('Completion (%)'),
                    '#options' => $rate,
                    '#default_value' => $data->completion_rate,
                );
            } else {

                $form['rate'] = array(
                    '#type' => 'hidden',
                    '#value' => 0,
                );
            }

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
            );
            $form['actions']['cancel'] = array(
                '#markup' => "<a href='" . Url::fromRoute('ek_sales.purchases.list')->toString() . "' >" . t('Cancel') . "</a>",
            );
        } else {

            $form['info'] = array(
                '#type' => 'item',
                '#markup' => $this->t('You cannot edit this purchase task.'),
            );

            $form['cancel'] = array(
                '#markup' => "<a href='" . Url::fromRoute('ek_sales.purchases.list')->toString() . "' >" . t('Return') . "</a>",
            );
        }




        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('delete') != 1) {
            if ($form_state->getValue('notify_who') != '') {

                $users = explode(',', $form_state->getValue('notify_who'));
                $error = '';
                $notify_who = '';
                foreach ($users as $u) {
                    if (trim($u) != '') {
                        //check it is a registered user 
                        $query = "SELECT uid from {users_field_data} WHERE name=:u";
                        $id = db_query($query, array(':u' => trim($u)))->FetchField();
                        if ($id == '') {
                            $error.= $u . ',';
                        } else {
                            $notify_who .= $id . ',';
                        }
                    }
                }

                if ($error <> '') {
                    $error = rtrim($error, ',');
                    $form_state->setErrorByName("notify_who", t('Invalid user(s)') . ': ' . $error);
                } else {
                    $form_state->setValue('notify_who', $notify_who);
                }
            }
            $or = $form_state->getValue('notify') == 2 || $form_state->getValue('notify') == 3 || $form_state->getValue('notify') == 4;

            if ($form_state->getValue('end') == '' && ( $or )) {
                $form_state->setErrorByName("end", t('You need a deadline for the selected period.') . ': ' . $error);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('delete') == 1) {
            $update = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_sales_purchase_tasks')
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        } else {

            if ($form_state->getValue('notify_who') != '') {

                $notify_who = rtrim($form_state->getValue('notify_who'), ',');
            } else {
                $notify_who = NULL;
            }


            $fields = array(
                'serial' => $form_state->getValue('for_serial'),
                'event' => Xss::filter($form_state->getValue('event')),
                'uid' => $form_state->getValue('uid'),
                'task' => Xss::filter($form_state->getValue('task')),
                'start' => strtotime($form_state->getValue('start')),
                'end' => strtotime($form_state->getValue('end')),
                'completion_rate' => $form_state->getValue('rate'),
                'notify' => $form_state->getValue('notify'),
                'notify_who' => $notify_who,
                'color' => $form_state->getValue('color'),
            );

            if ($form_state->getValue('for_id') != NULL) {

                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_sales_purchase_tasks')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
            } else {

                $update = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_sales_purchase_tasks')->fields($fields)
                        ->execute();
            }
        }


        if ($update) {
            Cache::invalidateTags(['sales_task']);
            $form_state->setRedirect('ek_sales.purchases.list');
        }
    }

}
