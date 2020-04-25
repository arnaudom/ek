<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterStatement.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to filter bank statement
 */
class FilterStatement extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'statement_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $year = date('Y');

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $options = array($year, $year - 1, $year - 2, $year - 3, $year - 4, $year - 5, $year - 6, $year - 7);
        $form['filters']['year'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($options, $options),
            '#default_value' => isset($_SESSION['statfilter']['year']) ? $_SESSION['statfilter']['year'] : $year,
            '#title' => $this->t('year'),
        );


        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['statfilter'])) {
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
        $_SESSION['statfilter']['year'] = $form_state->getValue('year');
        $_SESSION['statfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['statfilter'] = array();
    }

}
