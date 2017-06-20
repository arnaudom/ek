<?php

/**
 * @file
 * Contains \Drupal\ek_ducuments\Form\PostProject
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to post documents into projects.
 */
class PostProject extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_documents_post_project';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $query = "SELECT filename from {ek_documents} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))->fetchField();

        $form['filename'] = array(
            '#type' => 'item',
            '#markup' => $data,
        );

        $form['id'] = array(
            '#type' => 'hidden',
            '#size' => 1,
            '#value' => $id,
        );
        $form['pcode'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 150,
            '#required' => TRUE,
            '#default_value' => NULL,
            '#attributes' => array('placeholder' => t('Ex. 123')),
            '#title' => t('Project'),
            '#autocomplete_route_name' => 'ek_look_up_main_projects',
        );

        $folder = array('ap' => t('action plan'), 'com' => t('communication'), 'fi' => t('finance'),);
        $form['folder'] = array(
            '#type' => 'select',
            '#options' => $folder,
            '#size' => 4,
            '#attributes' => array('placeholder' => t('folder')),
            '#required' => TRUE,
        );

        $form['comment'] = array(
            '#type' => 'textfield',
            '#size' => 24,
            '#attributes' => array('placeholder' => t('comment')),
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'upbuttonid1',
            '#type' => 'submit',
            '#value' => t('Post'),
            '#attributes' => array('class' => array('use-ajax-submit')),
                //'#ajax' => array(
                //'callback' => array($this, 'submitForm'), 
                //'wrapper' => 'message',
                // ),
        );

        $form['message'] = array(
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => '<div id="message" class="red" >',
            '#suffix' => '</div>',
        );

        if ($form_state->get('message') <> '') {
            $form['message'] = array(
                '#markup' => "<div class='red'>" . t('Posting') . ": " . $form_state->get('message') . "</div>",
            );
            $form_state->set('message', '');
            $form_state->setRebuild();
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('pcode') == '') {
            $form_state->setErrorByName("pcode", $this->t('You need to select a project'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


        $string = explode(" ", $form_state->getValue('pcode'));

        //verify project access
        if (ProjectData::validate_access($string[0])) {
            $query = "SELECT * from {ek_documents} WHERE id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('id')))
                    ->fetchObject();

            $from = $data->uri;
            $pcode = array_reverse($string[1]);
            $pcode = $code[0];
            $to = "private://projects/documents/" . $pcode;

            file_prepare_directory($to, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $to .= '/' . $data->filename;
            $move = file_unmanaged_copy($from, $to, FILE_EXISTS_RENAME);

            // if($move) {
            $fields = array(
                'pcode' => $string[1],
                'filename' => $data->filename,
                'uri' => $to,
                'folder' => $form_state->getValue('folder'),
                'comment' => $form_state->getValue('comment') ? Xss::filter($form_state->getValue('comment')) : t('Posted from documents'),
                'date' => date('U'),
                'size' => filesize($to),
                'share' => 0,
                'deny' => 0,
            );

            $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project_documents')
                            ->fields($fields)->execute();

            if ($insert) {
                $log = 'user ' . \Drupal::currentUser()->id() . '|' . \Drupal::currentUser()->getUsername()
                        . '|post|' . $data->filename . '|pcode|'
                        . $form_state->getValue('pcode');
                \Drupal::logger('ek_documents')->notice($log);

                $form_state->set('message', t('success'));
                $form_state->setRebuild();
            } else {
                $form_state->set('message', t('failed'));
                $form_state->setRebuild();
            }
            
        }
            else {//no acess
                $form_state->set('message', t('access denied'));
                $form_state->setRebuild();
            }
    }

}
