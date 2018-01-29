<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterExpenses.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_finance\AidList;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to filter Expenses.
 */
class FilterExpenses extends FormBase {
    
  /**
   * Constructs a FilterExpenses object.
   */
  public function __construct() {
    $this->settings = new FinanceSettings();
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'expenses_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  $query = "SELECT SQL_CACHE date from {ek_journal} order by date DESC limit 1";
  $to = Database::getConnection('external_db', 'external_db')->query($query)->fetchObject();
  $from = date('Y-m') . "-01";
  $open = TRUE;
  if(isset($_SESSION['efilter']['filter']) && $_SESSION['efilter']['filter']  == 1) {
      $open = FALSE;
  }
    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => $open,
    );  
    
  $access = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
  $company = implode(',',$access);
  $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
  $company = Database::getConnection('external_db', 'external_db')->query($query, array(':t' => 1, ':c' => $company))->fetchAllKeyed();
  $coid = array( 0 => '');
  $coid += $company;

     
            $form['filters']['filter'] = array(
              '#type' => 'hidden',
              '#value' => 'filter',
            );            

            $form['filters'][0]['keyword'] = array(
              '#type' => 'textfield',
              '#maxlength' => 150,
              '#attributes' => array('placeholder'=>t('Search with keyword, ref No.')),
              '#default_value' => isset($_SESSION['efilter']['keyword']) ? $_SESSION['efilter']['keyword'] : NULL,
            ); 
            
                        
            $form['filters'][1]['coid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => $coid,
              '#default_value' => isset($_SESSION['efilter']['coid']) ? $_SESSION['efilter']['coid'] : 0,
              '#title' => t('company'),
              '#ajax' => array(
                  'callback' => array($this, 'set_coid'), 
                  'wrapper' => 'add',
              ),
              '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
              '#suffix' => '</div>',
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),           
            );               

          if( $form_state->getValue('coid') ) {
              $aid = array('%' => t('Any'));
              $chart = $this->settings->get('chart');
              $aid += AidList::listaid($form_state->getValue('coid'), array($chart['liabilities'], $chart['cos'], $chart['expenses'], $chart['other_expenses']), 1 );
              $_SESSION['efilter']['options'] = $aid;
          
          }

            $form['filters'][1]["aid"] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' =>  isset($_SESSION['efilter']['options']) ? $_SESSION['efilter']['options'] : array() ,
            '#title' => t('class'),
            '#default_value' => isset($_SESSION['efilter']['aid']) ? $_SESSION['efilter']['aid'] : NULL ,
            '#attributes' => array('style' => array('width:200px;')),
            '#prefix' => "<div id='add'  class='row'>",
            '#suffix' => '</div></div></div>',  
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),      
            ); 
        
            $form['filters'][2]['from'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['efilter']['from']) ? $_SESSION['efilter']['from'] : date('Y-m') . "-01",
              //'#attributes' => array('placeholder'=>t('from')),
              '#prefix' => "<div class=''><div class='row'><div class='cell'>",
              '#suffix' => '</div>',
              '#title' => t('from'),
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),               
            ); 

            $form['filters'][2]['to'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['efilter']['to']) ? $_SESSION['efilter']['to'] : $to->date,
              //'#attributes' => array('placeholder'=>t('to')),
              '#title' => t('to'),
              '#prefix' => "<div class='cell'>",
              '#suffix' => '</div></div></div>',  
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),                             
            ); 
            
              

  $supplier = array('%' => t('Any'));
  $supplier += Database::getConnection('external_db', 'external_db')
          ->query("SELECT DISTINCT ab.id,name FROM {ek_address_book} ab INNER JOIN {ek_expenses} e ON e.suppliername=ab.id order by name" )
          ->fetchAllKeyed();


            $form['filters'][3]['supplier'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $supplier,
                '#default_value' => isset($_SESSION['efilter']['supplier']) ? $_SESSION['efilter']['supplier'] :'%',
                '#title' => t('supplier'),
                '#attributes' => array('style' => array('width:200px;')),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                 
              );

  $client = array('%' => t('Any'));
  $client += Database::getConnection('external_db', 'external_db')
          ->query("SELECT DISTINCT ab.id,name FROM {ek_address_book} ab INNER JOIN {ek_expenses} e ON e.clientname=ab.id order by name" )
          ->fetchAllKeyed();


            $form['filters'][3]['client'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $client,
                '#default_value' => isset($_SESSION['efilter']['client']) ? $_SESSION['efilter']['client'] : '%',
                '#title' => t('client'),
                '#attributes' => array('style' => array('width:200px;')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',  
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                              
              );

  $option = array('%' => t('Any'), 'na' => t('not applicable'));
  $query = "SELECT DISTINCT e.pcode,pname,p.id from {ek_expenses} e LEFT JOIN {ek_project} p ON e.pcode=p.pcode ORDER by p.id DESC";
  $pcodes = Database::getConnection('external_db', 'external_db')
          ->query($query)->fetchAll();

          foreach ($pcodes as $p)  { 
                if($p->pcode != 'n/a' && $p->pcode != ''){
                  $pname=substr($p->pname,0,75).'...';
                  $pcode_parts = explode("-" , $p->pcode);
                  $pcode = array_reverse($pcode_parts);
                  if(!isset($pcode[4])) $pcode[4] = '-';
                  if(!isset($pcode[3])) $pcode[3] = '-';
                  if(!isset($pcode[2])) $pcode[2] = '-';
                  if(!isset($pcode[1])) $pcode[1] = '-';
                  $option[$p->pcode] = $pcode[0] . " | "
                          . $pcode[4] . "-" . $pcode[3] . '-' . $pcode[2] ."-" 
                          . $pcode[1] . " | ".  $pname ;
                } 
              }

            $form['filters'][3]['pcode'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $option,
                '#default_value' => isset($_SESSION['efilter']['pcode']) ? $_SESSION['efilter']['pcode'] : '%',
                '#title' => t('project'),
                '#attributes' => array('style' => array('width:200px;')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div></div>', 
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                                 
              );      
  

            $form['filters']['rows'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => [25 => '25',50 => '50',100 => '100',200 => '200',500 => '500', 1000 => '1000',2000 => '2000', 5000 => '5000'],
                '#default_value' => isset($_SESSION['efilter']['rows']) ? $_SESSION['efilter']['rows'] : '25',
                '#title' => t('show rows'),                            
              );

          
            
            
    $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      //'#suffix' => "</div>",
    );

    if (!empty($_SESSION['efilter'])) {
      $form['filters']['actions']['reset'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => array(),
        '#submit' => array(array($this, 'resetForm')),
      ); 
    } 
  return $form;
  
  
  }

  /**
   * callback functions
   */  
  public function set_coid(array &$form, FormStateInterface $form_state) {

    //return aid list

    return $form['filters'][1]['aid'];
    
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
    if($form_state->getValue('keyword') == '') {
    //check input if filter not by keyword
    
      if($form_state->getValue('coid') == 0)  {
      
      $form_state->setErrorByName("coid",  $this->t('Company not selected') );
      
      }

      if($form_state->getValue('aid') == '' ) {
      
      $form_state->setErrorByName("aid",  $this->t('Account not selected') );
      
      }   
    
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  $_SESSION['efilter']['from'] = $form_state->getValue('from');
  $_SESSION['efilter']['to'] = $form_state->getValue('to');
  $_SESSION['efilter']['coid'] = $form_state->getValue('coid');
  $_SESSION['efilter']['aid'] = $form_state->getValue('aid');
  $_SESSION['efilter']['supplier'] = $form_state->getValue('supplier');
  $_SESSION['efilter']['client'] = $form_state->getValue('client');
  $_SESSION['efilter']['pcode'] = $form_state->getValue('pcode');
  $_SESSION['efilter']['keyword'] = $form_state->getValue('keyword');
  $_SESSION['efilter']['rows'] = $form_state->getValue('rows');
  $_SESSION['efilter']['filter'] = 1;
  $aid = array('%' => t('Any'));
  $chart = $this->settings->get('chart');
  $aid += AidList::listaid($form_state->getValue('coid'), array($chart['liabilities'], $chart['cos'], $chart['expenses'], $chart['other_expenses']), 1 );
  $_SESSION['efilter']['options'] = $aid;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['efilter'] = array();
  }
  
}