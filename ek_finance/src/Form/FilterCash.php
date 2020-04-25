<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterCash.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\CurrencyData;

/**
 * Provides a form to filter cash balance.
 */
class FilterCash extends FormBase {

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
        return 'cash_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $to = date('Y-m-d');
        $from = date('Y-m-') . '01';


        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => true,
                //'#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );

        $form['filters']['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['cfilter']['from']) ? $_SESSION['cfilter']['from'] : $from,
            '#title' => $this->t('from'),
            '#prefix' => "<div class='container-inline'>",
        );

        $form['filters']['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['cfilter']['to']) ? $_SESSION['cfilter']['to'] : $to,
            //'#attributes' => array('placeholder' => $this->t('to')),
            '#title' => $this->t('to'),
        );

        $form['filters']['currency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => CurrencyData::listcurrency(1),
            '#required' => true,
            '#default_value' => isset($_SESSION['cfilter']['currency']) ? $_SESSION['cfilter']['currency'] : null,
            '#suffix' => '</div>',
        );

        $form['filters']['type'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#title' => $this->t('type'),
            '#options' => array('0' => $this->t('company'), '1' => $this->t('employee')),
            '#required' => true,
            '#validated' => true,
            '#prefix' => "<br/><div class='container-inline'>",
            '#disabled' => isset($_SESSION['cfilter']['type']) ? true : false,
            '#default_value' => isset($_SESSION['cfilter']['type']) ? $_SESSION['cfilter']['type'] : null,
            '#ajax' => array(
                'callback' => array($this, 'get_accounts'),
                'wrapper' => 'accounts_',
            ),
        );

        if (null !== $form_state->getValue('type') || isset($_SESSION['cfilter']['type'])) {
            if (isset($_SESSION['cfilter']['type'])) {
                $type = $_SESSION['cfilter']['type'];
            } else {
                $type = $form_state->getValue('type');
            }

            switch ($type) {
                case '1':
                    $i = 1;
                    $access = AccessCheck::GetCompanyByUser();
                    $company = implode(',', $access);
                    $query = "SELECT DISTINCT uid from {ek_cash} WHERE FIND_IN_SET (coid, :c )";
                    $a = array(':c' => $company);
                    $uid = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a);
                    $list = array();

                    while ($u = $uid->fetchObject()) {
                        $uaccount = \Drupal\user\Entity\User::load($u->uid);
                        if ($uaccount) {
                            $list[$u->uid] = $uaccount->getAccountName();
                        } else {
                            $list[$u->uid] = $this->t('Unknown') . " " . $i;
                            $i++;
                        }
                        //$name = db_query('SELECT name from {users_field_data} WHERE uid = :u', array(':u' => $u->uid))
                        //        ->fetchField();
                        // if($name == '') {
                        //     $name = $this->t('Unknown') . " " . $i;
                        //     $i++;
                        // }
                        // $list[$u->uid] = $name;
                    }
                    natcasesort($list);
                    break;
                case '0':
                    $list = AccessCheck::CompanyListByUid();
                    break;
            }
        } else {
            $_SESSION['cfilter'] = [];
        }


        $form['filters']['account'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => isset($list) ? $list : array(),
            '#required' => true,
            '#default_value' => isset($_SESSION['cfilter']['account']) ? $_SESSION['cfilter']['account'] : null,
            '#title' => $this->t('account'),
            '#prefix' => "<div id='accounts_'>",
            '#suffix' => '</div></div>',
        );
        $form['alert'] = array(
            '#type' => 'item',
            '#markup' => '',
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
                //'#suffix' => "</div>",
        );

        if (!empty($_SESSION['cfilter']['filter'])) {
            $form['filters']['actions']['reset'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => array(),
                '#submit' => array(
                    array($this, 'resetForm'),
                ),
            );
        }
        return $form;
    }

    /**
     * Callback
     */
    public function get_accounts(array &$form, FormStateInterface $form_state) {
        return $form['filters']['account'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (strtotime($form_state->getValue('to')) < strtotime($form_state->getValue('from'))) {
            $form_state->setErrorByName("from", $this->t('Start date is higher than ending date'));
        }
        //cash is compiled by single year only.
        //if date span across 2 years, the first state is recalculated
        if (date('Y', strtotime($form_state->getValue('from'))) < date('Y', strtotime($form_state->getValue('to')))) {
            $form_state->setValueForElement($form['filters']["from"], date('Y', strtotime($form_state->getValue('to'))) . '-01-01');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['cfilter']['from'] = $form_state->getValue('from');
        $_SESSION['cfilter']['to'] = $form_state->getValue('to');
        $_SESSION['cfilter']['currency'] = $form_state->getValue('currency');
        $_SESSION['cfilter']['type'] = $form_state->getValue('type');
        $_SESSION['cfilter']['account'] = $form_state->getValue('account');
        $_SESSION['cfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['cfilter'] = array();
    }

}
