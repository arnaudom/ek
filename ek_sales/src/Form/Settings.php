<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\Settings.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\ek_sales\SalesSettings;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a sales settings form.
 */
class Settings extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_sales_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        // add global settings used by accounts.

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => $this->t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? true : false,
            '#required' => true,
        );

        if (($form_state->getValue('coid')) == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
        }

        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);
            $settings = new SalesSettings($form_state->getValue('coid'));

            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $form_state->getValue('coid'),
            );

            $form['shortdue'] = array(
                '#type' => 'textfield',
                '#size' => 5,
                '#default_value' => ($settings->get('shortdue')) ? $settings->get('shortdue') : 7,
                '#title' => $this->t('Short due alert (days)'),
                '#required' => true,
            );

            $form['longdue'] = array(
                '#type' => 'textfield',
                '#size' => 5,
                '#default_value' => ($settings->get('longdue')) ? $settings->get('longdue') : 30,
                '#title' => $this->t('Long due alert (days)'),
                '#required' => true,
            );
            
            $body = 'We kindly ask you for your attention and your action within 3 working days';

            $form['reminder_body'] = array(
                '#type' => 'textarea',
                '#rows' => 3,
                '#default_value' => ($settings->get('reminder_body')) ? $settings->get('reminder_body') : $body,
                '#title' => $this->t('A custom message added to clients reminder alerts.'),
                '#required' => true,
            );    

            $form['actions'] = array('#type' => 'actions');
            $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }

        if ($form_state->get('step') == 3) {
            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {

            //verify coid exist first
            $query = 'SELECT coid from {ek_sales_settings} WHERE coid=:c';
            $coid = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':c' => $form_state->getValue('coid')))
                    ->fetchField();

            if (!$coid) {
                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_sales_settings')->fields(array('coid' => $form_state->getValue('coid')))
                        ->execute();
            }



            $settings = new SalesSettings($form_state->getValue('coid'));

            $settings->set('shortdue', $form_state->getValue('shortdue'));
            $settings->set('longdue', $form_state->getValue('longdue'));
            $settings->set('reminder_body', Xss::filter($form_state->getValue('reminder_body')));


            $save = $settings->save();

            if ($save) {
                \Drupal::messenger()->addStatus(t('The settings are recorded'));
                if ($_SESSION['install'] == 1) {
                    unset($_SESSION['install']);
                    $form_state->setRedirect('ek_admin.main');
                }
            }
        }
    }

}
