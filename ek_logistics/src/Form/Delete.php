<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Delete.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a form to delete logistics record.
 */
class Delete extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_logistics_delete';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $table = NULL, $type = NULL) {
  
 
  $dbTable = 'ek_logi_' . $table;
  $query = "SELECT status,serial FROM {$dbTable} WHERE id=:id";
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':id' => $id))->fetchObject();

  if($type == 'RR') {
      $route = "ek_logistics_list_receiving"; 
  } elseif($type == 'RT') {
      $route = "ek_logistics_list_returning"; 
  } else {
      $route = "ek_logistics_list_delivery"; 
  }
    $form['del_logistics'] = array(
      '#type' => 'item',
      '#markup' => t('Record ref. @p', array('@p' => $data->serial)),

    );   

       
        $form['for_id'] = array(
          '#type' => 'hidden',
          '#value' => $id,

        );
        $form['table'] = array(
          '#type' => 'hidden',
          '#value' => $table,
        );
        
        $form['serial'] = array(
          '#type' => 'hidden',
          '#value' => $data->serial, 
        );  
          
        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('Are you sure you want to delete this record ?'),

        );
        
        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );
        
        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Delete'),
        );     
        
        $form['actions']['cancel'] = array(
          '#type' => 'item',
          '#markup' => t('<a href="@url" >Cancel</a>', array('@url' => Url::fromRoute($route,[],[])->toString())) ,
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
  
    if( $form_state->getValue('table') == 'delivery' ) {

      $delete = Database::getConnection('external_db', 'external_db')
              ->delete('ek_logi_delivery')->condition('id', $form_state->getValue('for_id'))
              ->execute();
      $delete = Database::getConnection('external_db', 'external_db')
              ->delete('ek_logi_delivery_details')->condition('serial', $form_state->getValue('serial'))
              ->execute();

      if ($delete){
          \Drupal::messenger()->addStatus(t('The record has been deleted'));
           $form_state->setRedirect("ek_logistics_list_delivery" );  
      }  

    }   elseif($form_state->getValue('table') == 'receiving' ) {

      $type = Database::getConnection('external_db', 'external_db')
              ->query('SELECT type FROM {ek_logi_receiving} WHERE id=:id', array(':id' => $form_state->getValue('for_id') ) )
              ->fetchField();

      $delete = Database::getConnection('external_db', 'external_db')
              ->delete('ek_logi_receiving')->condition('id', $form_state->getValue('for_id'))
              ->execute();
      $delete = Database::getConnection('external_db', 'external_db')
              ->delete('ek_logi_receiving_details')->condition('serial', $form_state->getValue('serial'))
              ->execute();

              if ($delete && $type == 'RR'){
                  \Drupal::messenger()->addStatus(t('The record has been deleted'));
                   $form_state->setRedirect("ek_logistics_list_receiving" );  
              }    

              if ($delete && $type == 'RT'){
                  \Drupal::messenger()->addStatus(t('The record has been deleted'));
                   $form_state->setRedirect("ek_logistics_list_returning" );  
              } 
    }
  }


}