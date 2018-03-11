<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\ReceiveInvoice.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to record payment receipt.
 */
class ReceiveInvoice extends FormBase {

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
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $this->settings = new FinanceSettings();
            $this->journal = new Journal();
        }
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
        return 'ek_sales_receive_invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        $data = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT * from {ek_sales_invoice} where id=:id", array(':id' => $id))->fetchObject();

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $baseCurrency = $this->settings->get('baseCurrency');
        }

        $url = Url::fromRoute('ek_sales.invoices.list', array(), array())->toString();
        $form['back'] = array(
          '#type' => 'item',
          '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

        );
        
        $form['edit_invoice'] = array(
            '#type' => 'item',
            '#markup' => t('Invoice ref. @p', array('@p' => $data->serial)),
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );

        $form['date'] = array(
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => date('Y-m-d'),
            '#title' => t('Payment date'),
        );

        if ($this->moduleHandler->moduleExists('ek_finance')) {

            //bank account

            $settings = new CompanySettings($data->head);
            $aid = $settings->get('cash_account', $data->currency);
            if ($aid <> '') {
                $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
                $name = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':c' => $data->head, ':a' => $aid))
                        ->fetchField();
                $key = $data->currency . "-" . $aid;
                $cash = array($key => $name);
            }
            $aid = $settings->get('cash2_account', $data->currency);
            if ($aid <> '') {
                $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
                $name = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':c' => $data->head, ':a' => $aid))
                        ->fetchField();
                $key = $data->currency . "-" . $aid;
                $cash += array($key => $name);
            }

            $options[(string) t('cash')] = $cash;
            $options[(string) t('bank')] = BankData::listbankaccountsbyaid($data->head);

            $form['bank_account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $options,
                '#required' => TRUE,
                '#default_value' => $data->bank,
                '#title' => t('Account receiving payment'),
                '#ajax' => array(
                    'callback' => array($this, 'debit_fx_rate'),
                    'wrapper' => 'fx',
                ),
            );
        }

        if ($data->taxvalue > 0) {
            $query = "SELECT sum(quantity*value) from {ek_sales_invoice_details} WHERE serial=:s and opt=:o";
            $details = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $data->serial, ':o' => 1))
                    ->fetchField();
            $amount = ($details * (1+($data->taxvalue / 100)) - $data->amountreceived) ;
            
            $title = t('Amount with taxes (@c)', array('@c' => $data->currency));
        } else {
            $amount = $data->amount - $data->amountreceived;
            $title = t('Amount (@c)', array('@c' => $data->currency));
        }

        $form['balance'] = array(
            '#type' => 'hidden',
            '#value' => $amount,
        );

        $form['amount'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#required' => TRUE,
            '#default_value' => number_format($amount, 2),
            '#title' => $title,
            '#attributes' => array('class' => array('amount')),
            '#ajax' => array(
                'callback' => array($this, 'short_pay'),
                'wrapper' => 'short',
                'event' => 'change',
            ),
            '#prefix' => "<div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if ($data->currency != $baseCurrency) {
                $form['fx_rate'] = array(
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#maxlength' => 255,
                    '#default_value' => CurrencyData::rate($data->currency),
                    '#required' => TRUE,
                    '#title' => t('Base currency exchange rate'),
                    '#description' => '',
                    '#attributes' => array('class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                );
            } else {
                $form['fx_rate'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                );
            }
        } else {
            $form['fx_rate'] = array(
                '#type' => 'hidden',
                '#value' => 1,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            );
        }

        //calculate short payments
        if(!NULL == $form_state->getValue('amount')) {
            $balance = $form_state->getValue('balance') - str_replace(',', '', $form_state->getValue('amount'));
        } else {
            $balance = 0;
        }

        $form['short'] = array(
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 255,
            '#value' => number_format($balance, 2),
            '#required' => FALSE,
            '#title' => t('Short payment'),
            '#description' => '',
            '#attributes' => array('class' => array('amount')),
            '#disabled' => TRUE,
            '#prefix' => "<div id='short'>",
            '#suffix' => '</div>',
        );
        $form['close'] = array(
            '#type' => 'checkbox',
            '#title' => t('Force close invoice'),
            '#prefix' => "<div id='close' >",
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(
                    "input[name='short']" => array('value' => '0'),
                ),
            ),
        );

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $options = array('n/a' => t('not applicable'));
            $chart = $this->settings->get('chart');
            $options += AidList::listaid($data->head, array($chart['expenses'], $chart['other_expenses']), 1);
            $form['aid'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#title' => t('Select short payment debit account (apply to charges)'),
                '#options' => $options,
                '#required' => FALSE,
                '#attributes' => array(),
                '#prefix' => "<div id='aid'>",
                '#suffix' => '</div>',
                '#states' => array(
                    'invisible' => array(
                        "input[name='close']" => array('checked' => FALSE),
                    ),
                ),
            );


