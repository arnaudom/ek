<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\NewQuotation.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_products\ItemData;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;

/**
 * Provides a form to create and edit quotation.
 */
class NewQuotation extends FormBase {

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
        return 'ek_sales_new_quotation';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $settings = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_quotation_settings} ")
                ->fetchAll();
        $revision = NULL;

        if (isset($id) && !$id == NULL) {
            //edit
            $data = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * from {ek_quotation} where id=:id", array(':id' => $id))
                    ->fetchObject();
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * from {ek_quotation_details} where serial=:id ORDER BY id", array(':id' => $data->serial));

            $query = "SELECT DISTINCT revision FROM {ek_quotation_details} WHERE serial=:s order by revision DESC";
            $revision = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $data->serial))->fetchField();

            $form['edit_quotation'] = array(
                '#type' => 'item',
                '#markup' => t('Quotation ref. @q', array('@q' => $data->serial)),
            );
            $form['serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );

            $n = 0;
            $form_state->set('current_items', 0);
            if (!$form_state->get('num_items'))
                $form_state->set('num_items', 0);

            $form_state->setValue('head', $data->header);

            $incoterm = explode('|', $data->incoterm);
            if ($incoterm[0] <> 'na') {
                $incoterm_name = $incoterm[0];
                $incoterm_rate = $incoterm[1];
            } else {
                $incoterm_name = 'na';
                $incoterm_rate = 0;
            }

            $tax = explode('|', $data->tax);
            if ($tax[0] <> '') {
                $tax_name = $tax[0];
                $tax_rate = $tax[1];
            } else {
                $tax_name = '';
                $tax_rate = 0;
            }

            $form_state->setRebuild();
        } else {
            //new
            $form['new_quotation'] = array(
                '#type' => 'hidden',
                '#value' => 1,
            );
            $grandtotal = 0;
            $taxable = 0;
            $n = 0;
            $form_state->setValue('coid', '');
            $form_state->setRebuild();
            $AidOptions = array();
            $incoterm_name = 'na';
            $incoterm_rate = 0;
            $tax_name = '';
            $tax_rate = 0;
        }



        if ($this->moduleHandler->moduleExists('ek_finance')) {

            $CurrencyOptions = CurrencyData::listcurrency(1);
        }

        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => ($form_state->getValue('count') > 2) ? FALSE : TRUE,
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
            '#title' => t('Header'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'set_coid'),
                'wrapper' => 'add',
            ),
        );


        $form['options']['allocation'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
            '#title' => t('Allocated'),
            '#description' => t('select an entity for which the quotation is done'),
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
                    '#title' => t('Client'),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $new = "<a title='" . t('new') . "' href='" . $link . "'>" . t('client') . "</a>";
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
            //'#prefix' => "<div class='container-inline'>",
            '#title' => t('Date'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );


        $form['options']['title'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 255,
            '#default_value' => isset($data->title) ? $data->title : NULL,
            '#title' => t('Title'),
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
        }




        $form['options']['incoterm'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#title' => t('Incoterms'),
            '#options' => array('na' => t('not applicable'), 'CIF' => 'CIF', 'CIP' => 'CIP', 'FOB' => 'FOB'),
            '#default_value' => isset($incoterm_name) ? $incoterm_name : 'na',
            '#prefix' => "<div class='container-inline'>",
                /*
                  '#ajax' => array(
                  'callback' => array($this, 'term_rate'),
                  'wrapper' => 'term',
                  ),
                 */
        );



        $form['options']["term_rate"] = array(
            '#type' => 'textfield',
            '#id' => 'term_rate',
            '#size' => 8,
            '#default_value' => $incoterm_rate,
            '#maxlength' => 10,
            '#attributes' => array('placeholder' => t('rate') . ' (%)', 'title' => t('rate') . ' (%)', 'class' => array('amount')),
            '#prefix' => "<div id='term'>",
            '#suffix' => '</div></div>',
            '#states' => array(
                'invisible' => array(
                    "select[name='incoterm']" => array('value' => 'na'),
                ),
            ),
        );

        $form['options']['tax'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 50,
            '#title' => t('Add tax'),
            '#default_value' => isset($tax_name) ? $tax_name : NULL,
            '#attributes' => array('placeholder' => t('description, ex. VAT')),
            '#prefix' => "<div class='container-inline'>",
        );

        $form['options']["tax_rate"] = array(
            '#type' => 'textfield',
            '#id' => 'tax_rate',
            '#size' => 8,
            '#maxlength' => 10,
            '#default_value' => isset($tax_rate) ? $tax_rate : 0,
            '#attributes' => array('placeholder' => t('rate') . ' (%)', 'title' => t('rate') . ' (%)', 'class' => array('amount')),
            '#prefix' => "<div id='tax_'>",
            '#suffix' => '</div></div>',
            '#states' => array(
                'invisible' => array(
                    "input[name='tax']" => array('value' => ''),
                ),
            ),
        );

        $form['options']['comment'] = array(
            '#type' => 'textarea',
            '#rows' => 3,
            '#default_value' => isset($data->comment) ? $data->comment : NULL,
            '#prefix' => "<div class='container-inline'>",
            '#suffix' => "</div>",
            '#attributes' => array('placeholder' => t('comment')),
        );


        if ($revision <> NULL) {

            $form['options']['revision'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array($revision => $revision, 'new' => t('new revision')),
                '#required' => TRUE,
                '#default_value' => $revision,
                '#title' => t('current revision'),
            );
        }

        $form['items'] = array(
            '#type' => 'details',
            '#title' => $this->t('Items'),
            '#open' => TRUE,
            '#attributes' => '',
        );


        $form['items']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            //'#limit_validation_errors' => array(),
            '#submit' => array(array($this, 'addForm')),
            '#prefix' => "<div id='add' class='right'>",
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(
                    "select[name='head']" => array('value' => ''),
                ),
            ),
        );


        if ($this->moduleHandler->moduleExists('ek_products')) {

            $headerline = "<div class='table'  id='quotation_form_items'>
                  <div class='row'>
                      <div class='cell'>" . t("Items") . "</div>
                      <div class='cell'>" . t("Price type") . "</div>
                      <div class='cell'>" . t("Quantities") . "</div>
                      <div class='cell'>" . t("Unit price") . "</div>
                      <div class='cell'>" . t("delete") . "</div>

                      <div class='cell right'>" . t("Line total") . "</div>
                   ";
        } else {
            $headerline = "<div class='table'  id='quotation_form_items'>
                  <div class='row'>
                      <div class='cell'>" . t("Items") . "</div>
                      <div class='cell'>" . t("Quantities") . "</div>
                      <div class='cell'>" . t("Unit price") . "</div>
                      <div class='cell'>" . t("delete") . "</div>

                      <div class='cell right'>" . t("Line total") . "</div>
                   ";
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
                $form_state->setRebuild();

                if ($d->itemdetails == "" && $this->moduleHandler->moduleExists('ek_products')) {
                    //default is itemid reference if no custom input
                    $name = ItemData::item_bycode($d->itemid);
                } else {
                    //use userinput
                    $name = $d->itemdetails;
                }

                $cl = ($form_state->getValue("delete" . $n) == 1) ? 'delete' : 'current';

                $form['items']["itemid$n"] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 255,
                    '#default_value' => $name,
                    '#attributes' => array('placeholder' => t('item')),
                    '#prefix' => "<div class='row $cl' id='row$n'><div class='cell'>",
                    '#suffix' => '</div>',
                    '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                );

                if ($this->moduleHandler->moduleExists('ek_products')) {
                    $thisitemid = explode(' ', $form_state->getValue("itemid$n"));
                    $thisitemid = trim($thisitemid[1]);
                    if ($form_state->getValue("price_type-$n") <> '0' && $form_state->getValue("price_type-$n") <> NULL) {
                        $item = ItemData::item_sell_price($thisitemid, $form_state->getValue("price_type-$n"));
                        $form_state->setValue('value' . $n, $item);
                        $form_state->setRebuild();
                        $disabled = TRUE;
                    } else {
                        $disabled = FALSE;
                    }

                    /**/
                    if ($form_state->getValue('value' . $n)) {
                        $item = ItemData::item_sell_price_type($thisitemid, $form_state->getValue('value' . $n));
                        //$form_state->setValue('price_type-'.$n, $item);
                    } else {
                        $item = ItemData::item_sell_price_type($d->itemid, $d->value);
                        //$form_state->setValue('price_type-'.$n, $item) ;
                    }

                    $form['items']["price_type-$n"] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => array('0' => '-', '1' => t('normal'), '2' => t('promo'), '3' => t('discount')),
                        '#value' => $item,
                        '#default' => $item,
                        '#prefix' => "<div class='cell'>",
                        '#suffix' => '</div>',
                        '#attributes' => array('class' => array('amount')),
                        '#ajax' => array(
                            'callback' => array($this, 'put_price'),
                            'wrapper' => 'v' . $n,
                        ),
                    );
                }

                $form['items']["unit$n"] = array(
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
                    '#maxlength' => 20,
                    '#default_value' => $form_state->getValue('value' . $n) ? $form_state->getValue('value' . $n) : $d->value,
                    //'#value' => $form_state->getValue('value'.$n) ? $form_state->getValue('value'.$n) : $d->value,
                    '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                    '#disabled' => $disabled,
                    '#prefix' => "<div class='cell' id='v$n'>",
                    '#suffix' => '</div>',
                );

                $form['items']["delete$n"] = array(
                    '#type' => 'checkbox',
                    '#id' => 'del' . $n,
                    '#attributes' => array('title' => t('delete on save'), 'class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );


                $total = number_format($d->value * $d->unit, 2);
                $grandtotal += $d->value * $d->unit;
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

                if ($settings[1]->active == 1) {
                    //add origin field
                    $form['items']["column_2$n"] = array(
                        '#type' => 'textfield',
                        '#id' => 'column_2' . $n,
                        '#size' => 50,
                        '#maxlength' => 255,
                        '#default_value' => $d->column_2,
                        '#attributes' => array('placeholder' => $settings[1]->name),
                        '#prefix' => "<div class='row'><div class='cell'>",
                        '#suffix' => '</div>',
                    );
                }

                if ($settings[2]->active == 1) {
                    //add reference field
                    if ($settings[1]->active == 1) {
                        $prefix = "<div class='cell'>";
                    } else {
                        $prefix = "<div class='row'><div class='cell'>";
                    }

                    $form['items']["column_3$n"] = array(
                        '#type' => 'textfield',
                        '#id' => 'column_3' . $n,
                        '#size' => 30,
                        '#maxlength' => 255,
                        '#default_value' => $d->column_3,
                        '#attributes' => array('placeholder' => $settings[2]->name),
                        '#prefix' => $prefix,
                        '#suffix' => '</div>',
                    );
                }
                if ($settings[2]->active == 1 || $settings[1]->active == 1) {
                    $form['items']['close' . $n] = array(
                        '#type' => 'item',
                        '#markup' => '</div>',
                    );
                }
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

            $form['items']["itemid$i"] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => $form_state->getValue("itemid$i") ? $form_state->getValue("itemid$i") : NULL,
                '#attributes' => array('placeholder' => t('item')),
                '#prefix' => "<div class='container-inline'>",
                '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );

            if ($this->moduleHandler->moduleExists('ek_products')) {

                if ($form_state->getValue("price_type-$i") <> '0' && $form_state->getValue("price_type-$i") <> NULL) {
                    $thisitemid = explode(' ', $form_state->getValue("itemid$i"));
                    $thisitemid = $thisitemid[1];
                    $item = ItemData::item_sell_price($thisitemid, $form_state->getValue("price_type-$i"));
                    $form_state->setValue("value$i", $item);
                    $form_state->setRebuild();
                    $disabled = TRUE;
                } else {
                    $disabled = FALSE;
                }

                $form['items']["price_type-$i"] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => array('0' => '-', '1' => t('normal'), '2' => t('promo'), '3' => t('discount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                    '#attributes' => array('class' => array('amount')),
                    '#ajax' => array(
                        'callback' => array($this, 'put_price'),
                        'wrapper' => 'v' . $i,
                    ),
                );
            }

            $form['items']["unit$i"] = array(
                '#type' => 'textfield',
                '#id' => 'quantity' . $i,
                '#size' => 8,
                '#maxlength' => 255,
                '#default_value' => $form_state->getValue("unit$i") ? $form_state->getValue("unit$i") : NULL,
                '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );


            $form['items']["value$i"] = array(
                '#type' => 'textfield',
                '#id' => 'value' . $i,
                '#size' => 8,
                '#maxlength' => 255,
                '#default_value' => $form_state->getValue("value$i") ? $form_state->getValue("value$i") : 0,
                //'#value' => $form_state->getValue("value$i") ? $form_state->getValue("value$i") : 0,
                '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                '#disabled' => $disabled,
                '#prefix' => "<div class='cell' id='v$i'>",
                '#suffix' => '</div>',
            );

            $form['items']["delete$i"] = array(
                '#type' => 'item',
                '#attributes' => '',
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );


            $total = number_format($form_state->getValue("value$i") * $form_state->getValue("unit$i"), 2);
            $grandtotal += $form_state->getValue("value$i") * $form_state->getValue("unit$i");
            $form['items']["total$i"] = array(
                '#type' => 'textfield',
                '#id' => 'total' . $i,
                '#size' => 12,
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
                '#prefix' => "<div class='cell right'>",
                '#suffix' => '</div></div>',
            );

            if ($settings[1]->active == 1) {
                //add origin field
                $form['items']["column_2$i"] = array(
                    '#type' => 'textfield',
                    '#id' => 'column_2' . $i,
                    '#size' => 50,
                    '#maxlength' => 255,
                    '#default_value' => $form_state->getValue("column_2$i") ? $form_state->getValue("column_2$i") : NULL,
                    '#attributes' => array('placeholder' => $settings[1]->name),
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div>',
                );
            }

            if ($settings[2]->active == 1) {
                //add reference field
                if ($settings[1]->active == 1) {
                    $prefix = "<div class='cell'>";
                } else {
                    $prefix = "<div class='row'><div class='cell'>";
                }

                $form['items']["column_3$i"] = array(
                    '#type' => 'textfield',
                    '#id' => 'column_3' . $i,
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => $form_state->getValue("column_3$i") ? $form_state->getValue("column_3$i") : NULL,
                    '#attributes' => array('placeholder' => $settings[2]->name),
                    '#prefix' => $prefix,
                    '#suffix' => '</div>',
                );
            }
            if ($settings[2]->active == 1 || $settings[1]->active == 1) {
                $form['items']['close' . $i] = array(
                    '#type' => 'item',
                    '#markup' => '</div>',
                );
            }
        }

        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => isset($detail) ? $n - 1 + $form_state->get('num_items') : $form_state->get('num_items'),
            '#attributes' => array('id' => 'itemsCount'),
        );

        $form['items']['closetable'] = array(
            '#type' => 'item',
            '#markup' => '</div>',
        );


//
// FOOTER


        if (($form_state->get('num_items') > 0) || isset($detail)) {

            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    //'#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                    '#prefix' => "<div id='remove' class='right'>",
                    '#suffix' => '</div>',
                );
            }

            $form['items']['foot'] = array(
                '#type' => 'item',
                '#markup' => '<hr>',
                '#prefix' => "<div class='table' id='quotation_form_footer'>",
            );

            $form['items']["grandtotal"] = array(
                '#type' => 'textfield',
                '#id' => 'grandtotal',
                '#size' => 12,
                '#maxlength' => 255,
                '#value' => isset($grandtotal) ? number_format($grandtotal, 2) : 0,
                '#title' => t('Items total'),
                '#attributes' => array('placeholder' => t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div>',
            );



            $form['items']["incotermamount"] = array(
                '#type' => 'textfield',
                '#id' => 'incotermamount',
                '#title' => '',
                '#size' => 12,
                '#value' => number_format($grandtotal * $incoterm_rate / 100, 2),
                '#title' => t('Incoterm applied') . ' ' . $incoterm_name,
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => t('incoterm'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div>',
            );



            $taxamount = ( ($grandtotal + ($grandtotal * $incoterm_rate / 100) ) * $tax_rate / 100);

            $form['items']["taxamount"] = array(
                '#type' => 'textfield',
                '#id' => 'taxamount',
                '#title' => '',
                '#size' => 12,
                '#value' => number_format($taxamount, 2),
                '#title' => t('Tax applied'),
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => t('tax'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div>',
            );

            $form['items']["finaltotal"] = array(
                '#type' => 'textfield',
                '#id' => 'totaltax',
                '#size' => 12,
                '#maxlength' => 255,
                '#value' => number_format($grandtotal + ($grandtotal * $incoterm_rate / 100) + $taxamount, 2),
                '#title' => t('Total quotation value') . ' ' . $incoterm_name,
                '#attributes' => array('placeholder' => t('total quotation'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div></div>',
            );



            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
            );
        }

        $form['#attached']['library'][] = 'ek_sales/ek_sales.quotation';

        return $form;
    }

    /**
     * callback functions
     */
    public function set_coid(array &$form, FormStateInterface $form_state) {

        //return add button
        return $form['items']['actions']['add'];
    }

    /**
     * Callback
     */
    public function term_rate(array &$form, FormStateInterface $form_state) {

        return $form['options']["term_rate"];
    }

    /**
     * Callback
     */
    public function manual_term_rate(array &$form, FormStateInterface $form_state) {

        return $form['options']["term_rate"];
    }

    /**
     * Callback
     */
    public function tax_rate(array &$form, FormStateInterface $form_state) {

        return $form['options']["tax_rate"];
    }

    /**
     * Callback
     */
    public function manual_tax_rate(array &$form, FormStateInterface $form_state) {

        return $form['options']["tax_rate"];
    }

    /**
     * Callback
     */
    public function put_price(array &$form, FormStateInterface $form_state) {

        $i = explode('-', $_POST['_triggering_element_name']);
        //$form_state->setRebuild();
        return $form['items']["value" . $i[1]];
    }

    /**
     * Callback : Add item to form
     */
    public function addForm(array &$form, FormStateInterface $form_state) {

        if (!$form_state->get('num_items')) {
            $form_state->set('num_items', 1);
        } else {
            $c = $form_state->get('num_items') + 1;
            $form_state->set('num_items', $c);
        }


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            //$form_state->set('AidOptions', AidList::listaid($form_state->getValue('head'), array(1,5,6), 1 ));
        }
        $form_state->setRebuild();
    }

    /**
     * Callback: Remove item to form
     */
    public function removeForm(array &$form, FormStateInterface $form_state) {

        $c = $form_state->get('num_items') - 1;
        $form_state->set('num_items', $c);
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        /**/
        if ($form_state->getValue('incoterm') <> 'na' && ($form_state->getValue('term_rate') == '' || !is_numeric($form_state->getValue('term_rate')))) {
            $form_state->setErrorByName('term_rate', $this->t('Incoterm rate error'));
        }

        if (!$form_state->getValue('tax') <> '' && ($form_state->getValue('tax_rate') == '' || !is_numeric($form_state->getValue('tax_rate')))) {
            $form_state->setErrorByName('taxvalue', $this->t('Tax value error'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('new_quotation') && $form_state->getValue('new_quotation') == 1) {
            //create new serial No
            $quid = Database::getConnection('external_db', 'external_db')->query("SELECT count(id) from {ek_quotation}")->fetchField();
            $quid++;
            $short = Database::getConnection('external_db', 'external_db')->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))->fetchField();
            $serial = ucwords($short) . "-QU-" . $date . "-" . ucwords($sup) . "-" . $quid;
            $revision = 0;
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            // if new revision, keep current and insert new data, else delete current
            $query = "SELECT DISTINCT revision FROM {ek_quotation_details} WHERE serial=:s order by revision DESC";
            $revision = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $serial))->fetchField();

            if ($form_state->getValue('revision') == 'new') {
                $revision++;
            } else {
                //keep current revision and change data only
                $delete = Database::getConnection('external_db', 'external_db')->delete('ek_quotation_details')->condition('serial', $serial)->execute();
                $quid = Database::getConnection('external_db', 'external_db')->query('SELECT id from {ek_quotation} where serial=:s', array(':s' => $serial))->fetchField();
            }
        }


// Items  

        $line = 0;
        $total = 0;


        for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

            if (!$form_state->getValue("delete$n") == 1) {
                if ($this->moduleHandler->moduleExists('ek_products')) {
                    //verify if item is in the DB if not just record input

                    $item = explode(" ", $form_state->getValue("itemid$n"));
                    $id = trim($item[0]);
                    $code = trim($item[1]);

                    $check = ItemData::item_description_bycode($code, 1);

                    if ($check) {
                        $itemid = $code;
                        $itemdetails = '';
                    } else {
                        $input = $form_state->getValue("itemid$n");
                        $itemid = '';
                        $itemdetails = $input;
                    }
                } else {
                    //use input from user
                    $itemid = '';
                    $itemdetails = $form_state->getValue("itemid$n");
                }

                $line = (round($form_state->getValue("unit$n") * $form_state->getValue("value$n"), 2));
                $sum = $sum + $line;


                $fields = array('serial' => $serial,
                    'itemid' => $itemid, // code if item exist 
                    'itemdetails' => $itemdetails,
                    'unit' => $form_state->getValue("unit$n"),
                    'value' => $form_state->getValue("value$n"),
                    'total' => $line,
                    'opt' => $form_state->getValue("opt$n"),
                    'revision' => $revision,
                    'column_2' => $form_state->getValue("column_2$n"),
                    'column_3' => $form_state->getValue("column_3$n"),
                );

                $insert = Database::getConnection('external_db', 'external_db')->insert('ek_quotation_details')
                        ->fields($fields)
                        ->execute();
            }//if not delete
        }//for
