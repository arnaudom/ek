<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterExpenses.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_finance\AidList;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to filter Expenses.
 */
class FilterExpenses extends FormBase {

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
        $this->settings = new FinanceSettings();
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
        return 'expenses_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $query = "SELECT date from {ek_journal} order by date DESC limit 1";
        $to = Database::getConnection('external_db', 'external_db')->query($query)->fetchObject();
        $from = date('Y-m') . "-01";
        $open = true;
        if (isset($_SESSION['efilter']['filter']) && $_SESSION['efilter']['filter'] == 1) {
            $open = false;
        }
        $form['filters'] = [
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => $open,
        ];

        $coid = [];
        $coid += AccessCheck::CompanyListByUid();

        $form['filters']['filter'] = [
            '#type' => 'hidden',
            '#value' => 'filter',
        ];

        $form['filters'][0]['keyword'] = [
            '#type' => 'textfield',
            '#maxlength' => 150,
            '#attributes' => ['placeholder' => $this->t('Search with keyword, ref No.')],
            '#default_value' => isset($_SESSION['efilter']['keyword']) ? $_SESSION['efilter']['keyword'] : null,
        ];

        $form['filters'][1]['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $coid,
            '#default_value' => isset($_SESSION['efilter']['coid']) ? $_SESSION['efilter']['coid'] : null,
            '#title' => $this->t('company'),
            '#ajax' => [
                'callback' => array($this, 'set_coid'),
                'wrapper' => 'add',
            ],
            '#prefix' => "<div class='table'><div class='row'><div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => ['filled' => true]],
                'required' => [':input[name="keyword"]' => ['filled' => false]],
            ],
        ];

        if ($form_state->getValue('coid')) {
            $aid = ['%' => $this->t('Any')];
            $chart = $this->settings->get('chart');
            $aid += AidList::listaid($form_state->getValue('coid'), [$chart['liabilities'], $chart['cos'], $chart['expenses'], $chart['other_expenses']], 1);
            $_SESSION['efilter']['options'] = $aid;
        }

        $form['filters'][1]["aid"] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => isset($_SESSION['efilter']['options']) ? $_SESSION['efilter']['options'] : [],
            '#title' => $this->t('class'),
            '#default_value' => isset($_SESSION['efilter']['aid']) ? $_SESSION['efilter']['aid'] : null,
            '#attributes' => ['style' => array('width:200px;')],
            '#prefix' => "<div id='add'  class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
            ],
        ];

        $allocation_options = ['0' => $this->t('Any')];
        $allocation_options += $coid;
        $form['filters'][1]["allocation"] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $allocation_options,
            '#default_value' => isset($_SESSION['efilter']['allocation']) ? $_SESSION['efilter']['allocation'] : 0,
            '#title' => $this->t('Allocation'),
            '#attributes' => array('style' => array('width:200px;')),
            '#prefix' => "<div class='cell cellfloat'>",
            '#suffix' => '</div></div></div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
            ],
        ];
        $form['filters'][2]['from'] = [
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['efilter']['from']) ? $_SESSION['efilter']['from'] : date('Y-m') . "-01",
            //'#attributes' => array('placeholder'=>t('from')),
            '#prefix' => "<div class=''><div class='row'><div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#title' => $this->t('from'),
            '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
            ],
        ];

        $form['filters'][2]['to'] = [
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['efilter']['to']) ? $_SESSION['efilter']['to'] : $to->date,
            //'#attributes' => array('placeholder'=>t('to')),
            '#title' => $this->t('to'),
            '#prefix' => "<div class='cell cellfloat'>",
            '#suffix' => '</div></div></div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
            ],
        ];

        $supplier = ['%' => $this->t('Any')];
        $supplier += Database::getConnection('external_db', 'external_db')
                ->query("SELECT DISTINCT ab.id,name FROM {ek_address_book} ab INNER JOIN {ek_expenses} e ON e.suppliername=ab.id order by name")
                ->fetchAllKeyed();


        $form['filters'][3]['supplier'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $supplier,
            '#default_value' => isset($_SESSION['efilter']['supplier']) ? $_SESSION['efilter']['supplier'] : '%',
            '#title' => $this->t('supplier'),
            '#attributes' => array('style' => array('width:200px;')),
            '#prefix' => "<div class='table'><div class='row'><div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
            ],
        ];

        $client = ['%' => $this->t('Any')];
        $client += Database::getConnection('external_db', 'external_db')
                ->query("SELECT DISTINCT ab.id,name FROM {ek_address_book} ab INNER JOIN {ek_expenses} e ON e.clientname=ab.id order by name")
                ->fetchAllKeyed();


        $form['filters'][3]['client'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $client,
            '#default_value' => isset($_SESSION['efilter']['client']) ? $_SESSION['efilter']['client'] : '%',
            '#title' => $this->t('client'),
            '#attributes' => array('style' => array('width:200px;')),
            '#prefix' => "<div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#states' => [
                'invisible' => [':input[name="keyword"]' => array('filled' => true),],
            ],
        ];

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $pcode = ['%' => $this->t('Any'), 'na' => $this->t('not applicable')];
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e');
            $query->fields('e', ['id', 'pcode']);
            $query->condition('pcode', 'n/a', '<>');
            $query->distinct();
            $list = $query->execute()->fetchAllKeyed();

            $pcode += \Drupal\ek_projects\ProjectData::format_project_list($list);

            $form['filters'][3]['pcode'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $pcode,
                '#default_value' => isset($_SESSION['efilter']['pcode']) ? $_SESSION['efilter']['pcode'] : '%',
                '#title' => $this->t('project'),
                '#attributes' => array('style' => array('width:200px;')),
                '#prefix' => "<div class='cell cellfloat'>",
                '#suffix' => '</div></div></div>',
                '#states' => [
                    'invisible' => [':input[name="keyword"]' => array('filled' => true),],
                ],
            ];
        } else {
            $form['filters'][3]['pcode'] = [
                '#type' => 'hidden',
                '#value' => '%',
                '#suffix' => '</div></div>',
            ];
        }
        
        $op = ['%' => $this->t('Any')];
        $op += \Drupal\ek_finance\CurrencyData::listcurrency(1);
        $form['filters']['currency'] = [
                '#type' => 'select',
                '#options' => $op,
                '#title' => $this->t('currency'),
                '#default_value' => isset($_SESSION['efilter']['currency']) ? $_SESSION['efilter']['currency'] : '%',
                '#states' => [
                    'invisible' => [':input[name="keyword"]' => ['filled' => true],],
                ],
            ];
        
        $form['filters']['rows'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [25 => '25', 50 => '50', 100 => '100', 200 => '200', 500 => '500', 1000 => '1000', 2000 => '2000', 5000 => '5000'],
            '#default_value' => isset($_SESSION['efilter']['rows']) ? $_SESSION['efilter']['rows'] : '25',
            '#title' => $this->t('show rows'),
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

        if (!empty($_SESSION['efilter'])) {
            $form['filters']['actions']['reset'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => array(),
                '#submit' => array(array($this, 'resetForm')),
            );
        }
        return $form;
    }

    /**
     * callback functions
     */
    public function set_coid(array &$form, FormStateInterface $form_state) {

        //return aid list

        return $form['filters'][1]['aid'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('keyword') == '') {
            //check input if filter not by keyword

            if ($form_state->getValue('coid') == 0) {
                $form_state->setErrorByName("coid", $this->t('Company not selected'));
            }

            if ($form_state->getValue('aid') == '') {
                $form_state->setErrorByName("aid", $this->t('Account not selected'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['efilter']['from'] = $form_state->getValue('from');
        $_SESSION['efilter']['to'] = $form_state->getValue('to');
        $_SESSION['efilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['efilter']['allocation'] = $form_state->getValue('allocation');
        $_SESSION['efilter']['aid'] = $form_state->getValue('aid');
        $_SESSION['efilter']['supplier'] = $form_state->getValue('supplier');
        $_SESSION['efilter']['client'] = $form_state->getValue('client');
        $_SESSION['efilter']['pcode'] = $form_state->getValue('pcode');
        $_SESSION['efilter']['keyword'] = $form_state->getValue('keyword');
        $_SESSION['efilter']['currency'] = $form_state->getValue('currency');
        $_SESSION['efilter']['rows'] = $form_state->getValue('rows');
        $_SESSION['efilter']['filter'] = 1;
        $aid = array('%' => $this->t('Any'));
        $chart = $this->settings->get('chart');
        $aid += AidList::listaid($form_state->getValue('coid'), array($chart['liabilities'], $chart['cos'], $chart['expenses'], $chart['other_expenses']), 1);
        $_SESSION['efilter']['options'] = $aid;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['efilter'] = array();
    }

}
