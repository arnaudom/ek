<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SelectTask
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to filter tasks.
 */
class SelectTask extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_select_task';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $options = [ 0 => t('All'), 1 => t('Completed'), 2 => t('Pending'),
            3 => t('My tasks'), 4 => t('Expired'),
        ];
        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => TRUE,
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['type'] = array(
            '#type' => 'select',
            '#id' => 'filtercalendar',
            '#options' => $options,
            '#default_value' => isset($_SESSION['taskfilter']['type']) ? $_SESSION['taskfilter']['type'] : 0,
            '#attributes' => array('title' => t('display options'), 'class' => array()),
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
                //'#suffix' => "</div>",
        );

        if (!empty($_SESSION['taskfilter'])) {
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
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $_SESSION['taskfilter']['type'] = $form_state->getValue('type');
        $_SESSION['taskfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['taskfilter'] = array();
    }


}
