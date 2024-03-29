<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\UploadForm
 */

namespace Drupal\ek_projects\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to upload file.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_upload';
    }

    /* The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */

    protected $moduleHandler;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * Constructs an  object.
     *
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $form['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
        );

        $form['sub_folder'] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => $this->t('tag or folder')),
        );

        $form['comment'] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 200,
            '#attributes' => array('placeholder' => $this->t('comment')),
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#default_value' => $id,
        );
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
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
     * Callback
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {
        $ref = explode('|', $form_state->getValue('for_id'));

        switch ($ref[1]) {

            case 'doc':

                $pcode = explode('-', $ref[0]);
                $pcode_parts = array_reverse($pcode);
                $folder = $pcode_parts[0];

                //upload
                $extensions = 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
                $validators = array('file_validate_extensions' => array($extensions));
                $dir = "private://projects/documents/" . $folder;
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $file = file_save_upload("upload_doc", $validators, $dir, 0, FileSystemInterface::EXISTS_RENAME);

                if ($file) {
                    $file->setPermanent();
                    $file->save();
                    $uri = $file->getFileUri();
                    $filename = $file->getFileName();

                    $fields = array(
                        'pcode' => $ref[0],
                        'filename' => $filename,
                        'uri' => $uri,
                        'folder' => $ref[2],
                        'sub_folder' => Xss::filter($form_state->getValue('sub_folder')),
                        'comment' => Xss::filter($form_state->getValue('comment')),
                        'date' => time(),
                        'size' => filesize($uri),
                    );
                    $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project_documents')
                            ->fields($fields)
                            ->execute();

                    if ($this->moduleHandler->moduleExists('ek_extranet')) {
                        if ($ref[3] == 'extranet') {
                            //file uploaded from extranet user
                            //add file to content
                            $save = ek_extranet_save_content($insert, $ref[0]);
                        }
                    }
                }

                break;
        }


        if ($insert) {
            $log = $ref[0] . '|' . \Drupal::currentUser()->id() . '|upload|' . $filename;
            \Drupal::logger('ek_projects')->notice($log);

            $fields = array(
                'pcode' => $ref[0],
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => 'upload' . ' ' . $filename
            );
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_tracker')
                    ->fields($fields)->execute();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p');
            $query->fields('p', ['id']);
            $query->condition('pcode', $ref[0], '=');

            $id = $query->execute()->fetchField();
            $param = serialize(
                    array(
                        'id' => $id,
                        'field' => $this->t('File attachment'),
                        'value' => $filename,
                        'pcode' => $ref[0]
                    )
            );
            ProjectData::notify_user($param);
            $form['doc_upload_message']['#markup'] = $this->t('file uploaded @f', array('@f' => $filename));
        } else {
            $form['doc_upload_message']['#markup'] = $this->t('error copying file');
        }

        return $form['doc_upload_message'];
    }

}
