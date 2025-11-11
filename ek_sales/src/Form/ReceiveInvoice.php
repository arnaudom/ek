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

    protected $moduleHandler;
    protected $settings;
    protected $journal;
    
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
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_invoice', 'i');
        $query->fields('i');
        $query->leftJoin('ek_company', 'c', 'c.id = i.head');
        $query->fields('c', ['name']);
        $query->condition('i.id', $id, '=');
        $data = $query->execute()->fetchObject();
        $balance = $data->amount - $data->amountreceived;
        $total_receivable = $data->amount;
        $total_received = $data->amountreceived;
        $form_state->set('invoiceCurrency', $data->currency);

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $baseCurrency = $this->settings->get('baseCurrency');
            // set warning for configuration changes that may alter journal records
            $companysettings = new CompanySettings($data->head);
            $asset_account = $companysettings->get('asset_account', $data->currency);
            $stax_collect_aid = $companysettings->get('stax_collect_aid');
            $jrecord = $this->journal->entity_history(['entity' => 'invoice', 'format' => 'array','id' => $id]);
            $asset_flag = 0;
            $total_receivable = 0;
            $total_received = 0;
                foreach($jrecord as $key => $line) { 
                    if($line['aid'] == $asset_account) {
                        $asset_flag = 1;
                    }
                    if($line['aid'] == $asset_account && $line['type'] == 'debit' && $line['exchange'] == 0) {
                        $total_receivable += $line['value'];
                    }
                    if($line['aid'] == $asset_account && $line['type'] == 'credit' && $line['exchange'] == 0) {
                        $total_received += $line['value'];
                    }
                }

            if ($asset_flag == 0) {
                $alert = "<div class='messages messages--warning'>"
                        . t(
                                'Invoice was recorded with a different @acc account than the one currently selected in your settings. '
                                . 'Verify <a href="@url">settings</a>.', ['@acc' => $this->t('account receivable'), 
                                    '@url' => Url::fromRoute('ek_admin.company_settings.edit', ['id' => $data->head])->toString()]
                        ) . "</div>";
                $form['alert1'] = array(
                    '#type' => 'item',
                    '#markup' => $alert,
                );
            }

            if ($data->taxvalue > 0) {
                $tax_flag = 0;
                foreach($jrecord as $key => $line) { 
                    if($line['aid'] == $stax_collect_aid) {
                        $tax_flag = 1;
                    }
                }
                if ($tax_flag == 0) {
                    $alert = "<div class='messages messages--warning'>"
                            . t(
                                    'Invoice was recorded with a different @acc account than the one currently selected in your settings. '
                                    . 'Verify <a href="@url">settings</a>.', ['@acc' => $this->t('tax collection'), 
                                        '@url' => Url::fromRoute('ek_admin.company_settings.edit', ['id' => $data->head])->toString()]
                            ) . "</div>";
                    $form['alert2'] = [
                        '#type' => 'item',
                        '#markup' => $alert,
                    ];
                }

            }

            $balance = $total_receivable - $total_received;

        }

        $url = Url::fromRoute('ek_sales.invoices.list', [],[])->toString();
        $form['back'] = [
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => $url)),
        ];

        $form['edit_invoice'] = [
            '#type' => 'item',
            '#markup' => $this->t('Invoice ref. @p', array('@p' => $data->serial)),
        ];
        $form['company'] = [
            '#type' => 'item',
            '#markup' => "<p>" . $data->name . "</p>",
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];

        $form['invoice_data'] = [
            '#type' => 'value',
            '#value' => $data,
        ];

        $form['date'] = [
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => true,
            '#default_value' => date('Y-m-d'),
            '#title' => $this->t('Payment date'),
        ];

        if ($this->moduleHandler->moduleExists('ek_finance')) {

            // bank account
            $settings = new CompanySettings($data->head);
            $aid = $settings->get('cash_account', $data->currency);
            if ($aid <> '') {
                $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
                $key = $data->currency . "-" . $aid;
                $cash = array($key => AidList::aname($data->head, $aid));
            }
            $aid = $settings->get('cash2_account', $data->currency);
            if ($aid <> '') {
                $key = $data->currency . "-" . $aid;
                $cash += array($key => AidList::aname($data->head, $aid));
            }

            $options[(string) $this->t('cash')] = $cash;
            $options[(string) $this->t('bank')] = BankData::listbankaccountsbyaid($data->head);

            $form['bank_account'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $options,
                '#required' => true,
                '#default_value' => $data->bank,
                '#title' => $this->t('Account receiving payment'),
                '#ajax' => array(
                    'callback' => array($this, 'debit_fx_rate'),
                    'wrapper' => 'fx',
                ),
            ];
        }

        if ($data->taxvalue > 0) {    
            $title = $this->t('Amount with taxes (@c)', array('@c' => $data->currency));
        } else {
            $title = $this->t('Amount (@c)', array('@c' => $data->currency));
        }

        $form['balance'] = [
            '#type' => 'value',
            '#value' => $balance,
        ];
        $form['receivable'] = [
            '#type' => 'value',
            '#value' => $total_receivable,
        ];
        $form['received'] = [
            '#type' => 'value',
            '#value' => $total_received,
        ];

        $form['amount'] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#required' => true,
            '#default_value' => number_format($balance, 2),
            '#title' => $title,
            '#attributes' => ['class' => array('amount')],
            '#ajax' => [
                'callback' => [$this, 'short_pay'],
                'wrapper' => 'short',
                'event' => 'change',
            ],
            '#prefix' => "<div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        ];

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if ($data->currency != $baseCurrency) {
                $form['fx_rate'] = [
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#maxlength' => 255,
                    '#default_value' => CurrencyData::rate($data->currency),
                    '#required' => true,
                    '#title' => $this->t('Base currency exchange rate'),
                    '#description' => '',
                    '#attributes' => array('class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                ];
            } else {
                $form['fx_rate'] = [
                    '#type' => 'hidden',
                    '#value' => 1,
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div>',
                ];
            }
        } else {
            $form['fx_rate'] = [
                '#type' => 'hidden',
                '#value' => 1,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            ];
        }

        // calculate short payments
        if (!null == $form_state->getValue('amount')) { 
            $balance = $form_state->getValue('balance') - str_replace(',', '', $form_state->getValue('amount'));
        } else {
            $balance = 0;
        }

        $form['short'] = [
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 255,
            '#value' => number_format($balance, 2),
            '#required' => false,
            '#title' => $this->t('Short payment'),
            '#description' => '',
            '#attributes' => array('class' => array('amount')),
            '#disabled' => true,
            '#prefix' => "<div id='short'>",
            '#suffix' => '</div>',
        ];

        $form['close'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Force close invoice'),
            '#description' => $this->t('Select to close invoice when amount is not fully received'),
            '#prefix' => "<div id='close' >",
            '#suffix' => '</div>',
            '#states' => [
                'invisible' => [
                    "input[name='short']" => ['value' => '0'],
                ],
            ],
        ];

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $options = ['n/a' => $this->t('not applicable')];
            $chart = $this->settings->get('chart');
            $options += AidList::listaid($data->head, array($chart['expenses'], $chart['other_expenses']), 1);
            $form['aid'] = [
                '#type' => 'select',
                '#size' => 1,
                '#title' => $this->t('Select short payment debit account (apply to charges)'),
                '#options' => $options,
                '#required' => false,
                '#attributes' => [],
                '#prefix' => "<div id='aid'>",
                '#suffix' => '</div>',
                '#states' => [
                    'invisible' => [
                        "input[name='close']" => ['checked' => false],
                    ],
                ],
            ];


            // Force the input of debit exchange rate if payment is received
            // on account with different currency
            if (strpos($data->bank, "-")) {
                // the currency is in the form value
                $ct = explode("-", $data->bank);
                $currency2 = $ct[0];
            } else {
                // bank account
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_bank_accounts', 'b');
                $query->fields('b', ['currency']);
                $query->condition('id', $data->bank, '=');
                $currency2 = $query->execute()->fetchField();
                $form_state->set('bankAccountCurrency', $currency2);
            }

            if ($data->currency <> $currency2) {
                $required = true;
                $default = '';
            } else {
                $required = false;
                $default = 1;
            }
            $form['debit_fx_rate'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 255,
                '#default_value' => $default,
                '#required' => $required,
                '#title' => $this->t('Debit exchange rate'),
                '#description' => '',
                '#prefix' => "<div id='fx'>",
                '#suffix' => '</div>',
                '#ajax' => [
                    'callback' => [$this, 'credit_amount'],
                    'wrapper' => 'fx',
                    'event' => 'change',
                ],
            ];
        }

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['record'] = [
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        ];

        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';


        return $form;
    }

    /**
     * Callback : short value
     */
    public function short_pay(array &$form, FormStateInterface $form_state) {
        return $form['short'];
    }

    /**
     * Callback: if selected bank account is not in the invoice currency, provide a choice for exchange rate
     */
    public function debit_fx_rate(array &$form, FormStateInterface $form_state) {
        $currency = $form_state->get('invoiceCurrency');

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "-")) {
            //the currency is in the form value
            $data = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $data[0];
        } else {
            // bank account
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank_accounts', 'b');
            $query->fields('b', ['currency']);
            $query->condition('id', $form_state->getValue('bank_account', '='));
            $currency2 = $query->execute()->fetchField();
        }


        if ($currency <> $currency2) {
            $form['debit_fx_rate']['#required'] = true;
            $purchase_rate = CurrencyData::rate($currency);
            $pay_rate = CurrencyData::rate($currency2);
            if (is_numeric($pay_rate) && is_numeric($purchase_rate) && $pay_rate && $purchase_rate) {
                $form['debit_fx_rate']['#value'] = round(floatval($pay_rate) / floatval($purchase_rate), 4);
                $amount = str_replace(',', '', $form_state->getValue('amount'));
                $credit = round(floatval($amount) * floatval($pay_rate) / floatval($purchase_rate), 4);
                $form['debit_fx_rate']['#description'] = $this->t('Amount debited @c @a', array('@c' => $currency2, '@a' => $credit));
            } else {
                $form['debit_fx_rate']['#value'] = 0;
                $form['debit_fx_rate']['#description'] = '';
            }
        } else {
            $form['debit_fx_rate']['#required'] = false;
            $form['debit_fx_rate']['#value'] = 1;
            $form['debit_fx_rate']['#description'] = '';
        }

        return $form['debit_fx_rate'];
    }

    /**
     * Callback: update credit amount estimated when manual fx change
     */
    public function credit_amount(array &$form, FormStateInterface $form_state) {
        $currency = $form_state->get('invoiceCurrency');

        // FILTER cash account
        if (strpos($form_state->getValue('bank_account'), "-")) {
            // the currency is in the form value
            $data = explode("-", $form_state->getValue('bank_account'));
            $currency2 = $data[0];
        } else {
            // bank account
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank_accounts', 'b');
            $query->fields('b', ['currency']);
            $query->condition('id', $form_state->getValue('bank_account', '='));
            $currency2 = $query->execute()->fetchField();
        }


        if ($currency <> $currency2) {
            if ($form_state->getValue('debit_fx_rate')) {
                $amount = str_replace(',', '', $form_state->getValue('amount'));
                $credit = round($amount * $form_state->getValue('debit_fx_rate'), 4);
                $form['debit_fx_rate']['#description'] = $this->t('Amount debited @c @a', array('@c' => $currency2, '@a' => $credit));
            } else {
                $form['debit_fx_rate']['#description'] = '';
            }
        } else {
            $form['debit_fx_rate']['#required'] = false;
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

        // verify amount paid does not exceed amount due or partially paid
        $this_pay = str_replace(",", "", $form_state->getValue('amount'));
        $data = $form_state->getValue('invoice_data');
        // store data
        $form_state->set('max_pay', $form_state->getValue('balance'));
        $form_state->set('details', $data->amount);
        //$max_pay = ($data->amount * (1 + ($data->taxvalue / 100)) - $data->amountreceived); 

        // fix decimal number comparison
        $this_pay_cents = round($this_pay * 100);
        $balance_cents = round($form_state->getValue('balance') * 100);

        if ($this_pay_cents > $balance_cents) {
            $form_state->setErrorByName('amount', $this->t('payment exceeds invoice amount (input: @a, receivable: @b)', ['@a' => $this_pay, '@b' => $form_state->getValue('balance')]));
        }

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $data = $form_state->getValue('invoice_data');
        $this_pay = str_replace(",", "", $form_state->getValue('amount'));
        $max_pay = round($form_state->get('max_pay'), 2);
        $taxable = round($this_pay / (1+ $data->taxvalue / 100), 4);
        $rate = round($data->amount / $data->amountbase, 4); //original rate used to calculate currency gain/loss

        if ($this->moduleHandler->moduleExists('ek_finance') && $this_pay > 0) {
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

            if ($this->journal->getCredit() <> $this->journal->getDebit()) {
                $msg = 'debit: ' . $this->journal->getDebit() . ' <> ' . 'credit: ' . $this->journal->getCredit();
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            }
        }

        $amountpaid = $data->amountreceived + $this_pay; 
        if ($this_pay == $max_pay || $form_state->getValue('close') == 1) {
            $paid = 1; // full payment
            $short = str_replace(",", "", $form_state->getValue('short'));
            if ($short > 0 && $this->moduleHandler->moduleExists('ek_finance')) {
                // record a short payment applied to charges
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


        // the balance base recorded is without tax
        if($this_pay == $max_pay || $form_state->getValue('close') == 1) {
            $balancebase = 0;
        } else {
            // need to estimate value based on tax applicable on total amount.
            // value is wrong when not all items are taxed
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice_details', 'd')
                    ->fields('d')
                    ->condition('serial', $data->serial)
                    ->execute();
                $details = $query->fetchAll();
                $total_with_tax = 0;
                $total_no_tax = 0;

                foreach($details as $key => $line) { 
                    if($line->opt == 0) {
                        $total_no_tax += $line->total;
                    } else {
                        $total_with_tax += $line->total;
                    }
                }
            $tax_ratio = 1 - ($total_with_tax / ($total_with_tax + $total_no_tax)); 
            // the portion of payment that is subject to tax:
            $net = ($this_pay / (1+$tax_ratio)) / (1+($data->taxvalue / 100)); 
            // the resulting tax value:
            $tax = $this_pay - ($net + ($net * $data->taxvalue / 100));
            // the actual value in base currency without tax
            $balancebase = round($data->balancebase - (($this_pay - $tax) / $rate), 2); 
        }
        
        $fields = array(
            'status' => $paid,
            'amountreceived' => $amountpaid,
            'balancebase' => $balancebase,
            'pay_date' => $form_state->getValue('date'),
            'pay_rate' => $form_state->getValue('fx_rate'),
        );

        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_invoice')->fields($fields)
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();

        if ($update) {
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                // notify user if invoice is linked to a project
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
                    \Drupal::service('project.service')->notify_user($param);
                }
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            $form_state->setRedirect('ek_sales.invoices.list');
        }
    }

}
