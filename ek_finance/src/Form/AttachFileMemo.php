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
        $form['attach']['upload_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#prefix' => '<div class="container-inline">',
        ];

        $form['attach']['upload'] = [
            '#id' => 'upbuttonid',
            '#type' => 'button',
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

//

    /**
     * Callback for the ajax upload file
     *
     */
    public function uploadFile(array &$form, FormStateInterface $form_state) {


        $response = new AjaxResponse();
        $clear = new InvokeCommand('#error', "html", [""]);
        $response->addCommand($clear);

        $extensions = 'png jpeg jpg';
        // @TODO Remove for Drupal 8.5 and 8.6.
        $max_bytes = floatval(\Drupal::VERSION) < 8.7
            ? file_upload_max_size() : Environment::getUploadMaxSize();
        $max_filesize = Bytes::toNumber($max_bytes);
        $validators = array('file_validate_extensions' => [$extensions], 'file_validate_size' => [$max_filesize]);
        $file = file_save_upload("upload_doc", $validators, false, 0);

        if ($file) {
            $dir = "private://finance/memos";
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $dest = $dir . '/' . $file->getFilename();
            $filename = \Drupal::service('file_system')->copy($file->getFileUri(), $dest);

            $fields = array(
                'serial' => $form_state->getValue('tempSerial'),
                'uri' => $filename,
                'doc_date' => time(),
            );
            $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_expenses_memo_documents')->fields($fields)->execute();

            
            if ($insert) {
                
            } else {
                $msg = "<div aria-label='Error message' class='messages messages--error'>"
                        . $this->t('Error') . "</div>";
                $response->addCommand(new AppendCommand('#error', $msg));
            }
            return $response;

        } else {
            $m = \Drupal::messenger()->messagesByType('error');
            $e = '';
            if(!empty($m)){
                foreach ($m as $k){
                    $e .= "<p>". (string) $k . "</p>";
                }
                \Drupal::messenger()->deleteByType('error');
            }
            $size = round($max_filesize / 1000000, 0);
            $msg = "<div aria-label='Error message' class='messages messages--error'>"
                    . $this->t('Allowed extensions') . ": " . 'png jpg jpeg'
                    . ', ' . $this->t('maximum size') . ": " . $size . 'Mb.'
                    . $e
                    . "</div>";
            $response = new AjaxResponse();
            return $response->addCommand(new AppendCommand('#error', $msg));
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        //update the documents table
        Database::getConnection('external_db', 'external_db')
                ->update('ek_expenses_memo_documents')
                ->fields(array('serial' => $form_state->getValue('serial')))
                ->condition('serial', $form_state->getValue('tempSerial'))
                ->execute();


        if ($form_state->getValue('category') < 5) {
            $form_state->setRedirect('ek_finance_manage_list_memo_internal');
        } else {
            $form_state->setRedirect('ek_finance_manage_list_memo_personal');
        }
    }

}
