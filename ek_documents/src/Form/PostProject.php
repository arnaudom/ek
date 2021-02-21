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
class PostProject extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_documents_post_project';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
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
            '#required' => true,
            '#default_value' => null,
            '#attributes' => array('placeholder' => $this->t('Ex. 123')),
            '#title' => $this->t('Project'),
            '#autocomplete_route_name' => 'ek_look_up_projects',
        );

        $folder = array('com' => $this->t('communication'), 'fi' => $this->t('finance'),);
        $form['folder'] = array(
            '#type' => 'select',
            '#options' => $folder,
            '#size' => 4,
            '#attributes' => array('placeholder' => $this->t('folder')),
            '#required' => true,
        );

        $form['comment'] = array(
            '#type' => 'textfield',
            '#size' => 24,
            '#attributes' => array('placeholder' => $this->t('comment')),
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'upbuttonid1',
            '#type' => 'submit',
            '#value' => $this->t('Post'),
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
                '#markup' => "<div class='red'>" . $this->t('Posting') . ": " . $form_state->get('message') . "</div>",
            );
            $form_state->set('message', '');
            $form_state->setRebuild();
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->getValue('pcode') == '') {
            $form_state->setErrorByName("pcode", $this->t('You need to select a project'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $string = explode(" ", $form_state->getValue('pcode'));

        //verify project access
        if (ProjectData::validate_access($string[0])) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_documents', 'd');
            $query->fields('d');
            $query->condition('id', $form_state->getValue('id'));
            $doc = $query->execute()->fetchObject();
            
            $code = array_reverse(explode('-', $string[1]));
            $pcode = $code[0];
            $to = "private://projects/documents/" . $pcode;

            \Drupal::service('file_system')->prepareDirectory($to, 'FILE_CREATE_DIRECTORY' | 'FILE_MODIFY_PERMISSIONS');
            $to .= $data->filename;
            
            $move = \Drupal::service('file_system')->copy($doc->uri, $to, FILE_EXISTS_RENAME);
            // if($move) {
            $fields = array(
                'pcode' => $string[1],
                'filename' => $doc->filename,
                'uri' => $move,
                'folder' => $form_state->getValue('folder'),
                'comment' => $form_state->getValue('comment') ? Xss::filter($form_state->getValue('comment')) : $this->t('Posted from documents'),
                'date' => date('U'),
                'size' => filesize($move),
                'share' => 0,
                'deny' => 0,
            );

            $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project_documents')
                            ->fields($fields)->execute();

            if ($insert) {
                $log = 'user ' . \Drupal::currentUser()->id() . '|' . \Drupal::currentUser()->getAccountName()
                        . '|post|' . $data->filename . '|pcode|'
                        . $form_state->getValue('pcode');
                \Drupal::logger('ek_documents')->notice($log);

                $form_state->set('message', $this->t('success'));
                $form_state->setRebuild();
            } else {
                $form_state->set('message', $this->t('failed'));
                $form_state->setRebuild();
            }
        } else {//no acess
            $form_state->set('message', $this->t('access denied'));
            $form_state->setRebuild();
        }
    }
}
