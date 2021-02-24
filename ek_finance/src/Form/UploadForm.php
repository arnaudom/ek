<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\UploadForm
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\TemporaryStream;
use Drupal\ek_finance\FinanceSettings;

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

    public function __construct() {
        $this->settings = new FinanceSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $form['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#default_value' => $id,
        );
        $ref = explode('-', $id);
        if ($ref[1] == 'expense' && $this->settings->get('expenseAttachmentSize')) {
            $form['info1'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Max. file size') . ": " . $this->settings->get('expenseAttachmentSize') . 'Mb',
            );
            $form['info2'] = array(
                '#type' => 'item',
                '#markup' => $this->t('File type') . ": " . $this->settings->get('expenseAttachmentFormat'),
            );
        } else {
            $form['info2'] = array(
                '#type' => 'item',
                '#markup' => $this->t('File type') . ": " . 'png jpg jpeg pdf',
            );
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
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
        $ref = explode('-', $form_state->getValue('for_id'));

        switch ($ref[1]) {

            case 'expense':

                if (null !== $this->settings->get('expenseAttachmentFormat')) {
                    $extensions = $this->settings->get('expenseAttachmentFormat');
                } else {
                    $extensions = 'png jpg jpeg doc docx xls xlsx odt ods odp pdf rar rtf zip';
                }
                if (null !== $this->settings->get('expenseAttachmentSize')) {
                    $ext_size = $this->settings->get('expenseAttachmentSize') * 1000000;
                } else {
                    $ext_size = '500000';
                }
                // verify if current attachment exist
                $att = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT company, attachment from {ek_expenses} WHERE id=:id", array(':id' => $ref[0]))
                        ->fetchObject();

                //upload

                $validators = array('file_validate_extensions' => [$extensions], 'file_validate_size' => [$ext_size]);
                $file = file_save_upload("upload_doc", $validators, null, 0, 'FILE_EXISTS_RENAME');

                if ($file) {
                    //add the expense id to the filename
                    //$file->filename = $ref[0] . '_' . $file->getFilename();
                    //$file->save();
                    //move it to a new folder

                    $dir = "private://finance/receipt/" . $att->company;
                    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                    $filepath = \Drupal::service('file_system')->copy($file->getFileUri(), $dir . "/" . $ref[0] . '_' . $file->getFilename());
                    $receipt = 'yes';

                    if ($att->attachment) {
                        // delete current file
                        \Drupal::service('file_system')->delete($att->attachment);
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

                $redirect = 'ek_finance.manage.list_expense';

                break;

            case 'statement':

                // verify if current attachment exist
                $att = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT coid,uri from {ek_journal_reco_history} WHERE id=:id", array(':id' => $ref[0]))
                        ->fetchObject();

                $validators = array('file_validate_extensions' => array('png jpg jpeg pdf'));
                $file = file_save_upload("upload_doc", $validators, null, 0, 'FILE_EXISTS_RENAME');

                if ($file) {
                    $dir = "private://finance/bank/" . $att->coid;
                    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                    $filepath = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);


                    if ($att->uri) {
                        // delete current file
                        \Drupal::service('file_system')->delete($att->uri);
                    }

                    $fields = array(
                        'uri' => $filepath,
                    );
                    $insert = Database::getConnection('external_db', 'external_db')
                            ->update('ek_journal_reco_history')->fields($fields)
                            ->condition('id', $ref[0])
                            ->execute();
                }

                $redirect = 'ek_finance.manage.reconciliation_reports';
                break;
        }


        if ($insert) {
            \Drupal::messenger()->addStatus(t('file uploaded @f', array('@f' => $file->getFilename())));
        } else {
            \Drupal::messenger()->addError(t('error copying file'));
        }

        $form_state->setRedirect($redirect);
    }

}
