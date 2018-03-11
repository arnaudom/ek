<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Post.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_products\ItemData;

/**
 * Provides a form to post logistics quantities to stock.
 */
class Post extends FormBase {

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
    return 'ek_logistics_post';
  }

  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
  
  $path = \Drupal::request()->getRequestUri();
  
  if(strpos($path,'delivery')) {
    $query = "SELECT status,serial FROM {ek_logi_delivery} WHERE id=:id";
    $table = 'delivery';  
    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
    $query = "SELECT itemcode,quantity from {ek_logi_delivery_details} WHERE serial=:s";
    $quantities = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $data->serial)) ;   
    
  } else {
    $query = "SELECT status,serial,type FROM {ek_logi_receiving} WHERE id=:id";
    $table = 'receiving';
    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();    
    $query = "SELECT itemcode,quantity from ek_logi_receiving_details} WHERE serial=:s";
    $quantities = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $data->serial)) ;    
  }
  
    $form['del_logistics'] = array(
      '#type' => 'item',
      '#markup' => t('Post data for ref. @p', array('@p' => $data->serial)),

    );   

    if( ($table == 'delivery' && $data->status == 2) 
            || ($table == 'receiving' && $data->status == 1) ) {     

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

$i=0;        
While ($q = $quantities->fetchObject())  {

        $name = ItemData::item_bycode($q->itemcode);
        
        $form['q'][$i]['quantity'] = array(
        '#type' => 'textfield',
        '#id' => 'quantity'.$i,
        '#size' => 8,
        '#maxlength' => 255,
        '#default_value' => $q->quantity,
        '#attributes' => array('placeholder'=>t('units'), 'class' => array('amount')),
        '#prefix' => '<div class="container-inline">',    
        );           
        
        $form['q'][$i]['item' ] = array(
          '#type' => 'item',
          '#markup' => $name,
          '#suffix' => '</div>',   
        );

        $form['q'][$i]['itemcode' ] = array(
          '#type' => 'hidden',
          '#value' => $q->itemcode,  
        ); 

$i++;

}
      $form['actions'] = array('#type' => 'actions');
       $form['actions']['record'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Post'),
      );     
    
    $form['#tree'] = TRUE;

    } else {

        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('Quantities cannot be posted for this order.'),

        );  

    }
    
    $form['#attached']['library'][] = 'ek_logistics/ek_logistics_css';

  return $form;    
  }

  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    $list = $form_state->getValue('q'); 
    foreach ( $list as $key => $data) {

      if( !is_numeric( $data['quantity'] ) ) {
            $form_state->setErrorByName("item", $this->t('Item @n quantity is wrong', array('@n'=> $data['itemcode'] )) );
      }

   
    }
  
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    
    $list = $form_state->getValue('q');
    
    if( $form_state->getValue('table') == 'delivery' 
            && \Drupal::currentUser()->hasPermission('post_delivery')) {
          
     foreach ( $list as $key => $data) { 
   
      $query = 'UPDATE {ek_item_packing} SET units = units - :u WHERE itemcode = :i';
      $a = array(':u' => $data['quantity'], ':i' => $data['itemcode'] );
      $data = Database::getConnection('external_db', 'external_db')
              ->query($query, $a );

     
      }
      
        //change  status
        Database::getConnection('external_db', 'external_db')->update('ek_logi_delivery')
        ->fields(array('status' => 3))
        ->condition('id', $form_state->getValue('for_id'))
        ->execute();
    
       \Drupal::messenger()->addStatus(t('Quantities delivered have been posted to stock balance.'));
       $form_state->setRedirect("ek_logistics_list_delivery" );  
   
    } 

    elseif($form_state->getValue('table') == 'receiving' 
            && \Drupal::currentUser()->hasPermission('post_receiving')) {

      $type = Database::getConnection('external_db', 'external_db')
              ->query('SELECT type FROM {ek_logi_receiving} WHERE id=:id', array(':id' => $form_state->getValue('for_id') ) )
              ->fetchField();
         
         foreach ( $list as $key => $data) { 
         
          $query = 'UPDATE {ek_item_packing} SET units = units + :u WHERE itemcode = :i';
          $a = array(':u' => $data['quantity'], ':i' => $data['itemcode'] );
          $data = Database::getConnection('external_db', 'external_db')->query($query, $a );

         
          }  
      
        //change  status
        Database::getConnection('external_db', 'external_db')->update('ek_logi_receiving')
        ->fields(array('status' => 2))
        ->condition('id', $form_state->getValue('for_id'))
        ->execute();      
      
        \Drupal::messenger()->addStatus(t('Quantities received have been posted to stock balance.'));
      
      if ($delete && $type == 'RR'){
          $form_state->setRedirect("ek_logistics_list_receiving" );  
      } else {
          $form_state->setRedirect("ek_logistics_list_returning" );  
      } 
   
   
   } else {
       \Drupal::messenger()->addWarning(t('You do not have enough privileges to post quantities. Please contact administrator.'));
           
   }
  
 }


}