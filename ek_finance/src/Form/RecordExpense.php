<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\RecordExpense.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_address_book\AddressBookData;

/**
 * Provides a form to record an expense entry.
 */
class RecordExpense extends FormBase {

    /**
     * The file storage service.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $fileStorage;

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
    public function __construct(ModuleHandler $module_handler, EntityStorageInterface $file_storage) {
        $this->moduleHandler = $module_handler;
        $this->fileStorage = $file_storage;
        $this->settings = new FinanceSettings();
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler'), $container->get('entity_type.manager')->getStorage('file')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_expense_record';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $clone = null) {
        
        // store rounding settings for alteration option
        $form_state->set('taxrounding', $this->rounding);
        $form_state->set('amountrounding', $this->rounding);
        $recordProvision = $this->settings->get('recordProvision');
        $chart = $this->settings->get('chart');
        if (null !== $this->settings->get('expenseAttachmentFormat')) {
            $ext_format = $this->settings->get('expenseAttachmentFormat');
        } else {
            $ext_format = 'png jpg jpeg doc docx xls xlsx odt ods odp pdf rar rtf zip';
        }
        if (null !== $this->settings->get('expenseAttachmentSize')) {
            $ext_size = $this->settings->get('expenseAttachmentSize') * 1000000;
        } else {
            $ext_size = '500000';
        }
        $check = [];
        $tax = [];
        $credit = null;
        if (empty($chart)) {
            $alert = "<div id='fx' class='messages messages--warning'>" . t(
                            'You did not set the accounts chart structure. Go to <a href="@url">settings</a>.', array('@url' => Url::fromRoute('ek_finance.admin.settings', array(), array())->toString())
                    ) . "</div>";
            $form['alert'] = array(
                '#type' => 'item',
                '#weight' => -17,
                '#markup' => $alert,
            );
        }
        $form_state->set('chart', $chart);

        $form['cancel'] = array(
            '#type' => 'item',
            '#weight' => -16,
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => Url::fromRoute('ek_finance.manage.list_expense', array(), array())->toString())),
        );

        if ($id != null && $form_state->get('num_items') == null) {

            // get expense data
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e');
            $query->fields('e');
            $query->condition('id', $id, '=');
            $expense = $query->execute()->fetchObject();
            //$query = "SELECT * from {ek_expenses} WHERE id=:id";
            //$expense = Database::getConnection('external_db', 'external_db')
              //      ->query($query, array(':id' => $id))
                //    ->fetchObject();
            
            // get journal data
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->fields('j');
            $query->condition('source', "expense%", 'LIKE');
            $query->condition('reference', $id, '=');
            $query->condition('type', 'debit', '=');
            $query->condition('exchange', '0', '=');
            $j_entry = $query->execute();
            
            //$query = "SELECT * from {ek_journal} WHERE source like :s and reference = :r AND type=:t AND exchange=:e";
            //$a = array(':s' => "expense%", ':r' => $id, ':t' => 'debit', ':e' => 0);
            //$j_entry = Database::getConnection('external_db', 'external_db')
            //        ->query($query, $a);

            $form_state->set('step', 2);
            $form_state->set('coid', $expense->company);
            $form_state->set('currency', $expense->currency);

            if ($expense->cash == 'Y' || $expense->cash == 'P') {
                
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->fields('j',['aid']);
                $query->condition('source', "expense%", 'LIKE');
                $query->condition('reference', $id, '=');
                $query->condition('type', 'credit', '=');
                $query->condition('exchange', '0', '=');
                $jCredit = $query->execute()->fetchField();
                
                //$query = "SELECT aid from {ek_journal} WHERE source like :s and reference = :r AND type=:t AND exchange=:e";
                //$a = array(':s' => "expense%", ':r' => $id, ':t' => 'credit', ':e' => 0);
                //$jCredit = Database::getConnection('external_db', 'external_db')
                //        ->query($query, $a)
                //        ->fetchField();
                if ($expense->cash == 'Y') {
                    $credit = $expense->currency . '-' . $jCredit;
                } else {
                    $credit = 'P';
                }
            } else {
                $credit = $expense->cash;
            }

            // list of accounts chart is defined in the general finance settings
            // The chart structure is as follow
            // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 'other_liabilities', 'other_income', 'other_expenses'

            $opt1 = [$chart['assets'], $chart['liabilities'], $chart['cos'], $chart['expenses'], $chart['other_expenses']];
            $opt2 = [$chart['assets'], $chart['liabilities']];

            $form_state->set('AidOptions', AidList::listaid($expense->company, $opt1, 1));
            $form_state->set('ProvisionOptions', AidList::listaid($expense->company, $opt2, 1));

            $settings = new CompanySettings($expense->company);
            $form_state->set('stax_deduct', $settings->get('stax_deduct'));
            $form_state->set('stax_rate', $settings->get('stax_rate'));
            $form_state->set('stax_name', $settings->get('stax_name'));
            $stax_deduct_aid = $settings->get('stax_deduct_aid');
            $form_state->set('wtax_deduct', $settings->get('wtax_deduct'));
            $form_state->set('wtax_rate', $settings->get('wtax_rate'));
            $form_state->set('wtax_name', $settings->get('wtax_name'));
            $wtax_deduct_aid = $settings->get('wtax_deduct_aid');
            $form_state->set('ytax_deduct', $settings->get('ytax_deduct'));
            $form_state->set('ytax_rate', $settings->get('ytax_rate'));
            $form_state->set('ytax_name', $settings->get('ytax_name'));
            $ytax_deduct_aid = $settings->get('ytax_deduct_aid');

            $i = 1;
            //$tax = array();
            while ($d = $j_entry->fetchObject()) {
                if ($form_state->get('stax_deduct') == 1 && ($d->aid == $stax_deduct_aid || $d->aid == $wtax_deduct_aid)) {
                    // tax line
                    $tax[$i - 1] = "(" . $expense->currency . "  " . round($d->value, 2) . ")";
                    $check[$i - 1] = 1;
                    $selecttax[$i-1] = round($d->value, 2);
                } else {
                    $form_state->set("account" . $i, $d->aid);
                    if ($clone == 'clone') {
                        $form_state->set("pdate" . $i, date('Y-m-d'));
                    } else {
                        $form_state->set("pdate" . $i, $d->date);
                    }
                    $form_state->set("value" . $i, $d->value);
                    $form_state->set("comment" . $i, $expense->comment);
                    $check[$i - 1] = 0;
                    $tax[$i - 1] = null;
                    $i++;
                }
            }

            $n = $i - 1;
        } //edit data

        if ($id != null) {
            if ($clone != 'clone') {
                $form['edit'] = array(
                    '#type' => 'hidden',
                    '#value' => $id,
                );
            } else {
                $form['clone'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('Template expense from ref. @p . A new entry will be recorded.', array('@p' => $id)),
                    '#weight' => -100,
                );
            }
        }//edit tag


        if ($form_state->get('step') == '') {
            $step = 1;
        } else {
            $step = $form_state->get('storage_step');
        }
        $form_state->set('storage_step', $step);
        $CurrencyOptions = CurrencyData::listcurrency(1);
        $company = AccessCheck::CompanyListByUid();

        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->get('coid')) ? $form_state->get('coid') : null,
            '#title' => $this->t('company'),
            '#required' => true,
            '#weight' => -15,
            '#ajax' => array(
                'callback' => array($this, 'set_currency'),
                'wrapper' => 'currency',
            ),
        );

        $add = isset($expense->allocation) ? $company[$expense->allocation] : "";
        $form['allocation'] = array(
            '#type' => 'details',
            '#title' => $this->t('Allocation') . " " . $add,
            '#group' => '1',
            '#open' => false,
            '#weight' => -11,
        );
        $form['allocation']["change_location"] = array(
            '#type' => 'checkbox',
            '#default_value' => isset($expense->allocation) ? true : null,
            '#title' => $this->t('assign to other entity'),
            '#prefix' => "<div class='container-inline'>",
        );

        $form['allocation']['location'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => isset($expense->allocation) ? $expense->allocation : null,
            '#title' => $this->t('allocation'),
            '#required' => false,
            '#suffix' => "</div>",
            '#states' => array(
                'invisible' => array(
                    "input[name='change_location']" => array('checked' => false),
                ),
            ),
        );

        $form['credit'] = array(
            '#type' => 'details',
            '#title' => $this->t('Credit'),
            '#group' => '1',
            '#open' => true,
            '#weight' => -10,
        );

        $form['credit']['currency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $CurrencyOptions,
            '#required' => true,
            '#default_value' => ($form_state->get('currency')) ? $form_state->get('currency') : null,
            '#title' => $this->t('currency'),
            '#ajax' => array(
                'callback' => array($this, 'set_credit_account'),
                'wrapper' => 'credit',
            ),
            '#prefix' => "<div class='table'><div class='row'><div class='cell' id='currency'>",
            '#suffix' => '</div>',
        );

        // bank account
        if (($form_state->getValue('coid') || $form_state->get('coid')) && ($form_state->getValue('currency') || $form_state->get('currency'))) {
            $coid = $form_state->getValue('coid') ? $form_state->getValue('coid') : $form_state->get('coid');
            $currency = $form_state->getValue('currency') ? $form_state->getValue('currency') : $form_state->get('currency');

            $settings = new CompanySettings($coid);
            $aid = $settings->get('cash_account', $currency);
            $cash = [];
            if ($aid <> '') {
                $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
                $name = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':c' => $coid, ':a' => $aid))->fetchField();
                $key = $currency . "-" . $aid;
                $cash = array($key => $name);
            }
            $aid = $settings->get('cash2_account', $currency);
            if ($aid <> '') {
                $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
                $name = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':c' => $coid, ':a' => $aid))->fetchField();
                $key = $currency . "-" . $aid;
                $cash += array($key => $name);
            }
            //$options = array(0 => $this->t('- Select -'));
            $options[(string) $this->t('cash')] = $cash;
            $options[(string) $this->t('bank')] = BankData::listbankaccountsbyaid($coid, $currency);
            $alert = '';
            if (empty($options[(string) $this->t('cash')]) && empty($options[(string) $this->t('bank')])) {
                $alert = "<div id='fx' class='messages messages--warning'>"
                        . $this->t('You do not have any bank or cash payment accounts. Go to <a href="@url">settings</a>.', ['@url' => Url::fromRoute('ek_finance.manage.bank_list', [], [])->toString()]) . "</div>";
            }

            //provision option
            if ($recordProvision == '1' || $credit == 'P') {
                $options[(string) $this->t('provision')] = ['P' => $this->t('record as provision')];
            }
            //$form['credit']['bank_account']['#options'] = $options;
            //$form['credit']['bank_account']['#value'] = 0;
            //$form['credit']['bank_account']['#description'] = $alert;
            $form_state->set('bank_opt', $options);
            //$form_state->setRebuild();


            $form['credit']['bank_account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $form_state->get('bank_opt'),
                '#required' => true,
                '#default_value' => isset($credit) ? $credit : null,
                '#title' => $this->t('account payment'),
                '#prefix' => "<div id='credit' class='cell'>",
                '#suffix' => '</div>',
                '#description' => $alert,
                '#validated' => true,
                '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                '#ajax' => array(
                    'callback' => array($this, 'fx_rate'),
                    'wrapper' => 'fx',
                ),
            );
        } else {
            $form['credit']['bank_account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array(0 => $this->t('- Select -')),
                '#required' => true,
                '#default_value' => '',
                '#title' => $this->t('account payment'),
                '#prefix' => "<div id='credit' class='cell'>",
                '#suffix' => '</div>',
                '#description' => $this->t('Select a company first'),
                '#validated' => true,
                '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
            );
        }

        $form['credit']['fx_rate'] = array(
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 15,
            '#default_value' => isset($expense->rate) ? $expense->rate : null,
            '#required' => false,
            '#title' => $this->t('exchange rate'),
            '#description' => '',
            '#prefix' => "<div id='fx' class='cell'>",
            '#suffix' => '</div></div></div>',
        );

        // user acc
        // used to control cash payments and advances
        $form['user_acc'] = array(
            '#type' => 'details',
            '#title' => $this->t('User account'),
            '#group' => '1a',
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );

        if ($id != null && !null == $expense->employee && $expense->employee != 'n/a') {
            $user = \Drupal\user\Entity\User::load($expense->employee);
            if ($user) {
                $userName = $user->getAccountName;
            }
        }
        $form['user_acc']['user'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#default_value' => isset($userName) ? $userName : null,
            '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
            '#title' => $this->t('user account'),
            '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
            '#prefix' => "<div  class='container-inline'>",
        );

        $form['user_acc']['paid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array(0 => '', 'paid' => $this->t('paid'), 'no' => $this->t('not paid')),
            '#description' => $this->t('indicate "paid" if advanced by company or "not paid" if advanced by employee'),
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(
                    "input[name='user']" => array(
                        //array('value' => 'not applicable'),
                        array('value' => '')
                    ),
                ),
            ),
        );

        // References / tags
        $form['reference'] = array(
            '#type' => 'details',
            '#title' => $this->t('References'),
            '#group' => '2',
            '#open' => true,
        );


        $supplier = array('n/a' => $this->t('not applicable'));
        $supplier += AddressBookData::addresslist(2);


        $form['reference']['supplier'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $supplier,
            '#required' => true,
            '#default_value' => isset($expense->suppliername) ? $expense->suppliername : null,
            '#title' => $this->t('supplier'),
            '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
            '#prefix' => "<div  class='container-inline'>",
        );

        $client = array('n/a' => $this->t('not applicable'));
        $client += AddressBookData::addresslist(1);


        $form['reference']['client'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $client,
            '#required' => true,
            '#default_value' => isset($expense->clientname) ? $expense->clientname : null,
            '#title' => $this->t('client'),
            '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
            '#suffix' => '</div>',
        );

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            if (isset($expense->pcode) && $expense->pcode != 'n/a') {
                $thisPcode = $this->t('code') . ' ' . $expense->pcode;
            } else {
                $thisPcode = null;
            }
            $form['reference']['pcode'] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 150,
                //'#required' => TRUE,
                '#default_value' => $thisPcode,
                '#attributes' => array('placeholder' => $this->t('Ex. 123')),
                '#title' => $this->t('Project'),
                '#autocomplete_route_name' => 'ek_look_up_projects',
                '#autocomplete_route_parameters' => array('level' => 'all', 'status' => '0'),
            );
        } // project
        
        // provision type entry options
        if ($recordProvision == '1' || $credit == 'P') {
            $form['provision'] = array(
                '#type' => 'details',
                '#title' => $this->t('Provision'),
                '#group' => '2a',
                '#open' => true,
                '#attributes' => array('class' => array('container-inline')),
                '#states' => array(
                    'visible' => array(
                        "select[name='bank_account']" => array(
                            array('value' => 'P'),
                        ),
                    ),
                ),
            );
            $form['provision']['provision_account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $form_state->get('ProvisionOptions'),
                '#default_value' => isset($jCredit) ? $jCredit : null,
                '#attributes' => array('style' => array('width:250px;white-space:nowrap')),
                '#title' => $this->t('Provision account'),
                '#states' => array(
                    'visible' => array(
                        "select[name='bank_account']" => array(
                            array('value' => 'P'),
                        ),
                    ),
                ),
            );
        }

        // debits
        $form['debit'] = array(
            '#type' => 'details',
            '#title' => $this->t('Debits'),
            '#group' => '3',
            '#open' => true,
        );

        $form['debit']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => '+ ' . $this->t('Add item'),
            //'#limit_validation_errors' => array(),
            '#submit' => array(array($this, 'addForm')),
            '#prefix' => "<div id='add' class='right'>",
            '#suffix' => '</div>',
            '#attributes' => array('class' => array('button--add')),
            '#states' => array(
                // Hide data fieldset when coid is empty.
                'invisible' => array(
                    "select[name='coid']" => array('value' => ''),
                ),
            ),
        );


        if (isset($n)) {
            // reset the new rows items in edit mode
            $max = $form_state->get('num_items') + $n;
            $form_state->set('num_items', $max);
        } else {
            //new entry
            $max = $form_state->get('num_items');
            $n = 1;
        }
        $form['debit']['table'] = array(
            '#markup' => "<div class='table'>",
        );

        for ($i = 1; $i <= $max; $i++) {
            $form['debit']["account$i"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $form_state->get('AidOptions'),
                '#required' => true,
                '#default_value' => ($form_state->get("account$i")) ? $form_state->get("account$i") : null,
                '#attributes' => ['style' => ['width:100px;white-space:nowrap']],
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            if ($form_state->getValue("pdate1")) {
                $rowDate = $form_state->getValue("pdate1");
            } else {
                $rowDate = $rowDate = date('Y-m-d');
            }

            $form['debit']["pdate$i"] = array(
                '#type' => 'date',
                '#id' => "edit-from$i",
                '#size' => 16,
                '#required' => true,
                '#default_value' => ($form_state->get("pdate$i")) ? $form_state->get("pdate$i") : $rowDate,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            ///////////////////

            $form['debit']["value$i"] = array(
                '#type' => 'textfield',
                '#id' => 'value' . $i,
                '#size' => 18,
                '#maxlength' => 255,
                '#description' => '',
                '#default_value' => ($form_state->get("value$i")) ? $form_state->get("value$i") : null,
                '#attributes' => array('placeholder' => $this->t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            if ($form_state->get('stax_deduct') == 1) {
                $form['debit']["tv$i"] = array(
                    //'#type' => 'item',
                    //'#markup' => isset($tax[$i]) ? '' . $tax[$i] : null,
                    //'#prefix' => "<div class='cell' id='tval$i' >",
                    //'#suffix' => "</div>",
                );
                
                if ($form_state->get('wtax_rate') == null || $form_state->get('wtax_rate') == 0){
                // only 1 tax rate    
                    $form['debit']["tax$i"] = array(
                        '#type' => 'checkbox',
                        '#id' => 'tax-' . $i,
                        '#default_value' => isset($check[$i]) ? $check[$i] : null,
                        '#attributes' => array('title' => $this->t('add sales tax')),
                        '#prefix' => "<div class='cell' style='padding-right:2px' id='tval$i'>",
                        '#suffix' => "</div>",
                        '#description_display' => 'after',
                        '#description' => isset($tax[$i]) ? '' . $tax[$i] : null,
                        '#ajax' => array(
                            'callback' => array($this, "singletax"),
                            'wrapper' => "tval$i",
                            'progress' => array('message' => null),
                        ),
                    );
                } else { 
                    // select option 2 rates
                    if($clone == 'clone') {
                        $dv = "";
                        $desc = "";
                    } else {
                        $dv = isset($selecttax[$i]) ? round($selecttax[$i]/$form_state->get("value$i")*100,2) : null;
                        $desc = isset($tax[$i]) ? '' . $tax[$i] : null;
                    }
                    $form['debit']["tax$i"] = [
                        '#type' => 'select',
                        '#id' => 'tax-' . $i,
                        '#options' => [0 => $this->t('no tax'),$form_state->get('stax_rate') => $form_state->get('stax_name'),$form_state->get('wtax_rate') => $form_state->get('wtax_name'),$form_state->get('ytax_rate') => $form_state->get('ytax_name')],
                        '#required' => true,
                        '#default_value' => $dv,
                        '#attributes' => ['title' => $this->t('add sales tax'),'style' => array('width:80px;white-space:nowrap')],
                        '#prefix' => "<div class='cell' style='padding-right:2px' id='stval$i'>",
                        '#suffix' => "</div>",
                        '#description_display' => 'after',
                        '#description' => $desc,
                        '#ajax' => [
                            'callback' => [$this, "selecttax"],
                            'wrapper' => "stval$i",
                            'progress' => ['message' => null],
                        ],
                    ];
                    
                    // add hidden field for submit function
                    $form['debit']["taxtype$i"] = [
                        '#type' => 'hidden',
                        '#value' => 'multi',
                    ];
                }
            }

            $form['debit']['attachment' . $i] = [
                '#type' => 'managed_file',
                '#upload_validators' => [
                    'file_validate_extensions' => [$ext_format],
                    'file_validate_size' => [$ext_size],
                ],
                '#attributes' => ['class' => ['file_input', 'formexpense']],
                '#title' => $this->t('Add file'),
                
            ];

            $form['debit']["comment$i"] = array(
                '#type' => 'textfield',
                '#id' => 'value' . $i,
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => ($form_state->get("comment$i")) ? $form_state->get("comment$i") : null,
                '#attributes' => array('placeholder' => $this->t('comment'), 'ondblclick' => "this.value=''"),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            );


            if (isset($expense->attachment) && $i == 1) {
                //editing current entry
                $form['uri' . $i] = [
                    '#type' => 'hidden',
                    '#value' => $expense->attachment,
                ];
                $fname = array_reverse(explode('/', $expense->attachment));
                $markup = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($expense->attachment) . "' target='_blank'>" . $fname[0] . "</a>";
                $form['debit']["currenFile$i"] = [
                    '#type' => 'item',
                    '#markup' => $markup,
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div></div>',
                ];
            }
        } // loop added debits


        $form['debit']['_table'] = array(
            '#markup' => "</div>",
        );


        if ((($form_state->get('num_items') <> '' && $form_state->get('num_items') > 0) || isset($detail))) {
            if ($form_state->get('num_items') > 0) {
                $form['debit']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    '#limit_validation_errors' => array(['coid'], ['currency'], ['bank_account']),
                    '#submit' => array(array($this, 'removeForm')),
                    '#prefix' => "<p class='right'>",
                    '#suffix' => '</p>',
                    '#attributes' => array('class' => array('button--remove')),
                );
            }
        }


        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
            '#attributes' => array('class' => array('button--record')),
        );

        $form['#attached']['library'][] = 'ek_finance/ek_finance.expenses_form';

        return $form;
    }

    /**
     * Callback to Add item to form
     */
    public function addForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('coid')) {
            if ($form_state->get('num_items') == '') {
                $form_state->set('num_items', 1);
            } else {
                $i = $form_state->get('num_items') + 1;
                $form_state->set('num_items', $i);
            }

            if ($form_state->getValue('tax') == '') {
                $settings = new CompanySettings($form_state->getValue('coid'));
                $form_state->set('stax_deduct', $settings->get('stax_deduct'));
                $form_state->set('stax_rate', $settings->get('stax_rate'));
                $form_state->set('stax_name', $settings->get('stax_name'));
                $form_state->set('wtax_deduct', $settings->get('wtax_deduct'));
                $form_state->set('wtax_rate', $settings->get('wtax_rate'));
                $form_state->set('wtax_name', $settings->get('wtax_name'));
                $form_state->set('ytax_deduct', $settings->get('ytax_deduct'));
                $form_state->set('ytax_rate', $settings->get('ytax_rate'));
                $form_state->set('ytax_name', $settings->get('ytax_name'));
            }
            $chart = $form_state->get('chart');
            $opt1 = [$chart['assets'], $chart['liabilities'], $chart['cos'], $chart['expenses'], $chart['other_expenses']];
            $opt2 = [$chart['assets'], $chart['liabilities']];

            $form_state->set('AidOptions', AidList::listaid($form_state->getValue('coid'), $opt1, 1));
            $form_state->set('ProvisionOptions', AidList::listaid($form_state->getValue('coid'), $opt2, 1));
            $form_state->setRebuild();
        }
    }

    /**
     * Callback to Remove item to form
     */
    public function removeForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('num_items') > 0) {
            $i = $form_state->get('num_items') - 1;
            $form_state->set('num_items', $i);
            $form_state->setRebuild();
        }
    }

    /**
     * callback functions
     */
    public function singletax(array &$form, FormStateInterface $form_state) {
        $trigger = $form_state->getTriggeringElement();
        $i = str_replace('tax-', '', $trigger['#id']);
        if ($trigger['#value'] == 1) {
            $value = str_replace(",", "", $form_state->getValue("value$i"));
            $form['debit']["tax$i"]['#description'] = "(" . $form_state->getValue('currency') . "  " . round($value * $form_state->get('stax_rate') / 100, 2) . ")";
        } else {
            $form['debit']["tax$i"]['#description'] = '';
        }
        return $form['debit']["tax$i"];
    }
    
    public function selecttax(array &$form, FormStateInterface $form_state) {
        $trigger = $form_state->getTriggeringElement();
        $i = str_replace('tax-', '', $trigger['#id']);
        if ($trigger['#value'] != null && $trigger['#value'] != 0) {
            $value = str_replace(",", "", $form_state->getValue("value$i"));
            $form['debit']["tax$i"]['#description'] = "(" . $form_state->getValue('currency') . "  " . round($value * $trigger['#value'] / 100, 2) . ")";
        } else {
            $form['debit']["tax$i"]['#description'] = '';
        }
        return $form['debit']["tax$i"];
    }
    

    /**
     * Callback
     */
    public function set_currency(array &$form, FormStateInterface $form_state) {
        //force selection of currency on company change to force selection of credit account
        $form['credit']['currency']['#value'] = '';
        $form['credit']['currency']['#required'] = true;
        $form_state->setRebuild();
        return $form['credit']['currency'];
    }

    /**
     * Callback
     */
    public function set_credit_account(array &$form, FormStateInterface $form_state) {
        return $form['credit']['bank_account'];
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        /* if add exchange rate
         */

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value
            $data = explode("-", $form_state->getValue('bank_account'));
            $currency = $data[0];
        } elseif ($form_state->getValue('bank_account') == "P") {
            $currency = $form_state->getValue('currency');
        } else {
            // bank account
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('bank_account')))
                    ->fetchField();
        }

        $fx = CurrencyData::rate($currency);

        if ($fx <> 1) {
            $form['credit']['fx_rate']['#value'] = $fx;
            $form['credit']['fx_rate']['#required'] = true;
            $form['credit']['fx_rate']['#description'] = '';
        } else {
            $form['credit']['fx_rate']['#required'] = false;
            $form['credit']['fx_rate']['#value'] = 1;
            $form['credit']['fx_rate']['#description'] = '';
        }

        return $form['credit']['fx_rate'];
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('fx_rate') == '') {
            $form_state->setErrorByName("fx_rate", $this->t('exchange rate must be indicated'));
        }

        if (!is_numeric($form_state->getValue('fx_rate'))) {
            $form_state->setErrorByName("fx_rate", $this->t('exchange rate input is not correct'));
        }


        if ($form_state->getValue('user') <> '') {

            //$query = "SELECT uid FROM {users_field_data} WHERE name = :n";
            //$data = db_query($query, [':n' => $form_state->getValue('user')])
            //        ->fetchField();
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid']);
            $query->condition('name', $form_state->getValue('user'));
            $data = $query->execute()->fetchField();
            if ($data) {
                $form_state->setValue('uid', $data);
            } else {
                $form_state->setErrorByName('user', $this->t('Unknown user'));
            }

            if ($form_state->getValue('paid') == '0') {
                $form_state->setErrorByName("paid", $this->t('You need to indicate the payment status. Select yes if paid from cash advance given to employee or no if the employee advanced the payment (to be refund in cash later).'));
            }
        }

        //verify that the currency selected matches the account payment currency
        if ($form_state->getValue('bank_account') != 'P') {
            if (strpos($form_state->getValue('bank_account'), "-")) {
                $data = explode("-", $form_state->getValue('bank_account'));
                $currency = $data[0];
            } else {
                // bank account
                $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
                $currency = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $form_state->getValue('bank_account')))
                        ->fetchField();
            }

            if ($form_state->getValue('currency') != $currency) {
                $form_state->setErrorByName(
                        "bank_account", $this->t('You have selected an account that does not match the payment currency.')
                );
            }
        }

        //verify project ref
        if (!null == $form_state->getValue('pcode') && $form_state->getValue('pcode') != 'n/a') {
            $p = explode(' ', $form_state->getValue('pcode'));
            $query = "SELECT id FROM {ek_project} WHERE pcode = :p ";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':p' => $p[1]])
                    ->fetchField();
            if ($data) {
                $form_state->setValue('pcode', $p[1]);
            } else {
                $form_state->setErrorByName('pcode', $this->t('Unknown project'));
            }
        } else {
            $form_state->setValue('pcode', 'n/a');
        }

        for ($n = 1; $n <= $form_state->get('num_items'); $n++) {
            if ($form_state->getValue("account$n") == '') {
                $form_state->setErrorByName("account$n", $this->t('debit account @n is not selected', array('@n' => $n)));
            }

            //filter account when allocation is different from accounts entity.
            //this has an impact on analytical report
            if (!null == $form_state->getValue("change_location") && $form_state->getValue("location") != $form_state->getValue("coid")) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts', 'a')
                        ->fields('a', ['id'])
                        ->condition('coid', $form_state->getValue("location"))
                        ->condition('aid', $form_state->getValue("account$n"));
                $aid = $query->execute()->fetchField();
                if ($aid == null) {
                    $form_state->setErrorByName("account$n", $this->t('debit account @n does not exist in allocation company', array('@n' => $n)));
                }
            }

            if ($form_state->getValue("value$n") == '') {
                $form_state->setErrorByName("value$n", $this->t('there is no amount for debit @n', array('@n' => $n)));
            }

            $value = str_replace(",", "", $form_state->getValue("value$n"));
            if (!is_numeric($value)) {
                $form_state->setErrorByName("value$n", $this->t('incorrect amount for debit @n', array('@n' => $n)));
            }
            //$date_regex = '/^(19|20)\d\d[\-\/.](0[1-9]|1[012])[\-\/.](0[1-9]|[12][0-9]|3[01])$/';
            if ($form_state->getValue("pdate$n") == '') {
                $form_state->setErrorByName("pdate$n", $this->t('there is no date for debit @n', array('@n' => $n)));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $journal = new Journal();
        $baseCurrency = $this->settings->get('baseCurrency');
        $currency = $form_state->getValue('currency');
        

        if ($form_state->getValue('edit') != '') {
            //delete old  journal records
            $query = "SELECT company FROM {ek_expenses} WHERE id =:r";
            $a = array(':r' => $form_state->getValue('edit'));

            $old = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->fields('j');
            $query->condition('reference', $form_state->getValue('edit'));
            $query->condition('coid', $old);
            $query->condition('source', 'expense%', 'like');
            $query->condition('type', 'debit', '=');
            $query->condition('exchange', '0', '=');
            $backup = $query->execute()->fetchObject();

            $del = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_journal')
                    ->condition('coid', $old)
                    ->condition('source', 'expense%', 'like')
                    ->condition('reference', $form_state->getValue('edit'))
                    ->execute();
        }


        if ($form_state->getValue('change_location') == 1) {
            $allocation = $form_state->getValue('location');
        } else {
            $allocation = $form_state->getValue('coid');
        }

        for ($n = 1; $n <= $form_state->get('num_items'); $n++) {
            $class = substr($form_state->getValue("account$n"), 0, 2);

            $pdate = date('Y-m-d', strtotime($form_state->getValue("pdate$n")));
            $date = explode("-", $pdate);
            $value = str_replace(',', '', $form_state->getValue("value$n"));

            if ($baseCurrency != $currency) {
                $amount = round($value / $form_state->getValue('fx_rate'), $form_state->get('amountrounding'));
            } else {
                $amount = round($value, $form_state->get('amountrounding'));
                $form_state->setValue('fx_rate', 1);
            }
            // amount is recorded without tax($tax/$form_state->getValue('fx_rate'))

            if ($form_state->getValue("taxtype$n") == 'multi') {
                $tax = round($value * $form_state->getValue("tax$n") / 100, $form_state->get('taxrounding'));
            } elseif ($form_state->getValue("tax$n") == 1) {
                $tax = round($value * $form_state->get('stax_rate') / 100, $form_state->get('taxrounding'));
            } else {
                $tax = 0;
            }
            
            if (strpos($form_state->getValue('bank_account'), "-")) {
                $cash = 'Y';
                $credit = $form_state->getValue('bank_account');
                $provision = 0;
            } elseif ($form_state->getValue('bank_account') == 'P') {
                $cash = 'P';
                $credit = $form_state->getValue('provision_account');
                $provision = 1;
            } else {
                $cash = $form_state->getValue('bank_account');
                $credit = $form_state->getValue('bank_account');
                $provision = 0;
            }

            if ($form_state->getValue('paid') == '0') {
                $status = 'paid';
            } else {
                $status = $form_state->getValue('paid');
            }

            if ($form_state->getValue('uid') == '') {
                $uid = 'n/a';
            } else {
                $uid = $form_state->getValue('uid');
            }

            $fields = array(
                'class' => $class,
                'type' => $form_state->getValue("account$n"),
                'allocation' => $allocation,
                'company' => $form_state->getValue('coid'),
                'localcurrency' => $value + $tax,
                'rate' => $form_state->getValue('fx_rate'),
                'amount' => $amount,
                'currency' => $form_state->getValue('currency'),
                'amount_paid' => $amount,
                'tax' => $tax,
                'year' => $date[0],
                'month' => $date[1],
                'comment' => \Drupal\Component\Utility\Xss::filter($form_state->getValue("comment$n"), ['em', 'b', 'strong']),
                'pcode' => $form_state->getValue('pcode'),
                'clientname' => $form_state->getValue('client'),
                'suppliername' => $form_state->getValue('supplier'),
                'employee' => $uid,
                'status' => $status,
                'cash' => $cash,
                'pdate' => $pdate,
                'reconcile' => '0',
            );

            if ($form_state->getValue('edit') != '') {
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_expenses')
                        ->condition('id', $form_state->getValue('edit'))
                        ->fields($fields)
                        ->execute();
                $insert = $form_state->getValue('edit');
            } else {
                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_expenses')
                        ->fields($fields)
                        ->execute();
            }

            //upload with id ref. added to file name
            $receipt = 'no';
            $attach = "attachment$n";
            $fid = $form_state->getValue([$attach, 0]);
            if (!empty($fid)) {
                $receipt = 'yes';
                if ($form_state->getValue('uri' . $n) != '') {
                    //if edit and existing, delete current attach.
                    \Drupal::service('file_system')->delete($form_state->getValue('uri' . $n));
                }
                $file = $this->fileStorage->load($fid);
                $name = $file->getFileName();
                $dir = "private://finance/receipt/" . $form_state->getValue('coid');
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $load_attachment = \Drupal::service('file_system')->copy($file->getFileUri(), $dir . "/" . $insert . '_' . $name);
            } elseif ($form_state->getValue('uri' . $n) != '') {
                $receipt = 'yes';
                $load_attachment = $form_state->getValue('uri' . $n);
            } else {
                $load_attachment = '';
            }


            Database::getConnection('external_db', 'external_db')
                    ->update('ek_expenses')
                    ->condition('id', $insert)
                    ->fields(['receipt' => $receipt, 'attachment' => $load_attachment])
                    ->execute();

            // Record the accounting journal

            $journal->record(
                    array(
                        'source' => "expense",
                        'coid' => $form_state->getValue('coid'),
                        'aid' => $form_state->getValue("account$n"),
                        'bank' => $credit,
                        'provision' => $provision,
                        'reference' => $insert,
                        'date' => $pdate,
                        'value' => $value,
                        'currency' => $form_state->getValue('currency'),
                        'tax' => $tax,
                        'fxRate' => $form_state->getValue('fx_rate'),
                    )
            );
        }

        if ($journal->credit <> $journal->debit) {
            $msg = 'debit: ' . $journal->debit . ' <> ' . 'credit: ' . $journal->credit;
            \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
        }

        $url = Url::fromRoute('ek_finance.manage.edit_expense', ['id' => $insert], [])->toString();
        if ($form_state->getValue('edit') != '') {
            \Drupal::messenger()->addStatus(t('Expenses ref. <a href="@url">@id</a> edited', ['@url' =>  $url, '@id' => $insert]));
        } else {
            \Drupal::messenger()->addStatus(t('Expenses recorded ref. <a href="@url">@id</a>', ['@url' =>  $url,'@id' => $insert]));
        }
        \Drupal\Core\Cache\Cache::invalidateTags(['reporting', 'expenses']);
        $form_state->setRedirect('ek_finance.manage.list_expense');
    }

}
