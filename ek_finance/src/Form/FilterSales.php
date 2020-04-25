<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterSales.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_finance\AidList;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to filter sales or purchases ledger per client.
 */
class FilterSales extends FormBase {

    /**
     * Constructs a FilterSales object.
     */
    public function __construct() {
        $this->settings = new FinanceSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'sales_ledger_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $title = isset($_SESSION['salesledger']['from']) ? $this->t('from') . ': ' . $_SESSION['salesledger']['from'] . ' ' : $this->t('Filter');
        $title .= isset($_SESSION['salesledger']['to']) ? $this->t('to') . ': ' . $_SESSION['salesledger']['to'] : '';

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $title,
            '#open' => isset($_SESSION['postransfilter']['filter']) ? false : true,
        );


        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );

        $form['filters']['route'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );

        $coid = \Drupal\ek_admin\Access\AccessCheck::CompanyListByUid();
        if (!empty($coid)) {
            $form['filters'][1]['coid'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $coid,
                '#default_value' => isset($_SESSION['salesledger']['coid']) ? $_SESSION['salesledger']['coid'] : 0,
                '#title' => $this->t('company'),
            );
        } else {
            $form['filters'][1]['coid'] = array(
                '#type' => 'item',
                '#markup' => $this->t('No company'),
            );
        }

        $form['filters'][2]['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['salesledger']['from']) ? $_SESSION['salesledger']['from'] : date('Y-m') . "-01",
            '#title' => $this->t('from'),
            '#prefix' => "<div class='container-inline'>"
        );

        $form['filters'][2]['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['salesledger']['to']) ? $_SESSION['salesledger']['to'] : date('Y-m-d'),
            '#title' => $this->t('to'),
            '#suffix' => '</div>'
        );


        if ($id == 'purchase') {
            $supplier = array('%' => $this->t('All'));
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'name']);
            $query->innerJoin('ek_sales_purchase', 'p', 'p.client = ab.id');
            $query->distinct();
            $query->orderBy('name');
            $supplier += $query->execute()->fetchAllKeyed();
            /*
              $supplier += Database::getConnection('external_db', 'external_db')
              ->query("SELECT DISTINCT ab.id,name FROM {ek_address_book} ab "
              . "INNER JOIN {ek_sales_purchase} p ON p.client = ab.id order by name")
              ->fetchAllKeyed(); */


            $form['filters'][3]['client'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $supplier,
                '#default_value' => isset($_SESSION['salesledger']['client']) ? $_SESSION['salesledger']['client'] : '%',
                '#title' => $this->t('supplier'),
                '#attributes' => array('style' => array('width:200px;')),
            );
        } else {
            $client = array('%' => $this->t('All'));
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'name']);
            $query->innerJoin('ek_sales_invoice', 'i', 'i.client = ab.id');
            $query->distinct();
            $query->orderBy('name');
            $client += $query->execute()->fetchAllKeyed();
            /*
              $client += Database::getConnection('external_db', 'external_db')
              ->query("SELECT DISTINCT ab.id,name FROM {ek_address_book} ab "
              . "INNER JOIN {ek_sales_invoice} i ON i.client = ab.id order by name")
              ->fetchAllKeyed(); */

            $form['filters'][3]['client'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $client,
                '#default_value' => isset($_SESSION['salesledger']['client']) ? $_SESSION['salesledger']['client'] : '%',
                '#title' => $this->t('client'),
                '#attributes' => array('style' => array('width:200px;')),
            );
        }


        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['salesledger'])) {
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
        if (null == $form_state->getValue('coid')) {
            $form_state->setErrorByName("coid", $this->t('No company selected'));
        }
        if ($form_state->getValue('to') < $form_state->getValue('from')) {
            $form_state->setErrorByName("to", $this->t('Error dates range'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['salesledger']['from'] = $form_state->getValue('from');
        $_SESSION['salesledger']['to'] = $form_state->getValue('to');
        $_SESSION['salesledger']['coid'] = $form_state->getValue('coid');
        $_SESSION['salesledger']['client'] = $form_state->getValue('client');
        $_SESSION['salesledger']['route'] = $form_state->getValue('route');

        $_SESSION['salesledger']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['salesledger'] = array();
    }

}
