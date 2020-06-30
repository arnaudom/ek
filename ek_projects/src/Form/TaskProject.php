<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\TaskProject.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to record and edit project tasks.
 */
class TaskProject extends FormBase {

    use AjaxFormHelperTrait;
    
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
        return 'ek_task_project';
    }

    /**
     * {@inheritdoc}
     * @param pid: project id
     * @param id: task id
     */
    public function buildForm(array $form, FormStateInterface $form_state, $param = null) {
        $access = AccessCheck::GetCountryByUser();
        $perm = \Drupal::currentUser()->hasPermission('delete_project_task');

        if ($param['edit'] == true) {
            // edit a task mode
            $form['for_id'] = [
                '#type' => 'hidden',
                '#value' => $param['id'],
            ];

            //default 
            $read = ['delete_description' => $this->t('Delete this task')];
            if ($param['owner'] == true && $param['delete'] == false) {
                // if the task is assigned to current user, limited edition available
                $read = [
                    'delete_description' => $this->t('You need permission to delete this task'),
                    'delete' => 'disable',
                    'event' => 'readonly',
                    'uid' => true,
                    'task' => 'readonly',
                    'start' => 'readonly',
                    'end' => 'readonly',
                    'class' => 'date',
                    'notify' => false,
                    'notify_who' => false,
                ];
            } elseif ($param['owner'] == false && $param['delete'] == false) {
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
        }

        $form['edit_task_project'] = [
            '#type' => 'item',
            '#markup' => $this->t('Project ref. @p', array('@p' => $param['data']->pcode)),
        ];


        $form['for_pcode'] = [
            '#type' => 'hidden',
            '#value' => $param['data']->pcode,
        ];

        $form['for_pid'] = [
            '#type' => 'hidden',
            '#value' => $param['pid'],
        ];


        if ($param['edit']) {
            $form['delete'] = [
                '#type' => 'checkbox',
                '#title' => $read['delete_description'],
                '#attributes' => isset($read['delete']) ? array('disabled' => $read['delete']) : null,
            ];
        }

        if ($param['edit']) {

            $r = isset($param['data']->completion_rate) ? $param['data']->completion_rate : 0;
            $form['completion_rate'] = [
                '#type' => 'range',
                '#min' => 0,
                '#max' => 100,
                '#required' => true,
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

            $form['rate'] = array(
                '#type' => 'item',
                '#markup' => $r . " %",
                '#prefix' => "<div  id='rate'>",
                '#suffix' => "</div></div></div>",
            );
        }
        $form['event'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Event name'),
            '#size' => 30,
            '#maxlength' => 100,
            '#default_value' => isset($param['data']->event) ? $param['data']->event : null,
            '#attributes' => isset($read['event']) ? array('readonly' => $read['event']) : null,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];


        $form['uid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => \Drupal\ek_admin\Access\AccessCheck::listUsers(),
            '#required' => true,
            '#default_value' => isset($param['data']->uid) ? $param['data']->uid : null,
            '#title' => $this->t('Assigned to'),
            '#disabled' => isset($read['uid']) ? $read['uid'] : false,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['task'] = [
            '#type' => 'textarea',
            '#rows' => 3,
            '#title' => $this->t('Task description'),
            '#required' => true,
            '#default_value' => isset($param['data']->task) ? $param['data']->task : null,
            '#attributes' => isset($read['task']) ? array('readonly' => $read['task']) : null,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['start'] = [
            '#type' => 'date',
            '#size' => 12,
            '#required' => true,
            '#default_value' => isset($param['data']->start) ? date('Y-m-d', $param['data']->start) : date('Y-m-d'),
            '#title' => $this->t('Starting'),
            '#prefix' => "<div class='container-inline'>",
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['end'] = [
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($param['data']->end) ? date('Y-m-d', $param['data']->end) : null,
            '#title' => $this->t('ending'),
            '#suffix' => '</div>',
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['color'] = [
            '#type' => 'color',
            '#title' => $this->t('Color'),
            '#default_value' => isset($param['data']->color) ? $param['data']->color : '#80ff80',
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        if (isset($param['data']->notify_who) && $param['data']->notify_who != null) {
            $who = explode(',', $param['data']->notify_who);
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
            '#id' => 'edit-email',
            '#attributes' => array('placeholder' => $this->t('enter users names separated by comma (autocomplete enabled).')),
            '#default_value' => $list,
            '#disabled' => isset($read['notify_who']) ? $read['notify_who'] : false,
            //'#attributes' => isset($read['task']) ? array('readonly' => $read['task']) : null,
            '#attached' => [
                'library' => [
                    'ek_projects/ek_projects_autocomplete',
                ],
            ],
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $notify = array(
            '0' => $this->t('Never'),
            '5' => $this->t('Daily'),
            '1' => $this->t('Weekly'),
            '6' => $this->t('Monthly'),
            '2' => $this->t('5 days before deadline'),
            '3' => $this->t('3 days before dealine'),
            '4' => $this->t('1 day before dealine'),
        );

        $form['notify'] = [
            '#type' => 'select',
            '#title' => $this->t('Notification period'),
            '#options' => $notify,
            '#default_value' => isset($param['data']->notify) ? $param['data']->notify : null,
            '#disabled' => isset($read['notify']) ? $read['notify'] : false,
            '#states' => [
                'visible' => [":input[name='delete']" => ['checked' => false]],
            ],
        ];

        $form['actions'] = ['#type' => 'actions'];

        $form['actions']['record'] = [
            '#type' => 'submit',
            '#id' => 'task-record',
            '#value' => $this->t('Record'),
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
        $error = '';
        if ($form_state->getValue('delete') != 1) {
            if ($form_state->getValue('notify_who') != '') {
                $users = explode(',', $form_state->getValue('notify_who'));

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
                    //$error = $this->t('invalid user(s)') . ': ' .rtrim($error, ',');
                    $form_state->setErrorByName("notify_who", $this->t('Invalid user(s)') . ': ' . rtrim($error, ','));
                } else {
                    $form_state->setValue('notify_who', $notify_who);
                }
            }
            $or = $form_state->getValue('notify') == 2 || $form_state->getValue('notify') == 3 || $form_state->getValue('notify') == 4;

            if ($form_state->getValue('end') == '' && ($or)) {
                //$error .= '<br/>' . $this->t('You need a deadline for the selected period.');
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
            return "/projects/project/" . $form_state->getValue('for_pid') . "?s2=true#ps2";
        }
        
    }
    
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('delete') == 1) {
            $update = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_project_tasks')
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        } else {
            if ($form_state->getValue('notify_who') != '') {
                $notify_who = rtrim($form_state->getValue('notify_who'), ',');
            } else {
                $notify_who = null;
            }

            if ($form_state->getValue('completion_rate') == '') {
                $completion = 0;
            } else {
                $completion = $form_state->getValue('completion_rate');
            }


            $fields = array(
                'pcode' => $form_state->getValue('for_pcode'),
                'event' => Xss::filter($form_state->getValue('event')),
                'uid' => $form_state->getValue('uid'),
                'completion_rate' => $completion,
                'task' => Xss::filter($form_state->getValue('task')),
                'start' => strtotime($form_state->getValue('start')),
                'end' => strtotime($form_state->getValue('end')),
                'notify' => $form_state->getValue('notify'),
                'notify_who' => $notify_who,
                'color' => $form_state->getValue('color'),
            );

            if ($form_state->getValue('for_id') != null) {
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_tasks')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
            } else {
                $update = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_project_tasks')->fields($fields)
                        ->execute();
            }

            $acc = \Drupal\user\Entity\User::load($form_state->getValue('uid'));
            $name = '';
            if ($acc) {
                $name = $acc->getAccountName();
            }
            $param = serialize(
                    array(
                        'id' => $form_state->getValue('for_pid'),
                        'field' => $this->t('New task added for') . ": " . $name,
                        'value' => Xss::filter($form_state->getValue('task'))
                    )
            );
            ProjectData::notify_user($param);
        }

        Cache::invalidateTags(['project_task_block']);
    }

}
