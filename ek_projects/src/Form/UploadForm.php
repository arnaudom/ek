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
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\Service\ProjectService;

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


    protected $moduleHandler;
    protected $projectService;
    protected $fileUsage;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler'),
                $container->get('project.service'),
                $container->get('file.usage')
        );
    }

    /**
     * Constructs an  object.
     *
     */
    public function __construct(ModuleHandler $module_handler, ProjectService $projectService, FileUsageInterface $file_usage) {
        $this->moduleHandler = $module_handler;
        $this->projectService = $projectService;
        $this->fileUsage = $file_usage;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        // D11 compatibility edit
        $extensions = 'png gif jpg jpeg txt doc docx xls xlsx odt ods odp pdf ppt pptx rar rtf tiff zip';
        $ref = explode('|', $id);
        $pcode = explode('-', $ref[0]);
        $pcode_parts = array_reverse($pcode);
        $folder = $pcode_parts[0];
        $destination = "private://projects/documents/{$folder}";

        // Ensure the destination directory exists.
        \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $form['upload_doc'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Select file'),
            '#upload_location' => $destination,
            '#progress_indicator' => 'bar',
            '#progress_message'   => t('Processing...'),
            '#required' => TRUE, 
        ];

        $form['sub_folder'] = [
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => $this->t('tag or folder')),
        ];

        $form['comment'] = [
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 200,
            '#attributes' => array('placeholder' => $this->t('comment')),
        ];

        $form['for_id'] =[
            '#type' => 'hidden',
            '#default_value' => $id,
        ];

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['upload'] = [
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
            '#ajax' => array(
                'callback' => array($this, 'saveFile'),
                'wrapper' => 'doc_upload_message',
                'method' => 'replaceWith',
            ),
        ];


        $form['doc_upload_message'] = [
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => '<div id="doc_upload_message">',
            '#suffix' => '</div>',
        ];

        $form_state->set('allowed_extensions', $extensions);
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
    
        // Validate sub_folder if provided.
        $sub_folder = $form_state->getValue('sub_folder');
        if (!empty($sub_folder) && !preg_match('/^[a-zA-Z0-9 _-]+$/', $sub_folder)) {
            //$form_state->setErrorByName('sub_folder', $this->t('Sub-folder contains invalid characters.'));
            $form['doc_upload_message']['#markup'] = "<div class='red'>" .  $this->t('Subfolder contains invalid characters.') . "</div>";
            return $form['doc_upload_message'];
        }
        
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

        
        // Check for validation errors.
        if ($form_state->hasAnyErrors()) {
            // Collect and display validation errors.
            $errors = [];
            foreach ($form_state->getErrors() as $error) {
                $errors[] = $error;
            }
            $form['doc_upload_message']['#markup'] = "<div class='red'>" . $this->t('Upload failed: @errors', ['@errors' => implode('; ', $errors)]) . "</div>";
            return $form['doc_upload_message']['#markup'];
        }


        $ref = explode('|', $form_state->getValue('for_id'));
        $filename = NULL;

        $pcode = explode('-', $ref[0]);
        $pcode_parts = array_reverse($pcode);
        $folder = $pcode_parts[0];

        // Get the file ID from the managed_file field.
        $file_ids = $form_state->getValue('upload_doc');
        if (!empty($file_ids)) {
            $file_id = reset($file_ids); // Take the first file ID.
            $file = File::load($file_id);

            if ($file) {
                // Set the file as permanent.
                $file->setPermanent();
                $file->save();

                // Register file usage to prevent deletion.
                $this->fileUsage->add($file, 'ek_projects', 'project_document', $file->id());

                $uri = $file->getFileUri();
                $filename = $file->getFilename();

                // Save file metadata to the external database.
                $fields = [
                    'pcode' => $ref[0],
                    'filename' => $filename,
                    'uri' => $uri,
                    'folder' => $ref[2],
                    'sub_folder' => Xss::filter($form_state->getValue('sub_folder')),
                    'comment' => Xss::filter($form_state->getValue('comment')),
                    'date' => time(),
                    'size' => $file->getSize(),
                ];

                $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_documents')
                    ->fields($fields)
                    ->execute();

                if ($this->moduleHandler->moduleExists('ek_extranet') && $ref[3] === 'extranet') {
                    // File uploaded from extranet user; add to content.
                    $save = ek_extranet_save_content($insert, $ref[0]);
                }
            }
        }
        

        // Log and notify if the file was saved successfully.
        if (!empty($filename)) {
            $log = $ref[0] . '|' . \Drupal::currentUser()->id() . '|upload|' . $filename;
            \Drupal::logger('ek_projects')->notice($log);

            $fields = [
                'pcode' => $ref[0],
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => 'upload ' . $filename,
            ];
            Database::getConnection('external_db', 'external_db')
                ->insert('ek_project_tracker')
                ->fields($fields)
                ->execute();

            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project', 'p')
                ->fields('p', ['id'])
                ->condition('pcode', $ref[0], '=');
            $id = $query->execute()->fetchField();

            $param = serialize([
                'id' => $id,
                'field' => 'File attachment',
                'value' => $filename,
                'pcode' => $ref[0],
            ]);

            $this->projectService->notify_user($param);

            $form['doc_upload_message']['#markup'] = "<div class='green'>" .  $this->t('File uploaded: @f', ['@f' => $filename]) . "</div>";

        } else {
            $form['doc_upload_message']['#markup'] = "<div class='red'>" .  $this->t('Error uploading file.') . "</div>";
        }

        return $form['doc_upload_message'];
    }

}
