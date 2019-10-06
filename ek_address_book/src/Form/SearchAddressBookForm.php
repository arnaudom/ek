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
    public function buildForm(array $form, FormStateInterface $form_state) {



        $form['name'] = array(
            '#type' => 'textfield',
            //'#title' => t('Search'),
            '#id' => 'abook-search-form',
            '#size' => 35,
            '#required' => true,
            '#attributes' => array('placeholder' => t('Enter name or contact')),
            '#attached' => ['library' => array('ek_address_book/ek_address_book.search')],
            
        );
        
        $form['list_items'] = array(
            '#type' => 'item',
            '#markup' => "<div id='abook-search-result'></div>",
          );

       
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


    }

}
