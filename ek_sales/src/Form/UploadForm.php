<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\UploadForm
 * 
 * Improved version with:
 * - Drag and drop file upload with visual feedback
 * - Multiple file upload support
 * - Better error handling and validation
 * - Progress indication
 * - File preview/list before upload
 */

namespace Drupal\ek_sales\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use \Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Provides an improved form to upload multiple files with drag & drop.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_documents_upload';
    }

    /**
     * {@inheritdoc}
     * @param string $abid : address book id
     */
    public function buildForm(array $form, FormStateInterface $form_state, $abid = null) {

        $allowed = 'png gif jpg jpeg txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $upload_doc = ['FileExtension' => ['extensions' => $allowed]];

        // NOTE: CSS and JS are already attached by the controller via:
        // - 'ek_sales/ek_sales_css' (loads ek_sales.css)
        // - 'ek_sales/ek_sales_docs_updater' (loads ek_sales_docs.js)
        // No need to attach library here - controller handles it!

        // Wrap form elements in a container with drag-and-drop styling
        $form['upload_container'] = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['upload-drop-zone'],
                'id' => 'upload-drop-zone',
            ],
        ];

        // Hidden file input - will be triggered by drop zone
        $form['upload_container']['upload_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Select files'),
            '#title_display' => 'invisible',
            '#upload_validators' => $upload_doc,
            '#attributes' => [
                'id' => 'file-input',
                'class' => ['file-input-hidden'],
                'multiple' => 'multiple',
                'accept' => $this->getAllowedMimeTypes($allowed),
            ],
            // Force array name for multiple files
            '#name' => 'files[upload_doc][]',
        ];

        // Drag and drop hint
        $form['upload_container']['drop_hint'] = [
            '#type' => 'markup',
            '#markup' => '<div class="drop-hint">
                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <p class="drop-text">' . $this->t('Drag files here or click to select') . '</p>
                <p class="file-format-hint">' . $this->t('Supported: @formats', ['@formats' => $allowed]) . '</p>
            </div>',
            '#prefix' => '<div class="drop-hint-container">',
            '#suffix' => '</div>',
        ];

        // File list preview
        $form['upload_container']['file_list'] = [
            '#type' => 'markup',
            '#markup' => '<ul id="file-list" class="selected-files"></ul>',
        ];

        // Folder selection
        $form['upload_container']['folder'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Folder'),
            '#size' => 30,
            '#attributes' => [
                'placeholder' => $this->t('Enter folder name or select from autocomplete'),
                'class' => ['folder-input'],
            ],
            '#autocomplete_route_name' => 'ek_sales_folders',
            '#autocomplete_route_parameters' => ['abid' => $abid],
        ];

        // Comment/description
        $form['upload_container']['comment'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Comment'),
            '#size' => 30,
            '#attributes' => [
                'placeholder' => $this->t('Add a description (optional)'),
                'class' => ['comment-input'],
            ],
        ];

        // Hidden abid
        $form['upload_container']['abid'] = [
            '#type' => 'hidden',
            '#value' => $abid,
        ];

        // Submit button
        $form['upload_container']['actions'] = ['#type' => 'actions'];
        $form['upload_container']['actions']['upload'] = [
            '#id' => 'upload-button',
            '#type' => 'submit',
            '#value' => $this->t('Upload Files'),
            '#button_type' => 'primary',
            '#ajax' => [
                'callback' => [$this, 'saveFiles'],
                'wrapper' => 'upload-messages',
                'method' => 'replaceWith',
                'progress' => [
                    'type' => 'bar',
                    'message' => $this->t('Uploading files...'),
                ],
            ],
            '#attributes' => [
                'class' => ['upload-button'],
            ],
        ];

        // Upload cancel button
        $form['upload_container']['actions']['reset'] = [
            '#type' => 'button',
            '#value' => $this->t('Clear Selection'),
            '#button_type' => 'secondary',
            '#ajax' => [
                'callback' => [$this, 'resetForm'],
                'wrapper' => 'upload-drop-zone',
                'method' => 'replaceWith',
            ],
            '#attributes' => [
                'class' => ['reset-button'],
            ],
        ];

        // Message container
        $form['upload_container']['messages'] = [
            '#type' => 'container',
            '#id' => 'upload-messages',
            '#attributes' => [
                'class' => ['upload-messages'],
            ],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $field = 'upload_doc';

        $allowed = 'png gif jpg jpeg txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $validators = [
            'file_validate_extensions' => [$allowed],
        ];

        // Get uploads from request (files[upload_doc])
        $request_files = \Drupal::request()->files->get('files');
        $uploaded = $request_files[$field] ?? null;

        if (!$uploaded) {
            return;
        }

        // Normalize single upload to array
        if ($uploaded instanceof UploadedFile) {
            $uploaded = [$uploaded];
        }

        $files = [];

        foreach ($uploaded as $delta => $ignore) {
            $file = file_save_upload(
                $field,
                $validators,
                FALSE,
                $delta,
                FileExists::Rename
            );

            if ($file) {
                $file->setPermanent();
                $file->save();
                $files[] = $file;
            } elseif ($form_state->getErrors()) {
                break;
            }
        }

        if (!empty($files)) {
            $form_state->set($field, $files);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Logic handled in saveFiles callback
    }

    /**
     * AJAX Callback: Save uploaded files
     */
    public function saveFiles(array &$form, FormStateInterface $form_state) {
        $messages = [];
        $errors = [];
        $success_count = 0;

        $files = $form_state->get('upload_doc');
        if ($files) {
            if (!is_array($files)) {
                $files = [$files];
            }

            $abid = $form_state->getValue('abid');
            $folder = Xss::filter($form_state->getValue('folder'));
            $comment = Xss::filter($form_state->getValue('comment'));

            $dir = "private://sales/documents/" . $abid;
            if (!$folder) {
                $folder = 'general';
            }
            // $dir .= '/' . $folder;
            $dir .= '/';

            try {
                \Drupal::service('file_system')->prepareDirectory(
                    $dir,
                    FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
                );
            } catch (\Exception $e) {
                $errors[] = $this->t('Failed to create directory: @error', ['@error' => $e->getMessage()]);
            }

            foreach ($files as $file) {
                try {
                    $moved = \Drupal::service('file.repository')->move(
                        $file,
                        $dir,
                        FileExists::Rename
                    );

                    if ($moved) {
                        $fields = [
                            'abid' => $abid,
                            'filename' => $moved->getFileName(),
                            'uri' => $moved->getFileUri(),
                            'comment' => $comment,
                            'date' => time(),
                            'size' => $moved->getSize(),
                            'share' => 0,
                            'deny' => 0,
                            'folder' => $folder,
                        ];

                        Database::getConnection('external_db', 'external_db')
                            ->insert('ek_sales_documents')
                            ->fields($fields)
                            ->execute();

                        $log = 'user ' . \Drupal::currentUser()->id() . '|'
                            . \Drupal::currentUser()->getAccountName() . '|upload|'
                            . $moved->getFileName() . '|folder:' . $folder;
                        \Drupal::logger('ek_sales')->notice($log);

                        $success_count++;
                        $messages[] = $this->t('✓ @filename uploaded successfully', [
                            '@filename' => $moved->getFileName(),
                        ]);
                    } else {
                        $errors[] = $this->t('Failed to move @filename to destination', [
                            '@filename' => $file->getFileName(),
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors[] = $this->t('Error processing @filename: @error', [
                        '@filename' => $file->getFileName(),
                        '@error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            $errors[] = $this->t('No files were uploaded. Please select files and try again.');
        }

        // Build response
        $form['upload_container']['messages']['#markup'] = '';

        if ($success_count > 0) {
            $form['upload_container']['messages']['#markup'] .= '<div class="messages messages--status">';
            $form['upload_container']['messages']['#markup'] .= '<h2>' . $this->t('Upload successful!') . '</h2>';
            $form['upload_container']['messages']['#markup'] .= '<ul>';
            foreach ($messages as $msg) {
                $form['upload_container']['messages']['#markup'] .= '<li>' . $msg . '</li>';
            }
            $form['upload_container']['messages']['#markup'] .= '</ul></div>';
        }

        if (!empty($errors)) {
            $form['upload_container']['messages']['#markup'] .= '<div class="messages messages--error">';
            $form['upload_container']['messages']['#markup'] .= '<h2>' . $this->t('Some files could not be uploaded:') . '</h2>';
            $form['upload_container']['messages']['#markup'] .= '<ul>';
            foreach ($errors as $error) {
                $form['upload_container']['messages']['#markup'] .= '<li>' . $error . '</li>';
            }
            $form['upload_container']['messages']['#markup'] .= '</ul></div>';
        }

        return $form['upload_container']['messages'];
    }

    /**
     * AJAX Callback: Reset form
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        return $form['upload_container'];
    }

    /**
     * Helper: Convert file extensions to MIME types for accept attribute
     */
    private function getAllowedMimeTypes($extensions) {
        $ext_array = explode(' ', trim($extensions));
        return '.' . implode(',.', $ext_array);
    }
}