//main
        if ($form_state->getValue('pcode') == '') {
            $pcode = 'n/a';
        } else {
            $pcode = $form_state->getValue('pcode');
        }
        if ($form_state->getValue('tax') != '' && is_numeric($form_state->getValue('tax_rate'))) {
            $tax = Xss::filter($form_state->getValue('tax')) . '|' . $form_state->getValue('tax_rate');
        } else {
            $tax = '';
        }
        if ($form_state->getValue('incoterm') <> '') {
            $incoterm = $form_state->getValue('incoterm') . '|' . $form_state->getValue('term_rate');
        } else {
            $incoterm = '';
        }

        $fields1 = array(
            'serial' => $serial,
            'header' => $form_state->getValue('head'),
            'allocation' => $form_state->getValue('allocation'),
            'status' => 0,
            'amount' => $sum,
            'currency' => $form_state->getValue('currency'),
            'date' => $form_state->getValue('date'),
            'title' => Xss::filter($form_state->getValue('title')),
            'pcode' => $pcode,
            'comment' => Xss::filter($form_state->getValue('comment')),
            'client' => $form_state->getValue('client'),
            'incoterm' => $form_state->getValue('terms'),
            'incoterm' => $incoterm,
            'tax' => $tax,
            'bank' => '',
            'principal' => '',
            'type' => '',
        );

        if ($form_state->getValue('new_quotation') && $form_state->getValue('new_quotation') == 1) {
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_quotation')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')->update('ek_quotation')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $quid;
        }

        Cache::invalidateTags(['project_page_view']);
        if (isset($insert) || isset($update))
            drupal_set_message(t('The quotation is recorded'), 'status');
        $form_state->setRedirect('ek_sales.quotations.list');
    }

}
