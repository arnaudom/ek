<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\SearchPayroll
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for payroll search by name.
 */
class SearchPayroll extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_hr_search_payroll';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['search_payroll'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#attributes' => array('placeholder' => $this->t('Name')),
            '#required' => false,
            '#id' => 'hr-search-form',
            '#attached' => ['library' => array('ek_hr/ek_hr.autocomplete')],
        );


        $form['list_people'] = array(
            '#type' => 'item',
            '#markup' => "<div id='hr-search-result'></div>",
        );
        

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
    }

    //end class
}
