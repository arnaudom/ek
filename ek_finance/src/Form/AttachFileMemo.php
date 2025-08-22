<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\AttachFileMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\AttachCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to attach file to memo.
 */
class AttachFileMemo extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;
    protected $settings;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
        $this->settings = new FinanceSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_attach_file_memo';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $tempSerial = null) {
        if (isset($id) && $id != null) {
            $query = "SELECT serial,category,mission from {ek_expenses_memo} WHERE id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchObject();

            $form['edit_memo'] = [
                '#type' => 'item',
                '#markup' => '<h2>' . $this->t('Memo ref. @p', ['@p' => $data->serial]). '</h2>',
            ];

            $form['mission'] = [
                '#type' => 'item',
                '#markup' => '<h2>' . $data->mission . '</h2>',
            ];
            $form['serial'] = [
                '#type' => 'hidden',
                '#value' => $data->serial,
            ];
            $form['category'] = [
                '#type' => 'hidden',
                '#value' => $data->category,
            ];
        } else {
            
        }

        $type = [1 => "internal", 2 => "internal", 3 => "internal", 4 => "internal", 5 => "personal"];
        $url = Url::fromRoute('ek_finance_manage_list_memo_' . $type[$data->category], [], [])->toString();
        $form['back'] = [
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', ['@url' => $url]),
        ];

//
// Attachments
//

        $form['tempSerial'] = [
            //used for file uploaded
            '#type' => 'hidden',
            '#value' => $tempSerial,
        ];

        $form['attach'] = [
            '#type' => 'details',
            '#title' => $this->t('Attachments'),
            '#open' => true,
        ];

        // file is not managed by Drupal
        $extensions = 'png jpeg jpg';
        $max_bytes = Environment::getUploadMaxSize();
        $max_filesize = Bytes::toNumber($max_bytes);
        $upload_doc = ['FileExtension' => ['extensions' => $extensions], 'FileSizeLimit' => ['fileLimit' => $max_filesize]];
        $form['attach']['upload_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Select file'),         
            '#upload_validators' => $upload_doc,
            '#prefix' => '<div class="container-inline">',
        ];

        $form['attach']['upload'] = [
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => $this->t('Attach'),
            '#suffix' => '</div>',
            '#ajax' => [
                'callback' => [$this, 'uploadFile'],
                'wrapper' => 'new_attachments',
                'effect' => 'fade',
                'method' => 'append',
            ],
        ];

        $form['attach']['attach_new'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'attachments',
                'class' => 'table'
            ],
        ];

        $form['attach']['attach_error'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'error',
            ],
        ];

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['record'] = [
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        ];

        $form['#attached'] = [
            'drupalSettings' => ['id' => $id, 'serial' => $tempSerial],
            'library' => ['ek_finance/ek_finance.memo_form'],
        ];

        return $form;
    }

    /**
     * Callback for the ajax upload file
     *
     */
    public function uploadFile(array &$form, FormStateInterface $form_state) {

        $response = new AjaxResponse();
        $clear = new InvokeCommand('#error', "html", [""]);
        $response->addCommand($clear);
        // Collect errors from messenger service
        $messenger = \Drupal::messenger();
        $error_messages = $messenger->messagesByType('error');
        if (!empty($error_messages)) {
            foreach ($error_messages as $msg) {
                $response->addCommand(new AppendCommand('#error', "<div class='messages messages--error'>". $msg ."</div>"));
            }
            $messenger->deleteByType('error');
            return $response;
        }

        if ($file = $form_state->get('upload_doc')) {
            $dir = "private://finance/memos";
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $dest = $dir . '/' . $file->getFilename();
            $uri = \Drupal::service('file_system')->copy($file->getFileUri(), $dest);

            $fields = array(
                'serial' => $form_state->getValue('serial'),
                'uri' => $uri,
                'doc_date' => time(),
            );
            $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_expenses_memo_documents')->fields($fields)->execute();
            
            if ($insert) {
                 $field = "upload_doc";
                 $form_state->set($field, '') ;
            } else {
                $msg = "<div aria-label='Error message' class='messages messages--error'>"
                        . $this->t('Error') . "</div>";
                $response->addCommand(new AppendCommand('#error', $msg));
            }
            return $response;

        } 
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $field = "upload_doc";
        $file = _file_save_upload_from_form($form['attach'][$field], $form_state, 0);
        if ($file) {
            if($errors = $form_state->getErrors()) {
                foreach ($errors as $error) {
                    $form_state->setErrorByName($field, $error);
                }
                $file->delete();
            } else {
               $form_state->set($field, $file) ; 
            }
        } else {
            $form_state->setErrorByName($field, $this->t('File upload failed'));
        }
           
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('category') < 5) {
            $form_state->setRedirect('ek_finance_manage_list_memo_internal');
        } else {
            $form_state->setRedirect('ek_finance_manage_list_memo_personal');
        }
    }

}
