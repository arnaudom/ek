<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\PostNewYear.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to post finance closing values to next year opening values
 */
class PostNewYear extends FormBase {

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
    return 'post_new_accounting_year';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
    $year = date('Y');
    $month = date('m');

      $access = AccessCheck::GetCompanyByUser();
      $company = implode(',',$access);
      $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
      $company = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':t' => 1, ':c' => $company))
              ->fetchAllKeyed();  

    if ( $form_state->get('step') == '' ) {

      $form_state->set('step', 1);

    } 
  
            
                      
    $form['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $company,
        '#required' => TRUE,
        '#title' => t('company'),
        '#disabled' => $form_state->getValue('coid') ? TRUE : FALSE,
        '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
    );   

    if($form_state->get('step') == 1)  {
    $form['next'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Next') . ' >>' ,
      '#submit' =>  array(array($this, 'get_accounts')) ,
      '#states' => array(
        // Hide data fieldset when class is empty.
        'invisible' => array(
           "select[name='coid']" => array('value' => ''),
        ),
      ),

    );  
    }
    
    if($form_state->get('step') == 2)  {
        
        //extract data and seetings
        $settings = new CompanySettings($form_state->getValue('coid'));
        
        if($settings->get('fiscal_year') == NULL || $settings->get('fiscal_month') == NULL){
          //finance settings for the company have not been set.
          //display warning
            $form['info'] = array(
                '#type' => 'item',
                '#markup' => "<div class='messages messages--warning'>" .
                            t('Current fiscal year not set. Please update company financial settings first.'). 
                            "</div>"  ,

              );
            
        } elseif($year == $settings->get('fiscal_year') && $month < $settings->get('fiscal_month')) {
          //already posted? 
             $form['info'] = array(
                '#type' => 'item',
                '#markup' => "<div class='messages messages--warning'>" .
                            t('Fiscal year is already set to current year @y - @m', array('@y' => $settings->get('fiscal_year'), '@m' => $settings->get('fiscal_month'))). 
                            "</div>",

              );           
        } else {
          
            
             $form['info'] = array(
                '#type' => 'item',
                '#markup' => "<div class='messages messages--warning'>" .
                            t('You are going to post accounting data to next fiscal year.' ). 
                            "</div>",

              ); 
             
             
            //display detail of posted data
                $journal = new Journal();
                $settings = new CompanySettings($form_state->getValue('coid'));
                $finance = new FinanceSettings();
                $fiscal_year = $settings->get('fiscal_year') ;
                $fiscal_month = $settings->get('fiscal_month');

                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fiscal_month , $fiscal_year );
                $from = date('Y-m-d', strtotime($fiscal_year . '-' . $fiscal_month . '-' . $daysInMonth . ' - 1 year + 1 day'));
                $to = date('Y-m-d', strtotime($fiscal_year . '-' . $fiscal_month . '-' . $daysInMonth . ' + 1 day'));
                $earning = $journal->current_earning($form_state->getValue('coid') , $from , $to);
                $display = '';
                $rows = '';
                $q = "SELECT * FROM {ek_accounts} where coid=:coid ORDER BY aid";
                $a = array(':coid' => $form_state->getValue('coid'));
                $result =  Database::getConnection('external_db', 'external_db')
                                ->query($q,$a);
                
                while ($r=$result->fetchAssoc()) {
        
                    if($r['aid'] < '40000') {
                        if ($r['aid']=='39001') {

                        $r['balance_base']=$earning[1];
                        $r['balance']=$earning[0];

                        } elseif ($r['aid']=='38001') { 

                        $b[1] = $r['balance_base']+$earning[1];
                        $b[0] = $r['balance']+$earning[0];

                        } 

                        else {

                            $b = $journal->opening(
                                    array( 
                                    'aid' => $r['aid'],
                                    'coid'=> $form_state->getValue('coid'),
                                    'from'=> $to
                                     )
                                    );
                        }

                          $rows .="<tr class='detail'>
                          <td class='cursor' >" . $r['aid'] . " " . $r['aname'] . "</td>
                          <td align=center>" . $r['balance_date'] . "</td>"
                                  . "<td align=right>" . number_format($r['balance_base'],2) . "</td>"
                                  . "<td align=right>" . number_format($r['balance'],2) . "</td>
                          <td align=center>" . $to . "</td>"
                                  . "<td align=right>".number_format($b[1],2)."</td>"
                                  . "<td align=right>".number_format($b[0],2)."</td>
                          </tr>";          
                    }
                }
                    
                $display .= "<table>
                                <thead class='font24'>
                                  <tr>
                                    <td colspan='7'>" . t('Current year start') . ": ". $from 
                                        . " , " . t('New year start') . ": " . $to . "</td>
                                  </tr>
                                  <tr class=''>
                                    <td></td>
                                    <th colspan='3' align=center>Current</th>
                                    <th colspan='3' align=center>Next</th>
                                  </tr>
                                  <tr>
                                    <th >" . t('Account') . "</th>
                                    <th >" . t('Previous opening') . "</th>
                                    <th >" . $finance->get('baseCurrency') . "</th>
                                    <th>" . t('Local currency') . "</th>
                                    <th>" . t('New opening') . "</th>
                                    <th>" . $finance->get('baseCurrency') . "</th>
                                    <th>" . t('Local currency') . "</th>
                                  </tr>
                                </thead>
                                <tbody >" . $rows . "</tbody></table>";
                
                
            //////////////////////////////
                
              $form['table'] = array(
                '#type' => 'item',
                '#markup' => $display,

              ); 
             
             $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
              ); 
             $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Confirm New year posting'),
              );  
            
           
        }
    }
   
    
    return $form;       
}
        
    


  /**
   * Callback
   */
  public function get_accounts(array &$form, FormStateInterface $form_state) {

  $form_state->set('step', 2);

  $form_state->setRebuild(); 

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
  
    
    $journal = new Journal();
    $settings = new CompanySettings($form_state->getValue('coid'));
    $fiscal_year = $settings->get('fiscal_year') ;
    $fiscal_month = $settings->get('fiscal_month');

    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fiscal_month , $fiscal_year );
    $from = date('Y-m-d', strtotime($fiscal_year . '-' . $fiscal_month . '-' . $daysInMonth . ' - 1 year + 1 day'));
    $to = date('Y-m-d', strtotime($fiscal_year . '-' . $fiscal_month . '-' . $daysInMonth . ' + 1 day'));
    $earning = $journal->current_earning($form_state->getValue('coid') , $from , $to);
    $display = '';
    $rows = '';
    $report = array($form_state->getValue('coid'), $fiscal_year);
    $q = "SELECT * FROM {ek_accounts} where coid=:coid ORDER BY aid";
    $a = array(':coid' => $form_state->getValue('coid'));
    $result =  Database::getConnection('external_db', 'external_db')
                    ->query($q,$a);

    while ($r=$result->fetchAssoc()) {

        
            if ($r['aid']=='39001') {

            $r['balance_base']=$earning[1];
            $r['balance']=$earning[0];

            } elseif ($r['aid']=='38001') { 

            $b[1] = $r['balance_base']+$earning[1];
            $b[0] = $r['balance']+$earning[0];

            } 

            else {

                $b = $journal->opening(
                        array( 
                        'aid' => $r['aid'],
                        'coid'=> $form_state->getValue('coid'),
                        'from'=> $to
                         )
                        );
            }

          if($r['aid'] < '40000') {
              //balance sheet closing balance are reported to following year opening
              $fields = array('balance_date' => $to , 'balance' => $b[0], 'balance_base' => $b[1] );
              array_push($report,array($r['aid'],$r['balance'],$r['balance_base'],$b[0],$b[1]));
            } else {
              $fields = array('balance_date' => $to , 'balance' => 0, 'balance_base' => 0 ); 
              array_push($report,array($r['aid'],$r['balance'],$r['balance_base'],0,0));
            }
            
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_accounts')
                    ->condition('id', $r['id'])
                    ->fields($fields)
                    ->execute(); 
          
        
    }
    
    //save a report in the DB
      $report = json_encode($report); 
        $fields = array (
                  'type'=> 0,
                  'date'=> date('Y-m-d'),
                  'aid'=>0 ,
                  'coid'=> $form_state->getValue('coid'),
                  'data'=> $report
                  );   
        $insert = Database::getConnection('external_db', 'external_db')
              ->insert('ek_journal_reco_history')
              ->fields($fields)
              ->execute();
    
    //update the new fiscal year
    $settings->set('fiscal_year' , $fiscal_year+1 );
    $settings->save();
  
 }

}