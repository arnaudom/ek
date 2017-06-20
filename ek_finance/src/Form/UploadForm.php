<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\UploadForm
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\TemporaryStream;

/**
 * Provides a form to upload finance forms.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_upload';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $form['upload_doc'] = array(
            '#type' => 'file',
            '#title' => t('Select file'),
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#default_value' => $id,
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => t('Upload'),
            '#ajax' => array(
                'callback' => array($this, 'saveFile'),
                'wrapper' => 'doc_upload_message',
                'method' => 'replace',
            ),
        );


        $form['doc_upload_message'] = array(
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => '<div id="doc_upload_message" class="red" >',
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
     * Callback to save file
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {
        $ref = explode('-', $form_state->getValue('for_id'));

        switch ($ref[1]) {

            case 'expense' :

                // verify if current attachment exist
                $att = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT company, attachment from {ek_expenses} WHERE id=:id", array(':id' => $ref[0]))
                        ->fetchObject();

                //upload
                $extensions = 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
                $validators = array('file_validate_extensions' => array($extensions));
                $file = file_save_upload("upload_doc", $validators, NULL, 0, FILE_EXISTS_RENAME);
                
                if ($file) {
                    //add the expense id to the filename
                    //$file->filename = $ref[0] . '_' . $file->getFilename();
                    //$file->save();
                    //move it to a new folder
                    $dir = "private://finance/receipt/" . $att->company;
                    file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                    $filepath = file_unmanaged_copy($file->getFileUri(), $dir);
                    $receipt = 'yes';

                    if ($att->attachment) {
                        // delete current file
                        file_unmanaged_delete($att->attachment);
                    }

                    $fields = array(
                        'attachment' => $filepath,
                        'receipt' => $receipt
                    );
                    $insert = Database::getConnection('external_db', 'external_db')
                            ->update('ek_expenses')->fields($fields)
                            ->condition('id', $ref[0])
                            ->execute();
                }

                break;

            case 'statement' :
                
                // verify if current attachment exist
                $att = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT coid,uri from {ek_journal_reco_history} WHERE id=:id", array(':id' => $ref[0]))
                        ->fetchObject();
                
                $validators = array('file_validate_extensions' => array('png jpg jpeg pdf'));
                $file = file_save_upload("upload_doc", $validators, NULL, 0, FILE_EXISTS_RENAME);
                
                if ($file) {

                    $dir = "private://finance/bank/" . $att->coid;
                    file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                    $filepath = file_unmanaged_copy($file->getFileUri(), $dir);
                    

                    if ($att->uri) {
                        // delete current file
                        file_unmanaged_delete($att->uri);
                    }

                    $fields = array(
                        'uri' => $filepath,
                        
                    );
                    $insert = Database::getConnection('external_db', 'external_db')
                            ->update('ek_journal_reco_history')->fields($fields)
                            ->condition('id', $ref[0])
                            ->execute();
                }


                break;
        }


        if ($insert) {
            $form['doc_upload_message']['#markup'] = t('file uploaded @f', array('@f' => $file->getFilename()));
        } else {
            $form['doc_upload_message']['#markup'] = t('error copying file');
        }

        return $form['doc_upload_message'];
    }

}
