<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\MoveFile.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to move files / projects
 */
class MoveFile extends FormBase {

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'projects_file_move';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $p = NULL) {

        $form['m'] = [
            '#title' => $this->t('Move this file to a linked project'),
            '#type' => 'fieldset',
        ];


        $form['m']['fid'] = [
            '#type' => 'hidden',
            '#value' => $p['id'],
        ];
        $form['m']['name'] = [
            '#type' => 'hidden',
            '#value' => $p['name'],
        ];

        $form['m']['linked_project'] = [
            '#type' => 'select',
            '#options' => $p['options'],
            '#default_value' => null,
            '#ajax' => [
                'callback' => [$this, 'clear'], // clear message
            ],
            '#suffix' => '<p class="blue" id="moved"></p>'
        ];

        $form['m']['actions'] = ['#type' => 'actions'];
        $form['m']['actions']['record'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#ajax' => [
                'callback' => '::ajaxSubmit',
                'progress' => [
                    'type' => 'throbber',
                    'message' => $this->t('moving'),
                ],
            ],
            '#button_type' => 'primary',
        ];

        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

        // static::ajaxSubmit() requires data-drupal-selector to be the same between
        // the various Ajax requests. 
        // @todo Remove this workaround once https://www.drupal.org/node/2897377 
        $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
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
        $m = Database::getConnection('external_db', 'external_db')
                ->update('ek_project_documents')
                ->fields(['pcode' => $form_state->getValue('linked_project')])
                ->condition('id', $form_state->getValue('fid'))
                ->execute(); 
        if ($m) {
            $fields = [
                'pcode' => $form_state->getValue('linked_project'),
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => 'moved in' . ' ' . $form_state->getValue('name')
            ];
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_tracker')
                    ->fields($fields)->execute();
        }
    }

    /**
     * callback functions
     */
    public function clear(array &$form, FormStateInterface $form_state) {
        $command = new HtmlCommand('#moved', '');
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

    /**
     * {@inheritDoc}
     */
    public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        $l = \Drupal\ek_projects\ProjectData::geturl($form_state->getValue('linked_project'));
        $command = new HtmlCommand('#moved', '-> ' . $l);
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

}
