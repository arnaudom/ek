<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditLeave.
 */

namespace Drupal\ek_hr\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to edit HR leaves.
 */
class EditLeave extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_hr_edit_leave';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


    
    $form['for_id'] = array(
          '#type' => 'hidden',
          '#default_value' =>$id,
          
    ); 
    
    $form['type'] = array(
          '#type' => 'select',
          '#options' => array('a' => t('annual leave'), 'b' => t('medical leave')),
          '#required' => TRUE,
          '#prefix' => '<div class="container-inline">',
          '#title' => t('Edit leave data'),
    ); 
    
  $query = 'SELECT month from {ek_hr_post_data} WHERE emp_id=:e';
  $a = array(':e'=>$id);
  $options =  Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();

    $form['month'] = array(
          '#type' => 'select',
          '#options' => array_combine($options, $options),
           '#required' => TRUE,
         
    ); 

    $form['days'] = array(
          '#type' => 'textfield',
          '#size' => 3,
          '#maxlength' => 2,
         
    );       

    $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Edit'),
      '#suffix' => '</div>'
    ); 
            
    return $form;  
         

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
   if( !is_numeric($form_state->getValue('days')) ) {
     $form_state->setErrorByName('days', $this->t('Wrong input') );
    }
  }

  /**
   * {@inheritdoc}
   */  
  public function submitForm(array &$form, FormStateInterface $form_state) {

      $query = 'SELECT id, tleave,mc_day FROM {ek_hr_post_data} WHERE emp_id=:e AND month=:m';
      $a = array(':e'=> $form_state->getValue('for_id') , ':m' => $form_state->getValue('month'));
      $data =  Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

      if($form_state->getValue('type') == 'a' ) {
      
      $num = $data->tleave + $form_state->getValue('days');
      $query = Database::getConnection('external_db', 'external_db')->update('ek_hr_post_data')->fields(array('tleave' => $num))->condition('id', $data->id)->execute();
      
      } else {

      $num = $data->mc_day + $form_state->getValue('days');
      $query = Database::getConnection('external_db', 'external_db')->update('ek_hr_post_data')->fields(array('mc_day' => $num))->condition('id', $data->id)->execute();      
      
      }
    
  }


}
