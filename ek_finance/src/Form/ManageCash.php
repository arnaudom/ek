<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\ManageCash.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\BankData;
use Drupal\ek_admin\CompanySettings;

/**
 * Provides a form to manage cash movements
 */
class ManageCash extends FormBase {

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
    return 'manage_cash';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


  
  if ( $form_state->get('step') == '' ) {

    $form_state->set('step', 1);
  
  } 
  
  $settings = new FinanceSettings(); 
  $baseCurrency = $settings->get('baseCurrency');   
  $company = AccessCheck::CompanyListByUid();
  $actions = array(1 => t('Credit office cash'), 2 => t('Debit office cash'), 
      3 => t('Refund cash advanced by employee'), 4 => t('Credit employee account'), 
      5 => t('Debit amployee account'), 6 => t('Add opening balance or adjustment') );
  $CurrencyOptions = array('0' => ''); //this is added to force callback on select
  $CurrencyOptions += CurrencyData::listcurrency(1);
  
  $form['transaction'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => $actions,
    '#title' => t('Cash movement'),
    '#required' => TRUE,   
    '#prefix' => "<div class='container-inline'>",
  
    ); 

  

    $form['next'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#limit_validation_errors' => array(array('transaction')),
      '#submit' =>  array(array($this, 'get_accounts')) ,
      '#states' => array(
        // Hide data fieldset when class is empty.
        'invisible' => array(
           "select[name='transaction']" => array('value' => ''),
        ),
      ),

      '#suffix' => '</div>',

    );      
            
  if($form_state->get('step') == 2)  {
      

   if($form_state->getValue('transaction') == 1 || $form_state->getValue('transaction') == 2) {
   // office cash
   
    $form['coid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#title' => t('company receiving funds'),
      '#required' => TRUE,
        '#ajax' => array(
            'callback' => array($this, 'get_bank'), 
            'wrapper' => 'accounts_bank',
        
        ),    
      );    

      $form['bank'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => isset($_SESSION['bankoptions']) ? $_SESSION['bankoptions'] : array(),
      '#required' => TRUE,
      '#title' => t('bank account debited'),
      '#prefix' => "<div id='accounts_bank'>",
      '#suffix' => "</div>",
      '#validated' => TRUE,

      );   
      
      $form["amount"] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#maxlength' => 20,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('amount credited'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
      '#prefix' => "<div id='credit_amount' class='container-inline'>",
      '#suffix' => '', 
      );

      $form["option"] = array(
      '#type' => 'checkbox',
      '#description' => '',
      '#title' => t('convert'),
      '#prefix' => "",
      );      

      $form['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $CurrencyOptions,
      '#states' => array(
        'invisible' => array(
           "input[name='option']" => array('checked' => FALSE),
        ),
      ),
      '#ajax' => array(
        'callback' => array($this, 'fx_rate'), 
        'wrapper' => 'fx',
        ),
      ); 


      $form["fx_rate"] = array(
      '#type' => 'textfield',
      '#size' => 8,
      '#maxlength' => 10,
      '#attributes' => array('placeholder'=>t('rate') , 'title' => t('conversion currency exchange rate')  ),
      '#prefix' => "<div id='fx'>",
      '#suffix' => '</div></div>', 
      '#states' => array(
        'invisible' => array(
           "input[name='option']" => array('checked' => FALSE),
        ),
      ), 
      '#ajax' => array(
        'callback' => array($this, 'manual_fx_rate'), 
        'wrapper' => 'fx',
        'event' => 'change',
        ),     
      );      
           
   }//1 Credit office cash


