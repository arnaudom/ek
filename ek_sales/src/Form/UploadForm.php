<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\UploadForm
 */

namespace Drupal\ek_sales\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a form to upload file.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_documents_upload';
    }

    /**
     * {@inheritdoc}
     * @param abid : address book id
     */
    public function buildForm(array $form, FormStateInterface $form_state, $abid = null) {
        
        $allowed = 'png gif jpg jpeg txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $upload_doc = ['FileExtension' => ['extensions' => $allowed]];
        $form['upload_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Select file'),           
            '#upload_validators' => $upload_doc,
            '#prefix' => '<div class="container-inline">',
        ];

        $form['abid'] = [
            '#type' => 'hidden',
            '#value' => $abid,
        ];

        $form['folder'] = [
            '#type' => 'textfield',
            '#size' => 20,
            '#attributes' => ['placeholder' => $this->t('folder')],
            '#autocomplete_route_name' => 'ek_sales_folders',
            '#autocomplete_route_parameters' => ['abid' => $abid],
        ];

        $form['comment'] = [
            '#type' => 'textfield',
            '#size' => 20,
            '#attributes' => array('placeholder' => $this->t('comment')),
        ];


        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['upload'] = [
            '#id' => 'upbuttonid1',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
            '#ajax' => [
                'callback' => [$this, 'saveFile'],
                'wrapper' => 'message',
                'method' => 'replaceWith',
            ],
            '#suffix' => '</div>',
        ];

        $form['actions']['message'] = [
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => '<div id="message" class="" >',
            '#suffix' => '</div>',
        ];


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $field = "upload_doc";
        $file = _file_save_upload_from_form($form[$field], $form_state, 0);
        if($file) {
            if($errors = $form_state->getErrors()) {
                $e = '';
                foreach ($errors as $error) {
                    $e .= $error;
                }
                $form['message']['#markup'] = '<div class="red">' . $e . '</div>';
                return $form['message'];
                $file->delete();
            } else {
                $form_state->set($field, $file) ;
            }       
        }   
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * Callback
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {
       
        if ($file = $form_state->get('upload_doc')) {
            $dir = "private://sales/documents/" . $form_state->getValue('abid');
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $uri = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
            
            $fields = [
                'abid' => $form_state->getValue('abid'),
                'filename' => $file->getFileName(),
                'uri' => $uri,
                'comment' => Xss::filter($form_state->getValue('comment')),
                'date' => time(),
                'size' => filesize($uri),
                'share' => 0,
                'deny' => 0,
                'folder' => Xss::filter($form_state->getValue('folder')),
            ];

            $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_sales_documents')
                        ->fields($fields)->execute();
                        
            $log = 'user ' . \Drupal::currentUser()->id() . '|' . \Drupal::currentUser()->getAccountName() . '|upload|' . $filename;
            \Drupal::logger('ek_sales')->notice($log);
            $form['message']['#markup'] = '<div class="green">' . $this->t('file uploaded @f', array('@f' => $file->getFileName())). '</div>';
                        
        } else {
            $form['message']['#markup'] = '<div class="red">' . $this->t('error uploading file') . '</div>';
        }

        return $form['message'];

    }

}
