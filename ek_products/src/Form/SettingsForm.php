<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\SettingsForm.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
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
    
    public function __construct() {

        $this->settings = new ItemSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        
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

        $form['price'] = array(
            '#type' => 'details',
            '#title' => $this->t('Prices'),
            '#open' => TRUE,
        );

        $form['price']['selling_price_label'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => ($this->settings->get('selling_price_label')) ? $this->settings->get('selling_price_label') : $default_price_labels['selling_price_label'],
            '#title' => t('1st price label'),
            '#description' => t('I.e. "normal price"'),
        );

        $form['price']['promo_price_label'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => ($this->settings->get('promo_price_label')) ? $this->settings->get('promo_price_label') : $default_price_labels['promo_price_label'],
            '#title' => t('2nd price label'),
            '#description' => t('I.e. "promotion price"'),
        );

        $form['price']['discount_price_label'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => ($this->settings->get('discount_price_label')) ? $this->settings->get('discount_price_label') : $default_price_labels['discount_price_label'],
            '#title' => t('3rd price label'),
            '#description' => t('I.e. "discount price"'),
        );

        $form['price']['exp_selling_price_label'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => ($this->settings->get('exp_selling_price_label')) ? $this->settings->get('exp_selling_price_label') : $default_price_labels['exp_selling_price_label'],
            '#title' => t('4th price label'),
            '#description' => t('I.e. "export normal price"'),
        );


        $form['price']['exp_promo_price_label'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => ($this->settings->get('exp_promo_price_label')) ? $this->settings->get('exp_promo_price_label') : $default_price_labels['exp_promo_price_label'],
            '#title' => t('5th price label'),
            '#description' => t('I.e. "export promotion price"'),
        );

        $form['price']['exp_discount_price_label'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#default_value' => ($this->settings->get('exp_discount_price_label')) ? $this->settings->get('exp_discount_price_label') : $default_price_labels['exp_discount_price_label'],
            '#title' => t('6th price label'),
            '#description' => t('I.e. "export discount price"'),
        );


        $form['format'] = array(
            '#type' => 'details',
            '#title' => $this->t('Autocomplete format'),
            '#open' => TRUE,
        );
/*
        $form['format']['itemcode'] = array(
            '#type' => 'checkbox',
            '#default_value' => ($this->settings->get('auto_itemcode')) ? $this->settings->get('auto_itemcode') : 0,
        );
 */
        $form['format']['barcode'] = array(
            '#type' => 'checkbox',
            '#default_value' => ($this->settings->get('auto_barcode')) ? $this->settings->get('auto_barcode') : 0,
            '#title' => t('barcode'),
        );
        $form['format']['main_description'] = array(
            '#type' => 'checkbox',
            '#default_value' => ($this->settings->get('auto_main_description')) ? $this->settings->get('auto_main_description') : 0,
            '#title' => t('main description'),
        );
        $form['format']['supplier_code'] = array(
            '#type' => 'checkbox',
            '#default_value' => ($this->settings->get('auto_supplier_code')) ? $this->settings->get('auto_supplier_code') : 0,
            '#title' => t('supplier code'),
        );        
        $form['format']['other_description'] = array(
            '#type' => 'checkbox',
            '#default_value' => ($this->settings->get('auto_other_description')) ? $this->settings->get('auto_other_description') : 0,
            '#title' => t('other description'),
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

        $this->settings->set('selling_price_label', Xss::filter($form_state->getValue('selling_price_label')));
        $this->settings->set('promo_price_label', Xss::filter($form_state->getValue('promo_price_label')));
        $this->settings->set('discount_price_label', Xss::filter($form_state->getValue('discount_price_label')));
        $this->settings->set('exp_selling_price_label', Xss::filter($form_state->getValue('exp_selling_price_label')));
        $this->settings->set('exp_promo_price_label', Xss::filter($form_state->getValue('exp_promo_price_label')));
        $this->settings->set('exp_discount_price_label', Xss::filter($form_state->getValue('exp_discount_price_label')));
        //$this->settings->set('auto_itemcode',$form_state->getValue('auto_itemcode'));
        $this->settings->set('auto_barcode',$form_state->getValue('barcode'));
        $this->settings->set('auto_main_description',$form_state->getValue('main_description'));
        $this->settings->set('auto_supplier_code',$form_state->getValue('supplier_code'));
        $this->settings->set('auto_other_description',$form_state->getValue('other_description'));
        $save = $this->settings->save();

        if ($save) {
            \Drupal::messenger()->addStatus(t('The settings are recorded'));
        }
    }

}
