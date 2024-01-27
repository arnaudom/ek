<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Form\UploadForm
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\AttachCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_documents\Settings;

/**
 * Provides a form to upload document.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_documents_upload';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $here = $this->getRouteMatch();
        if ($here->getRouteName() == 'ek_documents_documents_common') {
            $form['common'] = [
                '#type' => 'hidden',
                '#value' => 1,
            ];

            $alert = "<div id='alert' class='messages messages--warning'>"
                    .$this->t('File will be accessible in common area.') . "</div>";
            $form['alert'] =[
                '#type' => 'item',
                '#markup' => $alert,
            ];
        }

        $form['upload_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Select file'),
        ];

        $form['folder'] = [
            '#type' => 'textfield',
            '#size' => 20,
            '#attributes' => ['placeholder' => $this->t('tag or folder')],
            '#autocomplete_route_name' => 'ek_look_up_folders',
        ];


        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['upload'] = [
            '#id' => 'upbuttonid1',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
            '#ajax' => [
                'callback' => [$this, 'saveFile'],
                'wrapper' => 'doc_upload_message',
                'method' => 'replace',
            ],
        ];

        $form['alert'] = [
            '#type' => 'item',
            '#prefix' => "<div class='alert'>",
            '#suffix' => '</div>',
        ];


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {}

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * Save file callback
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {
        
        if (!null == ($form_state->getValue('common'))) {
            $user = 0;
        } else {
            $user = \Drupal::currentUser()->id();
        }

        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);
        $settings = new Settings();
        // upload
        // $extensions = 'csv png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $extensions = $settings->get('file_extensions');
        $validators = ['file_validate_extensions' => [$extensions]];
        $dir = "private://documents/users/" . $user;
        \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file = file_save_upload("upload_doc", $validators, $dir, 0, FileSystemInterface::EXISTS_RENAME);

        if ($file) {

            if ($settings->get('filter_char') == '1' && preg_match('/[^\00-\255]+/u', $file->getFileName())) {
                // filter file name for special characters
                $form_state->setValue('upload_doc', NULL);
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('Error: check file name.') . "</div>"));
                return $response;
            } else {
                $file->setPermanent();
                $file->save();
                $filename = $file->getFileName();
                $uri = $file->getFileUri();
                $fields = array(
                    'uid' => $user,
                    //'fid' => '',
                    'type' => 0,
                    'filename' => $filename,
                    'uri' => $uri,
                    'folder' => Xss::filter($form_state->getValue('folder')),
                    'comment' => '',
                    'date' => time(),
                    'size' => filesize($uri),
                    'share' => 0,
                    'share_uid' => 0,
                    'share_gid' => 0,
                    'expire' => 0
                );

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_documents')
                        ->fields($fields)
                        ->execute();

                $log = 'user ' . \Drupal::currentUser()->id() . '|' . \Drupal::currentUser()->getAccountName() . '| upload |' . $filename;
                \Drupal::logger('ek_documents')->notice($log);
                $form['doc_upload_message']['#markup'] = $this->t('file uploaded @f', array('@f' => $filename));
                if ($user == 0) {
                    \Drupal\Core\Cache\Cache::invalidateTags(['common_documents']);
                } else {
                    \Drupal\Core\Cache\Cache::invalidateTags(['my_documents']);
                }
                if(!null == $form_state->getValue('folder')) {
                    $filename = "<a href='#".$form_state->getValue('folder')."'>" . $filename . "</a>";
                    $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . 
                    "<a href='#".$form_state->getValue('folder')."'>" . $filename . "</a> " .
                    $this->t('uploaded') . "</div>"));
                } else {
                    $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . $this->t('@f uploaded',['@f' => $filename]) . "</div>"));
                }
            }
        } else {
            $form_state->setValue('upload_doc', NULL);
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('Error: check file extension.') . "</div>"));
        }

        return $response;
    }

}