// Force the input of debit exchange rate if payment is received
// on account with different currency
            if (strpos($data->bank, "-")) {
                //the currency is in the form value
                $ct = explode("-", $data->bank);
                $currency2 = $ct[0];
            } else {
                // bank account
                $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
                $currency2 = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $data->bank))
                        ->fetchField();
            }

            if ($data->currency <> $currency2) {
                $required = TRUE;
                $default = '';
            } else {
                $required = FALSE;
                $default = 1;
            }
            $form['debit_fx_rate'] = array(
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 255,
                '#default_value' => $default,
                '#required' => $required,
                '#title' => t('Debit exchange rate'),
                '#description' => '',
                '#prefix' => "<div id='fx'>",
                '#suffix' => '</div>',
                '#ajax' => array(
                    'callback' => array($this, 'credit_amount'),
                    'wrapper' => 'fx',
                    'event' => 'change',
                ),
            );
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );

        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';


        return $form;
    }

    /**
     * Callback : short value
     */
    public function short_pay(array &$form, FormStateInterface $form_state) {

        //$form_state->setRebuild();
        return $form['short'];
    }

    /**
     * Callback: if selected bank account is not in the invoice currency, provide a choice for exchange rate
     */
    public function debit_fx_rate(array &$form, FormStateInterface $form_state) {

        $query = "SELECT currency from {ek_sales_invoice} where id=:id";
        $currency = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchField();

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value

            $data = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $data[0];
        } else {
            // bank account
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency2 = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('bank_account')))
                    ->fetchField();
        }


        if ($currency <> $currency2) {

            $form['debit_fx_rate']['#required'] = TRUE;

            $purchase_rate = CurrencyData::rate($currency);
            $pay_rate = CurrencyData::rate($currency2);
            if ($pay_rate && $purchase_rate) {
                $form['debit_fx_rate']['#value'] = round($pay_rate / $purchase_rate, 4);
                $amount = str_replace(',', '', $form_state->getValue('amount'));
                $credit = round($amount * $pay_rate / $purchase_rate, 4);
                $form['debit_fx_rate']['#description'] = t('Amount debited @c @a', array('@c' => $currency2, '@a' => $credit));
            } else {
                $form['debit_fx_rate']['#value'] = 0;
                $form['debit_debit_fx_rate']['#description'] = '';
            }
        } else {
            $form['debit_fx_rate']['#required'] = False;
            $form['debit_fx_rate']['#value'] = 1;
            $form['debit_fx_rate']['#description'] = '';
        }

        return $form['debit_fx_rate'];
    }

    /**
     * Callback: update credit amount estimated when manual fx change
     */
    public function credit_amount(array &$form, FormStateInterface $form_state) {

        $query = "SELECT currency from {ek_sales_invoice} where id=:id";
        $currency = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form['for_id']['#value']))
                ->fetchField();

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value

            $data = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $data[0];
        } else {
            // bank account
            $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
            $currency2 = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('bank_account')))
                    ->fetchField();
        }


        if ($currency <> $currency2) {

            if ($form_state->getValue('debit_fx_rate')) {
                $amount = str_replace(',', '', $form_state->getValue('amount'));
                $credit = round($amount * $form_state->getValue('debit_fx_rate'), 4);
                $form['debit_fx_rate']['#description'] = t('Amount debited @c @a', array('@c' => $currency2, '@a' => $credit));
            } else {
                $form['debit_fx_rate']['#description'] = '';
            }
        } else {
            $form['debit_fx_rate']['#required'] = False;
            $form['debit_fx_rate']['#value'] = 1;
            $form['debit_fx_rate']['#description'] = '';
        }

        return $form['debit_fx_rate'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if ($form_state->getValue('debit_fx_rate') <= 0 || !is_numeric($form_state->getValue('debit_fx_rate'))) {
                $form_state->setErrorByName("debit_fx_rate", $this->t('the exchange rate value input is wrong'));
            }
            if ($form_state->getValue('fx_rate') <= 0 || !is_numeric($form_state->getValue('fx_rate'))) {
                $form_state->setErrorByName("fx_rate", $this->t('the base exchange rate value input is wrong'));
            }
        }
        
        //verify amount paid does not exceed amount due or partially paid
        $this_pay = str_replace(",", "", $form_state->getValue('amount'));
        
        $query = "SELECT * from {ek_sales_invoice} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();
            $query = "SELECT sum(quantity*value) FROM {ek_sales_invoice_details} WHERE serial=:s ";
            $details = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $data->serial))
                    ->fetchField();
            //max payment is calculated from recorded total amount +
            //optional taxes applied per item line
            //$max_pay = round($data->amount + ($details * $data->taxvalue / 100), 2);
            $max_pay = ($details * (1+($data->taxvalue / 100)) - $data->amountreceived) ;
            //store data
            $form_state->set('max_pay', $max_pay);
            $form_state->set('details', $details);   
            
        if (!$this->moduleHandler->moduleExists('ek_finance')) {
      
            if ($this_pay > $max_pay) {
                $form_state->setErrorByName('amount', $this->t('payment exceeds invoice amount (@a, @b)', ['@a' => $this_pay, '@b' => $max_pay]));
            }            
            
        } else {
          
            //check from journal
            $companysettings = new CompanySettings($data->head);
            $assetacc = $companysettings->get('asset_account', $data->currency);

            $a = array(
                'source_dt' => 'invoice',
                'source_ct' => 'receipt',
                'reference' => $form_state->getValue('for_id'),
                'account' => $assetacc,
            );
            $value = round($this->journal->checkTransactionCredit($a), 4);
            $form_state->set('max_pay', abs($value));
            
                if (round($value + $this_pay, 4) > 0) {
                    $a = ['@a' => $value, '@b' => $this_pay, '@c' => $assetacc];
                    $form_state->setErrorByName('amount', $this->t('this payment exceeds receivable balance amount in journal (@a, @b, @c).', $a));
                }            
                      
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $query = "SELECT * from {ek_sales_invoice} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();

        $this_pay = str_replace(",", "", $form_state->getValue('amount'));
        $max_pay = round($form_state->get('max_pay'), 2);
        $taxable = round($form_state->get('details') * $this_pay / $max_pay, 4);
        $rate = round($data->amount / $data->amountbase, 4); //original rate used to calculate currency gain/loss

        

        if ($this->moduleHandler->moduleExists('ek_finance') && $this_pay > 0) {
            $currencyRate = CurrencyData::rate($data->currency);
            $baseCurrency = $this->settings->get('baseCurrency');
            $this->journal->record(
                    array(
                        'source' => "receipt",
                        'coid' => $data->head,
                        'aid' => $form_state->getValue('bank_account'),
                        'reference' => $form_state->getValue('for_id'),
                        'date' => $form_state->getValue('date'),
                        'value' => $this_pay,
                        'taxable' => $taxable,
                        'tax' => $data->taxvalue,
                        'currency' => $data->currency,
                        'rate' => $rate,
                        'fxRate' => $form_state->getValue('fx_rate'),
                        'fxRate2' => $form_state->getValue('debit_fx_rate'),
                    )
            );
            
            if($this->journal->credit <> $this->journal->debit) {
                $msg = 'debit: ' . $this->journal->debit . ' <> ' . 'credit: ' . $this->journal->credit;
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            } 
        }

        $amountpaid = $data->amountreceived + $this_pay;
        if ($this_pay == $max_pay || $form_state->getValue('close') == 1) {
            $paid = 1; //full payment
            $short = str_replace(",", "", $form_state->getValue('short'));
            if ($short > 0 && $this->moduleHandler->moduleExists('ek_finance')) {
                //record a short payment applied to charges
                if ($form_state->getValue('aid') != 'n/a') {
                    $this->journal->record(
                            array(
                                'source' => "short payment",
                                'coid' => $data->head,
                                'aid' => $form_state->getValue('aid'),
                                'reference' => $form_state->getValue('for_id'),
                                'date' => $form_state->getValue('date'),
                                'value' => $short,
                                'taxable' => $taxable,
                                'tax' => $data->taxvalue,
                                'currency' => $data->currency,
                                'rate' => $rate,
                                'fxRate' => $form_state->getValue('fx_rate'),
                                'fxRate2' => $form_state->getValue('debit_fx_rate'),
                            )
                    );
                }
            }
        } else {
            $paid = 2; // partial payment (can't edit anymore)
        }

        
        //the balance base recorded is without tax
        //$balancebase = round($data->balancebase - ($this_pay / $rate), 2);
        $balancebase = round($data->balancebase - ($this_pay / (1 + $data->taxvalue / 100) / $rate), 2);
        $fields = array(
            'status' => $paid,
            'amountreceived' => $amountpaid,
            'balancebase' => $balancebase,
            'pay_date' => $form_state->getValue('date'),
        );

        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_invoice')->fields($fields)
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();

        if ($update) {
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if invoice is linked to a project
                if ($data->pcode && $data->pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $data->pcode])
                            ->fetchField();
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => 'invoice_payment',
                                'value' => $data->serial,
                                'pcode' => $data->pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
            $form_state->setRedirect('ek_sales.invoices.list');
        }

    }

}
