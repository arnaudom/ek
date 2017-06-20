<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Delete.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to delete logistics record.
 */
class Delete extends FormBase {

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
    return 'ek_logistics_delete';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
  
  $path = \Drupal::request()->getRequestUri();
  
  if(strpos($path,'-delivery')) {
    $query = "SELECT status,serial FROM {ek_logi_delivery} WHERE id=:id";
    $table = 'delivery';
    
  } else {
    $query = "SELECT status,serial FROM {ek_logi_receiving} WHERE id=:id";
    $table = 'receiving';
  }
  
  
  $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();

  
    $form['del_logistics'] = array(
      '#type' => 'item',
      '#markup' => t('Record ref. @p', array('@p' => $data->serial)),

    );   

    if($data->status == 0 ) {     

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
      
           $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Delete'),

          );     
    } else {

        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('This order cannot be deleted because it has been printed'),

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
  
  if( $form_state->getValue('table') == 'delivery' && \Drupal::currentUser()->hasPermission('delete_delivery')) {
  
    $delete = Database::getConnection('external_db', 'external_db')
            ->delete('ek_logi_delivery')->condition('id', $form_state->getValue('for_id'))
            ->execute();
    $delete = Database::getConnection('external_db', 'external_db')
            ->delete('ek_logi_delivery_details')->condition('serial', $form_state->getValue('serial'))
            ->execute();
    
    if ($delete){
    drupal_set_message(t('The record has been deleted'), 'status');
         $form_state->setRedirect("ek_logistics_list_delivery" );  
    }  
  
  } 

  elseif($form_state->getValue('table') == 'receiving' && \Drupal::currentUser()->hasPermission('delete_receiving')) {

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
            drupal_set_message(t('The record has been deleted'), 'status');
                 $form_state->setRedirect("ek_logistics_list_receiving" );  
            }    

            if ($delete && $type == 'RT'){
            drupal_set_message(t('The record has been deleted'), 'status');
                 $form_state->setRedirect("ek_logistics_list_returning" );  
            } 
      
        } else {
         drupal_set_message(t('You do not have enough privileges to delete this record. Please contact administrator.'), 'warning');

        }

  
  }


}//class