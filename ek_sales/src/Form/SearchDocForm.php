<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SearchDocForm.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a search form.
 */
class SearchDocForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_doc_search';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['name'] = array(
            '#type' => 'textfield',
            '#id' => 'doc-search-form',
            '#size' => 35,
            '#required' => true,
            '#attributes' => array('placeholder' => $this->t('Enter document name')),
            '#attached' => ['library' => array('ek_sales/ek_sales.doc_search')],
        );

        $form['list_items'] = array(
            '#type' => 'item',
            '#markup' => "<ul id='doc-search-result'></ul>",
        );


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }

}
