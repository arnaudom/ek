<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\EditCountryForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

/**
 * Provides an item form.
 */
class EditCountryForm extends FormBase {

  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_edit_country_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form,  FormStateInterface $form_state, $id = NULL) {
  
  
    $query = "SELECT * from {ek_country} order by name";
    $data = Database::getConnection('external_db', 'external_db')->query($query);

          $form['1'] = array(
                '#type' => 'details', 
                '#title' => t('Active'), 
                '#collapsible' => TRUE, 
                '#collapsed' => FALSE,
                '#prefix' => "<div class='table'>",
            
           );
 
          $form['0'] = array(
                '#type' => 'details', 
                '#title' => t('Non Active'), 
                '#collapsible' => TRUE, 
                '#collapsed' => TRUE,
                '#prefix' => "<div class='table'>",
            
           );  

    while($r = $data->fetchAssoc()) {
    
    $id = $r['id'];
    
      if ($r['status'] == 1) { 
      
          $form['1']['id'.$id] = array(
                '#type' => 'hidden', 
                '#value' => $r['id'] , 
            
           );           
      
          $form['1']['name'.$id] = array(
                '#type' => 'item', 
                '#markup' =>  $r['name'] , 
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => "</div>",            
           );

          $form['1']['entity'.$id] = array(
              '#type' => 'textfield',
              '#size' => 30,
              '#maxlength' => 255,
              '#default_value' => isset($r['entity']) ? $r['entity'] : NULL,
              '#attributes' => array('placeholder'=>t('entity')),
              '#prefix' => "<div class='cell'>",
              '#suffix' => " </div>",

          );   
          
          $form['1']['status'.$id] = array(
              '#type' => 'checkbox',
              '#default_value' => 1,
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div></div>",
              '#title' => t('active'),

          );              
      
      } else {
      
          $form['0']['id'.$id] = array(
                '#type' => 'hidden', 
                '#value' => $r['id'] , 
            
           );           
      
          $form['0']['name'.$id] = array(
                '#type' => 'item', 
                '#markup' =>  $r['name'] , 
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => "</div>",            
            
           );

          $form['0']['entity'.$id] = array(
              '#type' => 'textfield',
              '#size' => 30,
              '#maxlength' => 255,
              '#default_value' => isset($r['entity']) ? $r['entity'] : NULL,
              '#attributes' => array('placeholder'=>t('entity')),
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div>",

          );   
          
          $form['0']['status'.$id] = array(
              '#type' => 'checkbox',
              '#default_value' => 0,
              '#suffix' => "</div>",
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div></div>",
              '#title' => t('select to activate'),

          );       
      
      }
    
    }
    
          $form['1']['close'] = array(
                '#type' => 'item', 
                '#markup' =>  '' , 
                '#suffix' => "</div>",            
           );
           
          $form['0']['close'] = array(
                '#type' => 'item', 
                '#markup' =>  '' , 
                '#suffix' => "</div>",            
           );
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));


        
        return $form;    
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function validateForm(array &$form,  FormStateInterface $form_state) {
  
  
  }
 
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form,  FormStateInterface $form_state) {
  
    $query = "SELECT * from {ek_country} order by name";
    $data = Database::getConnection('external_db', 'external_db')->query($query);

    while($r = $data->fetchAssoc()) {
    
        $entity = 'entity'.$r['id'];
        $status = 'status'.$r['id'];

          $update = Database::getConnection('external_db', 'external_db')->update('ek_country')
                   ->condition('id', $r['id'])
                   ->fields(array('entity' => $form_state->getValue($entity) , 'status' => $form_state->getValue($status)) )
                   ->execute(); 
    
    }

    drupal_set_message(t('Country data updated'), 'status');
          $form_state->setRedirect('ek_admin.country.list');
 
  }
  
  
}