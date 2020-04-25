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
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $source = null, $format = null) {
        $query = "SELECT serial,category from {ek_$source} WHERE id=:id";
        $doc = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))->fetchObject();

        if ($doc->category < 5) {
            $route = 'ek_finance_manage_list_memo_internal';
        } else {
            $route = 'ek_finance_manage_list_memo_personal';
        }
        $url = \Drupal\Core\Url::fromRoute($route, array(), array())->toString();

        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => $url)),
        );

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
            '#open' => true,
            '#attributes' => array('class' => array('container-inline')),
        );


        $form['filters']['signature'] = array(
            '#type' => 'checkbox',
            '#default_value' => 0,
            '#attributes' => array('title' => $this->t('signature')),
            '#title' => $this->t('signature'),
        );

        $stamps = array('0' => $this->t('no'), '1' => $this->t('original'), '2' => $this->t('copy'));

        $form['filters']['stamp'] = array(
            '#type' => 'radios',
            '#options' => $stamps,
            '#default_value' => 0,
            '#attributes' => array('title' => $this->t('stamp')),
            '#title' => $this->t('stamp'),
        );


        //
        // provide selector for templates
        //
        $list = array(0 => 'default');
        if (file_exists('private://finance/templates/' . $source . '/')) {
            $handle = opendir('private://finance/templates/' . $source . '/');
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    $list[$file] = $file;
                }
            }
        }

        $form['filters']['template'] = array(
            '#type' => 'select',
            '#options' => $list,
            '#default_value' => isset($_SESSION['printfilter']['template']) ? $_SESSION['printfilter']['template'] : null,
            '#title' => $this->t('template'),
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
