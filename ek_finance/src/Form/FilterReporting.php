<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterReporting.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;

/**
 * Provides a form to filter financial reporting tables
 */
class FilterReporting extends FormBase {

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
    return 'reporting_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $option = NULL) {
  
    $year = date('Y');


      $access = AccessCheck::GetCompanyByUser();
      $company = implode(',',$access);
      $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
      $company = Database::getConnection('external_db', 'external_db')->query($query, array(':t' => 1, ':c' => $company))->fetchAllKeyed(); 
      $company += ['all' => "-- " . t('compilation') . " --"];

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
            $options = array($year+1, $year, $year-1, $year-2, $year-3, $year-4);
            $form['filters']['year'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => array_combine($options,$options),
              '#default_value' => isset($_SESSION['repfilter']['year']) ? $_SESSION['repfilter']['year'] : $year,
              '#title' => t('year'),
            ); 
           
                      
    $form['filters']['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $company,
        '#required' => TRUE,
        '#title' => t('company'),
        '#default_value' => isset($_SESSION['repfilter']['coid']) ? $_SESSION['repfilter']['coid'] : NULL,

    );   
    if($option == 'report') {
        $form['filters']['view'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => ['1' => t('Actual'), '2' => t('Allocated')],
            //'#required' => TRUE,
            '#title' => '',
            '#default_value' => isset($_SESSION['repfilter']['view']) ? $_SESSION['repfilter']['view'] : NULL,
            '#states' => array(
                'invisible' => array('select[name="coid"]' => array('value' => 'all'),
                ),
              ),

        );  
    } else {
        $form['filters']['view'] = ['#type' => 'hidden' , '#value' => ''];
    } 
    
    $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      //'#suffix' => "</div>",
    );

    if (!empty($_SESSION['repfilter'])) {
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
  
  $_SESSION['repfilter']['year'] = $form_state->getValue('year');
  $_SESSION['repfilter']['coid'] = $form_state->getValue('coid');
  $_SESSION['repfilter']['view'] = $form_state->getValue('view');
  $_SESSION['repfilter']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['repfilter'] = array();
  }
  
}