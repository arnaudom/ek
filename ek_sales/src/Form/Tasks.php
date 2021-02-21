<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\Tasks.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;

/**
 * Provides a form to record and edit sales task alerts.
 */
class Tasks extends FormBase {

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_task_invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $data = null, $doc = null, $param = null) {

        $read = [];
        $read = ['delete_description' => $this->t('Delete this task')];
        if ($param['owner'] == false && $param['delete'] == false) {
            // read only
            $read = [
                'delete_description' => $this->t('You need permission to delete this task'),
                'delete' => 'disable',
                'event' => 'readonly',
                'uid' => true,
                'task' => 'readonly',
                'start' => 'readonly',
                'end' => 'readonly',
                'class' => 'date',
                'notify' => true,
                'notify_who' => true,
            ];
        }

        $form['edit_doc'] = [
            '#type' => 'item',
            '#markup' => "<h1>" . $this->t('@doc ref. @p', ['@doc' => $doc, '@p' => $data->serial]) . "</h1>",
        ];

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
            '#value' => $param['destination'],
        ];

        if ($data->id) {
            // edit 
            $form['for_id'] = [
                '#type' => 'hidden',
                '#value' => $data->id,
            ];

            $form['delete'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Delete this task'),
                '#attributes' => isset($read['delete']) ? ['disabled' => $read['delete']] : null,
            ];

            $r = null !== ($data->completion_rate) ? $data->completion_rate : 0;
            $form['completion_rate'] = [
                '#type' => 'range',
                '#min' => 0,
                '#max' => 100,
                '#required' => false,
                '#default_value' => $r,
                '#title' => $this->t('Completion rate'),
                '#states' => [
                    'visible' => [":input[name='delete']" => ['checked' => false]],
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
                '#markup' => $r . " %",
                '#prefix' => "<div  id='rate'>",
                '#suffix' => "</div></div>",
            ];
        } else {
            $form['rate'] = [
                '#type' => 'hidden',
                '#value' => 0,
            ];
        }

        $form['event'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Event name'),
            '#size' => 25,
            '#maxlength' => 100,
            '#default_value' => null !==$data->event ? $data->event : null,
            '#attributes' => isset($read['event']) ? ['readonly' => $read['event']] : null,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
            '#prefix' => "",
        ];


        $form['uid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => \Drupal\ek_admin\Access\AccessCheck::listUsers(1),
            '#required' => true,
            '#default_value' => null !== $data->uid ? $data->uid : null,
            '#disabled' => isset($read['uid']) ? $read['uid'] : false,
            '#title' => $this->t('Assigned to'),
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['task'] = [
            '#type' => 'textarea',
            '#rows' => 3,
            '#title' => $this->t('Task description'),
            '#required' => true,
            '#attributes' => null !== $read['task'] ? ['readonly' => $read['task']] : null,
            '#default_value' => isset($data->task) ? $data->task : null,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];


        $form['start'] = [
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => true,
            '#default_value' => (null !== $data->start && is_numeric($data->start)) ? date('Y-m-d', $data->start) : date('Y-m-d'),
            '#title' => $this->t('Starting'),
            '#prefix' => "<div class='container-inline'>",
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];


        $form['end'] = [
            '#type' => 'date',
            '#id' => 'edit-to',
            '#size' => 12,
            '#default_value' => (null !== $data->end && is_numeric($data->end)) ? date('Y-m-d', $data->end) : null,
            '#title' => $this->t('ending'),
            '#suffix' => '</div>',
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['color'] = [
            '#type' => 'color',
            '#title' => $this->t('Color'),
            '#default_value' => null !== $data->color ? $data->color : '#80ff80',
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        if ($data->notify_who != null) {
            $who = explode(',', $data->notify_who);
            $list = '';

            foreach ($who as $w) {
                if (trim($w) != null) {
                    $acc = \Drupal\user\Entity\User::load($w);
                    if ($acc) {
                        $list .= $acc->getAccountName() . ',';
                    }
                }
            }
        } else {
            $list = '';
        }


        $form['notify_who'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Notification recipients'),
            '#rows' => 2,
            '#id' => 'edit-users',
            '#attributes' => ['placeholder' => $this->t('enter users names separated by comma (autocomplete enabled).')],
            '#default_value' => $list,
            '#disabled' => isset($read['notify_who']) ? $read['notify_who'] : false,
            '#attached' => [
                'library' => [
                    'ek_admin/ek_admim.users_autocomplete',
                ],
            ],
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];


        $notify = [
            '0' => $this->t('Never'),
            '5' => $this->t('Daily'),
            '1' => $this->t('Weekly'),
            '6' => $this->t('Monthly'),
            '2' => $this->t('5 days before deadline'),
            '3' => $this->t('3 days before dealine'),
            '4' => $this->t('1 day before dealine'),
        ];

        $form['notify'] = [
            '#type' => 'select',
            '#title' => $this->t('Notification period'),
            '#options' => $notify,
            '#disabled' => isset($read['notify']) ? $read['notify'] : false,
            '#default_value' => isset($data->notify) ? $data->notify : null,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        
        $form['actions']['record'] = [
            '#type' => 'submit',
            '#id' => 'task-record',
            '#value' => $this->t('Record'),
            //'#attributes' => array('class' => array('use-ajax-submit')),
            '#ajax' => [
                'callback' => '::ajaxSubmit',
            ],
        ];

        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

        // static::ajaxSubmit() requires data-drupal-selector to be the same between
        // the various Ajax requests. 
        // @todo Remove this workaround once https://www.drupal.org/node/2897377 
        $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);

        return $form;
    }

    /**
     * Callback
     */
    public function getRate(array &$form, FormStateInterface $form_state) {
        $form['rate']["#markup"] = $form_state->getValue('completion_rate') . " %";
        return $form['rate'];
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
                        $query = Database::getConnection()->select('users_field_data', 'u');
                        $query->fields('u', ['uid']);
                        $query->condition('name', trim($u));
                        $id = $query->execute()->fetchField();
                        if ($id == '') {
                            $error .= $u . ',';
                        } else {
                            $notify_who .= $id . ',';
                        }
                    }
                }

                if ($error <> '') {
                    $error = rtrim($error, ',');
                    $form_state->setErrorByName("notify_who", $this->t('Invalid user(s)') . ': ' . rtrim($error, ','));
                } else {
                    $form_state->setValue('notify_who', $notify_who);
                }
            }
            $or = $form_state->getValue('notify') == 2 || $form_state->getValue('notify') == 3 || $form_state->getValue('notify') == 4;

            if ($form_state->getValue('end') == '' && ($or)) {
                $form_state->setErrorByName("end", $this->t('You need a deadline for the selected period.') . ': ' . $error);
            }

           
        }
    }

    /**
     * {@inheritDoc}
     */
    public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        if ($redirect_url = $this->getRedirectUrl($form, $form_state)) {
            $command = new RedirectCommand($redirect_url);
        } else {
            // always provides a destination.
            throw new \Exception("No destination provided by form");
        }
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

    /**
     * Gets the form's redirect URL from the form.
     *
     * @return \Drupal\Core\Url|null
     *   The redirect URL or NULL if dialog should just be closed.
     */
    protected function getRedirectUrl(array $form, FormStateInterface $form_state) {
       
        if ($form_state->getTriggeringElement()['#id'] == 'task-record') {
            return $form_state->getValue('destination');            
        }
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        switch ($form_state->getValue('for_doc')) {
            case 'invoice':
                $tb = 'ek_sales_invoice_tasks';
                $dest = 'ek_sales.invoices.list';
                break;
            case 'purchase':
                $tb = 'ek_sales_purchase_tasks';
                $dest = 'ek_sales.purchases.list';
                break;
        }
        if ($form_state->getValue('delete') == 1) {
            $update = Database::getConnection('external_db', 'external_db')
                    ->delete($tb)
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        } else {
            if ($form_state->getValue('notify_who') != '') {
                $notify_who = rtrim($form_state->getValue('notify_who'), ',');
            } else {
                $notify_who = null;
            }


            $fields = array(
                'serial' => $form_state->getValue('for_serial'),
                'event' => Xss::filter($form_state->getValue('event')),
                'uid' => $form_state->getValue('uid'),
                'task' => Xss::filter($form_state->getValue('task')),
                'start' => strtotime($form_state->getValue('start')),
                'end' => strtotime($form_state->getValue('end')),
                'completion_rate' => $form_state->getValue('completion_rate'),
                'notify' => $form_state->getValue('notify'),
                'notify_who' => $notify_who,
                'color' => $form_state->getValue('color'),
            );

            if ($form_state->getValue('for_id') != null) {
                $update = Database::getConnection('external_db', 'external_db')
                        ->update($tb)->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
            } else {
                $update = Database::getConnection('external_db', 'external_db')
                        ->insert($tb)->fields($fields)
                        ->execute();
            }
        }


        if ($update) {
            Cache::invalidateTags(['sales_task']);
        }
    }

}
