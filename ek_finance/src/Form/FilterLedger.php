<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterLedger.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to filter ledger balance.
 */
class FilterLedger extends FormBase {


  /**
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   */
  public function __construct() {
    $this->settings = new FinanceSettings();
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ledger_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  $to = date('Y-m-d');
  $from = date('Y-m-').'01';
  //$date1= date('Y-m-d', strtotime($data2." -30 days")) ;

      $access = AccessCheck::GetCompanyByUser();
      $company = implode(',',$access);
      $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
      $company = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':t' => 1, ':c' => $company))
              ->fetchAllKeyed();  

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
            $form['filters']['from'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['lfilter']['from']) ? $_SESSION['lfilter']['from'] : $from,
              '#title' => t('from'),
            ); 

            $form['filters']['to'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['lfilter']['to']) ? $_SESSION['lfilter']['to'] : $to,
              '#title' => t('to'),
            ); 
            
                    
    $form['filters']['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $company,
        '#required' => TRUE,
        '#title' => t('company'),
        '#default_value' => isset($_SESSION['lfilter']['coid']) ? $_SESSION['lfilter']['coid'] : NULL,
        '#ajax' => array(
          'callback' => array($this, 'get_accounts'), 
          'wrapper' => 'accounts_',
      ), 
    );   
    
    if($form_state->getValue('coid') || isset($_SESSION['lfilter']['coid'])) {
        
            if(isset($_SESSION['lfilter']['coid']) && null ==($form_state->getValue('coid')) ) {
                $coid = $_SESSION['lfilter']['coid'];
            } elseif(null !== ($form_state->getValue('coid'))) {
                $coid = $form_state->getValue('coid');
            }

        $list = AidList::listaid($coid, array(1,2,3,4,5,6,7,8,9), 1 );
    
    }

    $form['filters']['range']['account_from'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => isset( $list ) ? $list : array(),
        '#required' => TRUE,
        '#default_value' => isset($_SESSION['lfilter']['account_from']) ? $_SESSION['lfilter']['account_from'] : NULL,
        '#title' => t('range'),
        '#attributes' => array('style' => array('width:150px;')),
        '#prefix' => "<div id='accounts_'>",

    ); 
    $form['filters']['range']['account_to'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => isset( $list ) ? $list : array(),
        '#required' => TRUE,
        '#default_value' => isset($_SESSION['lfilter']['account_to']) ? $_SESSION['lfilter']['account_to'] : NULL,
        '#attributes' => array('style' => array('width:150px;')),
        '#suffix' => '</div>',
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

    if (!empty($_SESSION['lfilter'])) {
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
   * Callback
   */
  public function get_accounts(array &$form, FormStateInterface $form_state) {
  return  $form['filters']['range'];  
  }

  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    if ($form_state->getValue('to') < $form_state->getValue('from') ){
    
      $switch = $form_state->getValue('from');
      $form_state->setValue('from', $form_state->getValue('to') );
      $form_state->setValue('to', $switch );
    
    }

    if ($form_state->getValue('account_to') < $form_state->getValue('account_from') ){
    
      $switch = $form_state->getValue('account_from');
      $form_state->setValue('account_from',$form_state->getValue('account_to'));
      $form_state->setValue('account_to', $switch) ;
    
    }
     
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  $_SESSION['lfilter']['from'] = $form_state->getValue('from');
  $_SESSION['lfilter']['to'] = $form_state->getValue('to');
  $_SESSION['lfilter']['coid'] = $form_state->getValue('coid');
  $_SESSION['lfilter']['account_from'] = $form_state->getValue('account_from');
  $_SESSION['lfilter']['account_to'] = $form_state->getValue('account_to');
  $_SESSION['lfilter']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['lfilter'] = array();
    $_SESSION['lfilter']['coid'] = NULL;
  }
  
}