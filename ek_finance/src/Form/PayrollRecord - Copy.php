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
 * Provides a form.
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
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }
  
  public function getFormId() {
    return 'ek_finance_payroll_record';
  }


  public function buildForm(array $form, FormStateInterface $form_state, $param = NULL) {
    
  $pay = $_SESSION['pay'];
  $_SESSION['pay'] = NULL;
  $param = unserialize($param);
  $settings = NEW HrSettings($param['coid']);
  $list = $settings->HrAccounts[$param['coid']];
  
  if(empty($list) || $list['pay_account'] == '') {
      $error = 1;
      $url = Url::fromRoute('ek_hr.parameters-accounts', array(), array())->toString();
      $markup = "<div class='messages messages--error'>" . t("You do not have payroll accounts recorded. 
          Go to <a href='@s' target='_blank'>settings</a> first.", ['@s' => $url]) . '</div>';
        $form['error'] = array(
            '#type' => 'item',
            '#markup' => $markup,
        );
  }


  $headerline = "<div class='table'><div class='row'><div class='cell cellborder'></div><div class='cell cellborder'>"
          . t("Description") . "</div><div class='cell cellborder'>"
          . t("Client") . "</div><div class='cell cellborder'>"
          . t("Project") . "</div><div class='cell cellborder'>"
          . t("Account") . "</div><div class='cell cellborder'>"
          . t("Credit") . "</div><div class='cell cellborder'>"
          . t('fx') ."</div><div class='cell cellborder'>"
          . t("Pay date") . "</div><div class='cell cellborder'>"
          . t("Nett + advance") . "</div><div class='cell cellborder'>"
          . t("Currency") . "</div>";

  
    $form['items']["headerline"] = array(
      '#type' => 'item',
      '#markup' => $headerline,
    );
    
    $form["coid"] = array(
    '#type' => 'hidden',
    '#value' => $param['coid'],
    ); 

  $client = array('n/a' => t('not applicable'));
  $client += AddressBookData::addresslist(1); 
  if($this->moduleHandler->moduleExists('ek_projects')) $pcode = ProjectData::listprojects(0);
  $fsettings = new FinanceSettings();
  $chart = $fsettings->get('chart');
  $AidOptions = AidList::listaid($param['coid'], array($chart['cos'],$chart['expenses'], $chart['other_expenses']), 1 );
  $CurrencyOptions = CurrencyData::listcurrency(1);

  $settings = new CompanySettings($param['coid']);
  
  //get cash accounts ref.
  $cash = array();
  foreach($CurrencyOptions as $c => $name) {
 
    $aid = $settings->get('cash_account', $c);
    
    if($aid <> '') {
      $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
      $name = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':c' => $param['coid'], ':a' => $aid ))
              ->fetchField();
      $key = $c . "-" . $aid;
      $cash[$key] = '[' . $c . '] -'. $name ;
    }

    $aid = $settings->get('cash2_account', $c);
    
    if($aid <> '') {
      $query = "SELECT aname from {ek_accounts} WHERE coid=:c and aid=:a";
      $name = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':c' => $param['coid'], ':a' => $aid ))
              ->fetchField();
      $key = $c . "-" . $aid;
      $cash[$key] = '[' . $c . '] -'. $name ;
    }  
  }
  
  $credit[(string)t('bank')] =  BankData::listbankaccountsbyaid($param['coid']); 
  $credit[(string)t('cash')] = $cash;
  
  $n = 0;
 
  foreach($pay as $employee => $value) {
  
 
    if($employee <> 'coid') {
    $n++;
    $d = implode(',' , $value['deduction']);

    
        $form['items']["deduction-".$n] = array(
        '#type' => 'hidden',
        '#size' => 20,
        '#value' => $d,
        ); 
        $form['items']['include-'.$n] = array(
        '#type' => 'checkbox',
        '#id' => 'i-'.$employee,
        '#default_value' => 1,
        '#prefix' => "<div class='row current' id='$n' ><div class='cell'>",
        '#suffix' => '</div>',
        '#attributes' => array('onclick' => "jQuery('#$n ').toggleClass('delete');"),
        );
         
        $form['items']["description-".$n] = array(
        '#type' => 'textfield',
        '#size' => 20,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => t('allowance') . ' ' . $value['name'],
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
        ); 

        $form['items']['client-'.$n] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => array_combine($client, $client),
        '#required' => TRUE,
        '#default_value' => "not applicable",
        '#attributes' => array('style' => array('width:100px;')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
          );  

        if($this->moduleHandler->moduleExists('ek_projects')) {
            $query = "SELECT pcode,pdate FROM {ek_expenses} WHERE comment LIKE :c ORDER by pdate DESC limit 1";
            $default = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':c' => t('allowance') . ' ' . $value['name'] . '%' ))
                    ->fetchField();
            $form['items']['pcode-'.$n] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => $pcode,
              '#default_value' => isset($default) ? $default : NULL,
              '#attributes' => array('style' => array('width:100px;')),
              '#prefix' => "<div class='cell'>",
              '#suffix' => '</div>',          
              );
        } // project 
         else {
        $form['items']['pcode-'.$n] = array(
          '#type' => 'item',
          '#prefix' => "<div class='cell'>",
          '#suffix' => '</div>',          

          );         
        }
        
        $query = "SELECT type,pdate FROM {ek_expenses} WHERE comment LIKE :c ORDER by pdate DESC limit 1";
        $default = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':c' => t('allowance') . ' ' . $value['name'] . '%'))
            ->fetchField();
        $form['items']["account-".$n] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $AidOptions,
        '#required' => TRUE,
        '#default_value' => isset($default) ? $default : NULL,
        '#attributes' => array('style' => array('width:100px;')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',          
        );        

        $query = "SELECT cash,currency,pdate FROM {ek_expenses} WHERE comment LIKE :c ORDER by pdate DESC limit 1";
        $default = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':c' => t('allowance') . ' ' . $value['name'] . '%'))
            ->fetchObject();
        $form['items']['credit-'.$n] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $credit,
        '#required' => TRUE,
        '#default_value' => isset($default->cash) ? $default->cash : NULL,
        '#prefix' => "<div id='credit' class='cell'>",
        '#suffix' => '</div>',
        '#attributes' => array('style' => array('width:100px;')),
        '#ajax' => array(
             'callback' => '\Drupal\ek_finance\Form\PayrollRecord::gfx_rate', 
             'wrapper' => "fx$n",
             'event' => 'change',
          ),
        ); 

        if(isset($default->currency)) {
            $val = CurrencyData::rate($default->currency);
        } else {
            $val = 1;
        }
        $form['items']['fx_rate-'.$n] = array(
        '#type' => 'textfield',
        '#size' => 5,
        '#default_value' => $val,
        '#required' => FALSE,
        '#prefix' => "<div id='fx$n' class='cell'>",
        '#suffix' => '</div>',
        );

        $form['items']["pdate-".$n] = array(
        '#type' => 'date',
        '#id' => "edit-from$employee",
        '#size' => 11,
        '#required' => TRUE,
        '#default_value' => date('Y-m-d'),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
        );

        $form['items']["nett-".$n] = array(
        '#type' => 'textfield',
        '#size' => 20,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => number_format($value['nett']+$value['advance'], 2),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
        '#attributes' => array('class' => array('amount')),

        ); 

