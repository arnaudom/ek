<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditMainParameters.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to create or edit HR main parameters
 */
class EditMainParameters extends FormBase {

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
    return 'main_parameters_edit';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

  
  if ( $form_state->get('step') == '' ) {

    $form_state->set('step', 1);
  
  } 
  
  
  $company = AccessCheck::CompanyListByUid();
  $form['coid'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => $company,
    '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
    '#title' => t('company'),
    '#disabled' => ($form_state->getValue('coid')) ? TRUE : FALSE,
    '#required' => TRUE, 
    ); 
    
if ( $form_state->getValue('coid') == '' ) {
  $form['next'] = array(
    '#type' => 'submit',
    '#value' => t('Next'). ' >>',
    '#states' => array(
        // Hide data fieldset when class is empty.
        'invisible' => array(
           "select[name='coid']" => array('value' => ''),
        ),
      ),
  );
}
 
  if ( $form_state->get('step') == 2 ) {
   
  $form_state->set('step', 3);
  
  //verify if the settings table has the company
  $query = "SELECT count(coid) from {ek_hr_workforce_settings} where coid=:c";
  $row = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $form_state->getValue('coid') ) )->fetchField();
  
  if(!$row == 1) {

    Database::getConnection('external_db', 'external_db')
          ->insert('ek_hr_workforce_settings')
          ->fields(array('coid' => $form_state->getValue('coid') ))
          ->execute();  
  }
    
  $param = NEW HrSettings($form_state->getValue('coid') );
  $list = $param->HrParam[$form_state->getValue('coid')];
  

  if(empty($list)) {
  //create a new list
    $list = array( 
      $form_state->getValue('coid')  => array (
      //'a' => array( 'description' => '', 'value' => '', ), 
      //'b' => array( 'description' => '', 'value' => '0', ), 
      'c' => array( 'description' => 'Fund 1 name', 'value' => 'Fund 1', ), 
      'd' => array( 'description' => 'Fund 1 calculation (P=percent; T=table)', 'value' => 'P', ), 
      'e' => array( 'description' => 'employer Fund 1 contribution (%)', 'value' => '0', ), 
      'f' => array( 'description' => 'employee Fund 1 contribution (%)', 'value' => '0', ), 
      'g' => array( 'description' => 'Fund 1 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C', ), 
      'h' => array( 'description' => 'Fund 2 name', 'value' => 'Fund 2', ), 
      'i' => array( 'description' => 'Fund 2 calculation (P=percent; T=table)', 'value' => 'P', ), 
      'j' => array( 'description' => 'employer Fund 2 contribution (%)', 'value' => '0', ), 
      'k' => array( 'description' => 'employee Fund 2 contribution (%)', 'value' => '0', ), 
      'l' => array( 'description' => 'Fund 2 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C', ), 
      'm' => array( 'description' => 'income tax name', 'value' => 'Income tax', ), 
      'n' => array( 'description' => 'income tax calculation (P=percent; T=table)', 'value' => 'P', ), 
      'o' => array( 'description' => 'income tax calculation base (C=contract, A=average, B=basic, G=gross)', 'value' => 'C', ), 
      'p' => array( 'description' => 'income tax (%) ', 'value' => '0', ), 
      'q' => array( 'description' => 'Fund 3 name', 'value' => 'Fund 3', ), 
      'r' => array( 'description' => 'Fund 3 calculation (P=percent; T=table) ', 'value' => 'P', ), 
      's' => array( 'description' => 'employer Fund 3 (%) ', 'value' => '0', ), 
      't' => array( 'description' => 'employee Fund 3 (%) ', 'value' => '0', ), 
      'u' => array( 'description' => 'Fund 3 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C', ), 
      'v' => array( 'description' => 'Fund 4 name', 'value' => 'Fund 4', ), 
      'w' => array( 'description' => 'Fund 4 calculation (P=percent; T=table) ', 'value' => 'P', ), 
      'x' => array( 'description' => 'employer Fund 4 (%) ', 'value' => '0', ), 
      'y' => array( 'description' => 'employee Fund 4 (%) ', 'value' => '0', ), 
      'z' => array( 'description' => 'Fund 4 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C', ), 
      'aa' => array( 'description' => 'Fund 5 name', 'value' => 'Fund 5', ), 
      'ab' => array( 'description' => 'Fund 5 calculation (P=percent; T=table)', 'value' => 'P', ), 
      'ac' => array( 'description' => 'employer Fund 5 (%) ', 'value' => '0', ), 
      'ad' => array( 'description' => 'employee Fund 5 (%)', 'value' => '0', ), 
      'ae' => array( 'description' => 'Fund 5 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C', ), 
      )
      );

    Database::getConnection('external_db', 'external_db')
          ->update('ek_hr_workforce_settings')
          ->fields(array('param' => serialize($list) ) )
          ->condition('coid', $form_state->getValue('coid') )
          ->execute();    
          
    $category = NEW HrSettings($form_state->getValue('coid') );
    $list = $category->HrParam[$form_state->getValue('coid') ];  
  }
  
  foreach($list as $key => $value) {

  $form[$key] = array(
      '#type' => 'textfield',
      '#size' => 50,
      '#maxlength' => 100,
      '#default_value' => $value['value'],
      '#attributes' => array('placeholder'=>t('parameter value')),
      '#description' => $value['description'],
    );

    $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#suffix' => ''
    );  
  
  }//if

    $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';
 
   
  
  }
 return $form;
}


 
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
    if ($form_state->get('step') == 1) {
      $form_state->set('step', 2); 
      $form_state->setRebuild();
    }
  
  }
  

  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  if ( $form_state->get('step') == 3) {
    $category = NEW HrSettings($form_state->getValue('coid'));
    $list = $category->HrParam[$form_state->getValue('coid')];
    
        foreach($list as $key => $value) {
        $input = Xss::filter( $form_state->getValue($key) );
            $category->set(
            'param',
            $key,
            $input
            );
        }

      $category->save();
    }//step 3

  
  
  }
  

  
  
}