<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\AmortizationRecord.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to manage assets amortization record
 */
class AmortizationRecord extends FormBase {

    /**
     * Constructs a AmortizationRecord object
     *   
     */
    public function __construct() {
        $this->settings = new FinanceSettings();
    }

  /**
   * {@inheritdoc}
   */
    public function getFormId() {
        return 'journal_amortization_record';
    }

  /**
   * {@inheritdoc}
   */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $ref = NULL) {

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $baseCurrency = $this->settings->get('baseCurrency');
        $CurrencyOptions = CurrencyData::listcurrency(1);
        $chart = $this->settings->get('chart');
        $CreditOptions = array('0' => '');
        $DebitOptions = array('0' => '');


        $url = Url::fromRoute('ek_assets.set_amortization', array('id' => $id), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t("<a href='@url'>Schedule</a>", ['@url' => $url]),
        );

        $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid "
                . "WHERE id=:id "
                . "AND FIND_IN_SET (coid, :c)";
        $a = array(
            ':id' => $id,
            ':c' => $company,
        );

        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchObject();

        if (!isset($data->id)) {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('You cannot record this asset amortization schedule.'),
            );
        } else {
            $CreditOptions += AidList::listaid($data->coid, array($chart['assets']), 1);
            $DebitOptions += AidList::listaid($data->coid, array($chart['expenses'], $chart['other_expenses']), 1);
            $schedule = unserialize($data->amort_record);

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );
            $form['for_ref'] = array(
                '#type' => 'hidden',
                '#value' => $ref,
            );
            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $data->coid,
            );
            $form['amort_record'] = array(
                '#type' => 'hidden',
                '#value' => $data->amort_record,
            );
            $query = "SELECT name from {ek_company} WHERE id=:id";
            $company = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data->coid))
                    ->fetchField();


            $form['company'] = array(
                '#type' => 'item',
                '#markup' => $company,
            );
            $form['asset'] = array(
                '#type' => 'item',
                '#markup' => $data->asset_name . ' - ' . $data->asset_ref,
            );

            $form['currency'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $CurrencyOptions,
                '#required' => TRUE,
                '#default_value' => $data->currency,
                '#title' => t('Currency'),
                '#ajax' => array(
                    'callback' => array($this, 'fx_rate'),
                    'wrapper' => 'credit',
                ),
                '#prefix' => "<div class='container-inline'>",
            );

            $form['fx_rate'] = array(
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 15,
                '#default_value' => ($data->currency == $baseCurrency) ? 1 : NULL,
                '#required' => FALSE,
                '#title' => t('Exchange rate'),
                '#description' => '',
                '#prefix' => "<div id='credit'>",
                '#suffix' => '</div></div>',
            );

            $form["date"] = array(
                '#type' => 'date',
                '#size' => 12,
                '#required' => TRUE,
                '#default_value' => $schedule['a'][$ref]['record_date'],
                '#title' => t('record date'),
            );

            $headerline = "<div class='table'><div class='row'><div class='cell cellborder'>" . t("Debit account") . "</div><div class='cell cellborder'>" . t("Debit") . "</div><div class='cell cellborder'>" . t("Credit") . "</div><div class='cell cellborder'>" . t("Credit account") . "</div><div class='cell cellborder'>" . t("Comment") . "</div>";

            $totalcredit = 0;
            $totalcredit_exchange = 0;
            $totaldebit = 0;
            $totaldebit_exchange = 0;

            $form['items']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            $totalcredit = 0;
            $totaldebit = 0;

            $form['items']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            $form['items']["d-account1"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $DebitOptions,
                '#default_value' => NULL,
                '#attributes' => array('style' => array('width:150px;white-space:nowrap')),
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']['debit1'] = array(
                '#type' => 'textfield',
                '#id' => 'debit1',
                '#size' => 12,
                '#maxlength' => 60,
                '#description' => '',
                '#default_value' => number_format($schedule['a'][$ref]['value'], 2),
                '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']['credit1'] = array(
                '#type' => 'textfield',
                '#id' => 'credit1',
                '#size' => 12,
                '#maxlength' => 60,
                '#description' => '',
                '#default_value' => number_format($schedule['a'][$ref]['value'], 2),
                '#attributes' => array('placeholder' => t('value'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["c-account1"] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $CreditOptions,
                '#default_value' => NULL,
                '#attributes' => array('style' => array('width:150px;white-space:nowrap')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $no = $ref + 1;
            $comment = t('Depreciation') . ' (' . t('schedule') . ' ' . $no . ') '
                    . $data->asset_name . ', ' . $data->asset_ref;
            $form['items']["comment"] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => $comment,
                '#attributes' => array('placeholder' => t('comment'),),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            );

            $totalcredit += $form_state->getValue('credit1');
            $totaldebit += $form_state->getValue('debit1');

            if ($totalcredit == $totaldebit) {
                $style = '#64FE2E';
            } else {
                $style = '#FF9999';
            }

            //footer
            $form['items']["footer1"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='row'><div class='cell cellborder'>" . $data->currency,
                '#suffix' => '</div>',
            );
            $form['items']["footer2"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder' id='totald' style=\"background-color:$style\">" . number_format($totaldebit, 2) . "",
                '#suffix' => '</div>',
            );
            $form['items']["footer3"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder' id='totalc' style=\"background-color:$style\">" . number_format($totalcredit, 2) . "",
                '#suffix' => '</div>',
            );
            $form['items']["footer4"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder'>",
                '#suffix' => '</div>',
            );
            $form['items']["footer5"] = array(
                '#type' => 'item',
                '#prefix' => "<div class='cell cellborder'>",
                '#suffix' => '</div></div></div>',
            );

            $form['rows'] = array(
                '#type' => 'hidden',
                '#attributes' => array('id' => 'rows'),
                '#value' => 1,
            );
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );



            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            );
        }

        $form['#attached']['library'][] = 'ek_finance/ek_finance.journal_form';


        return $form;
    }

    public function fx_rate(array &$form, FormStateInterface $form_state) {
        $currency = $form_state->getValue('currency');
        $fx = CurrencyData::rate($currency);

        if ($fx <> 1) {
            $form['fx_rate']['#value'] = $fx;
            $form['fx_rate']['#required'] = TRUE;
            $form['credit']['fx_rate']['#description'] = '';
        } else {
            $form['fx_rate']['#required'] = False;
            $form['fx_rate']['#value'] = 1;
            $form['fx_rate']['#description'] = '';
        }

        return $form['fx_rate'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {


        $totalcredit = 0;
        $totaldebit = 0;

        for ($i = 1; $i <= $form_state->getValue('rows'); $i++) {

            $debit = str_replace(',', '', $form_state->getValue("debit$i"));
            if ($debit == '')
                $debit = 0;
            $credit = str_replace(',', '', $form_state->getValue("credit$i"));
            if ($credit == '')
                $credit = 0;

            if (!is_numeric($debit)) {
                $form_state->setErrorByName("debit$i", $this->t('input value error'));
            }
            if (!is_numeric($credit)) {
                $form_state->setErrorByName("credit$i", $this->t('input value error'));
            }

            if ($debit > 0 && $form_state->getValue("d-account$i") == 0) {
                $form_state->setErrorByName("d-account$i", $this->t('no account selected'));
            }

            if ($credit > 0 && $form_state->getValue("c-account$i") == 0) {
                $form_state->setErrorByName("c-account$i", $this->t('no account selected'));
            }


            $totalcredit += $credit;
            $totaldebit += $debit;
        }

        if ($totalcredit <> $totaldebit) {
            $form_state->setErrorByName('items][footer2', $this->t('entry is not balanced'));
            $form_state->setErrorByName('items][footer3');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        
        $journal = new Journal();

        $class = substr($form_state->getValue('d-account1'), 0, 2);
        $query = "SELECT country from {ek_company} WHERE id=:id";
        $allocation = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('coid')))
                ->fetchField();

        $date = explode("-", $form_state->getValue('date'));
        $value = str_replace(',', '', $form_state->getValue('debit1'));
        $amount = round($value / $form_state->getValue('fx_rate'), 2);
        $currency = $form_state->getValue('currency');

        $fields = array(
            'class' => $class,
            'type' => $form_state->getValue('d-account1'),
            'allocation' => $allocation,
            'company' => $form_state->getValue('coid'),
            'localcurrency' => $value,
            'rate' => $form_state->getValue('fx_rate'),
            'amount' => $amount,
            'currency' => $currency,
            'amount_paid' => $amount,
            'year' => $date[0],
            'month' => $date[1],
            'comment' => Xss::filter($form_state->getValue('comment')),
            'pcode' => 'n/a',
            'clientname' => '0',
            'suppliername' => '0',
            'receipt' => 'no',
            'employee' => 'n/a',
            'status' => 'yes',
            'cash' => 0,
            'pdate' => $form_state->getValue('date'),
            'reconcile' => '0',
            'attachment' => '',
        );
        
/**/
        $insert = Database::getConnection('external_db', 'external_db')
                ->insert('ek_expenses')
                ->fields($fields)
                ->execute();

           
          $debit = str_replace(',', '', $form_state->getValue('debit1'));
          $credit = str_replace(',', '', $form_state->getValue('credit1'));
          /**/ 
          if ($debit == $credit) {
                $rec1 = $journal->record(
                    array(
                    'source' => "expense amortization",
                    'coid' => $form_state->getValue('coid'),
                    'aid' => $form_state->getValue("d-account1"),
                    'type' => 'debit',
                    'reference' => $insert,
                    'date' => $form_state->getValue('date'),
                    'value' => $debit,
                    'currency' => $form_state->getValue('currency'),
                    'comment' => Xss::filter($form_state->getValue("comment")),
                    'fxRate' => $form_state->getValue('fx_rate')
                    )
                );
                $rec2 = $journal->record(
                    array(
                    'source' => "expense amortization",
                    'coid' => $form_state->getValue('coid'),
                    'aid' => $form_state->getValue("c-account1"),
                    'type' => 'credit',
                    'reference' => $insert,
                    'date' => $form_state->getValue('date'),
                    'value' => $credit,
                    'currency' => $form_state->getValue('currency'),
                    'comment' => Xss::filter($form_state->getValue("comment")),
                    'fxRate' => $form_state->getValue('fx_rate')
                    )
                );
          }
                   
       
          $status = 0;
          $schedule = unserialize($form_state->getValue('amort_record'));
          $ct_ref = $form_state->getValue('for_ref')+1;
          if(count($schedule['a']) == $ct_ref ) {
              $status = 1;
          } 
          $schedule['a'][$form_state->getValue('for_ref')]['journal_reference'] = ['expense' => $insert, 'journal' => $rec1 .'|'. $rec2];

          /**/
          Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets_amortization')
                    ->condition('asid',$form_state->getValue('for_id'))
                    ->fields(['amort_record' => serialize($schedule), 'amort_status' => $status])
                    ->execute();
          
          $url = Url::fromRoute('ek_finance.manage.list_expense', array(), array())->toString();
          drupal_set_message(t('Data updated. <a href="@url">Go to expenses</a>.', ['@url' => $url]), 'status');
          
          $form_state->setRedirect('ek_assets.set_amortization', ['id' => $form_state->getValue('for_id')]) ;

        
    }

}
