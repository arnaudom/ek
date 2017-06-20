<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\DeleteItem.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to delete item.
 */
class DeleteItem extends FormBase {

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
    return 'ek_products_delete_item';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        $form['back'] = array(
          '#type' => 'item',
          '#markup' => t('<a href="@url" >Items list</a>', array('@url' => Url::fromRoute('ek_products.list', array(), array())->toString() ) ) ,

        );  


  $query = "SELECT coid,i.itemcode,description1, active,units from {ek_items} i INNER JOIN {ek_item_packing} p on i.itemcode=p.itemcode where i.id=:id";
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':id' => $id))
          ->fetchObject();

    $access = AccessCheck::GetCompanyByUser();
    $coid = implode(',',$access);
  
    $form['edit_item'] = array(
      '#type' => 'item',
      '#markup' => t('Item ref. @p', array('@p' => $data->itemcode)),

    );   
    $form['edit_item2'] = array(
      '#type' => 'item',
      '#markup' => t('Item ref. @p', array('@p' => $data->description1)),

    );
    
    $del = 1;
    if(!in_array($data->coid, $access)) {
      $del = 0;
      $message = t('You are not authorized to delete this item.');
    } elseif($data->units <> 0) {
      $del = 0;
      $message = t('The stock value for this item is not null: @u units. It cannot be deleted.', array('@u' => $data->units ));
    
    } elseif ($this->moduleHandler->moduleExists('ek_sales') || $this->moduleHandler->moduleExists('ek_logistics')) {
      
        if($this->moduleHandler->moduleExists('ek_sales')) {
          $query = 'SELECT count(id) from {ek_invoice_details} WHERE itemdetail=:id';
          $invoice = Database::getConnection('external_db', 'external_db')
                  ->query($query, array(':id' => $id))
                  ->fetchField();
          if($invoice > 0) {
            $del = 0;
            $message = t('this item is used in Invoices module. It cannot be deleted') . '<br>';
          }
          $query = 'SELECT count(id) from {ek_purchase_details} WHERE itemdetail=:id';
          $purchase = Database::getConnection('external_db', 'external_db')
                  ->query($query, array(':id' => $id))
                  ->fetchField();        
          if($purchase > 0) {
            $del = 0;
            $message .= t('this item is used in Purchases module. It cannot be deleted') . '<br>';
          }
          $query = 'SELECT count(id) from {ek_quotation_details} WHERE itemdetails=:itemcode';
          $quotation = Database::getConnection('external_db', 'external_db')
                  ->query($query, array(':itemcode' => $data->itemcode))
                  ->fetchField();
          if($quotation > 0) {
            $del = 0;
            $message .= t('this item is used in Quotations module. It cannot be deleted') . '<br>';
          }        
        }
        //@todo logistics
    
    } 
    
    
    if($del != 0 ) {     

        $form['for_id'] = array(
          '#type' => 'hidden',
          '#value' => $id,

        );

        $form['itemcode'] = array(
          '#type' => 'hidden',
          '#value' => $data->itemcode, 
        );  
          
        $form['alert'] = array(
          '#type' => 'item',
          '#markup' => t('Are you sure you want to delete this item ?'),

        );   
      
           $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Delete'),

          );     
    } else {

        $back =  Url::fromRoute('ek_products.list', array(), array())->toString();
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
            ->delete('ek_items')
            ->condition('id', $form_state->getValue('for_id'))
            ->execute();

    Database::getConnection('external_db', 'external_db')
            ->delete('ek_item_barcodes')
            ->condition('itemcode', $form_state->getValue('itemcode'))
            ->execute();
    Database::getConnection('external_db', 'external_db')
            ->delete('ek_item_packing')
            ->condition('itemcode', $form_state->getValue('itemcode'))
            ->execute();
    Database::getConnection('external_db', 'external_db')
            ->delete('ek_item_prices')
            ->condition('itemcode', $form_state->getValue('itemcode'))
            ->execute();
    Database::getConnection('external_db', 'external_db')
            ->delete('ek_item_price_history')
            ->condition('itemcode', $form_state->getValue('itemcode'))
            ->execute();

     $query = "SELECT id,uri FROM {ek_item_images} WHERE itemcode=:i";
     $data = Database::getConnection('external_db', 'external_db')
             ->query($query, array(':i' => $form_state->getValue('itemcode')));

     WHILE ($d = $data->fetchObject()) {

      file_unmanaged_delete($d->uri);
      Database::getConnection('external_db', 'external_db')
              ->delete('ek_item_images')
              ->condition('id', $d->id)
              ->execute();

     }

    if ($delete){
    drupal_set_message(t('The item has been deleted'), 'status');
         $form_state->setRedirect("ek_products.list" );  
    }
  
  }


}