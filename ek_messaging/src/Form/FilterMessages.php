<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Form\FilterMessages.
 */

namespace Drupal\ek_messaging\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter internal messages.
 */
class FilterMessages extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'messages_key_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $company = AccessCheck::CompanyListByUid();

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Search'),
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['keyword'] = array(
            '#type' => 'textfield',
            '#default_value' => isset($_SESSION['mefilter']['keyword']) ? $_SESSION['mefilter']['keyword'] : null,
            '#attributes' => array('placeholder' => t('search by keyword')),
            '#required' => false,
        );


        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['mefilter'])) {
            $form['filters']['actions']['reset'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => array(),
                '#submit' => array(array($this, 'resetForm')),
            );
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('keyword') == null) {
            $form_state->setErrorByName('keyword', $this->t('Invalid keyword'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['mefilter']['keyword'] = Xss::filter($form_state->getValue('keyword'));
        $_SESSION['mefilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['mefilter'] = array();
    }

}
