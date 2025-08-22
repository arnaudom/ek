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
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to upload finance forms.
 */
class UploadForm extends FormBase {

    protected $settings;

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

        if (null !== $this->settings->get('expenseAttachmentSize')) {
                $ext_size = $this->settings->get('expenseAttachmentSize') * 1000000;
            } else {
                $ext_size = '500000';
            }
        if (null !== $this->settings->get('expenseAttachmentFormat')) {
            $ext_format = $this->settings->get('expenseAttachmentFormat');
        } else {
            $ext_format = 'png jpg jpeg doc docx xls xlsx odt ods odp pdf rar rtf zip';
        }

        $form['for_id'] = [
            '#type' => 'hidden',
            '#default_value' => $id,
        ];
        $ref = explode('-', $id);
        if ($ref[1] == 'expense') {
            $form['upload_doc'] = [
                '#type' => 'file',
                '#title' => $this->t('Select file'),
                '#upload_validators'  => [
                    'FileExtension' => ['extensions' => $ext_format],
                    'FileSizeLimit' => ['fileLimit' => $ext_size]
                ],
            ];
            $form['redirect'] = [
                '#type' => 'hidden',
                '#default_value' => 'ek_finance.manage.list_expense',
            ];

        } else {
            // reconciliation report
             $form['upload_doc'] = [
                '#type' => 'file',
                '#title' => $this->t('Select file'),
                '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'jpg jpeg png pdf'],
                    'FileSizeLimit' => ['fileLimit' => $ext_size]
                ],
                '#description' => $this->t('Format: @f', ['@f' => 'jpg jpeg png pdf']),
             ];

             $form['redirect'] = [
                '#type' => 'hidden',
                '#default_value' => 'ek_finance.manage.reconciliation_reports',
            ];
        }

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['upload'] = [
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
            '#ajax' => [
                'callback' => [$this, 'saveFile'],
                'wrapper' => 'alert',
                'method' => 'replaceWith',
            ],
        ];

        $form['alert'] = [
            '#type' => 'item',
            '#prefix' => "<div id='alert' class='alert'>",
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
    public function submitForm(array &$form, FormStateInterface $form_state) {}
   

    public function saveFile(array &$form, FormStateInterface $form_state) {
        $ref = explode('-', $form_state->getValue('for_id'));
        $response = new AjaxResponse();
        if (!$form_state->get('upload_doc')) {
            if($errors = $form_state->getErrors()) {
                $e = '';
                foreach ($errors as $error) {
                    $e.= $error;
                }
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $e . "</div>"));
                $form_state->clearErrors();
            } else {
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" .  $this->t('Error') . "</div>"));
            }
            return $response;
        }

        switch ($ref[1]) {

            case 'expense':

                // verify if current attachment exist
                $att = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT company, attachment from {ek_expenses} WHERE id=:id", array(':id' => $ref[0]))
                        ->fetchObject();

                if ($file = $form_state->get('upload_doc')) {

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

                break;

            case 'statement':

                // verify if current attachment exist
                $att = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT coid,uri from {ek_journal_reco_history} WHERE id=:id", array(':id' => $ref[0]))
                        ->fetchObject();

                if ($file = $form_state->get('upload_doc')) {
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

                break;
        }


        if ($insert) {
            // \Drupal::messenger()->addStatus(t('file uploaded @f', array('@f' => $file->getFilename())));
            
                $clear = new InvokeCommand('.alert', "html", [""]);
                $response->addCommand($clear);
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . $this->t('File uploaded') . "</div>"));
                return $response;
        } else {
            // \Drupal::messenger()->addError(t('error copying file'));
        }

        //$form_state->setRedirect($form_state->getValue('redirect'));
    }

}