  if($form_state->getValue('transaction') == 3) {
  //refund advance by employee
  // select employee
  // select list of expenses 
    $user = array(0 => '');
    $user += db_query('SELECT uid,name from {users_field_data} WHERE uid order by name> :u', array(':u' => 1))
            ->fetchAllKeyed();


    $form['info'] = array(
      '#type' => 'item',
      '#markup' => "<div class='messages messages--warning'>" .t('Use this <u>cash</u> payment only if expenses was previously <u>advanced by employee</u> in cash and recorded as "<u>not paid</u>".'). "</div>",
    
    );

    $form['coid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#title' => t('refund by'),
      '#required' => TRUE,
   
      );  
 
      $form['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $CurrencyOptions,
      '#prefix' => "<div id='credit_amount' class='container-inline'>",      
      '#ajax' => array(
        'callback' => array($this, 'fx_rate_2'), 
        'wrapper' => 'fx',
        ),
      ); 


      $form["fx_rate"] = array(
      '#type' => 'textfield',
      '#size' => 8,
      '#maxlength' => 10,
      '#attributes' => array('placeholder'=>t('rate') , 'title' => t('Currency exchange rate')  ),
      '#prefix' => "<div id='fx'>",
      '#suffix' => '</div></div>', 
    
      ); 

    /**/
    $form['user'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => array_combine($user, $user),
        '#required' => TRUE,
        '#title' => t('payee'),
        '#attributes' => array('style' => array('width:300px;')),
        '#ajax' => array(
          'callback' => array($this, 'list_expenses'), 
          'wrapper' => 'list',
        ),        
      );

      $form['list'] = array(
        '#type' => 'fieldset', 
        '#title' => t('list of payments'), 
        '#prefix' => "<div id='list'>", 
        '#suffix' => '</div>', 
        '#collapsible' => TRUE, 
        '#open' => TRUE, 
        '#validated' => TRUE, 
        '#tree' => TRUE,
     
      ); 
      
      if( ( $form_state->getValue('user') <> '' ) ) {
        $query =  "SELECT id,company, type, currency from {ek_expenses}  WHERE employee=:e and cash=:c and status=:s";
        $a = array(':e' => $form_state->getValue('user'), ':c' => 'Y', ':s' => 'no');
        
        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);
      
        if($data) {
        $form['list']['records'] = array();
    
        while ($d = $data->fetchObject()) {
   
        $query = "SELECT * from {ek_journal} WHERE reference=:r AND source=:s AND type=:t AND coid=:c";
        $a = array(':r' => $d->id, ':s' => 'expense', ':t' => 'debit', ':c' => $d->company);
        $journal = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
        
        $query = "SELECT aname from {ek_accounts} WHERE coid=:c AND aid=:a";
        $a = array(':c' => $journal->coid, ':a' => $journal->aid);
        $aname = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
        
        $query = "SELECT name from {ek_company} WHERE id=:c ";
        $a = array(':c' => $journal->coid,);
        $company = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
        
        $form['list']['records'][] = array(
          'id' => $d->id,
          'aname' => $aname,
          'value' => $journal->value,
          'currency' => $journal->currency,
          'date' => $journal->date,
          'company' => $company,
          
        
        );
      
        }
    }

      $i = 1;
      
      foreach($form['list']['records'] as $key => $val) {
      
       $form['list']['box']['entry-'.$val['id']] = array(
        '#type' => 'checkbox',
        '#attributes' => array('class' => array('sum')),
        '#id' => $i,
        '#title' => '<b>'. $val['currency'] . ' ' . number_format($val['value'], 2) . '</b>, ' . $val['aname'] . ', ' . $val['date'] . ', ' . $val['company'],
        '#return_value' => $val['value'],
      );
      
      $i++;
      }
      
      $form['list']['total'] = array(
        '#type' => 'item',
        '#markup' => "<span>" .t('total'). " : </span><b><span id='total'></span></b>",

      );
      
    }

  }//3 refund user

  if($form_state->getValue('transaction') == 4 || $form_state->getValue('transaction') == 5) { 
  //allocate cash to employee or return cash from employee to company cash
  // select employee

    $user = db_query('SELECT uid,name from {users_field_data} WHERE uid > :u order by name', array(':u' => 1))
            ->fetchAllKeyed();

    $form['coid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#title' => t('company cash account'),
      '#required' => TRUE,
   
      ); 
       
    $form['user'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $user,
        '#required' => TRUE,
        '#title' => t('employee'),
        '#attributes' => array('style' => array('width:300px;')),
      );

      $form["amount"] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#maxlength' => 20,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('amount credited'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
      '#prefix' => "<div id='credit_amount' class='container-inline'>",
      '#suffix' => '', 
      );

      $form['transaction_currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#required' => TRUE,
      '#options' => $CurrencyOptions,
      ); 

      $form["option"] = array(
      '#type' => 'checkbox',
      '#description' => '',
      '#title' => t('convert'),
      '#prefix' => "",
      );      

      $form['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $CurrencyOptions,
      '#states' => array(
        'invisible' => array(
           "input[name='option']" => array('checked' => FALSE),
        ),
      ),
      '#ajax' => array(
        'callback' => array($this, 'fx_rate'), 
        'wrapper' => 'fx',
        ),
      ); 


      $form["fx_rate"] = array(
      '#type' => 'textfield',
      '#size' => 8,
      '#maxlength' => 10,
      '#attributes' => array('placeholder'=>t('rate') , 'title' => t('conversion currency exchange rate')  ),
      '#prefix' => "<div id='fx'>",
      '#suffix' => '</div></div>', 
      '#states' => array(
        'invisible' => array(
           "input[name='option']" => array('checked' => FALSE),
        ),
      ), 
      '#ajax' => array(
        'callback' => array($this, 'manual_fx_rate'), 
        'wrapper' => 'fx',
        ),     
      );      

  } //4 ,5 
  
