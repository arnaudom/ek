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
            '#attributes' => '',
        );
        $form['attach']['upload_doc'] = array(
            '#type' => 'file',
            '#title' => t('Select file'),
            '#prefix' => '<div class="container-inline">',
        );

        $form['attach']['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'button',
            '#value' => t('Attach'),
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'uploadFile'),
                'wrapper' => 'attachments',
                'effect' => 'fade',
                'method' => 'append'
            ),
        );
        $form['attach']['t1'] = array(
            '#type' => 'item',
            '#prefix' => "<div class='table' id='attachments'>",
        );

        if ($id <> '') {
            $query = 'SELECT d.id,uri FROM {ek_expenses_memo_documents} d '
                    . 'INNER JOIN {ek_expenses_memo} m '
                    . 'ON d.serial=m.serial WHERE m.id=:id';
            $docs = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id));


            $i = 1;
            while ($doc = $docs->fetchObject()) {

                $name = explode('/', $doc->uri);

                $form['attach']['row' . $i] = array(
                    '#type' => 'item',
                    '#markup' => "<a href='" . file_create_url($doc->uri) . "' target='_blank'>" . array_pop($name) . "</a>",
                    '#prefix' => "<div class='row' id='row" . $i . "'><div class='cell'>",
                    '#suffix' => "</div>",
                );


                    $form['attach']['del-' . $doc->id] = array(
                        '#type' => 'button',
                        '#name' => $i . '-' . $doc->id,
                        '#value' => t('delete attachment') . ' ' . $i,
                        '#prefix' => "<div class='cell'>",
                        '#suffix' => "</div></div>",
                        '#ajax' => array(
                            'callback' => array($this, 'removeFile'),
                            'wrapper' => 'row' . $i,
                            'effect' => 'fade',
                        //'method' => 'append'
                        ),
                    );

                $i++;
            }

            $form['attach']['t2'] = array(
                '#type' => 'item',
                '#suffix' => "</div>",
            );
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );

        //$form['#tree'] = TRUE;
        $form['#attached']['library'][] = 'ek_finance/ek_finance.memo_form';


        return $form;
    }

//

    /**
     * Callback for the ajax upload file 
     * 
     */
    public function uploadFile(array &$form, FormStateInterface $form_state) {


        //upload
        $extensions = 'png jpg jpeg';
        $validators = array('file_validate_extensions' => array($extensions));
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
                        ->insert('ek_expenses_memo_documents')
                        ->fields($fields)->execute();
        }


        if ($insert) {

            $img = "<div class='row'>
                  <div class='cell'>
                    <a href='" . file_create_url($filename) . "' target='_blank'>" . array_pop(explode('/', $filename)) . "</a>
                  </div>
                  <div class='cell'>
                    
                  </div>
                </div>
                ";
            $response = new AjaxResponse();
            return $response->addCommand(new InsertCommand('#attachments', $img));
        }

        //upload
    }

    /**
     * Callback for  ajax file remove
     * 
     */
    public function removeFile(array &$form, FormStateInterface $form_state) {

        $element = explode('-', $_POST['_triggering_element_name']);
        $id = '#row' . $element[0];
        $uri = Database::getConnection('external_db', 'external_db')
                ->query("SELECT uri from {ek_expenses_memo_documents} where id=:id", array(':id' => $element[1]))
                ->fetchField();
        file_unmanaged_delete($uri);

        Database::getConnection('external_db', 'external_db')
                ->delete('ek_expenses_memo_documents')
                ->condition('id', $element[1])
                ->execute();

        $msg = "<div class='messages messages--status' aria-label='Status message' role='contentinfo'>
                " . t('Attachment removed') . ": " . array_pop(explode('/', $uri)) . "
            </div>";
        $response = new AjaxResponse();
        return $response->addCommand(new InsertCommand($id, $msg));
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
