<?php

/**
 * @file
 * Contains \Drupal\ek_ducuments\Form\DeleteFile
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_documents\DocumentsData;

/**
 * Provides a form to post documents into projects.
 */
class DeleteFile extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_documents_delete_file';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        if (DocumentsData::validate_owner($id)) {
            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_documents', 'd');
            $query->fields('d', ['filename']);
            $query->condition('id', $id);
            $data = $query->execute()->fetchField();


            $form['filename'] = [
                '#type' => 'item',
                '#markup' => $this->t('Confirm delete') . ": " . $data,
            ];

            $form['id'] = [
                '#type' => 'hidden',
                '#size' => 1,
                '#value' => $id,
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
                '#value' => $this->t('Delete'),
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
    } else {
        $form['alert'] = [
            '#type' => 'item',
            '#markup' => $this->t('You are not allowed to delete this file.'),
        ];
        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
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
    }


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
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * Ajax callback
     */
    public function formCallback(array &$form, FormStateInterface $form_state) {

        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);

        $query = Database::getConnection('external_db', 'external_db')
        ->select('ek_documents', 'd');
        $query->fields('d',['uri']);
        $query->condition('id', $form_state->getValue('id'), '=');
        $uri = $query->execute()->fetchField();

        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_documents')
                ->condition('id', $form_state->getValue('id'))
                ->execute();

        \Drupal\Core\Cache\Cache::invalidateTags(['common_documents','my_documents','shared_documents','new_documents_shared']);
        // remove from user data for new document
        \Drupal::service('user.data')->delete('ek_documents', \Drupal::currentUser()->id(), $id, 'shared');

        if ($delete) {
            $query = Database::getConnection()->select('file_managed', 'f');
            $query->fields('f', ['fid']);
            $query->condition('uri', $uri);
            $fid = $query->execute()->fetchField();
            if (!$fid) {
                unlink($uri);
            } else {
                $file = \Drupal\file\Entity\File::load($fid);
                $file->delete();
            }
                    
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" 
                . $this->t('File deleted') . "</div>"));
            //$response->setData(['status' => 'success', 'message' => 'Document deleted successfully.']);
            
        } else {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" 
                . $this->t('Error: file does not exist.') . "</div>"));
                
        }
        return $response;
    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }

}
