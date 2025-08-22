<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\UploadForm
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a form.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_admin_documents_upload';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {


        $form['coid'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];

        // file is not managed by Drupal
        $allowed = 'png gif jpg jpeg txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $upload_doc = ['FileExtension' => ['extensions' => $allowed]];
        $form['upload_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Select file'),           
            '#upload_validators' => $upload_doc,
            '#prefix' => '<div class="container-inline">',
        ];

        $form['comment'] = [
            '#type' => 'textfield',
            '#size' => 20,
            '#attributes' => ['placeholder' => $this->t('comment')],
        ];


        $form['actions'] = ['#type' => 'actions'];
        $form['upload'] = [
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
            $form_state->set($field, $file) ;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {


        if ($form_state->get('upload_doc')) {
            if ($file = $form_state->get('upload_doc')) {
                $dir = "private://admin/company" . $form_state->getValue('coid') . "/documents";
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $doc = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
                
            }

            $filename = $file->getFileName();

            $fields = [
                'coid' => $form_state->getValue('coid'),
                'fid' => 1,
                'filename' => $filename,
                'uri' => $doc,
                'comment' => Xss::filter($form_state->getValue('comment')),
                'date' => time(),
                'size' => filesize($doc),
                'share' => 0,
                'deny' => 0,
            ];

            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_company_documents')
                    ->fields($fields)
                    ->execute();

            $log = 'user ' . \Drupal::currentUser()->id() . '|' . \Drupal::currentUser()->getAccountName() . '|upload|' . $filename;
            \Drupal::logger('ek_company_documents')->notice($log);
            $form['message']['#markup'] = "<div class='green'>" . $this->t('file uploaded @f', array('@f' => $filename)) . "</div>";
        } else {
            if($form_state->getErrors()) {
                // Collect and display validation errors.
                $e = [];
                foreach ($form_state->getErrors() as $error) {
                    $e.= $error;
                }
               $form['actions']['message']['#markup'] = $e;
                
            } else {
                $form['actions']['message']['#markup'] = "<div class='red'>" . $this->t('Error uploading file'). "</div>";
            }
            
        }

        return $form['actions']['message'];
    }

}
