<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\updateJournalSales
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a form to record sales data in journal
 */
class updateJournalSales extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_update_sales_journal';
    }

    public function __construct() {
        $this->Financesettings = new \Drupal\ek_finance\FinanceSettings();
        $this->journal = new \Drupal\ek_finance\Journal();
        $this->chart = $this->Financesettings->get('chart');
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $count_invoices = null, $count_purchases = null, $coid = null) {
        $header = [
            'id' => $this->t('ID'),
            'document' => $this->t('Document'),
            'serial' => $this->t('Document reference'),
            'select' => $this->t('Select'),
            'account' => $this->t('Sales account'),
            'bank' => $this->t('Payment account'),
            'type' => '',
        ];
        $form['list'] = array(
            '#type' => 'table',
            '#caption' => ['#markup' => '<h2>' . $this->t('Sales document') . '</h2>'],
            '#header' => $header,
        );
        $options = [];
        $opt = \Drupal\ek_finance\AidList::listaid($coid, array($this->chart['income'], $this->chart['other_income']), 1);
        foreach ($count_invoices as $id => $arr) {
            $form['list'][$id]['id'] = array(
                '#type' => 'item',
                '#markup' => $id,
            );

            $form['list'][$id]['document'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Invoice'),
            );

            $url = Url::fromRoute('ek_sales.invoices.print_html', ['id' => $id], [])->toString();
            $form['list'][$id]['serial'] = array(
                '#type' => 'item',
                '#markup' => "<a target='_blank' href='" . $url . "'>" . $arr['serial'] . "</a>",
            );

            $form['list'][$id]['select'] = array(
                '#type' => 'checkbox',
                '#default_value' => 1,
            );
            $form['list'][$id]['account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $opt,
                '#required' => true,
                '#attributes' => array('style' => array('width:280px;;white-space:nowrap')),
            );

            if ($arr['status'] > 0) {
                $form['list'][$id]['bank'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => \Drupal\ek_finance\BankData::listbankaccountsbyaid($coid),
                    '#default_value' => null,
                    '#required' => true,
                    '#attributes' => array('style' => array('width:280px;;white-space:nowrap')),
                ];
            } else {
                $form['list'][$id]['bank'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('not paid'),
                );
            }

            $form['list'][$id]['type'] = array(
                '#type' => 'hidden',
                '#value' => 'invoice',
            );
        }

        $opt = \Drupal\ek_finance\AidList::listaid($coid, array($this->chart['assets'], $this->chart['cos'], $this->chart['expenses'], $this->chart['other_expenses']), 1);

        foreach ($count_purchases as $id => $arr) {
            $form['list'][$id]['id'] = array(
                '#type' => 'item',
                '#markup' => $id,
            );

            $form['list'][$id]['document'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Purchase'),
            );

            $url = Url::fromRoute('ek_sales.purchases.print_html', ['id' => $id], [])->toString();
            $form['list'][$id]['serial'] = array(
                '#type' => 'item',
                '#markup' => "<a target='_blank' href='" . $url . "'>" . $arr['serial'] . "</a>",
            );

            $form['list'][$id]['select'] = array(
                '#type' => 'checkbox',
                '#default_value' => 1,
            );

            $form['list'][$id]['account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $opt,
                '#required' => true,
                '#attributes' => array('style' => array('width:280px;;white-space:nowrap')),
            );

            if ($arr['status'] > 0) {
                $form['list'][$id]['bank'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => \Drupal\ek_finance\BankData::listbankaccountsbyaid($coid),
                    '#default_value' => null,
                    '#required' => true,
                    '#attributes' => array('style' => array('width:280px;;white-space:nowrap')),
                ];
            } else {
                $form['list'][$id]['bank'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('not paid'),
                );
            }

            $form['list'][$id]['type'] = array(
                '#type' => 'hidden',
                '#value' => 'purchase',
            );
        }


        $form['#tree'] = true;


        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['actions']['access'] = array(
            '#id' => 'accessbutton',
            '#type' => 'submit',
            '#value' => $this->t('Update journal'),
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        foreach ($form_state->getValue('list') as $key => $data) {
            $baseCurrency = $this->Financesettings->get('baseCurrency');

            //Record invoices
            if ($data['type'] == 'invoice' && $data['select'] == 1) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_invoice', 'i');
                $query->fields('i');
                $query->condition('id', $key);
                $invoice = $query->execute()->fetchObject();

                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_invoice_details', 'd');
                $query->fields('d');
                $query->condition('serial', $invoice->serial);
                $details = $query->execute();

                while ($d = $details->fetchObject()) {
                    if ($invoice->taxvalue > 0 && $d->opt == 1) {
                        $tax = round($d->value * $d->quantity * $invoice->taxvalue / 100, 2);
                    } else {
                        $tax = 0;
                    }
                    if ($baseCurrency != $invoice->currency) {
                        $currencyRate = round($invoice->amount / $invoice->amountbase, 2);
                    } else {
                        $currencyRate = 1;
                    }

                    $this->journal->record(
                            [
                                'source' => "invoice",
                                'coid' => $invoice->head,
                                'aid' => $data['account'],
                                'reference' => $key,
                                'date' => $invoice->date,
                                'value' => $d->total,
                                'currency' => $invoice->currency,
                                'fxRate' => $currencyRate,
                                'tax' => $tax,
                            ]
                    );
                }

                //tax
                $this->journal->recordtax(
                        [
                            'source' => "invoice",
                            'coid' => $invoice->head,
                            'reference' => $key,
                            'date' => $invoice->date,
                            'currency' => $invoice->currency,
                            'fxRate' => $currencyRate,
                            'type' => 'stax_collect_aid',
                        ]
                );

                if ($invoice->status > 0 && $invoice->amountreceived > 0) {
                    //record amount received
                    $this->journal->record(
                            [
                                'source' => "receipt",
                                'coid' => $invoice->head,
                                'aid' => $invoice->bank,
                                'reference' => $key,
                                'date' => $invoice->pay_date,
                                'value' => $invoice->amountreceived,
                                'taxable' => $tax,
                                'tax' => $invoice->taxvalue,
                                'currency' => $invoice->currency,
                                'rate' => $currencyRate,
                                'fxRate' => $currencyRate,
                                'fxRate2' => $currencyRate,
                            ]
                    );
                }

                if (round($this->journal->credit, 4) <> round($this->journal->debit, 4)) {
                    $msg = 'debit: ' . $this->journal->debit . ' <> ' . 'credit: ' . $this->journal->credit;
                    \Drupal::messenger()->addError(t('Error journal record (@aid) for invoice @i', ['@aid' => $msg, '@i' => $invoice->serial]));
                }
            }

            //Record purchases
            if ($data['type'] == 'purchase' && $data['select'] == 1) {
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase', 'p');
                $query->fields('p');
                $query->condition('id', $key);
                $purchase = $query->execute()->fetchObject();

                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase_details', 'd');
                $query->fields('d');
                $query->condition('serial', $purchase->serial);
                $details = $query->execute();

                while ($d = $details->fetchObject()) {
                    if ($purchase->taxvalue > 0 && $d->opt == 1) {
                        $tax = round($d->value * $d->quantity * $purchase->taxvalue / 100, 2);
                    } else {
                        $tax = 0;
                    }
                    if ($baseCurrency != $purchase->currency) {
                        $currencyRate = round($purchase->amount / $purchase->amountbc, 2);
                    } else {
                        $currencyRate = 1;
                    }
                    $this->journal->record(
                            [
                                'source' => "purchase",
                                'coid' => $purchase->head,
                                'aid' => $data['account'],
                                'reference' => $key,
                                'date' => $purchase->date,
                                'value' => $d->total,
                                'currency' => $purchase->currency,
                                'fxRate' => $currencyRate,
                                'tax' => $tax,
                            ]
                    );
                }

                $this->journal->recordtax(
                        [
                            'source' => "purchase",
                            'coid' => $purchase->head,
                            'reference' => $key,
                            'date' => $purchase->date,
                            'currency' => $purchase->currency,
                            'type' => 'stax_deduct_aid',
                        ]
                );

                if ($purchase->status > 0 && $purchase->amountpaid > 0) {
                    //record amount received
                    $this->journal->record(
                            array(
                                'source' => "payment",
                                'coid' => $purchase->head,
                                'aid' => $data['bank'],
                                'reference' => $key,
                                'date' => $purchase->pdate,
                                'value' => $purchase->amountpaid,
                                'taxable' => $tax,
                                'tax' => $purchase->taxvalue,
                                'currency' => $purchase->currency,
                                'rate' => $currencyRate,
                                'fxRate' => $currencyRate,
                            )
                    );
                }

                if ($this->journal->credit <> $this->journal->debit) {
                    $msg = 'debit: ' . $this->journal->debit . ' <> ' . 'credit: ' . $this->journal->credit;
                    \Drupal::messenger()->addError(t('Error journal record (@aid) for purchase @p', ['@aid' => $msg, '@p' => $purchase->serial]));
                }
            }
        }

        \Drupal::messenger()->addStatus(t("Journal updated"));
    }

}
