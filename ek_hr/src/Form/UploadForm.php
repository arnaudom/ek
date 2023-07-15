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
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a form to upload file.
 */
class UploadForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'ek_hr_upload';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
        $form['up'] = array(
      '#type' => 'details',
      '#title' => $this->t('Upload file'),
      '#collapsible' => true,
      '#open' => true,
  
  );
    
        $form['up']['for_id'] = array(
          '#type' => 'hidden',
          '#default_value' =>$id,
          
        );
    
        $form['up']['upload_doc'] = array(
      '#type' => 'file',
      '#prefix' => "<div class='container-inline'>",
      
    );
        /*
            $form['up']['upload'] = array(
                    '#id' => 'sharebuttonid',
                    '#type' => 'button',
                    '#value' =>  $this->t('Upload') ,
                    '#ajax' => array(
                      'callback' => array($this, 'submitForm'),
                      'wrapper' => 'hr_table',
                      'effect' => 'fade',
                      'method' => 'append'
                     ),
                    '#suffix' => '</div>',
              );
        */
        $form['up']['upload'] = array(
            '#type' => 'submit',
            '#value' =>  $this->t('Upload') ,
            '#suffix' => '</div>',
      );
        return $form;
         
  
        //buildForm
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $extensions = 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $validators = array( 'file_validate_extensions' => array($extensions));
        $file = file_save_upload("upload_doc", $validators, false, 0);
      
        if ($file) {
            $form_state->set('new_upload', $file);
        }
    }
 
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('new_upload')) {
            $dir = "private://hr/documents/" . $form_state->getValue('for_id')  ;
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $move = file_move($form_state->get('new_upload'), $dir);
          
            if ($move) {
                $move->setPermanent();
                $move->save();
                $uri = $move->getFileUri();
                $filename  = $move->getFileName();
              
                $fields = array(
                'employee_id' => $form_state->getValue('for_id'),
                'fid' => 1,
                'type' => 0,
                'filename' => $filename,
                'uri' => $uri,
                'filemime' => $move->getMimeType(),
                'comment' => '',
                'date' => time(),
                'size' => filesize($uri),
              );
                           
                $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_hr_documents')->fields($fields)->execute();
            }
        }
    }
}
