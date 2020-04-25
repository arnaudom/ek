<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\PayPurchase.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\Core\Url;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to record purchases payment.
 */
class PayPurchase extends FormBase {

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
        $this->journal = new Journal();
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
        return 'ek_sales_pay_purchase';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_purchase', 'p');
        $query->fields('p');
        $query->leftJoin('ek_company', 'c', 'c.id = p.head');
        $query->fields('c', ['name']);
        $query->condition('p.id', $id, '=');

        $data = $query->execute()->fetchObject();

        $url = Url::fromRoute('ek_sales.purchases.list', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => $url)),
        );
        $form['edit_purchase'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Purchase ref. @p', array('@p' => $data->serial)),
        );
        $form['company'] = array(
            '#type' => 'item',
            '#markup' => "<p>" . $data->name . "</p>",
        );
        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );

        $form['date'] = array(
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => true,
            '#default_value' => date('Y-m-d'),
            //'#prefix' => "<div class='container-inline'>",
            '#title' => $this->t('payment date'),
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

            $options[(string) $this->t('cash')] = $cash;
            $options[(string) $this->t('bank')] = BankData::listbankaccountsbyaid($data->head, $data->currency);

            $form['bank_account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $options,
                '#required' => true,
                '#default_value' => null,
                '#title' => $this->t('account payment'),
                '#ajax' => array(
                    'callback' => [$this, 'fx_rate'],
                    'wrapper' => 'fx',
                ),
            );
        }

        if ($data->taxvalue > 0) {
            $query = "SELECT sum(quantity*value) from {ek_sales_purchase_details} WHERE serial=:s and opt=:o";
            $details = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $data->serial, ':o' => 1))->fetchField();
            $amount = $data->amount + ($details * $data->taxvalue / 100) - $data->amountpaid;
            $title = $this->t('amount with taxes (@c)', array('@c' => $data->currency));
        } else {
            $amount = $data->amount - $data->amountpaid;
            $title = $this->t('amount (@c)', array('@c' => $data->currency));
        }

        $form['amount'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#required' => true,
            '#default_value' => number_format($amount, 2),
            '#title' => $title,
        );


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $form['fx_rate'] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => '',
                '#required' => false,
                '#title' => $this->t('exchange rate'),
                '#description' => '',
                '#prefix' => "<div id='fx'>",
                '#suffix' => '</div>',
                '#ajax' => array(
                    'callback' => '\Drupal\ek_sales\Form\PayPurchase::credit_amount',
                    'wrapper' => 'fx',
                    'event' => 'change',
                ),
            );
        }

        $form['close'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Force close purchase'),
            '#description' => $this->t('Select to close purchase when amount is not fully paid'),
            '#states' => array(
                'invisible' => array(
                    "input[name='short']" => array('value' => '0'),
                ),
            ),
        );

        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );

        return $form;
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        /* if selected bank account is not in the purchase currency, provide a choice for exchange rate
         */
        $query = "SELECT currency from {ek_sales_purchase} where id=:id";
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

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');

        if ($currency <> $currency2) {
            $form['fx_rate']['#required'] = true;

            $purchase_rate = CurrencyData::rate($currency);
            $pay_rate = CurrencyData::rate($currency2);

            if ($pay_rate && $purchase_rate) {
                $form['fx_rate']['#value'] = round($pay_rate / $purchase_rate, 4);
                $credit = round($form_state->getValue('amount') * $pay_rate / $purchase_rate, 4);
                $desc = $this->t('<strong>Warning</strong>: You are paying from different currency account. This may cause discrepancies.') . '<br/>';
                $desc .= $this->t('Amount credited @c @a', array('@c' => $currency2, '@a' => $credit));
                $form['fx_rate']['#description'] = $desc;
            } else {
                $form['fx_rate']['#value'] = 0;
                $form['fx_rate']['#description'] = '';
            }
        } elseif ($currency == $currency2 && $currency2 != $baseCurrency) {
            $form['fx_rate']['#required'] = true;
            $form['fx_rate']['#value'] = CurrencyData::rate($currency2);
            $form['fx_rate']['#description'] = $baseCurrency;
        } else {
            $form['fx_rate']['#required'] = false;
            $form['fx_rate']['#value'] = 1;
            $form['fx_rate']['#description'] = '';
        }

        return $form['fx_rate'];
    }

    /**
     * Callback : update credit amount estimated when manual fx change
     */
    public function credit_amount(array &$form, FormStateInterface $form_state) {
        $query = "SELECT currency from {ek_sales_purchase} where id=:id";
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

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');

        if ($currency <> $currency2) {
            if ($form_state->getValue('fx_rate')) {
                $credit = round($form_state->getValue('amount') * $form_state->getValue('fx_rate'), 4);
                $desc = $this->t('<strong>Warning</strong>: You are paying from different currency account. This may cause discrepancies.') . '<br/>';
                $desc .= $this->t('Amount credited @c @a', array('@c' => $currency2, '@a' => $credit));
                $form['fx_rate']['#description'] = $desc;
            } else {
                $form['fx_rate']['#description'] = '';
            }
        } elseif ($currency == $currency2 && $currency2 != $baseCurrency) {
            $form['fx_rate']['#required'] = true;

            $form['fx_rate']['#description'] = $baseCurrency;
        } else {
            $form['fx_rate']['#required'] = false;
            $form['fx_rate']['#value'] = 1;
            $form['fx_rate']['#description'] = '';
        }

        return $form['fx_rate'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('fx_rate') <= 0 || !is_numeric($form_state->getValue('fx_rate'))) {
            $form_state->setErrorByName("fx_rate", $this->t('the exchange rate value input is wrong'));
        }

        //verify amount paid does not exceed amount due orpartially paid
        $query = "SELECT * from {ek_sales_purchase} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('for_id')))
                ->fetchObject();
        $this_pay = str_replace(",", "", $form_state->getValue('amount'));
        $query = "SELECT sum(quantity*value) from {ek_sales_purchase_details} WHERE serial=:s and opt=:o";
        $details = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':s' => $data->serial, ':o' => 1))
                ->fetchField();
        $max_pay = round($data->amount + ($details * $data->taxvalue / 100), 2);


        if ($this_pay > $max_pay) {
            $form_state->setErrorByName("amount", $this->t('payment exceeds purchase amount'));
        }
        //validate against partial payments
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            //check from journal
            $companysettings = new CompanySettings($data->head);
            $liabacc = $companysettings->get('liability_account', $data->currency);
            if ($liabacc == '') {
                $form_state->setErrorByName("company", $this->t('Error with liability account'));
            }

            $a = array(
                'source_dt' => 'payment',
                'source_ct' => 'purchase',
                'reference' => $form_state->getValue('for_id'),
                'account' => $liabacc,
            );
            $journal_payable = $this->journal->checkTransactionDebit($a);
            if ($journal_payable == 0 || ($journal_payable + $this_pay) > 0) {
                $form_state->setErrorByName("amount", $this->t('this payment exceeds purchase balance amount in journal'));
            }
        } else {
            if (($this_pay + $data->amountpaid) > $max_pay) {
                $form_state->setErrorByName("amount", $this->t('payment exceeds purchase amount'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_purchase', 'p');
        $query->fields('p');
        $query->condition('id', $form_state->getValue('for_id'));
        $data = $query->execute()->fetchObject();

        $this_pay = str_replace(",", "", $form_state->getValue('amount'));

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_purchase_details', 'p');
        $query->condition('serial', $data->serial);
        $query->fields('p', ['total', 'opt']);
        $lines = $query->execute();

        $total_taxable = 0;
        $total = 0;
        $total_non_taxable = 0;
        while ($d = $lines->fetchObject()) {
            $total += $d->total;
            if ($d->opt == '1') {
                $total_taxable += $d->total;
            } else {
                $total_non_taxable += $d->total;
            }
        }

        $max_pay = round($data->amount + ($total_taxable * $data->taxvalue / 100), 2);

        $amountpaid = $data->amountpaid + $this_pay;
        if ($amountpaid == $max_pay) {
            $paid = 1; //full payment
        } elseif ($form_state->getValue('for_id') == 1) {
            $paid = 1; //close
        } else {
            $paid = 2; // partial payment (can't edit anymore)
        }
        if ($form_state->getValue('fx_rate')) {
            $currencyRate = $form_state->getValue('fx_rate');
        } else {
            $currencyRate = round($total / $data->amountbc, 2);
        }
        //because bal. in base currency is without tax, we need to convert the paid value into
        //base currency without tax
        //on short payment we assume tax are paid in full and short payment applies to non taxed portion
        $v1 = ($total_taxable * ($data->taxvalue / 100)) * $this_pay / ($max_pay - $data->amountpaid); //taxed portion value in current payment
        $v2 = ($this_pay - $v1) / $currencyRate; //payment without tax in base currency
        $balancebc = round($data->balancebc - $v2, 2);

        $fields = array(
            'status' => $paid,
            'amountpaid' => $amountpaid,
            'balancebc' => $balancebc,
            'pdate' => $form_state->getValue('date'),
        );

        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_sales_purchase')->fields($fields)
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $settings = new FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');

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

            if ($data->currency <> $currency2) {
                $fx = round(1 / $form_state->getValue('fx_rate'), 4);
            } elseif ($data->currency == $currency2 && $currency2 != $baseCurrency) {
                $fx = $form_state->getValue('fx_rate');
            } else {
                $fx = 1;
            }

            $taxable = round($total_taxable * $this_pay / ($max_pay - $data->amountpaid), 4);

            $this->journal->record(
                    [
                        'source' => "payment",
                        'coid' => $data->head,
                        'aid' => $form_state->getValue('bank_account'),
                        'reference' => $form_state->getValue('for_id'),
                        'date' => $form_state->getValue('date'),
                        'value' => $this_pay,
                        'taxable' => $taxable,
                        'tax' => $data->taxvalue,
                        'currency' => $data->currency,
                        'rate' => round($total / $data->amountbc, 4),
                        'fxRate' => $fx,
                    ]
            );

            if ($this->journal->credit <> $this->journal->debit) {
                $msg = 'debit: ' . $this->journal->debit . ' <> ' . 'credit: ' . $this->journal->credit;
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            }
        }

        if ($update) {
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if purchase is linked to a project
                if ($data->pcode && $data->pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $data->pcode])
                            ->fetchField();
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => 'purchase_payment',
                                'value' => $data->serial,
                                'pcode' => $data->pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
            $form_state->setRedirect('ek_sales.purchases.list');
        }
    }

}
