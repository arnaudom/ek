<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\SettingsForm.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\ek_products\ItemSettings;

/**
 * Provides a product settings form for parameters.
 */
class SettingsForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_edit_item_settings_form';
  }



  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

  $settings = new ItemSettings(); 
  
  /* price type available are divided in 2 categories (local, export) and 3 sub categories (normal, promo,discount)
   * This structure is kept for data storage, but label can be changed for display
   * when user wishes to have different types denominations
   */
    $default_price_labels = [
        'selling_price_label' => t('normal price'),
        'promo_price_label' => t('promotion price'),
        'discount_price_label' => t('discount price'),
        'exp_selling_price_label' => t('export normal price'),
        'exp_promo_price_label' => t('export promotion price'),
        'exp_discount_price_label' => t('export discount price'),
    ];
    
    $form['selling_price_label'] = array(
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => ($settings->get('selling_price_label')) ? $settings->get('selling_price_label') : $default_price_labels['selling_price_label'] ,
        '#title' => t('1st price label'),
        '#description' => t('I.e. "normal price"'),
      ); 
    
    $form['promo_price_label'] = array(
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => ($settings->get('promo_price_label')) ? $settings->get('promo_price_label') : $default_price_labels['promo_price_label'] ,
        '#title' => t('2nd price label'),
        '#description' => t('I.e. "promotion price"'),
      );     
    
    $form['discount_price_label'] = array(
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => ($settings->get('discount_price_label')) ? $settings->get('discount_price_label') : $default_price_labels['discount_price_label'] ,
        '#title' => t('3rd price label'),
        '#description' => t('I.e. "discount price"'),
      );    
    
    $form['exp_selling_price_label'] = array(
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => ($settings->get('exp_selling_price_label')) ? $settings->get('exp_selling_price_label') : $default_price_labels['exp_selling_price_label'] ,
        '#title' => t('4th price label'),
        '#description' => t('I.e. "export normal price"'),
      ); 
    
    
    $form['exp_promo_price_label'] = array(
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => ($settings->get('exp_promo_price_label')) ? $settings->get('exp_promo_price_label') : $default_price_labels['exp_promo_price_label'] ,
        '#title' => t('5th price label'),
        '#description' => t('I.e. "export promotion price"'),
      );     
    
    $form['exp_discount_price_label'] = array(
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => ($settings->get('exp_discount_price_label')) ? $settings->get('exp_discount_price_label') : $default_price_labels['exp_discount_price_label'] ,
        '#title' => t('6th price label'),
        '#description' => t('I.e. "export discount price"'),
      );     
    
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

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
  
  $settings = new ItemSettings();
  
  $settings->set('selling_price_label', Xss::filter($form_state->getValue('selling_price_label')));
  $settings->set('promo_price_label', Xss::filter($form_state->getValue('promo_price_label')));
  $settings->set('discount_price_label', Xss::filter($form_state->getValue('discount_price_label')));
  $settings->set('exp_selling_price_label', Xss::filter($form_state->getValue('exp_selling_price_label')));
  $settings->set('exp_promo_price_label', Xss::filter($form_state->getValue('exp_promo_price_label')));
  $settings->set('exp_discount_price_label', Xss::filter($form_state->getValue('exp_discount_price_label')));
 
  $save = $settings->save();
  
   if ($save) { 
       drupal_set_message(t('The settings are recorded'), 'status');
   }
  

  }
  
} 