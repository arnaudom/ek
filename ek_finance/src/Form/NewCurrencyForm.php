<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\NewCurrencyForm
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;

/**
 * Provides a form to create a new currency.
 */
class NewCurrencyForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_new_currency';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['code'] = array(
            '#type' => 'textfield',
            '#title' => t('Currency code'),
            '#size' => 8,
            '#maxlength' => 3,
            '#required' => TRUE,
            '#suffix' => '',
        );

        $form['name'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#title' => t('Currency name'),
            '#maxlength' => 100,
            '#required' => TRUE,
        );

        $form['rate'] = array(
            '#type' => 'textfield',
            '#size' => 8,
            '#title' => t('Exchange rate'),
            '#maxlength' => 10,
            '#default_value' => 0,
        );

        $form['active'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [0 => t('no'), 1 => t('yes')],
            '#title' => t('Active'),
            '#default_value' => 1,
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['save'] = array(
            '#id' => 'buttonid',
            '#type' => 'submit',
            '#value' => t('Record'),
            '#attributes' => array('class' => array('button--record')),
            
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if (strlen($form_state->getValue('code')) < 3) {
            $form_state->setErrorByName('code', $this->t('Error: code invalid'));
            
        } else {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_currency', 'c');
            $data = $query
                        ->fields('c', ['id'])
                        ->condition('c.currency', $form_state->getValue('code'), '=')
                        ->execute()->fetchField();

            if ($data) {
                $form_state->setErrorByName('code', $this->t('Error: code exists'));
              
            }
        }

        if (!is_numeric($form_state->getValue('rate'))) {
            $form_state->setErrorByName('rate', $this->t('Error: rate exists'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


            $name = Xss::filter($form_state->getValue('name'));

            $fields = array(
                'currency' => strtoupper($form_state->getValue('code')),
                'name' => ucfirst($name),
                'rate' => $form_state->getValue('rate'),
                'active' => $form_state->getValue('active'),
                'date' => date('Y-m-d H:i:s')
            );
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_currency')
                    ->fields($fields)->execute();
            
            
            if (isset($insert)) {
                drupal_set_message(t('Currency @c recorded', ['@c' => strtoupper($form_state->getValue('code'))]), 'status');
                $form_state->setRedirect('ek_finance.currencies');
            }
        
    }

}
