<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\ReconciliationForm.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\BankData;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\FinanceSettings;


/**
 * Provides a form to filter and record reconciliation data.
 */
class ReconciliationForm extends FormBase {

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
    return 'reconciliation';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  $from = date('Y-m') .'-' . cal_days_in_month(CAL_GREGORIAN,date('m'),date('Y'));
  if ($form_state->get('step') == '' ) {

    $form_state->set('step', 1);
  
  } 
      $access = AccessCheck::GetCompanyByUser();
      $company = implode(',',$access);
      $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
      $company = Database::getConnection('external_db', 'external_db')->query($query, array(':t' => 1, ':c' => $company))->fetchAllKeyed();  

    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => TRUE,
      '#attributes' => array('class' => array('container-inline')),
    );  
            $form['filters']['filter'] = array(
              '#type' => 'hidden',
              '#value' => 'filter',
              
            );
            $form['filters']['date'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => $from,
              '#title' => t('date'),
            ); 
            
                      
    $form['filters']['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $company,
        '#required' => TRUE,
        '#title' => t('company'),
        '#ajax' => array(
          'callback' => array($this, 'get_accounts'), 
          'wrapper' => 'accounts_',
      ), 
    );   
    
    if($form_state->getValue('coid') ) {
    
    $coid = ($form_state->getValue('coid')) ? $form_state->getValue('coid') : $_SESSION['lfilter']['coid'];
    $list = AidList::listaid($coid, array(1,2,3,4,5,6,7,8,9), 1 );
    
    }

    $form['filters']['account'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => isset( $list ) ? $list : array(),
        '#required' => TRUE,
        '#default_value' => ($form_state->getValue('account')) ? $form_state->getValue('account') : array(),
        '#title' => t('account'),
        //'#attributes' => array('style' => array('width:150px;')),
        '#prefix' => "<div id='accounts_'>",
        '#suffix' => '</div>',
    ); 


    $form['filters']['next'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      //'#limit_validation_errors' => array(),
      '#submit' =>  array(array($this, 'list_journal')) ,
      '#states' => array(
        'invisible' => array(
           "select[name='account']" => array('value' => ''),
        ),
      ),


    ); 

  if($form_state->get('step') == 2)  {
      
    $param = serialize(
              [
                  'account' => $form_state->getValue('account'),
                  'coid' => $form_state->getValue('coid'),
                  'date' => $form_state->getValue('date'),
              ]
              );
      $xlink = Url::fromRoute('ek_finance_reconciliation_excel', ['param' => $param] )->toString();
      $form['excel'] = array(
        '#type' => 'item',
        '#markup' => "<a title='".t('download')."' href='".$xlink."'>" . t('Excel') . "</a>",
      );
  
  //Fix a bug to retreive exchange value when inter accounts transfer with different currencies
  //1 select active currencies
    $currencies = CurrencyData::listcurrency(1);
  //verify settings for currency accounts
    $companysettings = new CompanySettings($form_state->getValue('coid'));
    $account_currency = NULL;
    foreach($currencies as $key => $value) {
        
        //check cash accounts
        $cash1 = $companysettings->get('cash_account', $key);
        $cash2 = $companysettings->get('cash2_account', $key);
        
        if($cash1 == $form_state->getValue('account') || $cash2 == $form_state->getValue('account')) {
            $account_currency = $key;
        }
        
        //check bank accounts
        if(BankData::currencyByaid($form_state->getValue('coid'), $form_state->getValue('account')) == $key) {
            $account_currency = $key;
        }
        
    }
             
  $form['account_currency'] = array(
        '#type' => 'hidden',
        '#value' => $account_currency,
      );
  
  $query = "SELECT id,date FROM {ek_journal} WHERE aid=:aid and coid=:coid and date<=:date2 and reconcile=0 and exchange=0 ORDER BY date";
  $a = array(
    ':aid' => $form_state->getValue('account'),
    ':coid' => $form_state->getValue('coid'),
    ':date2' => $form_state->getValue('date') 
    );

  $result = Database::getConnection('external_db', 'external_db')->query($query,$a);    
    
    
  $query = "SELECT * from {ek_accounts} WHERE aid=:account and coid=:coid";
  $a = array(':account' => $form_state->getValue('account'),':coid' => $form_state->getValue('coid'));
  $account = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchObject();

    
  if ($account->balance_date == '') {
      $account->balance_date = 0; //todo input alert for opening balance
  }
    
      // sum transaction currency
      // remove filter with 'exchange' flag for case where journal has records
      // with internal currency transfers - applies to base currency

      $settings = new FinanceSettings(); 
      $baseCurrency = $settings->get('baseCurrency');
     
      if($baseCurrency == $account_currency) {
          $exchange = '%';
      } else {
          $exchange = 0;
      }
  
      $query = "SELECT sum(value) from {ek_journal} "
              . "WHERE exchange like :exc and type=:type "
              . "AND aid=:aid and coid=:coid "
              . "AND ( (date>=:dateopen and reconcile=:reco) OR reconcile=:reco2 )";
      $a = array(
        ':exc' => $exchange,
        ':type' => 'credit',
        ':aid' => $form_state->getValue('account'),
        ':coid' => $form_state->getValue('coid'),
        ':dateopen' => $account->balance_date,
        ':reco'=> 1 , 
        ':reco2' => date('Y')
        );
     
      $credit = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchField();
      $a = array(
        ':exc' => $exchange,
        ':type' => 'debit',
        ':aid' => $form_state->getValue('account'),
        ':coid' => $form_state->getValue('coid'),
        ':dateopen' => $account->balance_date,
        ':reco'=> 1 , 
        ':reco2' => date('Y')
        );
      $query = "SELECT sum(value) from {ek_journal} "
              . "WHERE exchange like :exc and type=:type "
              . "and aid=:aid and coid=:coid "
              . "and ( (date>=:dateopen and reconcile=:reco) or reconcile=:reco2 )";
      
      $debit = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchField();
    
      if ($debit == NULL) $debit = 0;
      if ($credit == NULL) $credit = 0;
      $balance = $account->balance + $credit - $debit;
      if ($balance < 0 ) {$ab='dt';} else {$ab='ct';}  

      $form['openchart'] = array(
      '#type' => 'hidden',
      '#default_value' => $account->balance,
      '#attributes' => array('id' => 'openchart'),
      ); 
      $form['opencredit'] = array(
      '#type' => 'hidden',
      '#default_value' => round($credit,2),
      '#attributes' => array('id' => 'opencredits'),
      ); 
      $form['opendebit'] = array(
      '#type' => 'hidden',
      '#default_value' => round($debit,2),
      '#attributes' => array('id' => 'opendebits'),
      );            
      $form['openbalance'] = array(
      '#type' => 'hidden',
      '#default_value' => $balance,
      '#attributes' => array('id' => 'openbalance'),
      );        


      
// top bar displaying the total
      $form['bar']["debits"] = array(
        '#type' => 'textfield',
        '#id' => 'debits',
        '#title' => t("Debits"), 
        '#title_display' => 'before', 
        '#required' => FALSE,
        '#size' => 15,
        '#default_value' =>  number_format($debit,2),
        '#attributes' => array('title' => t('total debits'),'readonly' => 'readonly' , 'class' => array('amount')),
        '#prefix' => '<div class="table "><div class="row "><div class="cell cell150">',
        '#suffix' => '</div>',
        
      );     

      $form['bar']["credits"] = array(
        '#type' => 'textfield',
        '#id' => 'credits',
        '#title' => t("Credits"), 
        '#title_display' => 'before', 
        '#required' => FALSE,
        '#size' => 15,
        '#default_value' =>  number_format($credit,2),
        '#attributes' => array('title' => t('total credits'),'readonly' => 'readonly' , 'class' => array('amount')),
        '#prefix' => '<div class="cell cell150">',
        '#suffix' => '</div>',
      ); 
     
      $form['bar']['balance'] = array(
        '#type' => 'item',
        '#markup' => "<span id='balance'>" . abs(round($balance,2)) ."</span><span id='ab'> (" . $ab . ")</span>",
        '#prefix' => '<div class="cell cell150">',
        '#suffix' => '</div>',      
      );

      $form['bar']["statement"] = array(
        '#type' => 'textfield',
        '#id' => 'statement',
        '#title' => t("Statement"), 
        '#title_display' => 'before', 
        '#required' => TRUE,
        '#size' => 15,
        '#default_value' => abs(round($balance,2)),
        '#attributes' => array('title' => t('statement value'), 'class' => array('calculate amount')),
        '#prefix' => '<div class="cell cell150">',
        '#suffix' => '</div>',
      ); 

      $form['bar']["difference"] = array(
        '#type' => 'textfield',
        '#id' => 'difference',
        '#title' => t("Difference"), 
        '#title_display' => 'before', 
        '#required' => TRUE,
        '#size' => 15,
        '#default_value' =>  0,
        '#attributes' => array('title' => t('difference between system and balance value'), 'readonly' => 'readonly' , 'class' => array('amount')),
        '#prefix' => '<div class="cell cell150">',
        '#suffix' => '</div></div></div>',
      ); 

// listing of journal data
$headerline = "<div class='table'>
                  <div class='row'>
                      <div class='cell cell50' id='tour-item1'>" . t("ID") . "</div>
                      <div class='cell cell100' id='tour-item2'>" . t("Date") . "</div>
                      <div class='cell cell300' id='tour-item3'>" . t("Object") . "</div>
                      <div class='cell cell100' id='tour-item4'>" . t("Debit") . "</div>
                      <div class='cell cell100' id='tour-item5'>" . t("Credit") . "</div>
                      <div class='cell cell50' id='tour-item6'>" . t("Select") . "</div>
                   </div>
                   <div class='row'>
                      <div class='cell cell50' id=''></div>
                      <div class='cell cell100' id=''></div>
                      <div class='cell cell300' id=''></div>
                      <div class='cell cell100 cellborderbottom' id='sum_debit'>0</div>
                      <div class='cell cell100 cellborderbottom' id='sum_credit'>0</div>
                      <div class='cell cell50' id=''></div>                   
                   ";


$form['items']['#tree'] = TRUE;

    $form['items']["headerline"] = array(
      '#type' => 'item',
      '#markup' => $headerline,
    ); 

$i = 0;
    while($r = $result->fetchObject()) {
    
      $j = Journal::journalEntryDetails($r->id);
      
      //Fix a bug to retreive exchange value when inter accounts transfer with different currencies
      if($account_currency && ($account_currency != $j['currency']) ) {
          $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'jr');
          $query->fields('jr' , ['value']);
          $query->condition('coid', $j['coid'], '=');
          $query->condition('aid', $j['aid'], '=');
          $query->condition('date', $j['date'], '=');
          $query->condition('type', $j['type'], '=');
          $query->condition('source', $j['source'], '=');
          $query->condition('reference', $j['reference'], '=');
          $query->condition('exchange', 1, '=');
          
          $exchange = $query->execute()->fetchObject();
          
          $j['value'] = $j['value'] + $exchange->value;
          
      }
      
      
      if($i % 2 == 0) {
            $back = 'grey';
        } else {
            $back = '';
        }
    
        $form['items'][$i]['id'] = array(
         '#type' => 'item',
         '#markup' => $r->id,
         '#prefix' => "<div class='row'><div class='cell cell50 $back' title='".t('journal entry')."'>",
         '#suffix' => '</div>',

       ); 

        $form['items'][$i]['journal_id'] = array(
         '#type' => 'hidden',
         '#default_value' => $r->id,
       );

         $form['items'][$i]['type'] = array(
         '#type' => 'hidden',
         '#default_value' => $j['type'],
         '#attributes' => array('id' => 'type-'.$i),
       );

        $form['items'][$i]['date'] = array(
         '#type' => 'item',
         '#markup' => $j['date'],
         '#prefix' => "<div class='cell cell100 $back'>",
         '#suffix' => '</div>',

       );     

       if (strlen($j['comment'])>31) {
         $desc = substr($j['comment'],0,31)."...";
       } else {
         $desc = $j['comment'];
       }

        $form['items'][$i]['object'] = array(
         '#type' => 'item',
         '#markup' => '<span title="'. $j['comment'] .'">' . $j['reference'] . " - " . $desc . '</span>',
         '#prefix' => "<div class='cell cell300 $back'>",
         '#suffix' => '</div>',

       );   

       if($j['type'] == 'debit') {

        $form['items'][$i]['debit'] = array(
         '#type' => 'item',
         '#markup' => number_format($j['value'], 2),
         '#prefix' => "<div class='cell cell100 $back'>",
         '#suffix' => '</div>',

       );     

        $form['items'][$i]['credit'] = array(
         '#type' => 'item',
         '#markup' => ' - ',
         '#prefix' => "<div class='cell cell100 $back'>",
         '#suffix' => '</div>',

       );     

       } else {

        $form['items'][$i]['debit'] = array(
         '#type' => 'item',
         '#markup' => ' - ',
         '#prefix' => "<div class='cell cell100 $back'>",
         '#suffix' => '</div>',
       );     

        $form['items'][$i]['credit'] = array(
         '#type' => 'item',
         '#markup' => number_format($j['value'], 2),
         '#prefix' => "<div class='cell cell100 $back'>",
         '#suffix' => '</div>',

       );    

       }

        $form['items'][$i]['select'.$i] = array(
         '#type' => 'checkbox',
         '#id' => 'line-'.$i,
         '#return_value' => $j['value'],
         '#attributes' => array('class' => array('calculate')),
         '#prefix' => "<div class='cell cell50 $back'>",
         '#suffix' => '</div></div>',  
         );     

     $i++;  
       
    }//while

    $form['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Attach a file'),
            '#description' => 'image or pdf',
        );
    
    $form['rows'] = array(
      '#type' => 'hidden',
      '#default_value' => $i,
    );   
    

         
    $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Reconcile'),
      //'#suffix' => "</div>",
    );
    
    
  }//step 2

    $form['#attached']['library'][] = 'ek_finance/ek_finance.reco_form';


    
    
  return $form;
  
  
  }


  /**
   * Callback
   */
  public function list_journal(array &$form, FormStateInterface $form_state) {
  $form_state->set('step', 2);
  $form_state->setRebuild(); 

  }

  /**
   * Callback
   */  
  public function get_accounts(array &$form, FormStateInterface $form_state) {
  return  $form['filters']['account'];  
  }

  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    if($form_state->get('step') == 2) {
    

    
      if($form_state->getValue('difference') <> 0) {
          $form_state->setErrorByName("difference",  $this->t('Reconciliation discrepancy') );  
      }
      
      $select = 0;
      $items = $form_state->getValue('items');
      for ($i = 0; $i < $form_state->getValue('rows'); $i++) {
        
        $select += $items[$i]['select'.$i];
      
      }
      
      if ($select == 0 ) {
          $form_state->setErrorByName("headerline",  $this->t('No record selected') );       
      
      }
      
      //attachment
            $validators = array('file_validate_extensions' => array('png jpg jpeg pdf'));
            $field = "upload_doc";
            // Check for uploaded file.
            $file = file_save_upload($field, $validators, FALSE, 0);
                if (isset($file)) {
                    // File upload was attempted.
                    if ($file) {
                        // Put the temporary file in form_values so we can save it on submit.
                        $form_state->setValue($field, $file);
                    } else {
                        // File upload failed.
                        $form_state->setErrorByName($field, $this->t('File could not be uploaded'));
                    }
                } else {
                    $form_state->setValue($field, 0);
                }
    
    }
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
      if($form_state->get('step') == 2) {
      
        $reco_lines = array();
        $error = 0;
        $reco_lines[0] = array(
          $form_state->getValue('credits'),
          $form_state->getValue('debits'),
          $form_state->getValue('openbalance'),
          $form_state->getValue('statement'),
          $form_state->getValue('difference'),
          $form_state->getValue('date'),
          $form_state->getValue("coid"),
          $form_state->getValue('account')
          );
        
        $items = $form_state->getValue('items');
        for ($i = 0; $i < $form_state->getValue('rows'); $i++) {
          
          if($items[$i]['select'.$i] <> 0 ) {
          
          $journal_id = $items[$i]['journal_id'];
          
          
            //set the reconciliation flag to 1 in journal entry
            $update = Database::getConnection('external_db', 'external_db')->update('ek_journal')
                  ->condition('id', $journal_id )
                  ->fields(array('reconcile' => 1))
                  ->execute();
          
            if(!$update) {
                //keep a record of id not properly updated in journal
                $error = 1;
            } else {
                $error = 0;
            }
          
            //set the reconcilition flag to 1 for all journal entries (block edit of entries, i.e. expense)
            $j = Journal::journalEntryDetails($journal_id);
            
            //Fix a bug to retreive exchange value when inter accounts transfer with different currencies
            if($form_state->getValue('account_currency') && ($form_state->getValue('account_currency') != $j['currency']) ) {
                $query = Database::getConnection('external_db', 'external_db')
                          ->select('ek_journal', 'jr');
                $query->fields('jr' , ['value']);
                $query->condition('coid', $j['coid'], '=');
                $query->condition('aid', $j['aid'], '=');
                $query->condition('date', $j['date'], '=');
                $query->condition('type', $j['type'], '=');
                $query->condition('source', $j['source'], '=');
                $query->condition('reference', $j['reference'], '=');
                $query->condition('exchange', 1, '=');

                $exchange = $query->execute()->fetchObject();

                $j['value'] = $j['value'] + $exchange->value;
                $j['currency'] = $form_state->getValue('account_currency');
            }
          
                //verify if date of entry is current or past year. If past year reco tag is changed from 1 to current year
                // this is used for balance calculation with overlaping data when doing reconciliation
                $current_year = date('Y');   
                    if( date('Y', strtotime($j['date'])) < $current_year) {
                      $reco = $current_year;
                    } else {
                      $reco = 1;
                    }
                    
                Database::getConnection('external_db', 'external_db')->update('ek_journal')
                  //->condition('exchange', 1)
                  ->condition('aid',$j['aid'])
                  ->condition('reference',$j['reference'])
                  ->condition('source',$j['source'])
                  ->condition('date',$j['date'])
                  ->fields(array('reconcile' => $reco))
                  ->execute();
             
                //verify if aid is bank account, if yes update bank history
                $query = "SELECT ba.id,account_ref FROM {ek_bank_accounts} ba INNER JOIN {ek_bank} b ON ba.bid=b.id where aid=:aid and coid=:coid";
                $a = array(':aid' => $form_state->getValue('account'), ':coid' => $form_state->getValue('coid'));
                $result = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchObject();
                
                        if($result) {
                        $year=explode("-",$j['date']);
                            $fields = array (
                                'account_ref'=>$result->account_ref,
                                'date_transaction'=>$j['date'],
                                'year_transaction'=>$year[0],
                                'type'=>$j['type'],
                                'currency'=> $j['currency'],
                                'amount'=> $j['value'],
                                'description'=> $j['comment']
                                );   
                                
                          Database::getConnection('external_db', 'external_db')->insert('ek_bank_transactions')
                            ->fields($fields)
                            ->execute();                     
                        
                        }
          
                // save reco line for report

                $reco_lines[$i+1] = array(
                  $form_state->getValue('account'),
                  $j['date'],
                  $year[0],
                  $j['type'],
                  $j['currency'],
                  $j['value'],
                  $j['comment'],
                  $journal_id,
                  $error,
                );

          }
          
        
        } //for 
        
        //save report data
        
        if (!$form_state->getValue('upload_doc') == 0) {
            $file = $form_state->getValue('upload_doc');
            $dir = "private://finance/bank/" . $form_state->getValue('coid');
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $filename = file_unmanaged_copy($file->getFileUri(), $dir);
            
        } else {
            $filename = "";
        }
          $reco_lines = serialize($reco_lines);
          $error = serialize($error);
          $fields = array (
                    'type'=> 1,
                    'date'=> $form_state->getValue('date'),
                    'aid' => $form_state->getValue('account'),
                    'coid'=> $form_state->getValue('coid'),
                    'data'=> $reco_lines,
                    'uri' => $filename,
                    );   
          Database::getConnection('external_db', 'external_db')
                ->insert('ek_journal_reco_history')
                ->fields($fields)
                ->execute();          
        
      
      } //if

  }
  
  
}