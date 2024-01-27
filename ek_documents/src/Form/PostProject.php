<?php

/**
 * @file
 * Contains \Drupal\ek_ducuments\Form\PostProject
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to post documents into projects.
 */
class PostProject extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_documents_post_project';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_documents', 'd');
        $query->fields('d', ['filename']);
        $query->condition('id', $id);
        $data = $query->execute()->fetchField();

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_settings', 'p');
        $query->fields('p', ['settings']);
        $query->condition('coid', 0);
        $settings = $query->execute()->fetchField();
        $s = unserialize($settings);
        
        if (isset($s['sections'])) {
            $s3 = $s['sections']['s3'];
            $s5 = $s['sections']['s5'];
        } else {
            $s3 = $this->t("Section 3");
            $s5 = $this->t("Section 5");
        }
        $folders = ProjectData::sectionsName();

        $form['filename'] = [
            '#type' => 'item',
            '#markup' => $data,
        ];

        $form['id'] = [
            '#type' => 'hidden',
            '#size' => 1,
            '#value' => $id,
        ];

        $form['pcode'] = [
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 150,
            '#required' => true,
            '#default_value' => null,
            '#attributes' => ['placeholder' => $this->t('Ex. 123')],
            '#title' => $this->t('Project'),
            '#autocomplete_route_name' => 'ek_look_up_projects',
        ];

        $folder = ['com' => $folders[2], 'fi' => $folders[4]];
        $form['folder'] = [
            '#type' => 'select',
            '#options' => $folder,
            '#size' => 1,
            '#attributes' => ['placeholder' => $this->t('folder')],
            '#required' => true,
        ];

        $form['comment'] = [
            '#type' => 'textfield',
            '#size' => 24,
            '#attributes' => ['placeholder' => $this->t('comment')],
        ];
        $form['alert'] = [
            '#type' => 'item',
            '#prefix' => "<div class='alert'>",
            '#suffix' => '</div>',
        ];

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['save'] = [
            '#id' => 'savebutton',
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#ajax' => [
                'callback' => [$this, 'formCallback'],
                'wrapper' => 'alert',
                'method' => 'replace',
                'effect' => 'fade',
            ],
        ];

        $form['actions']['close'] = [
            '#id' => 'closebutton',
            '#type' => 'submit',
            '#value' => $this->t('Close'),
            '#ajax' => [
                'callback' => [$this, 'dialogClose'],
                'effect' => 'fade',
                
            ],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('pcode') == '') {
            $form_state->set("pcode", $this->t('You need to select a project'));
            $form_state->set("error", 1);
        }
        if ($form_state->getValue('folder') == '') {
            $form_state->set("folder", $this->t('You need to select a folder'));
            $form_state->set("error", 1);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * Ajax callback
     */
    public function formCallback(array &$form, FormStateInterface $form_state) {

        $string = explode(" ", $form_state->getValue('pcode'));
        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);

        if($form_state->get('error')) {
            if($form_state->get('pcode')){
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--warning'>" . $form_state->get('pcode') . "</div>"));
            }
            if($form_state->get('folder')){
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--warning'>" . $form_state->get('folder') . "</div>"));
            }
            
            return $response;

        }  elseif (ProjectData::validate_access($string[0])) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d');
            $query->condition('id', $form_state->getValue('id'));
            $data = $query->execute()->fetchObject();
            
            $code = array_reverse(explode('-', $string[1]));
            $pcode = $code[0];
            $to = "private://projects/documents/" . $pcode;

            \Drupal::service('file_system')->prepareDirectory($to, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $to .= $data->filename;
            $move = \Drupal::service('file_system')->copy($data->uri, $to, 'FILE_EXISTS_RENAME');
            if($move) {
                $fields = [
                    'pcode' => $string[1],
                    'filename' => $data->filename,
                    'uri' => $move,
                    'folder' => $form_state->getValue('folder'),
                    'comment' => $form_state->getValue('comment') ? Xss::filter($form_state->getValue('comment')) : $this->t('Posted from documents'),
                    'date' => date('U'),
                    'size' => filesize($move),
                    'share' => 0,
                    'deny' => 0,
                ];

                $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project_documents')
                            ->fields($fields)->execute();
            }  

            if ($insert) {
                $log = 'user ' . \Drupal::currentUser()->id() . ' | ' . \Drupal::currentUser()->getAccountName()
                        . '| post |' . $data->filename . '| pcode |'
                        . $form_state->getValue('pcode');
                \Drupal::logger('ek_documents')->notice($log);

                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" 
                . $this->t('copied') . " " . $data->filename . " " . $this->t('to') . " " . $form_state->getValue('pcode') . "</div>"));
            } else {
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('error copying file') . "</div>"));
            }
        } else {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--warning'>" . $this->t('access denied') . "</div>"));
            
        }
        return $response;
    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }

}
