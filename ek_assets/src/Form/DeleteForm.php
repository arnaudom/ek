<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\DeleteForm.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to reecord and edit purchase email alerts.
 */
class DeleteForm extends FormBase {

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
    return 'ek_assets_delete_item';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

    $url = Url::fromRoute('ek_assets.list', array(), array())->toString();
    $form['back'] = array(
      '#type' => 'item',
      '#markup' => t('<a href="@url" >Assets list</a>', array('@url' => $url ) ) ,

    );  


  $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid "
                . "WHERE id=:id";
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':id' => $id))
          ->fetchObject();

    $access = AccessCheck::GetCompanyByUser();
    $coid = implode(',',$access);
  
    $form['edit_item'] = array(
      '#type' => 'item',
      '#markup' => t('Asset : @p', array('@p' => $data->asset_name)),

    );   

    
    $del = 1;
    //if(!in_array(\Drupal::currentUser()->id(), $access)) {
    if(!in_array($data->coid, $access)) {
      $del = 0;
      $message = t('You are not authorized to delete this item.');
    } elseif($data->amort_record != '') {
      $del = 0;
      $message = t('This asset is not amortized. It cannot be deleted.');

    } 
    
    
    if($del != 0 ) {     

        $form['for_id'] = array(
          '#type' => 'hidden',
          '#value' => $id,
        ); 

        $form['asset_pic'] = array(
          '#type' => 'hidden',
          '#value' => $data->asset_pic,
        ); 

        $form['asset_doc'] = array(
          '#type' => 'hidden',
          '#value' => $data->asset_doc,
        ); 
                
        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('Are you sure you want to delete this asset ?'),

        );   
      
           $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Delete'),

          );     
    } else {

        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => $message ,

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
  
  
  $delete = Database::getConnection('external_db', 'external_db')
          ->delete('ek_assets')
          ->condition('id', $form_state->getValue('for_id'))
          ->execute();
  
  if($form_state->getValue('asset_pic') != '') {
    $uri = 'private://assets/' . $form_state->getValue('asset_pic');
    file_unmanaged_delete($uri);
    drupal_set_message(t('The asset image is deleted'), 'status');
  }
  if($form_state->getValue('asset_doc') != '') {
    $uri = 'private://assets/' . $form_state->getValue('asset_doc');
    file_unmanaged_delete($uri);
    drupal_set_message(t('The asset attachment is deleted'), 'status');
  }  

    $delete2 = Database::getConnection('external_db', 'external_db')
          ->delete('ek_assets_amortization')
          ->condition('asid', $form_state->getValue('for_id'))
          ->execute();
    
    if ($delete){
    drupal_set_message(t('The asset data have been deleted'), 'status');
         $form_state->setRedirect("ek_assets.list" );  
    }
  
  }


}