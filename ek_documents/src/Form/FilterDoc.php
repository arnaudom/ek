<?php

/**
 * @file
 * Contains \Drupal\ek_documents\Form\FilterDoc.
 */

namespace Drupal\ek_documents\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use DateTime;

/**
 * Provides a form to filter documents.
 */
class FilterDoc extends FormBase {

  /**
   * {@inheritdoc}
   */
    public function getFormId() {
        return 'documents_filter';
    }

  /**
   * {@inheritdoc}
   */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $open = FALSE;
        if (isset($_SESSION['documentfilter']['filter']) && $_SESSION['documentfilter']['filter'] == 1) {
            $open = TRUE;
        }
        if (isset($_SESSION['documentfilter']['to'])) {
            $to = $_SESSION['documentfilter']['to'];
        } else {
            $to = date('Y-m-d');
        }

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => $open,
        );


        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );

        $form['filters'][0]['keyword'] = array(
            '#type' => 'textfield',
            '#maxlength' => 150,
            '#attributes' => array('placeholder' => t('Search with keyword')),
            '#default_value' => isset($_SESSION['documentfilter']['keyword']) ? $_SESSION['documentfilter']['keyword'] : NULL,
        );


        $form['filters'][2]['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['documentfilter']['from']) ? $_SESSION['documentfilter']['from'] : NULL,
            //'#attributes' => array('placeholder' => 'YYYY-mm-dd'),
            '#prefix' => "<div class=''><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
            '#title' => t('from'),
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
            ),
        );

        $form['filters'][2]['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => $to,
            //'#attributes' => array('placeholder' => 'YYYY-mm-dd'),
            '#title' => t('to'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
            ),
        );


        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['documentfilter'])) {
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

        if ($form_state->getValue('keyword') == '') {
            //check input if filter not by keyword

            if ($form_state->getValue('from') == '') {
                $form_state->setErrorByName("from", $this->t('No date input'));
            }
            if (!DateTime::createFromFormat('Y-m-d', $form_state->getValue('from'))) {
             $form_state->setErrorByName("from", $this->t('Wrong date format input.') . ": " . $form_state->getValue('from') );
            }
            if (!DateTime::createFromFormat('Y-m-d', $form_state->getValue('to'))) {
             $form_state->setErrorByName("to", $this->t('Wrong date format input.'). ": " . $form_state->getValue('to') );
            }            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $_SESSION['documentfilter']['from'] = $form_state->getValue('from');
        if ($_SESSION['documentfilter']['to'] != '') {
            $_SESSION['documentfilter']['to'] = $form_state->getValue('to');
        } else {
            $_SESSION['documentfilter']['to'] = date('Y-m-d');
        }
        $_SESSION['documentfilter']['keyword'] = $form_state->getValue('keyword');
        $_SESSION['documentfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['documentfilter'] = array();
    }

}
