<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\EditFileComment.
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
class EditFileComment extends FormBase {

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'projects_file_edit_comment';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $p = NULL) {

        $form['f'] = [
            '#title' => $this->t('Edit comment'),
            '#type' => 'fieldset',
        ];


        $form['f']['fid'] = [
            '#type' => 'hidden',
            '#value' => $p['id'],
        ];

        $form['f']['pcode'] = [
            '#type' => 'hidden',
            '#value' => $p['pcode'],
        ];

        $form['f']['filename'] = [
            '#type' => 'hidden',
            '#value' => $p['filename'],
        ];
        
        $form['f']['comment'] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 200,
            '#default_value' => $p['comment'],

            '#ajax' => [
                'callback' => [$this, 'clear'], // clear message
            ],
            '#suffix' => '<p class="red" id="edited"></p>'
        ];

        $form['f']['actions'] = ['#type' => 'actions'];
        $form['f']['actions']['record'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#ajax' => [
                'callback' => '::ajaxSubmit',
                'progress' => [
                    'type' => 'throbber',
                    'message' => $this->t('saving'),
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
        $f = Database::getConnection('external_db', 'external_db')
                ->update('ek_project_documents')
                ->fields(['comment' => filter_var($form_state->getValue('comment'), FILTER_SANITIZE_SPECIAL_CHARS)])
                ->condition('id', $form_state->getValue('fid'))
                ->execute(); 
        if ($f) {
            $fields = [
                'pcode' => $form_state->getValue('pcode'),
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => 'edit comment' . ' ' . $form_state->getValue('filename')
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
        $command = new HtmlCommand('#edited', '');
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

    /**
     * {@inheritDoc}
     */
    public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        $command = new HtmlCommand('#edited', $this->t('saved'));
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

}
