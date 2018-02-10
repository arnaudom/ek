<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\DeleteMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to delete finance memo
 */
class DeleteMemo extends FormBase {

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
    return 'ek_finance_delete_memo';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
  
  $query = "SELECT status,serial,category,entity,auth from {ek_expenses_memo} where id=:id";
  $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();

  
    $form['edit_memo'] = array(
      '#type' => 'item',
      '#markup' => t('Memo ref. @p', array('@p' => $data->serial)),

    );   
    
    $url = Url::fromRoute('ek_finance_manage_list_memo_'. $data->category, array(), array())->toString();
    $form['back'] = array(
      '#type' => 'item',
      '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

    );
    
    if($data->status == 0 ) {  
       
    //check authorizations
    // can be deleted by user with admin privilege or owner or user with access
    if(\Drupal::currentUser()->hasPermission('admin_memos')) {
     $delete = TRUE;
    } else {
        if($data->category < 5) {
          
          $access = AccessCheck::CompanyListByUid();
          $delete = in_array($data->entity, $access ) ? TRUE : FALSE;
        
        } else {
          $delete = (\Drupal::currentUser()->id() == $data->entity) ? TRUE : FALSE;        
        }
    }

      if($delete) {

        $form['for_id'] = array(
          '#type' => 'hidden',
          '#value' => $id,

        );

        $form['serial'] = array(
          '#type' => 'hidden',
          '#value' => $data->serial, 
        );  

        $form['category'] = array(
          '#type' => 'hidden',
          '#value' => $data->category, 
        ); 
                 
        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('Are you sure you want to delete this memo ?'),

        );   
      
         $form['actions']['record'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Delete'),
        );     
      } else {
        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('You are not authorized to delete this memo.'),
        );       
      }
    
    } else {

        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('This memo cannot be deleted because it has been fully or partially paid'),
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
          ->delete('ek_expenses_memo')
          ->condition('id', $form_state->getValue('for_id'))
          ->execute();
  $delete2 = Database::getConnection('external_db', 'external_db')
          ->delete('ek_expenses_memo_list')
          ->condition('serial', $form_state->getValue('serial'))
          ->execute();

  $docs = Database::getConnection('external_db', 'external_db')->query("SELECT id,uri from {ek_expenses_memo_documents} where serial=:s", array(':s' => $form_state->getValue('serial') ) );
  
  while ($d = $docs->fetchObject() ) {
    unset($d->uri);
    Database::getConnection('external_db', 'external_db')->delete('ek_expenses_memo_documents')
      ->condition( 'id', $d->id )
      ->execute();
  } 
      

    if ($delete){
      if ($form_state->getValue('category') < 5) {
              $form_state->setRedirect('ek_finance_manage_list_memo_internal' ) ;
            } else {
              $form_state->setRedirect('ek_finance_manage_list_memo_personal' ) ;
            }  
    }
  
  }


}