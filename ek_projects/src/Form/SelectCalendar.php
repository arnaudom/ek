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
        $options = [0 => $this->t('Calendar'), 1 => $this->t('My tasks'), 2 => $this->t('Projects submission'),
            3 => $this->t('Projects validation'), 4 => $this->t('Projects start'),
            5 => $this->t('Projects deadlines'), 6 => $this->t('Projects completed')];
        $form['select'] = array(
            '#type' => 'select',
            '#id' => 'filtercalendar',
            '#options' => $options,
            '#attributes' => array('title' => $this->t('display options'), 'class' => array()),
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
