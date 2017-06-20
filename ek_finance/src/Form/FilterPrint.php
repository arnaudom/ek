<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterPrint.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to filter print docs.
 */
class FilterPrint extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_print_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $source = NULL, $format = NULL) {
        $query = "SELECT serial,category from {ek_$source} WHERE id=:id";
        $doc = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))->fetchObject();
       
    if($_SERVER['HTTP_REFERER']){  
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => '<a href="' . $_SERVER['HTTP_REFERER'] . '" >' . t('Back') . '</a>',
        );
    }
        $form['serial'] = array(
            '#type' => 'item',
            '#markup' => '<h2>' . $doc->serial . '</h2>',
        );
        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id . '-' . $source,
        );
        $form['format'] = array(
            '#type' => 'hidden',
            '#value' => $format,
        );
        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => TRUE,
            '#attributes' => array('class' => array('container-inline')),
        );


        $form['filters']['signature'] = array(
            '#type' => 'checkbox',
            '#default_value' => 0,
            '#attributes' => array('title' => t('signature')),
            '#title' => t('signature'),
        );

        $stamps = array('0' => t('no'), '1' => t('original'), '2' => t('copy'));

        $form['filters']['stamp'] = array(
            '#type' => 'radios',
            '#options' => $stamps,
            '#default_value' => 0,
            '#attributes' => array('title' => t('stamp')),
            '#title' => t('stamp'),
        );


        //
        // provide selector for templates
        //
  $list = array(0 => 'default');
        $handle = opendir('private://finance/templates/' . $source . '/');
        while ($file = readdir($handle)) {
            if ($file != '.' AND $file != '..') {
                $list[$file] = $file;
            }
        }

        $form['filters']['template'] = array(
            '#type' => 'select',
            '#options' => $list,
            '#default_value' => $_SESSION['printfilter']['template'],
            '#title' => t('template'),
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => ($format == 'pdf') ? $this->t('Print in Pdf') : $this->t('Display'),
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

        $_SESSION['printfilter']['for_id'] = $form_state->getValue('for_id');
        $_SESSION['printfilter']['signature'] = $form_state->getValue('signature');
        $_SESSION['printfilter']['stamp'] = $form_state->getValue('stamp');
        $_SESSION['printfilter']['template'] = $form_state->getValue('template');
        $filter = explode('-', $form_state->getValue('for_id'));
        $_SESSION['printfilter']['filter'] = $filter[0];
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['printfilter'] = array();
    }

}
