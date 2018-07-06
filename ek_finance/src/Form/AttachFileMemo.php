<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\AttachFileMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\HtmlCommand;
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
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $tempSerial = NULL) {

        if (isset($id) && $id != NULL) {

            $query = "SELECT serial,category,mission from {ek_expenses_memo} WHERE id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchObject();



            $form['edit_memo'] = array(
                '#type' => 'item',
                '#markup' => '<h2>' . t('Memo ref. @p', array('@p' => $data->serial)) . '</h2>',
            );

            $form['mission'] = array(
                '#type' => 'item',
                '#markup' => '<h2>' . $data->mission . '</h2>',
            );
            $form['serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );
            $form['category'] = array(
                '#type' => 'hidden',
                '#value' => $data->category,
            );
        } else {
            
        }

        $type = array(1 => "internal", 2 => "internal", 3 => "internal", 4 => "internal", 5 => "personal"); 
    $url = Url::fromRoute('ek_finance_manage_list_memo_'. $type[$data->category], array(), array())->toString();
        $form['back'] = array(
          '#type' => 'item',
          '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

        );

//
// Attachments
// 

        $form['tempSerial'] = array(
            //used for file uploaded
            '#type' => 'hidden',
            '#value' => $tempSerial,
        );

        $form['attach'] = array(
            '#type' => 'details',
            '#title' => $this->t('Attachments'),
            '#open' => TRUE,
        );
        $form['attach']['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#prefix' => '<div class="container-inline">',
        );

        $form['attach']['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'button',
            '#value' => t('Attach'),
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'uploadFile'),
                'wrapper' => 'new_attachments',
                'effect' => 'fade',
                'method' => 'append',
            ),
        );

        $form['attach']['attach_new'] = array(
            '#type' => 'container',
            '#attributes' => array(
                'id' => 'attachments',
                'class' => 'table'
            ),
        );

        $form['attach']['attach_error'] = array(
            '#type' => 'container',
            '#attributes' => array(
                'id' => 'error',
            ),
        );



        $form['actions'] = array('#type' => 'actions');
        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );

        $form['#attached'] = array(
            'drupalSettings' => array('id' => $id, 'serial' => $tempSerial),
            'library' => array('ek_finance/ek_finance.memo_form'),
        );


        return $form;
    }

//

    /**
     * Callback for the ajax upload file 
     * 
     */
    public function uploadFile(array &$form, FormStateInterface $form_state) {

        //upload
        $extensions = 'png jpeg jpg';
        $validators = array('file_validate_extensions' => [$extensions], 'file_validate_size' => [file_upload_max_size()]);
        $file = file_save_upload("upload_doc", $validators, FALSE, 0);

        if ($file) {

            $dir = "private://finance/memos";
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $dest = $dir . '/' . $file->getFilename();
            $filename = file_unmanaged_copy($file->getFileUri(), $dest);

            $fields = array(
                'serial' => $form_state->getValue('tempSerial'),
                'uri' => $filename,
                'doc_date' => time(),
            );
            $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_expenses_memo_documents')->fields($fields)->execute();

            $response = new AjaxResponse();
            if ($insert) {
                return $response->addCommand(new HtmlCommand('#error', ""));
            } else {
                $msg = "<div aria-label='Error message' class='messages messages--error'>"
                        . t('Error') . "</div>";
                return $response->addCommand(new HtmlCommand('#error', $msg));
            }
        } else {
            $size = round(file_upload_max_size() / 1000000,0);
            $msg = "<div aria-label='Error message' class='messages messages--error'>"
                    . t('Error') . ". " . t('Allowed extensions') . ": " . 'png jpg jpeg'
                    . ', ' . t('maximum size') . ": " . $size . 'Mb'
                    . "</div>";
            $response = new AjaxResponse();
            return $response->addCommand(new HtmlCommand('#error', $msg));
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
