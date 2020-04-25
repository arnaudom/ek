<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\AlertInvoice.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to reecord and edit invoice email alerts.
 */
class AlertInvoice extends FormBase {

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
        return 'ek_sales_alert_invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $access = AccessCheck::GetCompanyByUser();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_invoice', 'i');
        $or1 = $query->orConditionGroup();
        $or1->condition('head', $access, 'IN');
        $or1->condition('allocation', $access, 'IN');


        $data = $query
                ->fields('i', array('id', 'serial', 'alert', 'alert_who'))
                ->condition($or1)
                ->condition('i.id', $id, '=')
                ->execute()
                ->fetchObject();

        if ($data) {
            $alert_who = explode(',', $data->alert_who);
            $list = [];
            foreach ($alert_who as $k => $uid) {
                $acc = \Drupal\user\Entity\User::load($uid);
                if ($acc) {
                    $list[] = $acc->getAccountName();
                }
            }



            $form['edit_invoice'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Invoice ref. @p', array('@p' => $data->serial)),
            );
            $form['info'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Automatic alert will be send to the list of users for invoices not paid or payment received'),
            );

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $form['status'] = array(
                '#type' => 'select',
                '#options' => array('0' => $this->t('off'), '1' => $this->t('on')),
                '#default_value' => $data->alert,
            );
            if ($this->moduleHandler->moduleExists('ek_admin')) {
                $form['users'] = array(
                    '#type' => 'textarea',
                    '#rows' => 2,
                    '#attributes' => array('placeholder' => $this->t('enter users separated by comma (autocomplete enabled).')),
                    '#default_value' => (!empty($list)) ? implode(',', $list) . ',' : null,
                    '#attached' => array(
                        'library' => array(
                            'ek_admin/ek_admim.users_autocomplete'
                        ),
                    ),
                );
            } else {
                $form['users'] = array(
                    '#type' => 'textarea',
                    '#rows' => 2,
                    '#default_value' => (!empty($list)) ? implode(',', $list) . ',' : null,
                    '#required' => true,
                    '#attributes' => array('placeholder' => $this->t('enter email addresses separated by comma.')),
                );
            }

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
            );
            $form['actions']['cancel'] = array(
                '#markup' => "<a href='" . Url::fromRoute('ek_sales.invoices.list')->toString() . "' >" . $this->t('Cancel') . "</a>",
            );
        } else {
            $form['info'] = array(
                '#type' => 'item',
                '#markup' => $this->t('You cannot edit this invoice alert.'),
            );

            $form['cancel'] = array(
                '#markup' => "<a href='" . Url::fromRoute('ek_sales.invoices.list')->toString() . "' >" . $this->t('Return') . "</a>",
            );
        }


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('users') == '' && $form_state->getValue('status') == 1) {
            $form_state->setErrorByName('email', $this->t('there is no user'));
        } else {
            $users = explode(',', $form_state->getValue('users'));
            $error = '';
            $list = '';
            foreach ($users as $u) {
                if (trim($u) != null) {
                    //check it is a registered user
                    //$query = "SELECT uid from {users_field_data} WHERE name=:u";
                    //$id = db_query($query, array(':u' => $uname))->fetchField();
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
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $fields = array(
            'alert' => $form_state->getValue('status'),
            'alert_who' => $form_state->get('list'),
        );

        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_invoice')->fields($fields)
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();

        if ($update) {
            $form_state->setRedirect('ek_sales.invoices.list');
        }
    }

}
