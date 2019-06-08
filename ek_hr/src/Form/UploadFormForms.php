<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\FormsForm.
 */

namespace Drupal\ek_hr\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a form to upload files.
 */
class UploadFormForms extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_hr_upload_forms';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


  $form['up'] = array(
      '#type' => 'details', 
      '#title' => t('Upload file'), 
      '#collapsible' => TRUE, 
      '#open' => TRUE,
  
  );    
     
    
    $form['up']['upload_doc'] = array(
      '#type' => 'file',
      '#prefix' => "<div class='container-inline'>",
      '#required' => TRUE,
    );
    
    $form['up']['upload'] = array(
            '#id' => 'sharebuttonid',
            '#type' => 'button',
            '#value' =>  t('Upload') ,
            '#ajax' => array(
              'callback' => array($this, 'submitForm'), 
              'wrapper' => 'hr_table_payslip',
              'effect' => 'fade',
              'method' => 'append'
             ),
            '#suffix' => '</div>',
      );

    $form['up']['info'] = array(
    '#markup' => t("use file format name 'type_format_name.inc'. Ex. form_xls_abc.inc or image file for logo"),
    
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

      $extensions = 'inc jpg jpeg png';
      $validators = array( 'file_validate_extensions' => array($extensions));
      $file = file_save_upload("upload_doc" , $validators, FALSE, 0);
          
      if ($file) {
          
        $dir = "private://hr/forms" ;
        \Drupal::service('file_system')->prepareDirectory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        $filename = str_replace(' ', '_', $file->getFileName());
        $doc = $dir . '/' .  $filename ;
        \Drupal::service('file_system')->copy($file->getFileUri(), $doc, FILE_EXISTS_REPLACE);

        $vid = str_replace('.', '___', $filename);
        $link = "<a href='#' class='deleteButton red'  id='".$vid."' >[x]</a>" ;

        $response = new AjaxResponse();
        $insert = "<tr class='' id='r-". $vid  ."'>
               <td class='priority-medium'>" . $file->getFileName() . "</td>
               <td class='priority-medium' title=''>" . date('Y-m-d') . "</td>
               <td >" . $link . "</td>
             </tr>";   
        return $response->addCommand(new InsertCommand('tbody', $insert));

  
  
  
  }
  
  
  }



//end class
}
