<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\RosterNoteEdit
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;

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
    public function buildForm(array $form, FormStateInterface $form_state, $period = null, $eid = null, $name = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r', ['note']);
        $query->condition('period', $period);
        $query->condition('emp_id', $eid);
        $data = $query->execute();
        $note = $data->fetchField();
        $d = explode('-', $period);

        $form['period'] = [
            '#type' => 'hidden',
            '#default_value' => $period,
        ];
        $form['eid'] = [
            '#type' => 'hidden',
            '#default_value' => $eid,
        ];
        $form['note'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Roster note for @n on @d', ['@n' => $name, '@d' => $d[2]]) ,
            '#default_value' => $note,
            '#maxlength' => 255,
            '#description' => '<span id="count"/>',
            '#id' => 'rosterNote',
            '#description' => $this->t('maximum 255 characters'),
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

        $form['#attached']['library'][] = 'ek_hr/ek_hr.roster';

        return $form;
    }

    /**
    * @return \Drupal\Core\Ajax\AjaxResponse
    */
    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }
  
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) { }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * @return \Drupal\Core\Ajax\AjaxResponse
     */
    public function formCallback(array &$form, FormStateInterface $form_state) {

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r', ['id'])
                ->condition('period', $form_state->getValue('period'))
                ->condition('emp_id', $form_state->getValue('eid'));

        if ($rec = $query->execute()->fetchField()) {
            // update
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
        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);
        
        if ($db) {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . $this->t('Data saved') . "</div>"));
        } else {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('Error') . "</div>"));
        }

        return $response;
    }
}