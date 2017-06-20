<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterTax.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;


/**
 * Provides a form to filter tax collected and payable.
 */
class FilterTax extends FormBase {

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
    return 'filter_tax';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  $to = $from = date('Y-m') .'-' . cal_days_in_month(CAL_GREGORIAN,date('m'),date('Y'));
  $from = date('Y-m') .'-01' ;
  if ($form_state->get('step') == '' ) {

    $form_state->set('step', 1);
  
  } 
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
              '#default_value' => isset($_SESSION['taxfilter']['from']) ? $_SESSION['taxfilter']['from'] : $to,
              '#title' => t('from'),
            ); 
            $form['filters']['to'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['taxfilter']['to']) ? $_SESSION['taxfilter']['to'] : $to,
              '#required' => TRUE,
              '#title' => t('to'),
            );           
                      
    $form['filters']['coid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $company,
        '#default_value' => $_SESSION['taxfilter']['coid'],
        '#required' => TRUE,
        '#title' => t('company'),

    );   

   

         
    $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );

    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('View'),
        /**/
      '#states' => array(
        'invisible' => array(
           "select[name='coid']" => array('value' => ''),
        ),
      ),
         
         
      
    );
  
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
  
    $_SESSION['taxfilter']['from'] = $form_state->getValue('from');
    $_SESSION['taxfilter']['to'] = $form_state->getValue('to');
    $_SESSION['taxfilter']['coid'] = $form_state->getValue('coid');
    $_SESSION['taxfilter']['filter'] = 1;

  }
  
  
}