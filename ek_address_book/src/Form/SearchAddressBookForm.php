<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\SearchAddressBookForm.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Provides a search form.
 */
class SearchAddressBookForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_address_book_search';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $request = null) {     

        $text = (null !== $request) ? $request : "";
        $form['name'] = [
            '#type' => 'textfield',
            '#id' => 'abook-search-form',
            '#size' => 35,
            '#default_value' => $text,
            '#required' => true,
            '#attributes' => ['placeholder' => $this->t('Enter name or contact')],
            '#attached' => ['library' => array('ek_address_book/ek_address_book.search')],
            
        ];
        
        $form['filter_client'] = [
            '#type' => 'checkbox',
            '#id' => 'filter_client',
            '#description' => $this->t('Filter client'),
            '#prefix' => "<div class='container-inline'>",
        ];

        $form['filter_supplier'] = [
            '#type' => 'checkbox',
            '#id' => 'filter_supplier',
            '#description' => $this->t('Filter supplier'),
            '#suffix' => "</div>",
        ];
        
        $form['list_items'] = [
            '#type' => 'item',
            '#markup' => "<div id='abook-search-result'></div>",
        ];


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)  {

    }
}
