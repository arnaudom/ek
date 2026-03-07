<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\EditPayrollExpense.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Service\JournalService;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_address_book\AddressBookData;


/**
 * Provides a form to record an expense entry.
 */
class EditPayrollExpense extends FormBase {


    protected $moduleHandler;
    protected $journal;

    
    public function __construct(ModuleHandler $module_handler, JournalService $journal) {
        $this->moduleHandler = $module_handler;
        $this->journal = $journal;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler'),
                $container->get('ek_finance.journal')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_expense_payroll_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        $settingsFi = new FinanceSettings();
        $chart = $settingsFi->get('chart');
        if (null !== $settingsFi->get('expenseAttachmentFormat')) {
            $ext_format = $settingsFi->get('expenseAttachmentFormat');
        } else {
            $ext_format = 'png jpg jpeg doc docx xls xlsx odt ods odp pdf rar rtf zip';
        }
        if (null !== $settingsFi->get('expenseAttachmentSize')) {
            $ext_size = $settingsFi->get('expenseAttachmentSize') * 1000000;
        } else {
            $ext_size = '500000';
        }

        $tax = [];
        $credit = null;
        $form_state->set('chart', $chart);

        $form['cancel'] = array(
            '#type' => 'item',
            '#weight' => -16,
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => Url::fromRoute('ek_finance.manage.list_expense', [], [])->toString())),
        );


        if ($form_state->get('expense_data') === null && $id !== null) {
            $expense = Database::getConnection('external_db', 'external_db')
                ->select('ek_expenses', 'e')
                ->fields('e')
                ->condition('id', $id)
                ->execute()
                ->fetchObject();

            if (!$expense) {
                $form_state->setErrorByName('', $this->t('Expense record not found.'));
                return $form;
            }

            $form_state->set('expense_data', $expense);

            // Cache HR settings too
            $settingsHR = new \Drupal\ek_hr\HrSettings($expense->company);
            $form_state->set('settingsHR', $settingsHR);

            // Cache journal entries
            $jEntry = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j')
                ->fields('j')
                ->condition('source', 'expense%', 'LIKE')
                ->condition('reference', $id)
                ->condition('exchange', 0)
                ->execute();

            $journal_entries = [];
            while ($row = $jEntry->fetchObject()) {
                $journal_entries[] = $row;
            }
            $form_state->set('journal_entries', $journal_entries);
        }
        else {
            $expense = $form_state->get('expense_data');
            $settingsHR = $form_state->get('settingsHR');
        }

        if ($form_state->get('num_items') == null) {
            
            // get journal data
            $query = "SELECT * from {ek_journal} WHERE source like :s and reference = :r AND exchange=:e";
            $a = array(':s' => "expense%", ':r' => $id, ':e' => 0);
            $jEntry = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j')
                    ->fields('j')
                    ->condition('source', 'expense%', 'LIKE')
                    ->condition('reference', $id)
                    ->condition('exchange', 0)
                    ->execute();
            

            $i = 1;
            while ($d = $jEntry->fetchObject()) {
                $form_state->set("account" . $i, $d->aid);
                $form_state->set("pdate", $d->date);
                $form_state->set("value" . $i, $d->value);
                $form_state->set("comment" . $i, $expense->comment);
                $form_state->set("type" . $i, $d->type);
                $form_state->set("jid" . $i, $d->id);
                $i++;
            }

            $form_state->set('num_items', $i - 1);
            $form_state->set('coid', $expense->company);
            $form_state->set('currency', $expense->currency);

            if ($expense->cash == 'Y') {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j')
                        ->fields('j', ['aid'])
                        ->condition('source', 'expense%', 'LIKE')
                        ->condition('reference', $id)
                        ->condition('type', 'credit')
                        ->condition('exchange', 0)
                        ->execute();
                $jCredit = $query->fetchField();
                $credit = $expense->currency . '-' . $jCredit;
            } else {
                $credit = $expense->cash;
            }

            // list of accounts chart is defined in the general finance settings
            // The chart structure is as follow
            // 'assets', 'liabilities', 'equity', 'income', 'cos', 'expenses', 'other_liabilities', 'other_income', 'other_expenses'

            $opt1 = [$chart['cos'], $chart['expenses'], $chart['other_expenses']];
            $opt2 = [$chart['liabilities']];

            $form_state->set('AidOptions_pl', AidList::listaid($expense->company, $opt1, 1));
            $form_state->set('AidOptions_bs', AidList::listaid($expense->company, $opt2, 1));
        } //edit data

        if ($id != null) {
            $form['edit'] = [
                '#type' => 'hidden',
                '#value' => $id,
            ];
        }

        $CurrencyOptions = CurrencyData::listcurrency(1);
        $company = AccessCheck::CompanyListByUid();

        $form['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#disabled' => true,
            '#default_value' => ($form_state->get('coid')),
            '#title' => $this->t('company'),
            '#required' => true,
            '#weight' => -15,
        ];

        $form["delete"] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Delete record'),
            '#weight' => -14,
        ];

        $form["pdate"] = [
            '#type' => 'date',
            '#id' => "",
            '#size' => 12,
            '#required' => true,
            '#default_value' => ($form_state->get("pdate")),
            '#weight' => -13,
        ];


        $add = isset($expense->allocation) ? $company[$expense->allocation] : "";
        $form['allocation'] = [
            '#type' => 'details',
            '#title' => $this->t('Allocation') . " " . $add,
            '#group' => '1',
            '#open' => false,
            '#weight' => -11,
        ];

        $form['allocation']["change_location"] = [
            '#type' => 'checkbox',
            '#default_value' => isset($expense->allocation) ? true : null,
            '#title' => $this->t('assign to other entity'),
            '#prefix' => "<div class='container-inline'>",
        ];

        $form['allocation']['location'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => isset($expense->allocation) ? $expense->allocation : null,
            '#title' => $this->t('allocation'),
            '#required' => false,
            '#suffix' => "</div>",
            '#states' => [
                'invisible' => [
                    "input[name='change_location']" => array('checked' => false),
                ],
            ],
        ];

        $form['credit'] = [
            '#type' => 'details',
            '#title' => $this->t('Credit'),
            '#group' => '1',
            '#open' => true,
            '#weight' => -10,
        ];

        $form['credit']['currency'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $CurrencyOptions,
            '#required' => true,
            '#default_value' => ($form_state->get('currency')) ? $form_state->get('currency') : null,
            '#title' => $this->t('currency'),
            '#ajax' => [
                'callback' => [$this, 'set_credit_account'],
                'wrapper' => 'credit',
            ],
            '#prefix' => "<div class='table'><div class='row'><div class='cell' id='currency'>",
            '#suffix' => '</div>',
        ];

        // bank account
        if (($form_state->getValue('coid') || $form_state->get('coid')) && ($form_state->getValue('currency') || $form_state->get('currency'))) {
            $coid = $form_state->getValue('coid') ? $form_state->getValue('coid') : $form_state->get('coid');
            $currency = $form_state->getValue('currency') ? $form_state->getValue('currency') : $form_state->get('currency');

            $settingsCo = new CompanySettings($coid);
            $aid = $settingsCo->get('cash_account', $currency);
            $cash = '';
            if ($aid <> '') {
                $key = $currency . "-" . $aid;
                $cash = [$key => \Drupal\ek_finance\AidList::aname($coid, $aid)];
            }
            $aid = $settingsCo->get('cash2_account', $currency);
            if ($aid <> '') {
                $key = $currency . "-" . $aid;
                $cash += [$key => \Drupal\ek_finance\AidList::aname($coid, $aid)];
            }

            $options[(string) $this->t('cash')] = $cash;
            $options[(string) $this->t('bank')] = BankData::listbankaccountsbyaid($coid, $currency);

            $form['credit']['bank_account']['#options'] = $options;
            $form['credit']['bank_account']['#value'] = 0;
            $form['credit']['bank_account']['#description'] = '';
            $form_state->set('bank_opt', $options);

            $form['credit']['bank_account'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $form_state->get('bank_opt'),
                '#required' => true,
                '#default_value' => isset($credit) ? $credit : null,
                '#title' => $this->t('account payment'),
                '#prefix' => "<div id='credit' class='cell'>",
                '#suffix' => '</div>',
                '#description' => '',
                '#validated' => true,
                '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                '#ajax' => array(
                    'callback' => array($this, 'fx_rate'),
                    'wrapper' => 'fx',
                ),
            ];

        } else {
            $form['credit']['bank_account'] = [
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
            ];
        }

        $form['credit']['fx_rate'] = [
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 15,
            '#default_value' => isset($expense->rate) ? $expense->rate : null,
            '#required' => false,
            '#title' => $this->t('exchange rate'),
            '#description' => '',
            '#prefix' => "<div id='fx' class='cell'>",
            '#suffix' => '</div></div></div>',
        ];

        // References / tags
        $form['reference'] = [
            '#type' => 'details',
            '#title' => $this->t('References'),
            '#group' => '2',
            '#open' => false,
        ];

        $supplier = array('n/a' => $this->t('not applicable'));
        $supplier += AddressBookData::addresslist(2);

        $form['reference']['supplier'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $supplier,
            '#required' => true,
            '#default_value' => isset($expense->suppliername) ? $expense->suppliername : null,
            '#title' => $this->t('supplier'),
            '#attributes' => ['style' => array('width:200px;white-space:nowrap')],
            '#prefix' => "<div  class='container-inline'>",
        ];

        $client = ['n/a' => $this->t('not applicable')];
        $client += AddressBookData::addresslist(1);


        $form['reference']['client'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $client,
            '#required' => true,
            '#default_value' => isset($expense->clientname) ? $expense->clientname : null,
            '#title' => $this->t('client'),
            '#attributes' => ['style' => array('width:200px;white-space:nowrap')],
            '#suffix' => '</div>',
        ];

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            if (isset($expense->pcode) && $expense->pcode != 'n/a') {
                $thisPcode = $this->t('code') . ' ' . $expense->pcode;
            } else {
                $thisPcode = null;
            }

            $form['reference']['pcode'] = [
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 150,
                //'#required' => TRUE,
                '#default_value' => $thisPcode,
                '#attributes' => array('placeholder' => $this->t('Ex. 123')),
                '#title' => $this->t('Project'),
                '#autocomplete_route_name' => 'ek_look_up_projects',
                '#autocomplete_route_parameters' => array('level' => 'all', 'status' => '0'),
            ];

        } 

        // debits
        $form['debit'] = [
            '#type' => 'details',
            '#title' => $this->t('Transactions'),
            '#group' => '3',
            '#open' => true,
        ];


        $form['debit']['table'] = [
            '#markup' => "<div class='table'>",
        ];

        for ($i = 1; $i <= $form_state->get('num_items'); $i++) {
            $form['debit']["type$i"] = [
                '#type' => 'hidden',
                '#value' => $form_state->get("type$i"),
            ];

            $form['debit']["account$i"] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => ($form_state->get("type$i") == 'debit') ? $form_state->get('AidOptions_pl') : $form_state->get('AidOptions_bs'),
                '#disabled' => ($settingsHR->get('accounts','pay_account') != $form_state->get("account$i")) ? false : true,
                '#required' => true,
                '#default_value' => ($form_state->get("account$i")) ? $form_state->get("account$i") : null,
                '#attributes' => ['style' => ['width:100px;white-space:nowrap']],
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            ];


            if ($form_state->get("type$i") == 'debit') {
                $form['debit']["value$i"] = [
                    '#type' => 'textfield',
                    '#id' => 'value' . $i,
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#description' => '',
                    '#default_value' => ($form_state->get("value$i")) ? $form_state->get("value$i") : null,
                    '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'onKeyPress' => "return(number_format(this,',','.', event))"],
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                ];

                $form['debit']["ct$i"] = [
                    '#type' => 'item',
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                ];

            } else {
                $form['debit']["ct$i"] = [
                    '#type' => 'item',
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                ];

                $form['debit']["value$i"] = [
                    '#type' => 'textfield',
                    '#id' => 'value' . $i,
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#description' => '',
                    '#default_value' => ($form_state->get("value$i")) ? $form_state->get("value$i") : null,
                    '#attributes' => ['placeholder' => $this->t('value'), 'class' => ['amount'], 'onKeyPress' => "return(number_format(this,',','.', event))"],
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                ];
            }

            if ($form_state->get("type$i") == 'debit') {
                
                $form['debit']['attachment' . $i] = [
                    '#type' => 'file',       
                    '#upload_validators'  => [
                        'FileExtension' => ['extensions' => $ext_format],
                        'FileSizeLimit' => ['fileLimit' => $ext_size]
                    ],
                    '#attributes' => ['class' => ['']],
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                ];

                $form['debit']["comment$i"] = [
                    '#type' => 'textfield',
                    '#id' => 'value' . $i,
                    '#size' => 25,
                    '#maxlength' => 255,
                    '#default_value' => ($form_state->get("comment$i")) ? $form_state->get("comment$i") : null,
                    '#attributes' => ['placeholder' => $this->t('comment'),],
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                ];

                if (isset($expense->attachment) && $i == 1) {
                    // editing current entry
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
            } else {
                $form['debit']['noattachment' . $i] = [
                    '#type' => 'item',
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                ];
                $form['debit']['nocomment' . $i] = [
                    '#type' => 'item',
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                ];
            }
        }

        $form['debit']['_table'] = [
            '#markup' => "</div>",
        ];

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Record'),
            '#attributes' => ['class' => ['button--record']],
        ];

        $form['#attached']['library'][] = 'ek_finance/ek_finance.expenses_form';

        return $form;
    }

    /**
     * Callback
     */
    public function set_credit_account(array &$form, FormStateInterface $form_state) {
        //$form_state->setRebuild();
        return $form['credit']['bank_account'];
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value
            $data = explode("-", $form_state->getValue('bank_account'));
            $currency = $data[0];
        } elseif ($form_state->getValue('bank_account') == "P") {
            $currency = $form_state->getValue('currency');
        } else {
            // bank account
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank_accounts', 'ba')
                    ->fields('ba', ['currency'])
                    ->condition('id', $form_state->getValue('bank_account'))
                    ->execute();
            $currency = $query->fetchField();
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
        //$form_state->setRebuild();
        return $form['credit']['fx_rate'];
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if($form_state->getValue('delete') != 1) {
            if ($form_state->getValue('fx_rate') == '') {
                $form_state->setErrorByName("fx_rate", $this->t('exchange rate must be indicated'));
            }

            if (!is_numeric($form_state->getValue('fx_rate'))) {
                $form_state->setErrorByName("fx_rate", $this->t('exchange rate input is not correct'));
            }


            //verify that the currency selected matches the account payment currency
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


            // verify project ref
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


            if ($form_state->getValue("pdate") == '') {
                $form_state->setErrorByName("pdate", $this->t('there is no date for debit @n'));
            }

            // in this entry, debit and credits must be balanced
            $dt = 0;
            $ct = 0;
            for ($n = 1; $n <= $form_state->get('num_items'); $n++) {

                // filter account when allocation is different from accounts entity.
                // this has an impact on analytical report
                if (!null == $form_state->getValue("location") && $form_state->getValue("location") != $form_state->getValue("coid")) {
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

                if ($form_state->getValue("type$n") == 'debit') {
                    $dt += $value;
                } else {
                    $ct += $value;
                }

                // attachment
                $field = 'attachment' . $n;
                if(isset($form['debit'][$field]) ) {
                $file = _file_save_upload_from_form($form['debit'][$field], $form_state, 0);
                    /*if ($file) {
                        if($errors = $form_state->getErrors()) {
                            foreach ($errors as $error) {
                                $form_state->setErrorByName($field, $error);
                            }
                            // Mark the temporary file for deletion.
                            $file->delete();
                        } else {
                            $form_state->set($field, $file) ;
                        }        
                    } else {
                            $form_state->setErrorByName($field, $this->t('File upload failed'));
                    }*/

                    if($file) {
                        $form_state->set($field, $file) ;
                    }
                }
            }

            // balance
            if ($dt != $ct) {
                $form_state->setErrorByName("value1", $this->t('entry is not balanced'));
            }

            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        
        $del = Database::getConnection('external_db', 'external_db')
                ->delete('ek_journal')
                ->condition('coid', $form_state->getValue('coid'))
                ->condition('reference', $form_state->getValue('edit'));
        $or = $del->orConditionGroup();
        $or->condition('source', 'payroll', '=');
        $or->condition('source', 'expense payroll', '=');
        $del->condition($or)->execute();

        Database::getConnection('external_db', 'external_db')
            ->delete('ek_expenses')
            ->condition('company', $form_state->getValue('coid'))
            ->condition('id', $form_state->getValue('edit'))
            ->execute();          
            
        if($form_state->getValue('delete') == 0) {
            $settings = new FinanceSettings();
            $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            $baseCurrency = $settings->get('baseCurrency');
            $currency = $form_state->getValue('currency');

            if ($form_state->getValue('change_location') == 1) {
                $allocation = $form_state->getValue('location');
            } else {
                $allocation = $form_state->getValue('coid');
            }
            $pdate = date('Y-m-d', strtotime($form_state->getValue("pdate")));
            $date = explode("-", $pdate);
            $settingsHR = $form_state->get('settingsHR');
            if (!$settingsHR) {
                // Fallback (should not happen unless form tampering)
                $settingsHR = new \Drupal\ek_hr\HrSettings($form_state->getValue('coid'));
            }
            $expense = $form_state->get('expense_data');
            $deductions = 0;
            $funds = [
                'f1' => 0,
                'f1a' => $settingsHR->get('accounts','fund1_account'),
                'f2' => 0,
                'f2a' => $settingsHR->get('accounts','fund2_account'),
                'f3' => 0,
                'f3a' => $settingsHR->get('accounts','fund3_account'),
                'f4' => 0,
                'f4a' => $settingsHR->get('accounts','fund4_account'),
                'f5' => 0,
                'f5a' => $settingsHR->get('accounts','fund5_account'),
            ];
            $tax = [
                't1' => 0,
                't1a' => $settingsHR->get('accounts','tax1_account'),
                't2' => 0,
                't2a' => $settingsHR->get('accounts','tax2_account'),
            ];

            for ($n = 1; $n <= $form_state->get('num_items'); $n++) {
                $value = str_replace(',', '', $form_state->getValue("value$n"));
                if ($form_state->getValue("type$n") == 'debit') {
                    $exp_account = $form_state->getValue("account$n");
                    $class = substr($exp_account, 0, 2);
                    $gross = $value;
                    if ($baseCurrency != $currency) {
                        $amount = round($value / $form_state->getValue('fx_rate'), $rounding);
                    } else {
                        $amount = round($value, $rounding);
                        $form_state->setValue('fx_rate', 1);
                    }

                    if (strpos($form_state->getValue('bank_account'), "-")) {
                        $cash = 'Y';
                        $credit = $form_state->getValue('bank_account');
                    } else {
                        $cash = $form_state->getValue('bank_account');
                        $credit = $form_state->getValue('bank_account');
                    }

                    $exp_fields = array(
                        'class' => $class,
                        'type' => $exp_account,
                        'allocation' => $allocation,
                        'company' => $form_state->getValue('coid'),
                        'localcurrency' => $value,
                        'rate' => $form_state->getValue('fx_rate'),
                        'amount' => $amount,
                        'currency' => $form_state->getValue('currency'),
                        'amount_paid' => $amount,
                        'tax' => 0,
                        'year' => $date[0],
                        'month' => $date[1],
                        'comment' => \Drupal\Component\Utility\Xss::filter($form_state->getValue("comment$n"), ['em', 'b', 'strong']),
                        'pcode' => $form_state->getValue('pcode'),
                        'clientname' => $form_state->getValue('client'),
                        'suppliername' => $form_state->getValue('supplier'),
                        'employee' => 'n/a',
                        'status' => 'paid',
                        'cash' => $cash,
                        'pdate' => $pdate,
                        'reconcile' => '0',
                    );

                    // upload with id ref. added to file name
                    $receipt = 'no';
                    $attach = "attachment$n";
                    if ($file = $form_state->get($attach)) {
                        $receipt = 'yes';
                        if ($form_state->getValue('uri' . $n) != '') {
                            // if edit and existing, delete current attach.
                            \Drupal::service('file_system')->delete($form_state->getValue('uri' . $n));
                        }
                        
                        $name = $file->getFileName();
                        $dir = "private://finance/receipt/" . $form_state->getValue('coid');
                        \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                        $load_attachment = \Drupal::service('file_system')->copy($file->getFileUri(), $dir . "/" . $name);
                    } elseif ($form_state->getValue('uri' . $n) != '') {
                        $receipt = 'yes';
                        $load_attachment = $form_state->getValue('uri' . $n);
                    } else {
                        $load_attachment = '';
                    }
                } else {
                    //journal data

                    if ($form_state->getValue("account$n") == $funds['f1a']) {
                        $funds['f1'] = $funds['f1'] + $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    } elseif ($form_state->getValue("account$n") == $funds['f2a']) {
                        $funds['f2'] = $funds['f2'] + $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    } elseif ($form_state->getValue("account$n") == $funds['f3a']) {
                        $funds['f3'] = $funds['f3'] + $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    } elseif ($form_state->getValue("account$n") == $funds['f4a']) {
                        $funds['f4'] = $funds['f4'] + $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    } elseif ($form_state->getValue("account$n") == $funds['f5a']) {
                        $funds['f5'] = $funds['f5'] + $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    } elseif ($form_state->getValue("account$n") == $tax['t1a']) {
                        $tax['t1'] = $tax['t1'] + $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    } elseif ($form_state->getValue("account$n") == $tax['t2a']) {
                        $tax['t2'] = $form_state->getValue("value$n");
                        $deductions = $deductions + $value;
                    }
                }
            }

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_expenses')
                    ->fields($exp_fields)
                    ->execute();

            $net = round($gross - $deductions, $rounding);
            $this->journal->record(
                    [   'source' => "expense payroll",
                        'coid' => $form_state->getValue('coid'),
                        'aid' => $exp_account,
                        'reference' => $insert,
                        'fxRate' => $form_state->getValue('fx_rate'),
                        'date' => $form_state->getValue('pdate'),
                        'value' => $gross,
                        'currency' => $form_state->getValue('currency'),
                        'netpay' => $net,
                        'netpayaccount' => $settingsHR->get('accounts','pay_account'),
                        'funds' => $funds,
                        'tax' => $tax,
                    ]
            );

            // pay net salary to employee (DT liabilities, CT bank)
            $this->journal->record(
                    [
                        'source' => "payroll",
                        'coid' => $form_state->getValue('coid'),
                        'aid' => $settingsHR->get('accounts','pay_account'),
                        'bank' => $credit,
                        'reference' => $insert,
                        'date' => $form_state->getValue('pdate'),
                        'value' => $net,
                        'currency' => $form_state->getValue('currency'),
                        'tax' => '',
                        'fxRate' => $form_state->getValue('fx_rate'),
                    ]
            );


            Database::getConnection('external_db', 'external_db')
                    ->update('ek_expenses')
                    ->condition('id', $insert)
                    ->fields(['receipt' => $receipt, 'attachment' => $load_attachment])
                    ->execute();

            // Record the accounting journal
            if (round($this->journal->getCredit(), 4) <> round($this->journal->getDebit(), 4)) {
                $msg = 'debit: ' . $this->journal->getDebit() . ' <> ' . 'credit: ' . $this->journal->getCredit();
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            }

            if ($form_state->getValue('edit') != '') {
                \Drupal::messenger()->addStatus(t('Expenses ref. @id edited', ['@id' => $insert]));
            } else {
                \Drupal::messenger()->addStatus(t('Expenses recorded ref. @id', ['@id' => $insert]));
            }
        } else {
            \Drupal::messenger()->addStatus(t('Expenses deleted'));
        }
        \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
        $form_state->setRedirect('ek_finance.manage.list_expense');
    }

}
