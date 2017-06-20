<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterRecoReports.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter reconciliation reports.
 */
class FilterRecoReports extends FormBase {

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
    return 'reco_report_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  $to = date('Y-m-d');
  $from = date('Y-m-').'01';
      
      $coid = array('0' => t('Any'));
      $access = AccessCheck::GetCompanyByUser();
      $company = implode(',',$access);
      $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
      $coid += Database::getConnection('external_db', 'external_db')->query($query, array(':t' => 1, ':c' => $company))->fetchAllKeyed();  

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
              '#default_value' => isset($_SESSION['recofilter']['from']) ? $_SESSION['recofilter']['from'] : $from,
              //'#attributes' => array('placeholder'=>t('from')),
              '#title' => t('from'),
            ); 

            $form['filters']['to'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['recofilter']['to']) ? $_SESSION['recofilter']['to'] : $to,
              //'#attributes' => array('placeholder'=>t('to')),
              '#title' => t('to'),
            ); 
            
                      
    $form['filters']['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $coid,
        '#required' => TRUE,
        '#title' => t('company'),
        '#default_value' => isset($_SESSION['recofilter']['coid']) ? $_SESSION['recofilter']['coid'] : NULL,
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

    if (!empty($_SESSION['recofilter'])) {
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  $_SESSION['recofilter']['from'] = $form_state->getValue('from');
  $_SESSION['recofilter']['to'] = $form_state->getValue('to');
  $_SESSION['recofilter']['coid'] = $form_state->getValue('coid');
  $_SESSION['recofilter']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['recofilter'] = array();
  }
  
}