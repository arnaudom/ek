<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\ReceiveMemo
 * 
 
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;


/**
 * Provides a form to record received payment from internal memo.
 * This process is essentially used to record an income in journal
 */
class ReceiveMemo extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_finance_receive_memo';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
  
  $data = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_expenses_memo} where id=:id", array(':id' => $id))->fetchObject();

  
    $form['ref'] = array(
      '#type' => 'item',
      '#markup' => t('Memo ref. @p', array('@p' => $data->serial)),

    );   

    $form['pay'] = array(
      '#type' => 'item',
      '#markup' => t('Recorded value @p', array('@p' => number_format($data->value,2) . ' ' . $data->currency)),

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
      '#title' => t('Receive date'),

    );     
    
    
    //bank account

    $settings = new CompanySettings($data->entity);
    $aid = $settings->get('cash_account', $data->currency);
    if($aid <> '') {
      $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
      $name = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':c' => $data->entity, ':a' => $aid ))->fetchField();
      $key = $data->currency . "-" . $aid;
      $cash = array($key => $name);
      
    }
    $aid = $settings->get('cash2_account', $data->currency);
    if($aid <> '') {
      $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
      $name = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':c' => $data->entity, ':a' => $aid ))->fetchField();
      $key = $data->currency . "-" . $aid;
      $cash += array($key => $name);
    }

    $options[(string)t('cash')] = $cash;
    $options[(string)t('bank')] = BankData::listbankaccountsbyaid($data->entity);
    
    $form['bank_account'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => NULL,
      '#title' => t('Account to be debited'),
      '#ajax' => array(
            'callback' => array($this , 'fx_rate'), 
            'wrapper' => 'fx',
        ),
      );   
    
    $form['debit_fx_rate'] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#maxlength' => 255,
      '#default_value' => $rate,
      '#required' => $req,
      '#title' => t('Exchange rate'),
      '#description' =>'',
      '#prefix' => "<div id='fx'>", 
      '#suffix' => '</div>', 

    ); 

$settings = new FinanceSettings();
$chart = $settings->get('chart');
$AidOptions = AidList::listaid($data->entity, array($chart['income'],$chart['other_income']), 1 );
$i = 0;

    $form['items']['table'] = array(
    '#type' => 'item',
    '#prefix'=> "<div class='table'>"
    );


 
       $form["aid"] = array(
        '#type' => 'select',
        '#size' => 1,
        '#title' => t('Credit account'),
        '#options' => $AidOptions,
        '#required' => TRUE,
        '#default_value' => NULL ,
        '#attributes' => array('style' => array('width:130px;')),
        '#prefix' => "<div class=''>",
        '#suffix' => '</div>',
        );             
              
      $form["grandtotal"] = array(
        '#type' => 'textfield',
        '#id' => 'grandtotal',  
        '#title' => t('Value'),
        '#size' => 25,
        '#maxlength' => 255,
        '#default_value' => $data->amount_paid,
        '#attributes' => array('placeholder'=>t('total'), 'title' => t('value received expressed in debited account currency')),
        '#prefix' => "<div class=''>",
        '#suffix' => '</div>',        
        );       

  

    $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
 
     $form['actions']['record'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Record'),
    );     
    
    $form['#attached']['library'][] = 'ek_finance/ek_finance.memo_pay_form';         

  return $form;    
  
  }

  /**
   * Callback
   */
  public function fx_rate(array &$form, FormStateInterface $form_state) {
  
      /* if selected bank account is not in the base currency,
      */
      $settings = new FinanceSettings(); 
      $baseCurrency = $settings->get('baseCurrency');
      //determin the currency of the receiving account : currency2
      
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


      if($baseCurrency != $currency2) {

      $form['debit_fx_rate']['#required'] = TRUE;

      //$purchase_rate = CurrencyData::rate($currency);
      $pay_rate = CurrencyData::rate($currency2);
      if($pay_rate ) {
        $form['debit_fx_rate']['#value'] = $pay_rate;
        $amount = str_replace(',', '', $form_state->getValue('grandtotal'));
        $credit = round($amount * $pay_rate, 4);
        $form['debit_fx_rate']['#description'] =t('Estimated amount debited @c @a', array('@c' => $currency2, '@a' => $credit));
        $form['debit_fx_rate']['#title']= t('Exchange rate against') . ' ' . $baseCurrency;
      } else {
        $form['debit_fx_rate']['#value'] = 0;
        $form['debit_debit_fx_rate']['#description'] ='';
      }

        } else {
        $form['debit_fx_rate']['#required'] = False;
        $form['debit_fx_rate']['#value'] = 1;
        $form['debit_fx_rate']['#description'] ='';
        $form['debit_fx_rate']['#title']= t('Exchange rate');

        }

      return  $form['debit_fx_rate'];


  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    if( $form_state->getValue('debit_fx_rate') <= 0 || !is_numeric($form_state->getValue('debit_fx_rate') ) ) {
      $form_state->setErrorByName("fx_rate", $this->t('the exchange rate value input is wrong'));
    }
    if( !is_numeric($form_state->getValue('grandtotal')) ) {
          $form_state->setErrorByName('amount'.$i, $this->t('The input value is wrong'));
    }
  
  
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
   $journal = new Journal();   
   
   $query = "SELECT * from {ek_expenses_memo} where id=:id";
   $memo = Database::getConnection('external_db', 'external_db')
           ->query($query, array(':id' => $form_state->getValue('for_id') ))
           ->fetchObject();
 
  
    // FILTER cash account
      if (strpos($form_state->getValue('bank_account'), "-")) {
      //the currency is in the form value

          $data = explode("-", $form_state->getValue('bank_account'));
          $currency = $data[0]; 
          $aid = $data[1];

      } else {
      // bank account
        $query = "SELECT currency,aid from {ek_bank_accounts} where id=:id ";
        $bank = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $form_state->getValue('bank_account')))
                ->fetchObject();
        $aid = $bank->aid;
        $currency = $bank->currency;

      } 
   

        $journal->record(
            array( 
            'aid'=> $form_state->getValue('aid'),
            'coid' => $memo->entity,
            'type' => 'credit',
            'source' => "general memo",
            'reference' => $form_state->getValue('for_id'),
            'date' => $form_state->getValue('date'),   
            'value' => $form_state->getValue('grandtotal'), 
            'currency' => $currency,
            'comment'=> t('Receipt memo') . ' ' . $memo->serial,
            'fxRate' => $form_state->getValue('debit_fx_rate'),
             )
            );
               
        $journal->record(
            array( 
            'aid'=> $aid,
            'coid' => $memo->entity,
            'type' => 'debit',
            'source' => "general memo",
            'reference' => $form_state->getValue('for_id'),
            'date' => $form_state->getValue('date'),   
            'value' => $form_state->getValue('grandtotal'),  
            'currency' => $currency,
            'comment'=> t('Receipt memo') . ' ' . $memo->serial,
            'fxRate' => $form_state->getValue('debit_fx_rate'),
             )
            );      
    
  $post = 2;
        
  $fields = array(
    'post' => $post,
  );
  
  $update = Database::getConnection('external_db', 'external_db')
          ->update('ek_expenses_memo')->fields($fields)
          ->condition('id', $form_state->getValue('for_id'))->execute();
  
  if ($update){
      \Drupal::messenger()->addStatus(t('Receipt recorded for @id', ['@id' => $memo->serial]));
        if ($memo->category < 5) {
          $form_state->setRedirect('ek_finance_manage_list_memo_internal' ) ;
        } else {
          $form_state->setRedirect('ek_finance_manage_list_memo_personal' ) ;
        }
  }
 
  }


}