if($form_state->getValue('transaction') == 6) { 
  //Add a balance (opening) or adjustment
    $form['coid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#title' => t('company'),
      '#required' => TRUE,   
    );    
 
    $form["amount"] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#maxlength' => 20,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('amount'), 'class' => array('amount'), 'onKeyPress' => "return(number_format(this,',','.', event))"),
      '#prefix' => "<div id='credit_amount' class='container-inline'>",
      '#suffix' => '', 
    ); 
    
    $form['transaction_currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $CurrencyOptions,
      '#ajax' => array(
        'callback' => array($this, 'fx_rate_3'), 
        'wrapper' => 'fx',
        'event' => 'change',
        ),
    );
    
    $form["fx_rate"] = array(
      '#type' => 'textfield',
      '#size' => 8,
      '#maxlength' => 10,
      '#attributes' => array('placeholder'=>t('rate') , 'title' => t('conversion currency exchange rate')  ),
      '#prefix' => "<div id='fx'>",
      '#suffix' => '</div></div>', 
      '#ajax' => array(
        'callback' => array($this, 'manual_fx_rate_2'), 
        'wrapper' => 'fx',
        'event' => 'change',
        ),     
      );     
    
}


    $form['date'] = array(
    '#type' => 'textfield',
    '#title' => t('transaction date'),
    '#size' => 12,
    '#maxlength' => 10,
    '#required' => TRUE,
    '#attributes' => array('placeholder'=>t('YYYY-mm-dd'), 'class' => array('date')),
    '#attached' => array(
        'library' => array(
          'ek_finance/ek_finance.datepicker',
        ),
      ),          
    
    );   
    

if($form_state->getValue('transaction') <> 3 ) {    
    $form['comment'] = array(
    '#type' => 'textfield',
    '#size' => 30,
    '#maxlength' => 200,
    '#attributes' => array('placeholder'=>t('references'),),
    );  
}    
   
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

    
    $form['#attached']['library'][] = 'ek_finance/ek_finance.cash_form';
  
 return $form;
}

  /**
   * Callback
   */
  public function fx_rate(array &$form, FormStateInterface $form_state) {
  /* if add exchange rate
  */
  
  if($form_state->getValue('transaction') == 1 
          || $form_state->getValue('transaction') == 2) {
    // bank account
    $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
    $currency = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $form_state->getValue('bank') ))
            ->fetchField();
  
  } else {
    $currency = $form_state->getValue('transaction_currency');
  }

  $fx = 1/CurrencyData::rate($currency);
  $convert = CurrencyData::rate($form_state->getValue('currency'));
  $rate = round($fx*$convert,4);
  $value = str_replace(',', '', $form_state->getValue('amount'))*$rate;

  if($fx <> 1) {
  $form['fx_rate']['#required'] = TRUE;
  
  } else {
    $form['fx_rate']['#required'] = False;
  }
  $form['fx_rate']['#value'] = $rate;
  $form['fx_rate']['#description'] = t('converted amount') .': ' . number_format($value, 2);
    
  return  $form['fx_rate'];
  
  }

  /**
   * Callback
   */
  public function fx_rate_2(array &$form, FormStateInterface $form_state) {
  /* if add exchange rate
  */
  $fx = CurrencyData::rate($form_state->getValue('currency'));
  if($fx <> 1) {
  $form['fx_rate']['#value'] = $fx;
  $form['fx_rate']['#required'] = TRUE;
  $form['fx_rate']['#description'] = '';
  
  } else {
    $form['fx_rate']['#required'] = False;
    $form['fx_rate']['#value'] = 1;
    $form['fx_rate']['#description'] = '';
    
  }
    
  return  $form['fx_rate'];
  
  }

  /**
   * Callback
   */
