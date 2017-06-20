<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\SearchProductsForm.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Provides a form to search items
 */
class SearchProductsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_products_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


          $form['name'] = array(
              '#type' => 'textfield',
              '#id' => 'product-search-form',
              '#size' => 50,
              '#attributes' => array('placeholder'=>t('Enter item code, barcode or name')),
              '#attached' => ['library' => array('ek_products/ek_products.autocomplete')],
            );
          
          $form['list_items'] = array(
            '#type' => 'item',
            '#markup' => "<div id='product-search-result'></div>",
          );
        
        
    return $form;  

  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  }

}
