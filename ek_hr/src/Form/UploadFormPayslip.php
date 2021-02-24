<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\PayslipsForm.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to upload files.
 */
class UploadFormPayslip extends FormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'ek_hr_upload_payslip';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['up'] = array(
      '#type' => 'details',
      '#title' => $this->t('Upload file'),
      '#collapsible' => true,
      '#open' => true,
  
  );
     
    
        $form['up']['upload_doc'] = array(
      '#type' => 'file',
      '#prefix' => "<div class='container-inline'>",
      '#required' => true,
    );
    
        $form['up']['upload'] = array(
            '#id' => 'sharebuttonid',
            '#type' => 'button',
            '#value' =>  $this->t('Upload') ,
            '#ajax' => array(
              'callback' => array($this, 'submitForm'),
              'wrapper' => 'hr_table_payslip',
              'effect' => 'fade',
              'method' => 'append'
             ),
            '#suffix' => '</div>',
      );

        $form['up']['info'] = array(
    '#markup' => $this->t("use file format name 'type_format_name.inc'. Ex. payslip_pdf_abc.inc"),
    
    );
        return $form;
         
  
        //buildForm
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $extensions = 'inc';
        $validators = array( 'file_validate_extensions' => array($extensions));
        $file = file_save_upload("upload_doc", $validators, false, 0);
          
        if ($file) {
            $dir = "private://hr/payslips" ;
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $filename = str_replace(' ', '_', $file->getFileName());
            $doc = $dir . '/' .  $filename;
            \Drupal::service('file_system')->copy($file->getFileUri(), $doc, 'FILE_EXISTS_REPLACE');
   
            $vid = str_replace('.', '___', $filename);
            $link = "<a href='#' class='deleteButton red'  id='". $vid ."' >[x]</a>" ;

            $response = new AjaxResponse();
            $insert = "<tr class='' id='r-". $vid  ."'>
                   <td class='priority-medium'>" . $file->getFileName() . "</td>
                   <td class='priority-medium' title=''>" . date('Y-m-d') . "</td>
                   <td >" . $link . "</td>
                 </tr>";
            return $response->addCommand(new InsertCommand('tbody', $insert));
        }
    }
}
