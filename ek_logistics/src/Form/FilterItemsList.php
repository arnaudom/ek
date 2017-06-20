<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\FilterItemsList.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter items in stock.
 */
class FilterItemsList extends FormBase {

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
    return 'items_stock_list_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  $company = array('%' => t('Any'));
  $company += AccessCheck::CompanyListByUid();

    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => TRUE,
     
    );  
            $form['filters']['filter'] = array(
              '#type' => 'hidden',
              '#value' => 'filter',
              
            );
            
            $form['filters'][0]['keyword'] = array(
              '#type' => 'textfield',
              '#maxlength' => 150,
              '#attributes' => array('placeholder'=>t('Search with item code')),
              '#default_value' => isset($_SESSION['istockfilter']['keyword']) ? $_SESSION['istockfilter']['keyword'] : NULL,
            );            
            
            $form['filters'][1]['coid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => $company,
              '#default_value' => isset($_SESSION['istockfilter']['coid']) ? $_SESSION['istockfilter']['coid'] : NULL,
              '#title' => t('Entity'),
              '#prefix' => "<div class='container-inline'>", 
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),                  
              );           

  
            $form['filters'][1]['status'] = array(
              '#type' => 'select',
              '#title' => t('Status'),
              '#options' => array('%' => t('Any'), '1' => t('Active'), '0' => t('Stop') ),
              '#default_value' => isset($_SESSION['istockfilter']['status']) ? $_SESSION['istockfilter']['status'] : 'working' ,
              '#suffix' => '</div>',
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),                  
            ); 
            
            $form['filters'][1]['tag'] = array(
              '#type' => 'select',
              '#title' => t('Tags'),
              '#options' => array('%' => t('Any'), 'type' => t('Type'), 'department' => t('Department'), 'family' => t('Family'), 'collection' => t('Collection'), 'color' => t('Color') ),
              '#default_value' => isset($_SESSION['istockfilter']['tag']) ? $_SESSION['istockfilter']['tag'] : 'working' ,
              '#ajax' => array(
                  'callback' => array($this, 'tagvalue'), 
                  'wrapper' => 'tagvalue',
              ),
              '#prefix' => "<div class='container-inline'>", 
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),                  
            ); 
           
 if(isset($_SESSION['istockfilter']['tag']) || $form_state->getValue('tag') != '') {
  
  if($form_state->getValue('tag') != '') {
    $f = $form_state->getValue('tag');
  } else {
    $f = $_SESSION['istockfilter']['tag'];
  } 
  
  switch($f) {
    case 'type' :
    $query = "SELECT DISTINCT type FROM {ek_items} ORDER BY type";
    break;
    case 'department' :
    $query = "SELECT DISTINCT department FROM {ek_items} ORDER BY department";
    break;    
    case 'family' :
    $query = "SELECT DISTINCT family FROM {ek_items} ORDER BY family";
    break; 
    case 'collection' :
    $query = "SELECT DISTINCT collection FROM {ek_items} ORDER BY collection";
    break;
    case 'color' :
    $query = "SELECT DISTINCT color FROM {ek_items} ORDER BY color";
    break;
    case '%' :
    $query = NULL;
    break;
  }
  
  if($query != NULL) {
  $options = Database::getConnection('external_db', 'external_db')->query($query)->fetchCol();
  } else {
  $options = array('%' => t('Any'));
  }
  
 } else {         
  $options = array();
 }

            $form['filters'][1]['tagvalue'] = array(
              '#type' => 'select',
              '#options' => array_combine($options, $options),
              '#default_value' => isset($_SESSION['istockfilter']['tagvalue']) ? $_SESSION['istockfilter']['tagvalue']: NULL,
              '#prefix' => "<div id='tagvalue'>", 
              '#suffix' => '</div></div>', 
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),                  
            );            
            
                        

    $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      
    );

    if (!empty($_SESSION['istockfilter'])) {
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
  public function tagvalue(array &$form, FormStateInterface $form_state) {
    return  $form['filters'][1]['tagvalue'];
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if($form_state->getValue('keyword') == '') {
    //check input if filter not by keyword
    
      if($form_state->getValue('coid') == '0')  {
      
      $form_state->setErrorByName("coid",  $this->t('Entity not selected') );
      
      }
    
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form,  FormStateInterface $form_state) {
  
  $_SESSION['istockfilter']['keyword'] = $form_state->getValue('keyword');
  $_SESSION['istockfilter']['coid'] = $form_state->getValue('coid');
  $_SESSION['istockfilter']['status'] = $form_state->getValue('status');
  $_SESSION['istockfilter']['tag'] = $form_state->getValue('tag');
  $_SESSION['istockfilter']['tagvalue'] = $form_state->getValue('tagvalue');
  $_SESSION['istockfilter']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['istockfilter'] = array();
  }
  
}