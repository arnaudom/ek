<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SalesFieldEdit
 */

namespace Drupal\ek_sales\Form;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;


/**
 * Provides a form to manage sales field.
 */
class SalesFieldEdit extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_edit_fileds';
    }

    /**
     * {@inheritdoc}
     * @param id : the table row id
     * @param field : the field to be updated
     * @param form design base on field type
     * can add other fields in the future if necessary
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $field = null) {
        $form['for_id'] = [
            '#type' => 'hidden',
            '#default_value' => $id,
        ];

        $form['field'] = [
            '#type' => 'hidden',
            '#default_value' => $field,
        ];

        switch ($field) {

            case 'comment':
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_comment', 'c');
                $query->fields('c', ['comment']);
                $query->leftJoin('ek_address_book', 'b', 'b.id = c.abid');
                $query->condition('b.id', $id);
                $t = $query->execute()->fetchField();
                $form['value'] = [
                    '#type' => 'textarea',
                    '#title' => $this->t('Comment'),
                    '#default_value' => $t,
                ];

                break;
        }


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

        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';

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

    public function formCallback(array &$form, FormStateInterface $form_state) {
        switch ($form_state->getValue('field')) {
            case 'comment':

                $text = Xss::filter($form_state->getValue('value')) . ' [' . \Drupal::currentUser()->getAccountName() . '] - ' . date('Y-m-d');
                $fields = [$form_state->getValue('field') => $text];
                $update = Database::getConnection('external_db', 'external_db')
                                ->update('ek_address_book_comment')->fields($fields)
                                ->condition('abid', $form_state->getValue('for_id'))->execute();
                $value = '';
                break;
        }
        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);
        if ($update) {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . $this->t('saved') . "</div>"));
        } else {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('error') . "</div>"));
        }
        return $response;
    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }

}
