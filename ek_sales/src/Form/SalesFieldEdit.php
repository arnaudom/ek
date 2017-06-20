<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SalesFieldEdit
 */

namespace Drupal\ek_sales\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\CloseDialogCommand;

/**
 * Provides a form to manage sales field.
 */
class SalesFieldEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_sales_edit_fileds';
  }



 /**
 * {@inheritdoc}
 * @param id : the table row id
 * @param field : the field to be updated
 * @param form design base on field type
 * can add other fields in the future if necessary
 */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $field = NULL) {
  
  
    
    $form['for_id'] = array(
          '#type' => 'hidden',
          '#default_value' =>$id,
    ); 

    $form['field'] = array(
          '#type' => 'hidden',
          '#default_value' =>$field,
    );
    
    switch($field) {

      case 'comment' :
      $query = "SELECT c.comment FROM {ek_address_book_comment} c "
              . "LEFT JOIN  {ek_address_book} a "
              . "ON a.id=c.abid WHERE id=:id";
      $data = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $id))->fetchField();
      
      $form['value'] = array (
      '#type' => 'textarea',
      '#title' => t('Comment'),
      '#default_value' => $data
      );
      break;  

      
        
    }

$form['actions'] = array('#type' => 'actions');
    $form['actions']['btn'] = array(
            '#id' => 'confirmbutton',
            '#type' => 'submit',
            '#value' =>  t('Save') ,
            '#attributes' => array('class' => array('use-ajax-submit')),
      );

$form['#attached']['library'][] = 'ek_sales/ek_sales_css';

    if($form_state->get('message') <> '' ) {
    $form['message'] = array(
      '#markup' => "<div class='red'>" . t('Data') . ": " . $form_state->get('message') . "</div>",
    ); 
   
    $form_state->set('message', '');
    $form_state->setRebuild(); 
    
    $response = new AjaxResponse();
    $html = nl2br($data);
    $response->addCommand(new HtmlCommand('.comment_text' , $html ));
    $response->addCommand(new CloseDialogCommand());
    return $response; 
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
  

    switch( $form_state->getValue('field') ) {
        
      
      case 'comment' :

        $text = Xss::filter($form_state->getValue('value')) . ' [' .  \Drupal::currentUser()->getUsername() . '] - ' . date('Y-m-d');
	$fields = array(
            $form_state->getValue('field') => $text
	);
        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_address_book_comment')->fields($fields)
                ->condition('abid', $form_state->getValue('for_id'))->execute();
        $value = '';
      break;

     
    }  
   
   if($update) {
    
    $form_state->set('message', t('saved') );
    $form_state->setRebuild();
 

   } else {
   $form_state->set('message', t('error') );
   $form_state->setRebuild();
   }

  }


}