public function fx_rate_3(array &$form, FormStateInterface $form_state) {
  /* if add opening or adjusment
  */
  
  $currency = $form_state->getValue('transaction_currency');
  $fx = CurrencyData::rate($currency);
  $value = round(str_replace(',', '', $form_state->getValue('amount'))/$fx, 2);

  if($fx <> 1) {
  $form['fx_rate']['#required'] = TRUE;
  
  } else {
    $form['fx_rate']['#required'] = False;
  }
  $form['fx_rate']['#value'] = $fx;
  $form['fx_rate']['#description'] = t('converted amount') .': ' . number_format($value, 2);
    
  return  $form['fx_rate'];
  
  }

  /**
   * Callback
   */  
  public function manual_fx_rate(array &$form, FormStateInterface $form_state) {
  /* if add exchange rate manually, calculate value
  */

    $value = str_replace(',', '', $form_state->getValue('amount'))*$form_state->getValue('fx_rate');
    $form['fx_rate']['#description'] = t('converted amount') .': ' . number_format($value, 2);    
    return  $form['fx_rate'];
  
  }
 
  /**
   * Callback
   */ 
  public function manual_fx_rate_2(array &$form, FormStateInterface $form_state) {
  /* if add opening or adjustment
  */

    $value = str_replace(',', '', $form_state->getValue('amount')) / $form_state->getValue('fx_rate');
    $form['fx_rate']['#description'] = t('converted amount') .': ' . number_format($value, 2);    
    return  $form['fx_rate'];
  
  }

  /**
   * Callback
   */    
  public function get_bank(array &$form, FormStateInterface $form_state) {
    
    $options = array();
    $options[(string)t('Local')] = BankData::listbankaccountsbyaid($form_state->getValue('coid'));
    $options[(string)t('Group')] = array();
    $query = 'SELECT id from {ek_company} WHERE id<>:id';
    $data = Database::getConnection('external_db', 'external_db')->query($query,array(':id' => $form_state->getValue('coid')));
    
      while($d = $data->fetchObject()) {
        $options[(string)t('Group')] += BankData::listbankaccountsbyaid($d->id);
      }
    
    $_SESSION['bankoptions'] =  $options;
    $form['bank']['#options'] = $_SESSION['bankoptions'];

    return $form['bank'];
  }

  /**
   * Callback
   */
  public function get_accounts(array &$form, FormStateInterface $form_state) {

    $form_state->set('step', 2);
    
    $form_state->setRebuild(); 

  }

  /**
   * Callback
   */  
  public function list_expenses(array &$form, FormStateInterface $form_state) {     
    return $form['list'];
  }
  
   
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    if ($form_state->get('step') == 1) {
    
    }
    
    if ($form_state->get('step') == 2) { 
    
      if($form_state->getValue('transaction') == 1 || $form_state->getValue('transaction') == 2) {
      
        $companysettings = new CompanySettings($form_state->getValue('coid')); 
        
        if($form_state->getValue('option') == 1) {

          if( $form_state->getValue('currency') == '0') {
            $form_state->setErrorByName('currency',  $this->t('conversion currency not selected') );  
          }             
          if($form_state->getValue('fx_rate') == '') {
            $form_state->setErrorByName('fx_rate',  $this->t('exchange rate must be indicated') );  
          }
          if(!is_numeric($form_state->getValue('fx_rate')) || $form_state->getValue('fx_rate') == 0) {
            $form_state->setErrorByName('fx_rate',  $this->t('exchange rate input is not correct') );  
          }       
          $account = $companysettings->get('cash_account', $form_state->getValue('currency') ); 

          if($account == NULL) {
            $form_state->setErrorByName('bank', $this->t('you do not have cash account set for this currency. Please contact administrator') );  
          }
            
      //return $account;
        } else {
          $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
          $currency = Database::getConnection('external_db', 'external_db')
                  ->query($query, array(':id' => $form_state->getValue('bank')))
                  ->fetchField();  
          $account = $companysettings->get('cash_account', $currency );
           if($account == NULL) {
            $form_state->setErrorByName("bank",  $this->t('you do not have cash account set for this currency. Please contact administrator') );  
          }        
        }
        $v = str_replace(',', '', $form_state->getValue('amount'));
        if(!is_numeric($v)) {
            $form_state->setErrorByName("amount",  $this->t('amount is not correct') );  
        }    
      }// 1, 2
    
        
      if($form_state->getValue('transaction') == 3 ) {

        $companysettings = new CompanySettings($form_state->getValue('coid')); 
        $account = $companysettings->get('cash_account', $form_state->getValue('currency') ); 

        if($account == NULL) {
           $form_state->setErrorByName("bank",  $this->t('you do not have cash account set for this currency. Please contact administrator') );  
        }
      
      $total = 0;
      $list = $form_state->getValue('list');
        foreach($list['box'] as $key => $val) { 
          if( strpos($key, '-', 0) ) { //filter checkbox only
            
            $total += $val;
          }
        }

        if ($total == 0) {
            $form_state->setErrorByName("list",  $this->t('you did not select any expense to refund.') );  
        }           
        if($form_state->getValue('fx_rate') == '') {
            $form_state->setErrorByName("fx_rate",  $this->t('exchange rate must be indicated') );  
        }
        if(!is_numeric($form_state->getValue('fx_rate') ) || $form_state->getValue('fx_rate') == 0) {
            $form_state->setErrorByName("fx_rate",  $this->t('exchange rate input is not correct') );  
        }       
        if( $form_state->getValue('currency') == '0') {
                    $form_state->setErrorByName('currency',  $this->t('currency not selected') );  
          } 
      
      
      }//3
      
      if($form_state->getValue('transaction') == 4 || $form_state->getValue('transaction') == 5) {
        if( $form_state->getValue('transaction_currency') == '0') {
             $form_state->setErrorByName('transaction_currency',  $this->t('currency not selected') );  
        } 
         if( $form_state->getValue('option') == '1') {
            if( $form_state->getValue('currency') == '0') {
                 $form_state->setErrorByName('currency',  $this->t('conversion currency not selected') );  
            }  
         }
        $v = str_replace(',', '', $form_state->getValue('amount'));
        if(!is_numeric($v)) {
            $form_state->setErrorByName("amount",  $this->t('amount is not correct') );  
        }    
      }
      
      if($form_state->getValue('transaction') ==  6) {
          
        if( $form_state->getValue('coid') == '') {
             $form_state->setErrorByName('coid',  $this->t('company not selected') );  
        } 
        $v = str_replace(',', '', $form_state->getValue('amount'));
        if(!is_numeric($v)) {
            $form_state->setErrorByName("amount",  $this->t('amount is not correct') );  
        }         
        if( $form_state->getValue('transaction_currency') == '0') {
             $form_state->setErrorByName('transaction_currency',  $this->t('currency not selected') );  
        }
        if($form_state->getValue('fx_rate') == '') {
            $form_state->setErrorByName("fx_rate",  $this->t('exchange rate must be indicated') );  
        }

        if(!is_numeric($form_state->getValue('fx_rate') ) || $form_state->getValue('fx_rate') == 0) {
            $form_state->setErrorByName("fx_rate",  $this->t('exchange rate input is not correct') );  
        }        
      }
      
    }//step 2

    
  /**/
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  if ($form_state->get('step') == 2) {

  $journal = new Journal();
  $companysettings = new CompanySettings($form_state->getValue('coid')); 

       if($form_state->getValue('transaction') == 1 || $form_state->getValue('transaction') == 2) {
       //Credit office cash or refund office cash

      $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
      $bankcurrency = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $form_state->getValue('bank')))
              ->fetchField();        
     
        if($form_state->getValue('transaction') == 1 ) {
          $type = 'credit';
        } else {
          $type = 'debit';
        }
        
        
        if($form_state->getValue('option') == 1) {
        // the credited amount is converted into another currency
        $form_state->setValue( 'amount', str_replace(',', '', $form_state->getValue('amount')) ) ;
        $input = $form_state->getValue('amount');
        
          if($type == 'credit' ) {
          //credit
          $ct = round($form_state->getValue('amount')*$form_state->getValue('fx_rate'), 2);
            $form_state->setValue('amount', $ct) ;
            }
        
          if($type == 'debit' ) {
          //debit
          $dt = round($form_state->getValue('amount')*$form_state->getValue('fx_rate'), 2);
            $form_state->setValue('amount', $dt); 
            }
                    
        $adjustment = $input - $form_state->getValue('amount');
        
        } else {
        $form_state->setValue('currency', $bankcurrency ) ;
        $form_state->setValue('amount', str_replace(',', '', $form_state->getValue('amount')));
        $adjustment = 0;
        }   
        
        $baseamount = $form_state->getValue('amount') + CurrencyData::journalexchange($form_state->getValue('currency'),$form_state->getValue('amount'));
        $baseamount = round($baseamount, 2);

       $fields = array(
        'date' => date('Y-m-d'),
        'pay_date' => $form_state->getValue('date'),
        'type' => $type,
        'amount' => $form_state->getValue('amount'),
        'currency' => $form_state->getValue('currency'),
        'cashamount' => $baseamount, //base currency conversion
        'coid' => $form_state->getValue('coid'),
        'baid' => $form_state->getValue('bank'),
        'uid' => 0,
        'comment' => $form_state->getValue('comment'),
        'reconcile' => 0,
       );
       
       $insert1 = Database::getConnection('external_db', 'external_db')
               ->insert('ek_cash')
               ->fields($fields)
               ->execute();
       
       if($form_state->getValue('option') == 1) {
       $fields = array(
        'date' => date('Y-m-d'),
        'pay_date' => $form_state->getValue('date'),
        'type' => $type,
        'amount' => $adjustment,
        'currency' => $form_state->getValue('currency'),
        'cashamount' => '0', //base currency conversion
        'coid' => 'x',
        'baid' => $form_state->getValue('bank'),
        'uid' => 0,
        'comment' => 'currency conversion during cash transaction',
        'reconcile' => 0,
       );
        $insert2 = Database::getConnection('external_db', 'external_db')
                ->insert('ek_cash')
                ->fields($fields)
                ->execute();      
       }
       
       
       
      $cash = $companysettings->get('cash_account', $form_state->getValue('currency')); 
      $bank = Database::getConnection('external_db', 'external_db')
              ->query("SELECT aid from {ek_bank_accounts} WHERE id=:id", array(':id'=> $form_state->getValue('bank')))
              ->fetchField();
      if ($type == 'credit') { 
      $t1 = 'debit'; $t2 = 'credit';
      } else { 
      $t1 = 'credit'; $t2 = 'debit';
      }
      
      $journal->record(
        array( 
        'source' => "general cash",
        'coid' => $form_state->getValue('coid'),
        'aid'=> $cash,
        'type' => $t1,
        'reference' => $insert1,
        'date' => $form_state->getValue('date'),
        'value' => $form_state->getValue('amount'),
        'currency' => $form_state->getValue('currency'),
        'comment'=> $form_state->getValue('comment'),
         )
        );

      $journal->record(
        array( 
        'source' => "general cash",
        'coid' => $form_state->getValue('coid'),
        'aid' => $bank,
        'type' => $t2,
        'reference' => $insert1,
        'date' => $form_state->getValue('date'),
        'value' => $form_state->getValue('amount')+$adjustment,
        'currency' => $bankcurrency,
        'comment' => $form_state->getValue('comment'),
         )
        ); 
       
       }//1
       
       if($form_state->getValue('transaction') == 3)
       {
       //Cash advanced by user is refund
       
       $total = 0;
       $list = $form_state->getValue('list');
        foreach($list['box'] as $key => $val) { 
          if( strpos($key, '-', 0) ) { //filter checkbox only
            $total += $val;

            $query = "UPDATE {ek_expenses set status = 'yes' where id=:id";
            $id = explode('-', $key);
            if ($val > 0) {
            Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id[1]));
            $comment .= $id[1] . ',';
            }
          }
        }
        
       $base = CurrencyData::journalexchange($form_state->getValue('currency'),$total, $form_state->getValue('fx_rate') );
       $fields = array(
        'date' => date('Y-m-d'),
        'pay_date' => $form_state->getValue('date'),
        'type' => 'debit',
        'amount' => $total,
        'currency' => $form_state->getValue('currency'),
        'cashamount' => $total + $base, //base currency conversion
        'coid' => $form_state->getValue('coid'),
        'baid' => 0,
        'uid' => 0,
        'comment' => 'refund expense ' . $comment,
        'reconcile' => 0,
       );
       Database::getConnection('external_db', 'external_db')
               ->insert('ek_cash')->fields($fields)
               ->execute();
       $fields = array(
        'date' => date('Y-m-d'),
        'pay_date' => $form_state->getValue('date'),
        'type' => 'credit',
        'amount' => $total,
        'currency' => $form_state->getValue('currency'),
        'cashamount' => $total + $base, //base currency conversion
        'coid' => $form_state->getValue('coid'),
        'baid' => 0,
        'uid' => 0,
        'comment' => 'cash advance on expense ' . $comment,
        'reconcile' => 0,
       );
       Database::getConnection('external_db', 'external_db')
               ->insert('ek_cash')->fields($fields)
               ->execute();       
       
       } // 3
       
       
       if($form_state->getValue('transaction') == 4 || $form_state->getValue('transaction') == 5) {
       //Credit office cash or refund office cash
        
        if($form_state->getValue('transaction') == 4 ) {
          $t1 = 'credit'; $t2 = 'debit';
          } else { 
          $t1 = 'debit'; $t2 = 'credit';
          }

        if($form_state->getValue('option') == 1) {
        // the credited amount is converted into another currency
        $form_state->setValue('amount', str_replace(',', '', $form_state->getValue('amount') ) );
        $input = $form_state->getValue('amount');
        
          if($type == 'credit' ) {
          //credit
            $form_state->setValue('amount', round($form_state->getValue('amount')*$form_state->getValue('fx_rate'), 2) );
            }
        
          if($type == 'debit' ) {
          //debit
            $form_state->setValue('amount', round($form_state->getValue('amount')/$form_state->getValue('fx_rate'), 2) );
            }
                    
        $adjustment = $input - $form_state->getValue('amount');
        
        } else {
        $form_state->setValue('currency', $form_state->getValue('transaction_currency') );
        $form_state->setValue('amount', str_replace(',', '', $form_state->getValue('amount')) );
        }        

        $baseamount = $form_state->getValue('amount') + CurrencyData::journalexchange($form_state->getValue('currency'),$form_state->getValue('amount'));
        $baseamount = round($baseamount, 2);

       $fields = array(
        'date' => date('Y-m-d'),
        'pay_date' => $form_state->getValue('date'),
        'type' => $t1,
        'amount' => $form_state->getValue('amount'),
        'currency' => $form_state->getValue('currency'),
        'cashamount' => $baseamount, //base currency conversion
        'coid' => $form_state->getValue('coid'),
        'baid' => 0,
        'uid' => $form_state->getValue('user'),
        'comment' => $form_state->getValue('comment'),
        'reconcile' => 0,
       );
       
       $insert1 = Database::getConnection('external_db', 'external_db')
               ->insert('ek_cash')
               ->fields($fields)
               ->execute();
       

       $fields = array(
        'date' => date('Y-m-d'),
        'pay_date' => $form_state->getValue('date'),
        'type' => $t2,
        'amount' => $form_state->getValue('amount'),
        'currency' => $form_state->getValue('currency'),
        'cashamount' => $baseamount, //base currency conversion
        'coid' => $form_state->getValue('coid'),
        'baid' => 0,
        'uid' => 0,
        'comment' => $form_state->getValue('comment'),
        'reconcile' => 0,
       );
        $insert2 = Database::getConnection('external_db', 'external_db')
                ->insert('ek_cash')->fields($fields)
                ->execute();      
            
      } // 4,5
      
      if($form_state->getValue('transaction') == 6) {
          //add opening or adjustment
          $form_state->setValue('amount', str_replace(',', '', $form_state->getValue('amount') ) );
          $baseamount = round($form_state->getValue('amount') / $form_state->getValue('fx_rate'), 2);
          $fields = array(
            'date' => date('Y-m-d'),
            'pay_date' => $form_state->getValue('date'),
            'type' => 'Credit',
            'amount' => $form_state->getValue('amount'),
            'currency' => $form_state->getValue('transaction_currency'),
            'cashamount' => $baseamount, //base currency conversion
            'coid' => $form_state->getValue('coid'),
            'baid' => 0,
            'uid' => 0,
            'comment' => $form_state->getValue('comment'),
            'reconcile' => 0,
           );
          $insert = Database::getConnection('external_db', 'external_db')
                  ->insert('ek_cash')
                  ->fields($fields)
                  ->execute(); 
      }// 6
    
  drupal_set_message(t('Data updated'), 'status');

  }//step 2

  $_SESSION['bankoptions'] = array();
  $_SESSION['records'] = array();
  }
  

  
  
}