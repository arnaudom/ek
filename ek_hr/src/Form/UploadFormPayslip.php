<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\PayslipsForm.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileExists;

/**
 * Provides a form to upload files.
 */
class UploadFormPayslip extends FormBase {

  /**
   * {@inheritdoc}
   */
    public function getFormId() {
        return 'ek_hr_upload_payslip';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)  {
        $form['up'] = [
          '#type' => 'details',
          '#title' => $this->t('Upload file'),
          '#collapsible' => true,
          '#attributes' => ['classes' => ['container-inline']],
          '#open' => true,
        ];
     
    
        $form['up']['upload_doc'] = [
          '#type' => 'managed_file',
          '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'inc'],
                ],
        ];
       
        $form['up']['upload'] = [
            '#type' => 'submit',
            '#value' =>  $this->t('Upload') ,
        ];

        $form['up']['info'] = [
          '#type' => 'markup',
          '#markup' => '<p>' . $this->t("use file format name 'type_format_name.inc'. Ex. payslip_pdf_abc.inc") . '</p>',
        ];
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
        
          
         $file_ids = $form_state->getValue('upload_doc');
          if (!empty($file_ids)) {
            $file_id = reset($file_ids);
              $file = File::load($file_id);
              if ($file) {
                  $dir = "private://hr/payslips" ;
                  \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                  $filename = str_replace(' ', '_', $file->getFileName());
                  $path = $dir . '/' .  $filename;
                  \Drupal::service('file_system')->copy($file->getFileUri(), $path, FileExists::Replace);
                  \Drupal::messenger()->addStatus(t("File uploaded"));

              }
        }
    }
}
