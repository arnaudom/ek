<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SelectCalendar
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to switch calendar views.
 */
class SelectCalendar extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_select_cal';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $options = [0 => t('Calendar'), 1 => t('My tasks'), 2 => t('Projects submission'),
            3 => t('Projects validation'), 4 => t('Projects start'),
            5 => t('Projects deadlines'), 6 => t('Projects completed')];
        $form['select'] = array(
            '#type' => 'select',
            '#id' => 'filtercalendar',
            '#options' => $options,
            '#attributes' => array('title' => t('display options'), 'class' => array()),
        );

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
        
    }

}
