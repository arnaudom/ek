<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\DocMetaEdit.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to edit a company document comment and folder.
 */
class DocMetaEdit extends FormBase {

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_admin_doc_meta_edit';
    }

    /**
     * {@inheritdoc}
     *
     * @param array $p
     *   Document data: id, coid, filename, comment, folder.
     */
    public function buildForm(array $form, FormStateInterface $form_state, $p = NULL) {

        $form['f'] = [
            '#title' => $this->t('Edit document'),
            '#type' => 'fieldset',
        ];

        $form['f']['id'] = [
            '#type' => 'hidden',
            '#value' => $p['id'],
        ];

        $form['f']['coid'] = [
            '#type' => 'hidden',
            '#value' => $p['coid'],
        ];

        $form['f']['filename'] = [
            '#type' => 'item',
            '#markup' => $p['filename'],
        ];

        $form['f']['comment'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Comment'),
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => $p['comment'],
            '#suffix' => '<p class="red" id="edited"></p>',
        ];

        $form['f']['folder'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Folder'),
            '#size' => 30,
            '#maxlength' => 200,
            '#default_value' => $p['folder'],
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
        $fields = [
            'comment' => filter_var($form_state->getValue('comment'), FILTER_SANITIZE_SPECIAL_CHARS),
            'folder' => filter_var($form_state->getValue('folder'), FILTER_SANITIZE_SPECIAL_CHARS),
        ];
        Database::getConnection('external_db', 'external_db')
                ->update('ek_company_documents')
                ->fields($fields)
                ->condition('id', $form_state->getValue('id'))
                ->execute();
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
