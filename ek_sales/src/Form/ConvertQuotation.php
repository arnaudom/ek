<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\ConvertQuotation.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\BankData;
use Drupal\ek_products\ItemData;

/**
 * Provides a form to convert quotation into invoice.
 */
class ConvertQuotation extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
        $this->settings = new FinanceSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_quotation_invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $query = "SELECT * from {ek_sales_quotation} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
        $query = "SELECT * from {ek_sales_quotation_details} where serial=:id";
        $detail = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data->serial));

        $form['edit_invoice'] = array(
            '#type' => 'item',
            '#markup' => t('Quotation ref. @p', array('@p' => $data->serial)),
        );

        $form['quotation_serial'] = array(
            '#type' => 'hidden',
            '#value' => $data->serial,
        );

        $n = 0;
        $form_state->set('current_items', 0);
        if (!$form_state->get('num_items'))
            $form_state->set('num_items', 0);

        $form_state->setValue('head', $data->header);


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $chart = $this->settings->get('chart');
            $AidOptions = AidList::listaid($data->header, array($chart['income'], $chart['other_income']), 1);
            $baseCurrency = $this->settings->get('baseCurrency');
            if ($baseCurrency <> $data->currency) {
                $requireFx = TRUE;
            } else {
                $requireFx = FALSE;
            }
        }


        if ($this->moduleHandler->moduleExists('ek_finance')) {

            $CurrencyOptions = CurrencyData::listcurrency(1);
        }

        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => TRUE,
            '#attributes' => '',
            '#prefix' => "",
        );

        $company = AccessCheck::CompanyListByUid();
        $form['options']['head'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#default_value' => isset($data->header) ? $data->header : NULL,
            '#title' => t('header'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'set_coid'),
                'wrapper' => 'debit',
            //will define the list of bank accounts by company below
            ),
        );


        $form['options']['allocation'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
            '#title' => t('allocated'),
            '#description' => t('select a company for which the invoice is done'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);

            if (!empty($client)) {
                $form['options']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => TRUE,
                    '#default_value' => isset($data->client) ? $data->client : NULL,
                    '#title' => t('client'),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                    '#attributes' => array('style' => array('width:300px;')),
                );
            } else {
                $new = Url::fromRoute('ek_address_book.new', array())->toString();
                $form['options']['client'] = array(
                    '#markup' => t("You do not have any @n in your record.", ['@n' => $new]),
                    '#default_value' => 0,
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );
            }
        } else {

            $form['options']['client'] = array(
                '#markup' => t('You do not have any client list.'),
                '#default_value' => 0,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );
        }


        $form['options']['date'] = array(
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
            '#title' => t('date'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $options = array(t('Invoice'), t('Commercial invoice'), t('Credit note'));
        $form['options']['title'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($options, $options),
            '#required' => TRUE,
            '#default_value' => isset($data->title) ? $data->title : 0,
            '#title' => t('title'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );

        if ($this->moduleHandler->moduleExists('ek_projects')) {


            $form['options']['pcode'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => ProjectData::listprojects(0),
                '#required' => TRUE,
                '#default_value' => isset($data->pcode) ? $data->pcode : NULL,
                '#title' => t('Project'),
            );
        } // project

        if ($this->moduleHandler->moduleExists('ek_finance')) {


            $form['options']['currency'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $CurrencyOptions,
                '#required' => TRUE,
                '#default_value' => isset($data->currency) ? $data->currency : NULL,
                '#title' => t('currency'),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
                '#ajax' => array(
                    'callback' => array($this, 'check_aid'),
                    'wrapper' => 'fx',
                //will define if currency asset account exist and input the exchange rate against
                // base currency
                ),
            );

            if ($form_state->getValue('currency')) {

                $value = '';

                $description = '';
                $settings = new CompanySettings($form_state->getValue('head'));
                $aid = $settings->get('asset_account', $form_state->getValue('currency'));

                if ($aid == '') {

                    $description = "<div id='fx' class='messages messages--warning'>"
                            . t('You need to select header first. You cannot proceed.') . "</div>";
                } else {
                    $fx = CurrencyData::rate($form_state->getValue('currency'));
                    if ($fx <> 1) {
                        $value = $fx;
                        $required = TRUE;
                    } else {
                        $value = 1;
                        $description = '';
                        $required = FALSE;
                    }
                }
            } else {
                $value = '';

                $description = '';
                $settings = new CompanySettings($data->header);
                $aid = $settings->get('asset_account', $data->currency);

                if ($aid == '') {

                    $description = "<div id='fx' class='messages messages--warning'>"
                            . t('You need to select header first. You cannot proceed.') . "</div>";
                } else {
                    $fx = CurrencyData::rate($data->currency);
                    if ($fx <> 1) {
                        $value = $fx;
                        $required = TRUE;
                    } else {
                        $value = 1;
                        $description = '';
                        $required = FALSE;
                    }
                }
            }

            /*
              if(isset($data->amountbase) ){
              $query = "SELECT sum(quantity*value) from {ek_sales_invoice_details} WHERE serial=:s and opt=:o";
              $details = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $data->serial, ':o' => 1))->fetchField();
              $amount = $data->amount+($details *$data->taxvalue/100);
              $value =  round($amount/$data->amountbase, 4);
              }
             */


            $form['options']['fx_rate'] = array(
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 15,
                '#value' => $value,
                '#default_value' => ($form_state->getValue('fx_rate')) ? $form_state->getValue('fx_rate') : $value,
                '#required' => $required,
                '#title' => t('exchange rate'),
                '#description' => $description,
                '#prefix' => "<div id='fx' class='cell'>",
                '#suffix' => '</div>',
            );

//bank account
            if ($form_state->getValue('head')) {
                $options['bank'] = BankData::listbankaccountsbyaid($form_state->getValue('head'));
            } else {
                $options['bank'] = BankData::listbankaccountsbyaid($data->header);
            }


            $form['options']['bank_account'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => isset($options['bank']) ? $options['bank'] : array(),
                '#default_value' => isset($data->bank) ? $data->bank : $form_state->getValue('bank_account'),
                '#required' => TRUE,
                '#title' => t('account payment'),
                '#prefix' => "<div id='debit' class='cell'>",
                '#suffix' => '</div></div></div>',
                '#description' => '',
                '#attributes' => array('style' => array('width:280px;')),
                '#ajax' => array(
                    'callback' => array($this, 'check_tax'),
                    'wrapper' => 'taxwrap',
                ),
            );
        } // finance
        else {
            $l = explode(',', file_get_contents(drupal_get_path('module', 'ek_sales') . '/currencies.inc'));
            foreach ($l as $key => $val) {
                $val = explode(':', $val);
                $currency[$val[0]] = $val[1];
            }
            $form['options']['currency'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $currency,
                '#required' => TRUE,
                '#default_value' => isset($data->currency) ? $data->currency : NULL,
                '#title' => t('currency'),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div></div></div>',
            );

            $form['options']['bank_account'] = array();
            $form['options']['fx_rate'] = array();
        }

        $tax = explode('|', $data->tax);

        $form['options']['tax'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => isset($tax[0]) ? $tax[0] : NULL,
            '#title' => t('tax'),
            '#prefix' => "<div class='container-inline'>",
            '#attributes' => array('placeholder' => t('ex. sales tax')),
        );

        $form['options']['taxvalue'] = array(
            '#type' => 'textfield',
            '#id' => 'taxvalue',
            '#size' => 10,
            '#maxlength' => 6,
            '#default_value' => isset($tax[1]) ? $tax[1] : NULL,
            '#description' => t('percent'),
            '#title_display' => 'after',
            '#prefix' => "<div id='taxwrap'>",
            '#suffix' => "</div></div>",
            '#attributes' => array('placeholder' => t('%'), 'class' => array('amount')),
        );

        $form['options']['terms'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array(t('on receipt'), t('due days')),
            '#default_value' => isset($data->terms) ? $data->terms : NULL,
            '#title' => t('terms'),
            '#prefix' => "<div class='container-inline'>",
        );

        $form['options']['due'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#maxlength' => 3,
            '#default_value' => isset($data->due) ? $data->due : NULL,
            '#suffix' => "</div>",
            '#attributes' => array('placeholder' => t('days')),
        );

        $form['options']['comment'] = array(
            '#type' => 'textarea',
            '#rows' => 1,
            '#default_value' => t('Based on quotation ref.') . ' ' . $data->serial,
            '#prefix' => "<div class='container-inline'>",
            '#suffix' => "</div>",
            '#attributes' => array('placeholder' => t('comment')),
        );



        $form['items'] = array(
            '#type' => 'details',
            '#title' => $this->t('Items'),
            '#open' => TRUE,
        );


        $form['items']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            '#limit_validation_errors' => array(array('head')),
            '#submit' => array(array($this, 'addForm')),
            '#prefix' => "<div id='add'>",
            '#suffix' => '</div>',
        );


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $headerline = "<div class='table'  id='invoice_form_items'>
                  <div class='row'>
                      <div class='cell cellborder' id='tour-item1'>" . t("Description") . "</div>
                      <div class='cell cellborder' id='tour-item2'>" . t("Account") . "</div>
                      <div class='cell cellborder' id='tour-item3'>" . t("Units") . "</div>
                      <div class='cell cellborder' id='tour-item4'>" . t("Value") . "</div>
                      <div class='cell cellborder' id='tour-item5'>" . t("delete") . "</div>
                      <div class='cell cellborder' id='tour-item6'>" . t("tax") . "</div>
                      <div class='cell cellborder right' id='tour-item7'>" . t("Line total") . "</div>
                   ";
        } else {
            $headerline = "<div class='table' id='invoice_form_items'><div class='row'><div class='cell cellborder'>" . t("Description") . "</div><div class='cell cellborder'>" . t("Units") . "</div><div class='cell cellborder'>" . t("Value") . "</div><div class='cell cellborder'>" . t("delete") . "</div><div class='cell cellborder'>" . t("tax") . "</div><div class='cell cellborder'>" . t("Line total") . "</div>";
        }

        $form['items']["headerline"] = array(
            '#type' => 'item',
            '#markup' => $headerline,
        );


        if (isset($detail)) {
//edition mode
//list current items

            while ($d = $detail->fetchObject()) {

                $n++;
                $c = $form_state->get('current_items') + 1;
                $form_state->set('current_items', $c);

                if ($d->itemdetails == "" && $this->moduleHandler->moduleExists('ek_products')) {

                    $name = ItemData::item_bycode($d->itemid);
                } else {
                    $name = $d->itemdetails;
                }

                $form['items']["description$n"] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 255,
                    '#default_value' => $name,
                    '#attributes' => array('placeholder' => t('item')),
                    '#prefix' => "<div class='row current'><div class='cell'>",
                    '#suffix' => '</div>',
                    '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                );


                if ($this->moduleHandler->moduleExists('ek_finance')) {

                    $form['items']["account$n"] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $AidOptions,
                        '#required' => TRUE,
                        '#default_value' => isset($d->aid) ? $d->aid : NULL,
                        '#attributes' => array('style' => array('width:100px;')),
                        '#prefix' => "<div class='cell'>",
                        '#suffix' => '</div>',
                    );
                } // finance    

                $form['items']["quantity$n"] = array(
                    '#type' => 'textfield',
                    '#id' => 'quantity' . $n,
                    '#size' => 8,
                    '#maxlength' => 255,
                    '#default_value' => $d->unit,
                    '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["value$n"] = array(
                    '#type' => 'textfield',
                    '#id' => 'value' . $n,
                    '#size' => 8,
                    '#maxlength' => 255,
                    '#default_value' => $d->value,
                    '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["delete$n"] = array(
                    '#type' => 'checkbox',
                    '#attributes' => array('title' => t('delete'), 'class' => array()),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["tax$n"] = array(
                    '#type' => 'checkbox',
                    '#id' => 'optax' . $n,
                    '#attributes' => array('title' => t('tax include'), 'class' => array('amount')),
                    '#default_value' => $d->opt,
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $total = number_format($d->value * $d->unit, 2);
                $grandtotal += ($d->value * $d->unit);
                if ($d->opt == 1) {
                    $taxable += ($d->value * $d->unit);
                }

                $form['items']["total$n"] = array(
                    '#type' => 'textfield',
                    '#id' => 'total' . $n,
                    '#size' => 12,
                    '#maxlength' => 255,
                    '#default_value' => $total,
                    '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
                    '#prefix' => "<div class='cell right'>",
                    '#suffix' => '</div></div>',
                );
            }
        } //details of current records


        if (isset($detail)) {
            // reset the new rows items
            $max = $form_state->get('num_items') + $n;
            $n++;
        } else {
            $max = $form_state->get('num_items');
            $n = 1;
        }

        for ($i = $n; $i <= $max; $i++) {

            $form['items']["description$i"] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => ($form_state->getValue("description$i")) ? $form_state->getValue("description$i") : NULL,
                '#attributes' => array('placeholder' => t('item')),
                '#prefix' => "<div class='container-inline'>",
                '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );


            if ($this->moduleHandler->moduleExists('ek_finance')) {

                $form['items']["account$i"] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $form_state['AidOptions'],
                    '#required' => TRUE,
                    '#default_value' => isset($d->aid) ? $d->aid : NULL,
                    '#attributes' => array('style' => array('width:100px;')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );
            } // finance    

            $form['items']["quantity$i"] = array(
                '#type' => 'textfield',
                '#id' => 'quantity' . $i,
                '#size' => 8,
                '#maxlength' => 255,
                '#default_value' => ($form_state->getValue("quantity$i")) ? $form_state->getValue("quantity$i") : NULL,
                '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["value$i"] = array(
                '#type' => 'textfield',
                '#id' => 'value' . $i,
                '#size' => 8,
                '#maxlength' => 255,
                '#default_value' => ($form_state->getValue("value$i")) ? $form_state->getValue("value$i") : NULL,
                '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["delete$i"] = array(
                '#type' => 'item',
                '#attributes' => '',
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["tax$i"] = array(
                '#type' => 'checkbox',
                '#id' => 'optax' . $i,
                '#attributes' => array('title' => t('tax include'), 'class' => array('amount')),
                '#default_value' => 1,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $total = number_format($form_state->getValue("value$i") * $form_state->getValue("quantity$i"), 2);
            $grandtotal += $form_state->getValue("value$i") * $form_state->getValue("quantity$i");
            $form['items']["total$i"] = array(
                '#type' => 'textfield',
                '#id' => 'total' . $i,
                '#size' => 12,
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div>',
            );
        } //added items
        //insert 1 more line if incoterm is set
        $incoterm = explode('|', $data->incoterm);
        if (isset($data->incoterm) && $incoterm[0] != 'na') {

            $inco = $incoterm[0] . ' ' . $incoterm[1] . '%';
            $i++;
            $add = 1;

            $form['items']["incoterm"] = array(
                '#type' => 'hidden',
                '#attributes' => array('id' => 'incoterm'),
                '#value' => $incoterm[1],
            );

            $form['items']['description_incoterm'] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => ($form_state->getValue("description_incoterm")) ? $form_state->getValue("description_incoterm") : $inco,
                '#attributes' => array('placeholder' => t('incoterm')),
                '#prefix' => "<div class='container-inline'>",
                '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );


            if ($this->moduleHandler->moduleExists('ek_finance')) {

                $form['items']["account_incoterm"] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $AidOptions,
                    '#required' => TRUE,
                    '#default_value' => NULL,
                    '#attributes' => array('style' => array('width:100px;')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );
            } // finance    

            $form['items']["quantity_incoterm"] = array(
                '#type' => 'item',
                '#id' => '',
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["value_incoterm"] = array(
                '#type' => 'item',
                '#id' => '',
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["delete_incoterm"] = array(
                '#type' => 'item',
                '#attributes' => '',
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["tax_incoterm"] = array(
                '#type' => 'checkbox',
                '#id' => 'optax_incoterm',
                '#attributes' => array('title' => t('tax include'), 'class' => array('amount')),
                '#default_value' => 0,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $total = number_format($grandtotal * $incoterm[1] / 100, 2);
            $grandtotal += $grandtotal * $incoterm[1] / 100;
            $form['items']["total_incoterm"] = array(
                '#type' => 'textfield',
                '#id' => 'total_incoterm',
                '#size' => 12,
                '#maxlength' => 255,
                '#default_value' => $total,
                '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div>',
            );
        } //incoterm line

        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => isset($detail) ? $n - 1 + $form_state->get('num_items') : $form_state->get('num_items'),
            '#attributes' => array('id' => 'itemsCount'),
        );

        $form['items']['closetable'] = array(
            '#type' => 'item',
            '#markup' => '</div>',
        );

        if (($form_state->get('num_items') && $form_state->get('num_items') > 0) || isset($detail)) {

            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    '#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                );
            }

            $form['items']['foot'] = array(
                '#type' => 'item',
                '#markup' => '<hr>',
                '#prefix' => "<div class='table' id='invoice_form_footer'>",
            );

            $form['items']["grandtotal"] = array(
                '#type' => 'textfield',
                '#id' => 'grandtotal',
                '#size' => 12,
                '#maxlength' => 255,
                '#value' => isset($grandtotal) ? number_format($grandtotal, 2) : 0,
                '#title' => isset($grandtotal) ? t('Items total') : NULL,
                '#attributes' => array('placeholder' => t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div>',
            );

            $taxamount = number_format(($taxable * $data->taxvalue / 100), 2);

            $form['items']["taxamount"] = array(
                '#type' => 'textfield',
                '#id' => 'taxamount',
                '#title' => '',
                '#size' => 12,
                '#value' => $taxamount,
                '#title' => isset($taxamount) ? t('Tax payable') : NULL,
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => t('tax'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div>',
            );

            $form['items']["totaltax"] = array(
                '#type' => 'textfield',
                '#id' => 'totaltax',
                '#size' => 12,
                '#maxlength' => 255,
                '#value' => number_format($grandtotal + ($taxable * $data->taxvalue / 100), 2),
                '#title' => isset($grandtotal) ? t('Total invoice') : NULL,
                '#attributes' => array('placeholder' => t('total invoice'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div></div>',
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
            );
        }

        $form['#attached']['library'][] = 'ek_sales/ek_sales.invoice';


        return $form;
    }

//

    /**
     * callback functions
     */
    public function set_coid(array &$form, FormStateInterface $form_state) {

        return $form['options']['bank_account'];
    }

    /**
     * Callback
     */
    public function check_aid(array &$form, FormStateInterface $form_state) {

        return $form['options']['fx_rate'];
    }

    /**
     * Callback
     */
    public function check_tax(array &$form, FormStateInterface $form_state) {
        $settings = new CompanySettings($form_state['input']['head']);
        if ($settings->get('stax_collect') == 1) {
            $form['options']['taxvalue']['#value'] = $settings->get('stax_rate');
        }
        return $form['options']['taxvalue'];
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * Callback: Add item to form
     */
    public function addForm(array &$form, FormStateInterface $form_state) {

        if (!$form_state->get('num_items')) {
            $form_state->set('num_items', 1);
        } else {
            $i = $form_state->get('num_items') + 1;
            $form_state->set('num_items', $i);
        }


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $chart = $this->settings->get('chart');
            $form_state->set('AidOptions', AidList::listaid($form_state->getValue('head'), array($chart['income'], $chart['other_income']), 1));
        }
    }

    /**
     * Callback: Remove item to form
     */
    public function removeForm(array &$form, FormStateInterface $form_state) {

        $i = $form_state->get('num_items') - 1;
        $form_state->set('num_items', $i);
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

//input used to update values set by user
        $input = $form_state->getUserInput();
        if ($input['fx_rate'] != '' && !is_numeric($input['fx_rate'])) {
            $form_state->setErrorByName('fx_rate', $this->t('Exchange rate is wrong'));
        }

        if (!$form_state->getValue('tax') == '' && $form_state->getValue('taxvalue') == '') {
            $form_state->setErrorByName('taxvalue', $this->t('Tax value is empty'));
        }

        if ($form_state->getValue('tax') == '' && !$form_state->getValue('taxvalue') == '') {
            $form_state->setErrorByName('tax', $this->t('Tax description is empty'));
        }

        if (!$form_state->getValue('tax') == '' && !is_numeric($form_state->getValue('taxvalue'))) {
            $form_state->setErrorByName('taxvalue', $this->t('Tax value should be numeric'));
        }

        if ($form_state->getValue('terms') == 1 && $form_state->getValue('due') == '') {
            $form_state->setErrorByName('due', $this->t('Terms days is empty'));
        }

        if ($form_state->getValue('terms') == 1 && !is_numeric($form_state->getValue('due'))) {
            $form_state->setErrorByName('due', $this->t('Terms days should be numeric'));
        }

        for ($n = 1; $n <= $form_state->get('num_items'); $n++) {

            if ($form_state->getValue("description$n") == '') {
                $form_state->setErrorByName("description$n", $this->t('Item @n is empty', array('@n' => $n)));
            }

            if ($form_state->getValue("quantity$n") == '' || !is_numeric($form_state->getValue("quantity$n"))) {
                $form_state->setErrorByName("quantity$n", $this->t('there is no quantity for item @n', array('@n' => $n)));
            }
            if ($form_state->getValue("value$n") == '' || !is_numeric($form_state->getValue("value$n"))) {
                $form_state->setErrorByName("value$n", $this->t('there is no value for item @n', array('@n' => $n)));
            }
            if ($this->moduleHandler->moduleExists('ek_finance')) {

                // validate account
                //@TODO
            } // finance            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        //create new serial No
        $iid = Database::getConnection('external_db', 'external_db')
                ->query("SELECT count(id) from {ek_sales_invoice}")->fetchField();
        $iid++;
        $short = Database::getConnection('external_db', 'external_db')
                ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))->fetchField();
        $date = substr($form_state->getValue('date'), 2, 5);
        $sup = Database::getConnection('external_db', 'external_db')
                ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))->fetchField();
        $serial = ucwords($short) . "-QI-" . $date . "-" . ucwords($sup) . "-" . $iid;

//input used to update values set by user
        $input = $form_state->getUserInput();
        $fx_rate = $input['fx_rate'];

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            // used to calculate currency gain/loss from rate at invoice record time
            $baseCurrency = $this->settings->get('baseCurrency');
            $currencyRate = 1;
            if ($baseCurrency <> $form_state->getValue('currency')) {

                if ($fx_rate <> '' && is_numeric($fx_rate)) {
                    $currencyRate = $fx_rate;
                } else {
                    $currencyRate = CurrencyData::rate($form_state->getValue('currency'));
                }
            }
        }
// Items  

        $line = 0;
        $total = 0;
        $taxable = 0;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $journal = new Journal();
        }

        for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

            if (!$form_state->getValue("delete$n") == 1) {
                if ($this->moduleHandler->moduleExists('ek_products')) {
                    //verify if item is in the DB if not just record input

                    $item = explode(" ", $form_state->getValue("description$n"));
                    $id = trim($item[0]);
                    $code = trim($item[1]);
                    $query = "SELECT description1,id from {ek_items} where id=:id and itemcode=:ic";
                    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id, ':ic' => $code))->fetchObject();

                    if ($data) {
                        $item = $data->description1;
                        $itemdetail = $data->id;
                    } else {
                        $item = $form_state->getValue("description$n");
                        $itemdetail = '';
                    }
                } else {
                    //use input from user
                    $item = $form_state->getValue("description$n");
                    $itemdetail = '';
                }

                $line = (round($form_state->getValue("quantity$n") * $form_state->getValue("value$n"), 2));
                $linebase = (round($form_state->getValue("quantity$n") * $form_state->getValue("value$n") / $currencyRate, 2));
                $sum = $sum + $line;
                if ($form_state->getValue("tax$n") == 1) {
                    $taxable = $taxable + $line;
                }


                $account = $form_state->getValue("account$n");

                if ($account == '') {
                    $account = 0;
                }

                $fields = array('serial' => $serial,
                    'item' => $item, // description used in displays
                    'itemdetail' => $itemdetail, //add detail / id if item is in DB
                    'quantity' => $form_state->getValue("quantity$n"),
                    'value' => $form_state->getValue("value$n"),
                    'total' => $line,
                    'totalbase' => $linebase,
                    'opt' => $form_state->getValue("tax$n"),
                    'aid' => $account
                );

                $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_invoice_details')
                        ->fields($fields)
                        ->execute();
            }//if not delete
        }//for

        if ($form_state->getValue('incoterm')) {
            //add incoterm line
            $incoterm_value = round($sum * $form_state->getValue('incoterm') / 100, 2);
            $sum = $sum + $incoterm_value;
            $incoterm_valuebase = (round($incoterm_value / $currencyRate, 2));

            if ($form_state->getValue('tax_incoterm') == 1) {
                $taxable = $taxable + $incoterm_value;
            }

            $fields = array('serial' => $serial,
                'item' => $form_state->getValue('description_incoterm'),
                'itemdetail' => '',
                'quantity' => 1,
                'value' => $incoterm_value,
                'total' => $incoterm_value,
                'totalbase' => $incoterm_valuebase,
                'opt' => $form_state->getValue('tax_incoterm'),
                'aid' => $form_state->getValue('account_incoterm')
            );

            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_invoice_details')
                    ->fields($fields)
                    ->execute();
        }

//main

        if ($form_state->getValue('due') == '') {
            $due = 0;
        } else {
            $due = $form_state->getValue('due');
        }
        if ($form_state->getValue('pcode') == '') {
            $pcode = 'n/a';
        } else {
            $pcode = $form_state->getValue('pcode');
        }
        if ($form_state->getValue('taxvalue') == '') {
            $taxvalue = 0;
        } else {
            $taxvalue = $form_state->getValue('taxvalue');
        }

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $sumwithtax = $sum + (round($taxable * $taxvalue / 100, 2));
            if ($baseCurrency <> $form_state->getValue('currency')) {
                $amountbc = round($sumwithtax / $currencyRate, 2);
            } else {
                $amountbc = $sumwithtax;
            }
        }

        $fields1 = array(
            'serial' => $serial,
            'head' => $form_state->getValue('head'),
            'allocation' => $form_state->getValue('allocation'),
            'status' => 0,
            'amount' => $sum,
            'currency' => $form_state->getValue('currency'),
            'date' => $form_state->getValue('date'),
            'title' => $form_state->getValue('title'),
            'pcode' => $pcode,
            'comment' => $form_state->getValue('comment'),
            'client' => $form_state->getValue('client'),
            'amountreceived' => 0,
            'pay_date' => '',
            'amountbase' => $amountbc,
            'balancebase' => $amountbc,
            'terms' => $form_state->getValue('terms'),
            'due' => $due,
            'bank' => $form_state->getValue('bank_account'),
            'tax' => $form_state->getValue('tax'),
            'taxvalue' => $taxvalue,
            'reconcile' => 0,
        );

        $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_invoice')
                ->fields($fields1)
                ->execute();


        //
        // Record the accounting journal
        //
    if ($this->moduleHandler->moduleExists('ek_finance')) {


            for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

                if (!$form_state->getValue("delete$n") == 1) {
                    if ($form_state->getValue('taxvalue') > 0 && $form_state->getValue("tax$n") == 1) {
                        $tax = round($form_state->getValue("value$n") * $form_state->getValue("quantity$n") * $form_state->getValue('taxvalue') / 100, 2);
                    } else {
                        $tax = 0;
                    }
                    $line = (round($form_state->getValue("quantity$n") * $form_state->getValue("value$n"), 2));
                    $journal->record(
                            array(
                                'source' => "invoice",
                                'coid' => $form_state->getValue('head'),
                                'aid' => $form_state->getValue("account$n"),
                                'reference' => $insert,
                                'date' => $form_state->getValue('date'),
                                'value' => $line,
                                'currency' => $form_state->getValue('currency'),
                                'tax' => $tax,
                            )
                    );
                }
            } //for

            if ($form_state->getValue('incoterm')) {
                //add incoterm line
                if ($form_state->getValue('taxvalue') > 0 && $form_state->getValue('tax_incoterm') == 1) {
                    $tax = round($incoterm_value * $form_state->getValue('taxvalue') / 100, 2);
                } else {
                    $tax = 0;
                }

                $journal->record(
                        array(
                            'source' => "invoice",
                            'coid' => $form_state->getValue('head'),
                            'aid' => $form_state->getValue('account_incoterm'),
                            'reference' => $insert,
                            'date' => $form_state->getValue('date'),
                            'value' => $incoterm_value,
                            'currency' => $form_state->getValue('currency'),
                            'tax' => $tax,
                        )
                );
            }

            $journal->recordtax(
                    array(
                        'source' => "invoice",
                        'coid' => $form_state->getValue('head'),
                        'reference' => $insert,
                        'date' => $form_state->getValue('date'),
                        'currency' => $form_state->getValue('currency'),
                        'type' => 'stax_collect_aid',
                    )
            );
            
            if($journal->credit <> $journal->debit) {
                $msg = 'debit: ' . $journal->debit . ' <> ' . 'credit: ' . $journal->credit;
                drupal_set_message(t('Error journal record (@aid)', array('@aid' => $msg)), 'error');
            }
            
        } //if finance  
        //change quotation status
        Database::getConnection('external_db', 'external_db')->update('ek_sales_quotation')
                ->fields(array('status' => 2))
                ->condition('serial', $form_state->getValue('quotation_serial'))
                ->execute();

        if (isset($insert)) {
            drupal_set_message(t('The invoice @r is recorded', array('@r' => $serial)), 'status');
            $form_state->setRedirect('ek_sales.invoices.list');
        }
    }

}
