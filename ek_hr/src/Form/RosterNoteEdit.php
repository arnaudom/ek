<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\RosterNoteEdit
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;

/**
 * Provides a form to edit note in roster.
 */
class RosterNoteEdit extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_hr_edit_roster_note';
    }

    /**
     * {@inheritdoc}
     * @param period : the note date
     * @param eid :employee id
     * @param name :employee name
     * 
     */
    public function buildForm(array $form, FormStateInterface $form_state, $period = NULL, $eid = NULL, $name = NULL) {

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r', ['note']);
        $query->condition('period', $period);
        $query->condition('emp_id', $eid);
        $data = $query->execute();
        $note = $data->fetchField();
        $d = explode('-', $period);

        $form['period'] = array(
            '#type' => 'hidden',
            '#default_value' => $period,
        );
        $form['eid'] = array(
            '#type' => 'hidden',
            '#default_value' => $eid,
        );
        $form['note'] = array(
            '#type' => 'textarea',
            '#title' => t('Roster note for @n on @d', ['@n' => $name, '@d' => $d[2]]) ,
            '#default_value' => $note,
            '#maxlength' => 255,
            '#description' => '<span id="count"/>',
            '#id' => 'rosterNote',
            '#description' => t('maximum 255 characters'),
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['btn'] = array(
            '#id' => 'confirmbutton',
            '#type' => 'submit',
            '#value' => t('Save'),
            '#attributes' => array('class' => array('use-ajax-submit')),
        );
        
        if ($form_state->get('alert') != '') {
            $form['alert'] = array(
                '#markup' => "<div class='red'>" . $form_state->get('alert') . "</div>",
            );
            /*
            $form['close'] = array(
                '#id' => 'closebutton',
                '#type' => 'button',
                '#value' => t('Close'),
                '#ajax' => [
                    'callback' => [$this, 'closeModal'],
                    'event' => 'click',
                ],
            );*/
            $form_state->set('error', '');
            //$form_state->setRebuild();
        }

        $form['#attached']['library'][] = 'ek_hr/ek_hr.roster';

        return $form;
    }

   /**
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
    public function closeModal(array &$form, FormStateInterface $form_state) {
        $response = new AjaxResponse();
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
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

        /**/
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r', ['id'])
                ->condition('period', $form_state->getValue('period'))
                ->condition('emp_id', $form_state->getValue('eid'));

        if ($rec = $query->execute()->fetchField()) {
            //update
            $db = Database::getConnection('external_db', 'external_db')
                    ->update('ek_hr_workforce_roster')
                    ->fields(['note' => Xss::filter($form_state->getValue('note'))])
                    ->condition('period', $form_state->getValue('period'))
                    ->condition('emp_id', $form_state->getValue('eid'))
                    ->execute();
        } else {
            $fields = array(
                'period' => $form_state->getValue('period'),
                'emp_id' => $form_state->getValue('eid'),
                'roster' => '',
                'status' => '',
                'note' => Xss::filter($form_state->getValue('note'))
                
            );
            $db = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_hr_workforce_roster')
                    ->fields($fields)
                    ->execute();
        }
        
        if ($db) {
            $form_state->set('alert', t('Data saved'));
            $form_state->setRebuild();
        } else {
            $form_state->set('alert', t('Error'));
            $form_state->setRebuild();
        }
    }

}
