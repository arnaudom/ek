<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\FormsForm.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileExists;

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

      $form['up'] = [
        '#type' => 'details',
        '#title' => $this->t('Upload file'),
        '#collapsible' => true,
        '#open' => true,
      ];
    
      $form['up']['upload_doc'] = [
        '#type' => 'managed_file',
        '#required' => true,
        '#upload_validators'  => [
          'FileExtension' => ['extensions' => 'inc jpeg jpg png ico'],
        ],
      ];
    
      $form['up']['upload'] = [
            '#type' => 'submit',
            '#value' =>  $this->t('Upload') ,
        ];

      $form['up']['info'] = array(
        '#markup' => $this->t("use file format name 'type_format_name.inc'. Ex. form_xls_abc.inc or image file for logo"),
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
       
        $file_ids = $form_state->getValue('upload_doc');
          if (!empty($file_ids)) {
            $file_id = reset($file_ids);
            $file = File::load($file_id);
            if ($file) {
                $dir = "private://hr/forms" ;
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $filename = str_replace(' ', '_', $file->getFileName());
                $doc = $dir . '/' .  $filename ;
                \Drupal::service('file_system')->copy($file->getFileUri(), $doc, FileExists::Replace);
                \Drupal::messenger()->addStatus(t("File uploaded"));
            }
          }
    }
}
