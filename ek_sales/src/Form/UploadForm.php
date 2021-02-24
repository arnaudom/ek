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
        $form['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#prefix' => '<div class="container-inline">',
        );

        $form['abid'] = array(
            '#type' => 'hidden',
            '#value' => $abid,
        );

        $form['folder'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#attributes' => array('placeholder' => $this->t('folder')),
            '#autocomplete_route_name' => 'ek_sales_folders',
            '#autocomplete_route_parameters' => array('abid' => $abid),
        );

        $form['comment'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#attributes' => array('placeholder' => $this->t('comment')),
        );


        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'upbuttonid1',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
            '#ajax' => array(
                'callback' => array($this, 'saveFile'),
                'wrapper' => 'message',
                'mehtod' => 'replace'
            ),
            '#suffix' => '</div>',
        );

        $form['actions']['message'] = array(
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => '<div id="message" class="red" >',
            '#suffix' => '</div>',
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
        
    }

    /**
     * Callback
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {


        //upload

        $extensions = 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $validators = array('file_validate_extensions' => array($extensions));
        $dir = "private://sales/documents/" . $form_state->getValue('abid');
        \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file = file_save_upload("upload_doc", $validators, $dir, 0, 'FILE_EXISTS_RENAME');

        if ($file) {
            $file->setPermanent();
            $file->save();
            $uri = $file->getFileUri();
            $filename = $file->getFileName();

            $fields = array(
                'abid' => $form_state->getValue('abid'),
                'filename' => $filename,
                'uri' => $uri,
                'comment' => Xss::filter($form_state->getValue('comment')),
                'date' => time(),
                'size' => filesize($uri),
                'share' => 0,
                'deny' => 0,
                'folder' => Xss::filter($form_state->getValue('folder')),
            );

            $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_sales_documents')
                            ->fields($fields)->execute();


            $log = 'user ' . \Drupal::currentUser()->id() . '|' . \Drupal::currentUser()->getAccountName() . '|upload|' . $filename;
            \Drupal::logger('ek_sales')->notice($log);
            $form['message']['#markup'] = $this->t('file uploaded @f', array('@f' => $filename));
        } else {
            $form['message']['#markup'] = $this->t('error uploading file');
        }

        return $form['message'];
    }

}
