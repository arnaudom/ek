<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FinanceSettingsForm.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\AidList;

/**
 * Provides a company settings form for global finance parameters.
 */
class FinanceSettingsForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_finance_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        //
        // add global settings used by accounts.
        //
        $settings = new FinanceSettings();

        $form['baseCurrency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#disabled' => $settings->get('baseCurrency') ? TRUE : FALSE,
            '#options' => CurrencyData::listcurrency(1),
            '#default_value' => $settings->get('baseCurrency'),
            '#title' => t('Base currency'),
            '#description' => t('rate @r', array('@r' => CurrencyData::rate($settings->get('baseCurrency')))),
        );
        if (empty(CurrencyData::listcurrency(1))) {

            $url = Url::fromRoute('ek_finance.currencies', [], [])->toString();
            $alert = t('You need to <a href="@url">activate</a> at list 1 currency', ['@url' => $url]);
            $form['activate_alert'] = array(
                '#type' => 'item',
                '#markup' => "<div class='messages messages--warning'>" . $alert . '</div>',
            );
        }
        
        $form['rounding'] = array(
            '#type' => 'number',
            '#min' => 0,
            '#max' => 5,
            '#step' => 1,
            '#size' => 10,
            '#required' => TRUE,
            '#default_value' => (NULL !== $settings->get('rounding')) ? $settings->get('rounding') : '2',
            '#title' => t('Default rounding'),
        );

        $form['companyMemo'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#options' => array(0 => t('no'), 1 => t('yes')),
            '#default_value' => $settings->get('companyMemo'),
            '#title' => t('Memos companies'),
            '#description' => t('Restrict selection of companies in memo (I.e: claim from)'),
        );

        $form['authorizeMemo'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#options' => array(0 => t('no'), 1 => t('yes')),
            '#default_value' => $settings->get('authorizeMemo'),
            '#title' => t('Authorize Memos'),
            '#description' => t('Request authorization for personal memos'),
        );

        $form['budgetUnit'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#options' => array(1 => '1', 2 => "'000", 3 => "'000,000"),
            '#default_value' => $settings->get('budgetUnit'),
            '#title' => t('Budgets'),
            '#description' => t('Computation unit'),
        );


        $form['expenseAttachmentFormat'] = array(
            '#type' => 'textfield',
            '#size' => 100,
            '#required' => TRUE,
            '#default_value' => (NULL !== $settings->get('expenseAttachmentFormat')) ? $settings->get('expenseAttachmentFormat') : 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip',
            '#title' => t('Files attachment format for expenses'),
            '#description' => t('Extensions list'),
        );

        $form['expenseAttachmentSize'] = array(
            '#type' => 'number',
            '#min' => 0.5,
            '#max' => 5,
            '#step' => 0.5,
            '#size' => 10,
            '#required' => TRUE,
            '#default_value' => (NULL !== $settings->get('expenseAttachmentSize')) ? $settings->get('expenseAttachmentSize') : '1',
            '#title' => t('Files attachment size for expenses'),
            '#description' => t('In Mb'),
        );

        $form['chart'] = array(
            '#type' => 'details',
            '#title' => $this->t('Chart of accounts structure'),
            '#open' => TRUE,
        );
        $chart = $settings->get('chart');
        $perm = in_array('administrator', \Drupal::currentUser()->getRoles()) ? 0 : 1;

        $form['chart']['zero'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['zero'] != NULL) ? $chart['zero'] : 0,
            '#title' => t('Other assets'),
            '#disabled' => ($chart['zero'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['assets'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#maxlength' => 1,
            '#required' => TRUE,
            '#default_value' => ($chart['assets'] != NULL) ? $chart['assets'] : 1,
            '#title' => t('Assets class'),
            '#disabled' => ($chart['assets'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['liabilities'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['liabilities'] != NULL) ? $chart['liabilities'] : 2,
            '#title' => t('Liabilities class'),
            '#disabled' => ($chart['liabilities'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['equity'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['equity'] != NULL) ? $chart['equity'] : 3,
            '#title' => t('Equity class'),
            '#disabled' => ($chart['equity'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['income'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['income'] != NULL) ? $chart['income'] : 4,
            '#title' => t('Income class'),
            '#disabled' => ($chart['income'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['cos'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['cos'] != NULL) ? $chart['cos'] : 5,
            '#title' => t('Cost of sales class'),
            '#disabled' => ($chart['cos'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['expenses'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['expenses'] != NULL) ? $chart['expenses'] : 6,
            '#title' => t('Expenses class'),
            '#disabled' => ($chart['expenses'] != NULL && $perm) ? TRUE : FALSE,
        );

        $form['chart']['other_liabilities'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['other_liabilities'] != NULL) ? $chart['other_liabilities'] : 7,
            '#title' => t('Other liabilities class'),
            '#disabled' => ($chart['other_liabilities'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['other_income'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['other_income'] != NULL) ? $chart['other_income'] : 8,
            '#title' => t('Other income class'),
            '#disabled' => ($chart['other_income'] != NULL && $perm) ? TRUE : FALSE,
        );
        $form['chart']['other_expenses'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#required' => TRUE,
            '#default_value' => ($chart['other_expenses'] != NULL) ? $chart['other_expenses'] : 9,
            '#title' => t('Other expenses class'),
            '#disabled' => ($chart['other_expenses'] != NULL && $perm) ? TRUE : FALSE,
        );

        $form['recordProvision'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#options' => array(0 => t('no'), 1 => t('yes')),
            '#default_value' => $settings->get('recordProvision'),
            '#title' => t('Provision record'),
            '#description' => t('Allow record of provisions in expense'),
        );

        $form['listPurchases'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#options' => array(0 => t('no'), 1 => t('yes')),
            '#default_value' => $settings->get('listPurchases'),
            '#title' => t('Display purchases in expenses list'),
            '#description' => t('Allow view of purchases when listing expenses'),
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        $chart = [
            $form_state->getValue('assets'),
            $form_state->getValue('liabilities'),
            $form_state->getValue('equity'),
            $form_state->getValue('income'),
            $form_state->getValue('cos'),
            $form_state->getValue('expenses'),
            $form_state->getValue('other_liabilities'),
            $form_state->getValue('other_income'),
            $form_state->getValue('other_expenses'),
            $form_state->getValue('zero')
        ];

        $chain = 0;

        foreach ($chart as $k => $v) {
            $chain += $v;
            if (!is_numeric($v) || $v > 9) {
                $form_state->setErrorByName("chart][", $this->t('All chart values should be a numeric value from 0 to 9.'));
            }
        }

        asort($chart);

        $a = array_diff([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $chart);

        if (!empty($a)) {
            $a = implode(',', $a);
            $form_state->setErrorByName("chart][", $this->t('The chart structure is not complete. Missing class @a', ['@a' => $a]));
        }

        if ($chain != 45) {
            $form_state->setErrorByName("chart][", $this->t('The chart structure has duplicate numbers.'));
        }

        if ($form_state->getValue('expenseAttachmentSize') > 5) {
            $form_state->setErrorByName("expenseAttachmentSize", $this->t('Size should be below 5Mb.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {



        $settings = new FinanceSettings();

        $settings->set('baseCurrency', $form_state->getValue('baseCurrency'));

        //force base currency with rate = 1 
        \Drupal\Core\Database\Database::getConnection('external_db', 'external_db')->update('ek_currency')
                ->condition('currency', $form_state->getValue('baseCurrency'))
                ->fields(array('rate' => 1, 'active' => 1))
                ->execute();
        $settings->set('rounding', $form_state->getValue('rounding'));
        $settings->set('authorizeMemo', $form_state->getValue('authorizeMemo'));
        $settings->set('companyMemo', $form_state->getValue('companyMemo'));
        $settings->set('budgetUnit', $form_state->getValue('budgetUnit'));
        $chart = [
            'other_assets' => $form_state->getValue('zero'),
            'assets' => $form_state->getValue('assets'),
            'liabilities' => $form_state->getValue('liabilities'),
            'equity' => $form_state->getValue('equity'),
            'income' => $form_state->getValue('income'),
            'cos' => $form_state->getValue('cos'),
            'expenses' => $form_state->getValue('expenses'),
            'other_liabilities' => $form_state->getValue('other_liabilities'),
            'other_income' => $form_state->getValue('other_income'),
            'other_expenses' => $form_state->getValue('other_expenses')
        ];
        $settings->set('chart', $chart);
        $settings->set('recordProvision', $form_state->getValue('recordProvision'));
        $settings->set('listPurchases', $form_state->getValue('listPurchases'));
        $settings->set('expenseAttachmentFormat', $form_state->getValue('expenseAttachmentFormat'));
        $settings->set('expenseAttachmentSize', $form_state->getValue('expenseAttachmentSize'));
        $save = $settings->save();

        if ($save) {
            \Drupal::messenger()->addStatus(t('The settings are recorded'));
        }
    }

}
