<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\AttFilePurchase.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to attache a file url to purchase
 */
class AttFilePurchase extends FormBase {

    use AjaxFormHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'purchase_file_attach';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $p = NULL) {

        $form['m'] = [
            '#title' => $this->t('Attach this file to a purchase'),
            '#type' => 'fieldset',
        ];


        $form['m']['uri'] = [
            '#type' => 'hidden',
            '#value' => $p['uri'],
        ];

        if (\Drupal::currentUser()->hasPermission('create_purchase')) {
            // query pos
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_purchase', 's')
                    ->fields('s', ['id', 'serial', 'pcode', 'uri']);
            $query->condition('pcode', $p['pcode']);
            $pos = $query->execute();
            $options = [];
            while ($po = $pos->fetchObject()) {
                null == $po->uri ? $has_doc = "" : $has_doc = " [" . $this->t('has attachment') . "]";
                $options[$po->id] = $po->serial . $has_doc;
            }

            if (count($options) > 0) {
                $form['m']['linked_po'] = [
                    '#type' => 'select',
                    '#options' => $options,
                    '#description' => $this->t('will replace any existing attachment'),
                    '#default_value' => null,
                    '#ajax' => [
                        'callback' => [$this, 'clear'], // clear message
                    ],
                    '#suffix' => '<p class="red" id="attached"></p>'
                ];
                $form['m']['actions'] = ['#type' => 'actions'];
                $form['m']['actions']['record'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('Save'),
                    '#ajax' => [
                        'callback' => '::ajaxSubmit',
                        'progress' => [
                            'type' => 'throbber',
                            'message' => $this->t('attaching'),
                        ],
                    ],
                    '#button_type' => 'primary',
                ];

                $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
            } else {
                $form['m']['no_po'] = [
                    '#markup' => $this->t('There is no purchase in this project')
                ];
            }
        } else {
            $form['m']['no_po'] = [
                '#markup' => $this->t('You do not have permission to edit purchase')
            ];
        }

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

        $to = "private://sales/purchase/" . $form_state->getValue('linked_po');
        \Drupal::service('file_system')
                ->prepareDirectory($to, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $move = \Drupal::service('file_system')->copy($form_state->getValue('uri'), $to, 'FILE_EXISTS_RENAME');


        if ($move) {

            Database::getConnection('external_db', 'external_db')
                    ->update('ek_sales_purchase')
                    ->condition('id', $form_state->getValue('linked_po'))
                    ->fields(['uri' => $move])->execute();
        }
    }

    /**
     * callback functions
     */
    public function clear(array &$form, FormStateInterface $form_state) {
        $command = new HtmlCommand('#attached', '');
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

    /**
     * {@inheritDoc}
     */
    public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {

        $command = new HtmlCommand('#attached', '-> ' . $this->t('file attached'));
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

}
