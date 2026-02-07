<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\UploadForm
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
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
    protected $settings;

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
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_settings', 'p');
        $query->fields('p', ['settings']);
        $query->condition('coid', 0);
        $settings = $query->execute()->fetchField();
        $this->settings = $settings !== null ? unserialize($settings) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $extensions = $this->settings['file_extenions'] ? $this->settings['file_extenions'] : 'png gif jpg jpeg txt doc docx xls xlsx odt ods odp pdf ppt pptx rar rtf tiff zip';
        $ref = explode('|', $id);
        $pcode = explode('-', $ref[0]);
        $pcode_parts = array_reverse($pcode);
        $folder = $pcode_parts[0];
        $destination = "private://projects/documents/{$folder}";

        \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $form['#prefix'] = '<div id="upload-form-wrapper">';
        $form['#suffix'] = '</div>';
        
        // Custom drag/drop zone wrapper
        $form['drop_zone'] = [
            '#type' => 'container',
            '#attributes' => [
            'class' => ['file-drop-zone'],
            'id' => 'file-drop-zone',
            ],
        ];

        $form['drop_zone']['drop_message'] = [
            '#type' => 'markup',
            '#markup' => '<div class="drop-zone-message">
                            <svg class="upload-icon" viewBox="0 0 24 24" width="48" height="48">
                            <path fill="currentColor" d="M9,16V10H5L12,3L19,10H15V16H9M5,20V18H19V20H5Z"/>
                            </svg>
                            <p class="drop-text">' . $this->t('Drag & drop your file here') . '</p>
                            <p class="drop-text-or">' . $this->t('or') . '</p>
                            <span class="browse-button">' . $this->t('Browse files') . '</span>
                            <p class="allowed-extensions">' . $this->t('Allowed: @ext', ['@ext' => str_replace(' ', ', ', $extensions)]) . '</p>
                        </div>',
        ];

        // File preview container
        $form['drop_zone']['preview'] = [
            '#type' => 'markup',
            '#markup' => '
                <div id="file-preview-container" class="file-preview-container" style="display:none;">
                <div class="preview-content">
                    <button type="button" class="remove-file-btn" id="remove-file-btn" style="display:none;">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                    </svg>
                    </button>
                    <img id="image-preview" class="image-preview" style="display:none;" alt="" />
                    <div id="file-info" class="file-info" style="display:none;">
                    <svg class="file-icon" viewBox="0 0 24 24" width="48" height="48">
                        <path fill="currentColor" d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z"/>
                    </svg>
                    <div class="file-details">
                        <p class="file-name" id="file-name"></p>
                        <p class="file-size" id="file-size"></p>
                    </div>
                    </div>
                </div>
                </div>',
            ];

        $form['upload_doc'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Select file'),
            '#upload_location' => $destination,
            '#progress_indicator' => 'bar',
            '#progress_message' => t('Processing...'),
            '#required' => TRUE,
            '#upload_validators' => [
                'FileExtension' => ['extensions' => $extensions],
            ],
            '#prefix' => '<div class="hidden-file-input">',
            '#suffix' => '</div>',
            '#ajax' => [
                'callback' => '::updateFileWidget',
                'wrapper' => 'upload-doc-wrapper',
                'effect' => 'fade',
            ],
            '#wrapper_attributes' => ['id' => 'upload-doc-wrapper'],
        ];

        $form['sub_folder'] = [
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 30,
            '#attributes' => ['placeholder' => $this->t('tag or folder')],
            '#prefix' => '<div class="form-fields-wrapper">',
        ];

        $form['comment'] = [
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 200,
            '#attributes' => ['placeholder' => $this->t('comment')],
            '#suffix' => '</div>',
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#default_value' => $id,
        ];

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['upload'] = [
            '#id' => 'upbuttonid',
            '#type' => 'submit',
            '#value' => $this->t('Upload'),
            '#attributes' => ['class' => ['upload-submit-btn']],
            '#ajax' => [
            'callback' => [$this, 'saveFile'],
            'wrapper' => 'doc_upload_message',
            'method' => 'replaceWith',
            ],
        ];

        $form['doc_upload_message'] = [
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => '<div id="doc_upload_message">',
            '#suffix' => '</div>',
        ];

        $form['#attached']['library'][] = 'ek_projects/ek_projects_upload';
        $form['#attached']['drupalSettings']['ek_projects']['extensions'] = explode(' ', $extensions);

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
    
        // Validate sub_folder if provided.
        $sub_folder = $form_state->getValue('sub_folder');
        if (!empty($sub_folder) && !preg_match('/^[a-zA-Z0-9 _-]+$/', $sub_folder)) {
            $form_state->setErrorByName('sub_folder', $this->t('Sub-folder <@sf> contains invalid characters. Only letters, numbers, spaces, hyphens, and underscores are allowed.', ['@sf' => $sub_folder]));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Empty submit handler - actual submission handled by AJAX callback
    }

    /**
     * AJAX callback for file upload widget.
     */
    public function updateFileWidget(array &$form, FormStateInterface $form_state) {
        return $form['upload_doc'];
    }

    /**
     * AJAX Callback to save file
     */
    public function saveFile(array &$form, FormStateInterface $form_state) {
        if ($form_state->hasAnyErrors()) {
            $errors = [];
            foreach ($form_state->getErrors() as $error) {
            $errors[] = $error;
            }
            $response = new AjaxResponse();
            $response->addCommand(new ReplaceCommand(
            '#doc_upload_message',
            '<div id="doc_upload_message"><div class="red upload-error">' . 
            implode('; ', $errors) . '</div></div>'
            ));
            return $response;
        }

        $ref = explode('|', $form_state->getValue('for_id'));
        $filename = NULL;
        $pcode = explode('-', $ref[0]);
        $pcode_parts = array_reverse($pcode);
        $folder = $pcode_parts[0];

        $file_ids = $form_state->getValue('upload_doc');
        if (!empty($file_ids)) {
            $file_id = reset($file_ids);
            $file = File::load($file_id);
            
            if ($file) {
            $file->setPermanent();
            $file->save();
            $this->fileUsage->add($file, 'ek_projects', 'project_document', $file->id());
            
            $uri = $file->getFileUri();
            $filename = $file->getFilename();

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

            if ($this->moduleHandler->moduleExists('ek_extranet') && isset($ref[3]) && $ref[3] === 'extranet') {
                $save = ek_extranet_save_content($insert, $ref[0]);
            }
            }
        }

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

            $markup = "<div class='green upload-success'>" . 
            $this->t('File uploaded: @f', ['@f' => $filename]) . "</div>";
        } else {
            $markup = "<div class='red upload-error'>" . 
            $this->t('Error uploading file.') . "</div>";
        }

        $form['doc_upload_message']['#markup'] = $markup;
        return $form['doc_upload_message'];
        }

}