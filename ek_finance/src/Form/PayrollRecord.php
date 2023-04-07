<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\PayrollRecord.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_address_book\AddressBookData;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to record payroll expenses.
 */
class PayrollRecord extends FormBase {

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
        $this->rounding = (!null == $this->settings->get('rounding')) ? $this->settings->get('rounding') : 2;
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
        return 'ek_finance_payroll_record';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $param = null) {
        $pcode = [];
        $param = unserialize($param);
        $settings = new HrSettings($param['coid']);
        $list = $settings->HrAccounts[$param['coid']];

        if (empty($list) || $list['pay_account'] == '') {
            $error = 1;
            $url = Url::fromRoute('ek_hr.parameters-accounts', array(), array())->toString();
            $markup = "<div class='messages messages--error'>" . $this->t("You do not have payroll accounts recorded. 
          Go to <a href='@s' target='_blank'>settings</a> first.", ['@s' => $url]) . '</div>';
            $form['error'] = array(
                '#type' => 'item',
                '#markup' => $markup,
            );
        }


        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_post_data', 'p');
        $query->fields('p', ['emp_id', 'month', 'n_days', 'nett', 'advance', 'epf_er', 'epf_yee', 'socso_er', 'socso_yee', 'incometax', 'with_yer', 'with_yee']);
        $query->innerJoin('ek_hr_workforce', 'w', 'p.emp_id = w.id');
        $query->fields('w', ['name', 'currency']);
        $query->condition('company_id', $param['coid'], '=');
        $query->condition('month', $param['month'], '=');
        $query->distinct();
        $data = $query->execute();



        $header = array(
            'deductions' => [],
            'select' => array(
                'data' => $this->t('select'),
            ),
            'description' => array(
                'data' => $this->t('Description'),
                'field' => 'name',
                'sort' => 'asc',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'client' => array(
                'data' => $this->t('client'),
            ),
            'project' => array(
                'data' => $this->t('Project'),
            ),
            'account' => array(
                'data' => $this->t('Account'),
            ),
            'credit' => array(
                'data' => $this->t('Credit'),
            ),
            'fx' => array(
                'data' => $this->t('Exc. rate'),
            ),
            'payDate' => array(
                'data' => $this->t('Pay date'),
            ),
            'net' => array(
                'data' => $this->t('Net + advance'),
            ),
            'currency' => array(
                'data' => $this->t('Currency'),
            ),
        );

        $form['HrTable'] = array(
            '#tree' => true,
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => array(),
            '#attributes' => array('id' => 'HrTable'),
            '#empty' => $this->t('No data'),
        );

        $form['HrTable']["coid"] = array(
            '#type' => 'hidden',
            '#value' => $param['coid'],
        );
        
        $form['HrTable']["month"] = array(
            '#type' => 'hidden',
            '#value' => $param['month'],
        );

        $client = array('n/a' => $this->t('not applicable'));
        $client += AddressBookData::addresslist(1);
        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $pcode = ProjectData::listprojects(0);
        }
        $fsettings = new FinanceSettings();
        $chart = $fsettings->get('chart');
        $AidOptions = AidList::listaid($param['coid'], array($chart['cos'], $chart['expenses'], $chart['other_expenses']), 1);
        $CurrencyOptions = CurrencyData::listcurrency(1);

        $settings = new CompanySettings($param['coid']);

        //get cash accounts ref.
        $cash = array();
        foreach ($CurrencyOptions as $c => $name) {
            $aid = $settings->get('cash_account', $c);

            if ($aid <> '') {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts', 'a')
                        ->fields('a', ['aname'])
                        ->condition('coid', $param['coid'])
                        ->condition('aid', $aid)
                        ->execute();
                $key = $c . "-" . $aid;
                $cash[$key] = '[' . $c . '] -' . $query->fetchField();
            }

            $aid = $settings->get('cash2_account', $c);

            if ($aid <> '') {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts', 'a')
                        ->fields('a', ['aname'])
                        ->condition('coid', $param['coid'])
                        ->condition('aid', $aid)
                        ->execute();

                $key = $c . "-" . $aid;
                $cash[$key] = '[' . $c . '] -' . $query->fetchField();
            }
        }

        $credit[(string) $this->t('bank')] = BankData::listbankaccountsbyaid($param['coid']);
        $credit[(string) $this->t('cash')] = $cash;

        $n = 0;

        while ($r = $data->fetchObject()) {
            $n++;
            //pull default/previous expense,credit accounts for user convenience
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e')
                    ->fields('e', ['type'])
                    ->condition('comment', '%' . $r->name . '%', 'LIKE')
                    ->orderBy('pdate', 'DESC')
                    ->range(0, 1)
                    ->execute();
            $expense_account = $query->fetchField();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e')
                    ->fields('e', ['cash', 'currency', 'pdate'])
                    ->condition('comment', $this->t('allowance') . '%' . $r->name . '%', 'LIKE')
                    ->orderBy('pdate', 'DESC')
                    ->range(0, 1)
                    ->execute();
            $credit_account = $query->fetchObject();
            //deductions
            $deductions = array(
                '0' => $r->epf_er + $r->epf_yee,
                '1' => $r->socso_er + $r->socso_yee,
                '2' => $r->with_yer + $r->with_yee,
                '3' => 0,
                '4' => 0,
                '5' => $r->incometax,
                '6' => 0
            );
            $form['deductions'] = array(
                '#id' => 'deductions-' . $r->emp_id,
                '#type' => 'hidden',
                '#value' => serialize($deductions),
            );
            $form['select'] = array(
                '#id' => 'select-' . $r->emp_id,
                '#type' => 'checkbox',
                '#default_value' => 1,
                '#attributes' => array(
                    'title' => $this->t('select'),
                    'onclick' => "jQuery('#" . $r->emp_id . "').toggleClass('delete');jQuery('#" . $r->emp_id . "').toggleClass('odd', $n % 3 === 0);"
                ),
            );
            $form['description'] = array(
                '#id' => 'description-' . $r->emp_id,
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 255,
                '#default_value' => $this->t('allowance') . ' ' . $r->month . ' ' . $r->name,
                '#required' => true,
            );
            $form['client'] = array(
                '#id' => 'client-' . $r->emp_id,
                '#type' => 'select',
                '#options' => $client,
                '#attributes' => array('style' => array('width:80px;')),
                '#default_value' => "not applicable",
                '#required' => true,
            );

            if ($this->moduleHandler->moduleExists('ek_projects')) {
                $form['pcode'] = array(
                    '#id' => 'pcode-' . $r->emp_id,
                    '#type' => 'select',
                    '#options' => $pcode,
                    '#default_value' => null,
                    '#default_value' => "not applicable",
                    '#attributes' => array('style' => array('width:80px;')),
                    '#required' => true,
                );
            } else {
                $form['pcode'] = array(
                    '#id' => 'pcode-' . $r->emp_id,
                    '#type' => 'item',
                );
            }

            $form['account'] = array(
                '#id' => 'account-' . $r->emp_id,
                '#type' => 'select',
                '#options' => $AidOptions,
                '#attributes' => array('style' => array('width:80px;')),
                '#default_value' => isset($expense_account) ? $expense_account : null,
            );

            $form['credit'] = array(
                '#id' => 'credit-' . $r->emp_id,
                '#type' => 'select',
                '#options' => $credit,
                '#attributes' => array('style' => array('width:80px;')),
                '#default_value' => isset($credit_account->cash) ? $credit_account->cash : null,
                '#ajax' => array(
                    'callback' => array($this, 'fx_rate'),
                    'wrapper' => "fx" . $r->emp_id,
                    'event' => 'change',
                ),
            );

            $form['fx'] = array(
                '#id' => 'fx-' . $r->emp_id,
                '#type' => 'textfield',
                '#size' => 5, '#default_value' => isset($credit_account->currency) ? CurrencyData::rate($credit_account->currency) : 1,
                '#required' => false,
                '#prefix' => "<div id='fx" . $r->emp_id . "'>",
                '#suffix' => '</div>',
            );

            $form['payDate'] = array(
                '#id' => 'payDate-' . $r->emp_id,
                '#type' => 'date',
                '#size' => 14,
                '#default_value' => date('Y-m-d'),
                '#required' => true,
            );

            $form['net'] = array(
                '#id' => 'net-' . $r->emp_id,
                '#type' => 'textfield',
                '#size' => 20,
                '#maxlength' => 255,
                '#default_value' => number_format($r->nett + $r->advance, 2),
                '#required' => true,
            );

            $form['currency'] = array(
                '#id' => 'currency-' . $r->emp_id,
                '#type' => 'textfield',
                '#size' => 3,
                '#maxlength' => 5,
                '#default_value' => $r->currency,
                '#disabled' => true,
            );


            $form['HrTable'][$r->emp_id] = array(
                'deductions' => &$form['deductions'],
                'select' => &$form['select'],
                'description' => &$form['description'],
                'client' => &$form['client'],
                'pcode' => &$form['pcode'],
                'account' => &$form['account'],
                'credit' => &$form['credit'],
                'fx' => &$form['fx'],
                'payDate' => &$form['payDate'],
                'net' => &$form['net'],
                'currency' => &$form['currency'],
            );

            $form['HrTable']['#rows'][] = array(
                'data' => array(
                    array('data' => &$form['deductions']),
                    array('data' => &$form['select']),
                    array('data' => &$form['description'], 'title' => ['#markup' => $r->name]),
                    array('data' => &$form['client']),
                    array('data' => &$form['pcode']),
                    array('data' => &$form['account']),
                    array('data' => &$form['credit']),
                    array('data' => &$form['fx']),
                    array('data' => &$form['payDate']),
                    array('data' => &$form['net'], 'title' => ['#markup' => $r->nett . " + " . $r->advance]),
                    array('data' => &$form['currency']),
                ),
                'id' => array($r->emp_id)
            );

            unset($form['deductions']);
            unset($form['select']);
            unset($form['description']);
            unset($form['client']);
            unset($form['pcode']);
            unset($form['account']);
            unset($form['credit']);
            unset($form['fx']);
            unset($form['payDate']);
            unset($form['net']);
            unset($form['currency']);
        }


        if (!isset($error)) {
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => array('class' => array('')),
            );
        }
        $form['#attached']['library'][] = 'ek_finance/ek_finance_css';



        return $form;
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        // if add exchange rate
        $data = $form_state->getValue('HrTable');

        $trigger = $form_state->getTriggeringElement();
        $n = $trigger['#parents'][1];

        // FILTER cash account
        if (strpos($data[$n]['credit'], "-")) {
            //the currency is in the form value
            $c = explode("-", $data[$n]['credit']);
            $currency = $c[0];
        } else {
            // bank account
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data[$n]['credit']))
                    ->fetchField();
        }

        $fx = CurrencyData::rate($currency);

        if ($fx <> 1) {
            $form['HrTable'][$n]['fx']['#value'] = $fx;
            $form['HrTable'][$n]['fx']['#required'] = true;
        } else {
            $form['HrTable'][$n]['fx']['#required'] = false;
            $form['HrTable'][$n]['fx']['#value'] = 1;
        }

        return $form['HrTable'][$n]['fx'];
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $data = $form_state->getValue('HrTable'); 
        $n = 0;
        foreach ($data as $key => $value) { 
            if ($key != 'coid' && $key != 'month' && $value['select'] == 1) {
                $n++;
                if ($value['account'] == '' || !is_numeric($value['account'])) {
                    $form_state->setErrorByName(
                            "HrTable][$key][account", $this->t('The input value is wrong for item @n', array('@n' => $value['account']))
                    );
                }

                if ($value['credit'] == '') {
                    $form_state->setErrorByName(
                            "HrTable][$key][credit", $this->t('The input value is wrong for item @n', array('@n' => $value['credit']))
                    );
                }

                if ($value['fx'] == '0' || !is_numeric($value['fx'])) {
                    $form_state->setErrorByName(
                            "HrTable][$key][fx", $this->t('The input value is wrong for item @n', array('@n' => $value['fx']))
                    );
                }

                $net = (float) str_replace(',', '', $value['net']);
                if ($net == '' || !is_numeric($net)) {
                    $form_state->setErrorByName(
                            "HrTable][$key][net", $this->t('The input value is wrong for item @n', array('@n' => $value['net']))
                    );
                }
            }
        }

        if ($n == 0) {
            $form_state->setErrorByName("HrTable", $this->t('No record selected'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $array = $form_state->getValue('HrTable');
        $journal = new Journal();
        $settings = new HrSettings($array['coid']);
        $list = $settings->HrAccounts[$array['coid']];
        $expenses_entry = 0;
        $journal_entry = 0;
        $coid = $array['coid'];

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_bank_accounts', 'ba')
                ->fields('ba', ['id', 'currency'])
                ->execute();
        $bank_acc_list = $query->fetchAllKeyed();

        foreach ($array as $key => $value) {
            if ($key != 'coid' && $key != 'month' && $value['select'] == 1) {
                $class = substr($value["account"], 0, 2);
                $allocation = $coid;
                $date = explode("-", $value['payDate']);
                $net = (float) str_replace(',', '', $value['net']);


                if (strpos($value['credit'], "-")) {
                    $cash = 'Y';
                    $credit = $value['credit'];
                    $acc = explode('-', $value['credit']);
                    $crt_currency = $acc[0];
                } else {
                    $cash = $value['credit'];
                    $credit = $value['credit'];
                    $crt_currency = $bank_acc_list[$value['credit']];
                }


                if ($value['currency'] <> $crt_currency) {
                    //currency of credit account is different from currency of value
                    $rate2 = \Drupal\ek_finance\CurrencyData::rate($value['currency']);
                    $rate1 = \Drupal\ek_finance\CurrencyData::rate($crt_currency);
                    $net = round($net * $rate1 / $rate2, $this->rounding);
                    $amount = round($net / $value['fx'], $this->rounding);
                    $currency = $crt_currency;
                } else {
                    $amount = round($net / $value['fx'], $this->rounding);
                    $currency = $value['currency'];
                }

                $fields = array(
                    'class' => $class,
                    'type' => $value['account'],
                    'allocation' => $allocation,
                    'company' => $coid,
                    'localcurrency' => $net,
                    'rate' => $value['fx'],
                    'amount' => $amount,
                    'currency' => $currency,
                    'amount_paid' => $amount,
                    'year' => $date[0],
                    'month' => $date[1],
                    'comment' => Xss::filter($value['description']),
                    'pcode' => $value['pcode'],
                    'clientname' => $value['client'],
                    'suppliername' => '0',
                    'receipt' => 'no',
                    'employee' => 'n/a',
                    'status' => 'paid',
                    'cash' => $cash,
                    'pdate' => $value['payDate'],
                    'reconcile' => '0',
                    'attachment' => '',
                );

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_expenses')
                        ->fields($fields)
                        ->execute();

                if ($insert) {
                    $expenses_entry++;
                }

                //
                //Record the accounting journal
                //


                $d = unserialize($value['deductions']);

                $net = str_replace(',', '', $value['net']);
                $gross = $net;

                for ($i = 0; $i < count($d); $i++) {
                    //add deduction to value to be credited to liabilities
                    $gross = $gross + $d[$i];
                }

                if ($value['currency'] <> $crt_currency) {
                    //currency of credit account is different from currency of value
                    $net = round($net * $rate1 / $rate2, $this->rounding);
                    $gross = round($gross * $rate1 / $rate2, $this->rounding);
                    $currency = $crt_currency;
                } else {
                    $currency = $value['currency'];
                }
                //record the total liabilities payable (included the above 'paid' net salary) - DT and credit the expense account
                $journal->record(
                        array(
                            'source' => "expense payroll",
                            'coid' => $coid,
                            'aid' => $value['account'],
                            'reference' => $insert,
                            'fxRate' => $value['fx'],
                            'date' => $value['payDate'],
                            'value' => $gross,
                            'currency' => $currency,
                            'p1' => $net,
                            'p1a' => $list['pay_account'],
                            'funds' => array(
                                'f1' => $d[0],
                                'f1a' => $list['fund1_account'],
                                'f2' => $d[1],
                                'f2a' => $list['fund2_account'],
                                'f3' => $d[2],
                                'f3a' => $list['fund3_account'],
                                'f4' => $d[3],
                                'f4a' => $list['fund4_account'],
                                'f5' => $d[4],
                                'f5a' => $list['fund5_account'],
                            ),
                            'tax' => array(
                                't1' => $d[5],
                                't1a' => $list['tax1_account'],
                                't2' => $d[6],
                                't2a' => $list['tax2_account'],
                            ),
                        )
                );

                //pay net salary to employee (DT liabilities, CT bank)
                $journal->record(
                        array(
                            'source' => "payroll",
                            'coid' => $coid,
                            'aid' => $list['pay_account'],
                            'bank' => $credit,
                            'reference' => $insert,
                            'date' => $value['payDate'],
                            'value' => $net,
                            'currency' => $currency,
                            'tax' => '',
                            'fxRate' => $value['fx'],
                        )
                );
            } // if include
        }

        if (round($journal->credit, 4) <> round($journal->debit, 4)) {
            $msg = 'debit: ' . $journal->debit . ' <> ' . 'credit: ' . $journal->credit;
            \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
        }

        \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
        \Drupal::messenger()->addStatus(t('Expenses recorded'));
        $form_state->setRedirect('ek_finance.manage.list_expense');
    }

}