$currency = Database::getConnection('external_db', 'external_db')
        ->query("SELECT currency FROM {ek_hr_workforce} WHERE id=:id", array(':id' => $employee))->fetchField();

        $form['items']['currency-'.$n] = array(
        '#type' => 'hidden',
        '#default_value' => $currency,
        '#prefix' => "<div class='cell'>" . $currency,
        '#suffix' => '</div></div>',

        );

       
   }      
  }//for

        $form['items']['count'] = array(
          '#type' => 'hidden',
          '#value' => $n,
          '#suffix' => '</div>',          

          ); 

if(!isset($error)) {
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

  
  public function gfx_rate(array &$form, FormStateInterface $form_state) {    
    /* if add exchange rate
    */
    $trigger = explode('-', $_POST['_triggering_element_name']);
    $n = $trigger[1];

    // FILTER cash account
    if (strpos($form_state->getValue('credit-'.$n), "-")) {
    //the currency is in the form value

        $data = explode("-", $form_state->getValue('credit-'.$n) );
        $currency = $data[0]; 


    } else {
    // bank account
    $query = "SELECT currency from {ek_bank_accounts} where id=:id ";
      $currency = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $form_state->getValue('credit-'.$n) ) )->fetchField();

    }  

    $fx = CurrencyData::rate($currency);

    if($fx <> 1) {
    $form['items']['fx_rate-'.$n]['#value'] = $fx;
    $form['items']['fx_rate-'.$n]['#required'] = TRUE;


    } else {
      $form['items']['fx_rate-'.$n]['#required'] = False;
      $form['items']['fx_rate-'.$n]['#value'] = 1;


    }

    return  $form['items']['fx_rate-'.$n];
    
  }
  /**
   * {@inheritdoc}
   * 
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  
  
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $journal = new Journal();
    $settings = NEW HrSettings($form_state->getValue('coid'));
    $list = $settings->HrAccounts[$form_state->getValue('coid')];  

  
    for ($n=1; $n<=$form_state->getValue('count'); $n++) {
    
    if($form_state->getValue('include-'.$n) == 1) {
    
      $class = substr($form_state->getValue("account-".$n), 0, 2);
      $query = "SELECT country from {ek_company} WHERE id=:id";
      $allocation = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $form_state->getValue('coid') ) )
              ->fetchField();
      
      $date = explode("-", $form_state->getValue("pdate-".$n) );
      $value = str_replace(',', '', $form_state->getValue("nett-".$n) );
      
      $query = "SELECT id,currency FROM {ek_bank_accounts}";
      $bank_acc_list = Database::getConnection('external_db', 'external_db')
              ->query($query)
              ->fetchAllKeyed();
      
      
      if (strpos($form_state->getValue('credit-'.$n), "-")) {
        $cash = 'Y';
        $credit = $form_state->getValue('credit-'.$n);
        $acc = explode('-', $form_state->getValue('credit-'.$n));
        $crt_currency = $acc[0];
        
      } else {
        $cash = $form_state->getValue('credit-'.$n);
        $credit = $form_state->getValue('credit-'.$n);
        $crt_currency = $bank_acc_list[$form_state->getValue('credit-'.$n)];
      }
      
      
      if($form_state->getValue('currency-'.$n) <> $crt_currency) {
      //currency of credit account is different from currency of value    
        $query = "SELECT rate FROM {ek_currency} WHERE currency=:c";
        $rate2 =  Database::getConnection('external_db', 'external_db')
              ->query($query, [':c' => $form_state->getValue('currency-'.$n)])
                ->fetchField();
        $rate1 =  Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $crt_currency])
                    ->fetchField();
        $value = round($value*$rate1/$rate2,2);
        $amount = round($value/$form_state->getValue('fx_rate-'.$n), 2);
        $currency = $crt_currency;
      } else {
        $amount = round($value/$form_state->getValue('fx_rate-'.$n), 2); 
        $currency =  $form_state->getValue('currency-'.$n); 
      }
      
      
      
      $fields = array(
        'class' => $class,
        'type' => $form_state->getValue("account-".$n),
        'allocation' => $allocation,
        'company' => $form_state->getValue('coid'),
        'localcurrency' => $value,
        'rate' => $form_state->getValue('fx_rate-'.$n),
        'amount' => $amount,
        'currency' => $currency ,
        'amount_paid' => $amount,
        'year' => $date[0],
        'month' => $date[1],
        'comment' => t('Payroll record') . ' ' . $form_state->getValue('description-'.$n),
        'pcode'=> $form_state->getValue('pcode-'.$n),
        'clientname'=> $form_state->getValue('client-'.$n),
        'suppliername' => 'n/a',
        'receipt' => 'no',
        'employee' => 'n/a',
        'status'=> 'yes',
        'cash' => $cash,
        'pdate'=> $form_state->getValue("pdate-".$n),
        'reconcile'=> '0',
        'attachment' => '',
      );

    $insert = Database::getConnection('external_db', 'external_db')
            ->insert('ek_expenses')
            ->fields($fields)
            ->execute();
    
    
    /*
    * Record the accounting journal
     */
    
   
        $d = explode(',' , $form_state->getValue('deduction-'.$n));

        $value = str_replace(',', '', $form_state->getValue("nett-".$n) );
        $gross = $value;
        
        for($i = 0; $i < count($d); $i++) {
        //add deduction to value to be credited to liabilities
        $gross = $gross + $d[$i];
        }

        if($form_state->getValue('currency-'.$n) <> $crt_currency) {
        //currency of credit account is different from currency of value    
          $value = round($value*$rate1/$rate2,2);
          $gross = round($gross*$rate1/$rate2,2);
          $currency = $crt_currency;
        } else {
          $currency =  $form_state->getValue('currency-'.$n); 
        }
        //record the total liabilities payable (included the above 'paid' net salary) - DT and credit the expense account
        $journal->record(
                array( 
                'source' => "expense payroll",
                'coid' => $form_state->getValue('coid'),
                'aid' => $form_state->getValue("account-".$n),
                'reference' => $insert,
                'fxRate' => $form_state->getValue('fx_rate-'.$n),
                'date' => $form_state->getValue("pdate-".$n),
                'value' => $gross,
                'currency' => $currency,
                'p1' => $value,
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
                  'coid' => $form_state->getValue('coid'),
                  'aid' => $list['pay_account'],
                  'bank' => $credit,
                  'reference' => $insert,
                  'date' => $form_state->getValue("pdate-".$n),
                  'value' => $value,
                  'currency' => $currency,
                  'tax' => '',
                  'fxRate' => $form_state->getValue('fx_rate-'.$n),
                   )
          );    



  
 } // if include       
}
  drupal_set_message(t('Expenses recorded'), 'status');
  $form_state->setRedirect('ek_finance.manage.list_expense') ;

}


//class
}