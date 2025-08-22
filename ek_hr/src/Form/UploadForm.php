<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\UploadForm.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;


/**
 * Provides a form to upload file.
 */
class UploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
    public function getFormId() {
        return 'ek_hr_upload';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)  {
        $form['up'] = [
            '#type' => 'details',
            '#title' => $this->t('Upload file'),
            '#collapsible' => true,
            '#open' => true,
        ];
    
        $form['up']['for_id'] = [
          '#type' => 'hidden',
          '#default_value' =>$id,
        ];
    
        $form['up']['upload_doc'] = [
          '#type' => 'managed_file',
          '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip'],
                ],
        ];
       
        $form['up']['upload'] = [
            '#type' => 'submit',
            '#value' =>  $this->t('Upload') ,
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

          $dir = "private://hr/documents/" . $form_state->getValue('for_id')  ;
           \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          $file_ids = $form_state->getValue('upload_doc');
          if (!empty($file_ids)) {
              $file_id = reset($file_ids);
              $file = File::load($file_id);
              if ($file) {
                  $uri = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);

                   if ($uri) {
                    
                      $fields = [
                        'employee_id' => $form_state->getValue('for_id'),
                        'fid' => 1,
                        'type' => 0,
                        'filename' => $file->getFilename(),
                        'uri' => $uri,
                        'filemime' => $file->getMimeType(),
                        'comment' => '',
                        'date' => time(),
                        'size' => filesize($uri),
                      ];

                      Database::getConnection('external_db', 'external_db')
                          ->insert('ek_hr_documents')->fields($fields)->execute();
                  }
              }
          }
    }
}
