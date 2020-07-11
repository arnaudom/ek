<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\Alert.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_sales\SalesSettings;

/**
 * Provides a form to record and edit email alerts.
 */
class Alert extends FormBase {

    use AjaxFormHelperTrait;

    /**
     *    
     */
    public function __construct() {
        
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_alert_document';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $data = null, $param = null) {

        $w = unserialize($data->alert_who);
        $alert_who = explode(',', $w['users']);
        $list = [];
        foreach ($alert_who as $k => $uid) {
            $acc = \Drupal\user\Entity\User::load($uid);
            if ($acc) {
                $list[] = $acc->getAccountName();
            }
        }

        $client = $w['client'];
        $settings = new SalesSettings($data->head);

        $form['destination'] = [
            '#type' => 'hidden',
            '#value' => $param['destination'],
        ];

        $form['document'] = [
            '#type' => 'hidden',
            '#value' => $param['doc'],
        ];

        $form['info1'] = [
            '#type' => 'item',
            '#markup' => $this->t('@d ref. @p', ['@d' => ucfirst($param['doc']), '@p' => $data->serial]),
        ];
        $form['info2'] = [
            '#type' => 'item',
            '#markup' => $this->t('Automatic alert will be sent to the list of users for late payment'),
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $data->id,
        ];

        $form['astatus'] = [
            '#type' => 'select',
            '#description' => $this->t('Switch on or off for all alerts'),
            '#options' => [0 => $this->t('off'), 1 => $this->t('on')],
            '#default_value' => $data->alert,
        ];

        $form['users'] = [
            '#type' => 'textarea',
            '#description' => $this->t('Your company users'),
            '#rows' => 2,
            '#id' => 'edit-users',
            '#attributes' => ['placeholder' => $this->t('enter users separated by comma (autocomplete enabled).')],
            '#default_value' => (!empty($list)) ? implode(',', $list) . ',' : null,
            '#attached' => [
                'library' => ['ek_admin/ek_admim.users_autocomplete'],
            ],
            '#states' => [
                'invisible' => [
                    "select[name='astatus']" => ['value' => 0],
                ],
            ],
        ];

        if ($param['doc'] == 'invoice') {
            $form['client'] = [
                '#type' => 'details',
                '#title' => $this->t('Client alert'),
                '#open' => empty($w['client']) ? false : true,
            ];

            $form['client']['info'] = [
                '#type' => 'item',
                '#markup' => $this->t('Automatic alert will be sent to a client'),
            ];
            $link = Url::fromRoute('ek_sales_settings')->toString();
            $form['client']['aging'] = [
                '#type' => 'item',
                '#markup' => $this->t('Message threshold: @d day(s) after due date.', ['@d' => $settings->get('shortdue')]),
                '#description' => $this->t('You can change this number in <a href="@l">sales settings</a>.',['@l' => $link]),
            ];


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'ab')
                    ->fields('ab', ['id', 'contact_name', 'email'])
                    ->condition('abid', $data->client)
                    ->execute();
            $options = [];
            while ($c = $query->fetchObject()) {
                $options[$c->id] = $c->contact_name . " <" . $c->email . ">";
            }

            $form['client']['frequency'] = [
                '#type' => 'select',
                '#options' => [0 => $this->t('Disabled'), '8' => $this->t('Days'), '1' => $this->t('Mon'), '2' => $this->t('Tue'), '3' => $this->t('Wed'), '4' => $this->t('Thu'), '5' => $this->t('Fri'), '6' => $this->t('Sat'), '7' => $this->t('Sun')],
                '#default_value' => isset($w['client']['frequency']) ? $w['client']['frequency'] : 0,
                '#title' => $this->t('Send every'),
                '#states' => [
                    'invisible' => [
                        "select[name='astatus']" => ['value' => 0],
                    ],
                ],
            ];
            $form['client']['contact'] = [
                '#type' => 'select',
                '#options' => $options,
                '#default_value' => isset($w['client']['id']) ? $w['client']['id'] : null,
                '#title' => $this->t('Send to'),
                '#states' => [
                    'invisible' => [
                        "select[name='frequency']" => ['value' => 0],
                    ],
                ],
            ];

            $form['client']['body'] = [
                '#type' => 'item',
                '#markup' => '"' . $settings->get('reminder_body') . '"',
                '#title' => $this->t('Info : custom text attached to alert:'),
                '#description' => $this->t('You can change this text in sales settings.'),
                '#states' => [
                    'invisible' => [
                        "select[name='frequency']" => ['value' => 0],
                    ],
                ],
            ];
        }




        $form['actions']['record'] = [
            '#type' => 'submit',
            '#id' => 'alert-record',
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
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('users') == '' && $form_state->getValue('astatus') == 1) {
            $form_state->setErrorByName('users', $this->t('there is no user'));
        } else {
            $users = explode(',', $form_state->getValue('users'));
            $error = '';
            $list = '';
            foreach ($users as $u) {
                if (trim($u) != null) {
                    $uname = trim($u);
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u', ['uid']);
                    $query->condition('name', $uname);
                    $id = $query->execute()->fetchField();
                    if (!$id) {
                        $error .= $uname . ' ';
                    } else {
                        $list .= $id . ',';
                    }
                }
            }
            if ($list != '') {
                $form_state->set('list', rtrim($list, ","));
            }
            if ($error != '') {
                $form_state->setErrorByName("users", $this->t('Invalid user(s)') . ': ' . $error);
            }
            if (!null == $form_state->getValue('frequency') && $form_state->getValue('frequency') > 0) {
                
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

        if ($form_state->getTriggeringElement()['#id'] == 'alert-record') {
            return $form_state->getValue('destination');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        if($form_state->getValue('document') == 'invoice') {
            $w = [
                'users' => $form_state->get('list'),
                'client' => ['frequency' => $form_state->getValue('frequency'), 'id' => $form_state->getValue('contact')],
            ];
            $fields = array(
                'alert' => $form_state->getValue('astatus'),
                'alert_who' => serialize($w),
            );
            
            $table = 'ek_sales_invoice';

        }
        
        if($form_state->getValue('document') == 'purchase') {
            $w = [
                'users' => $form_state->get('list'),
                'client' => [],
            ];
            $fields = array(
                'alert' => $form_state->getValue('astatus'),
                'alert_who' => serialize($w),
            );

            $table = 'ek_sales_purchase';
        }
        
        $update = Database::getConnection('external_db', 'external_db')
                    ->update($table)->fields($fields)
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        
    }

}
