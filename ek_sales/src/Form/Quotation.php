<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\Quotation.
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
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to create and edit quotation.
 */
class Quotation extends FormBase {

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
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $this->settings = new \Drupal\ek_finance\FinanceSettings();
        }
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
                ->query("SELECT * FROM {ek_sales_quotation_settings}")
                ->fetchAll();
        $revision = NULL;

        if (isset($id) && !$id == NULL) {
            //edit
            $data = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * FROM {ek_sales_quotation} where id=:id", array(':id' => $id))
                    ->fetchObject();
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * FROM {ek_sales_quotation_details} where serial=:id ORDER BY id", array(':id' => $data->serial));

            $query = "SELECT DISTINCT revision FROM {ek_sales_quotation_details} WHERE serial=:s order by revision DESC";
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
            if ($incoterm[0] != '0') {
                $incoterm_name = $incoterm[0];
                $incoterm_rate = $incoterm[1];
            } else {
                $incoterm_name = '';
                $incoterm_rate = 0;
            }
            $taxable = 0;
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
            $incoterm_name = '';
            $incoterm_rate = 0;
            $tax_name = '';
            $tax_rate = 0;
            
        }


        $baseCurrency = '';
        $currenciesList = '';
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $CurrencyOptions = \Drupal\ek_finance\CurrencyData::listcurrency(1);
            $baseCurrency = $this->settings->get('baseCurrency');
            $currenciesList = \Drupal\ek_finance\CurrencyData::currencyRates();
        }

        $form['settings'] = array(
            '#type' => 'hidden',
            '#value' => serialize($settings),
        );

        $url = Url::fromRoute('ek_sales.quotations.list', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@url" >List</a>', array('@url' => $url)),
        );
        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => (isset($id) || $form_state->get('num_items') > 0) ? FALSE : TRUE,
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
                '#options' => \Drupal\ek_projects\ProjectData::listprojects(0),
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
            '#options' => array('0' => t('not applicable'), 'CIF' => 'CIF', 'CIP' => 'CIP', 'FOB' => 'FOB'),
            '#default_value' => isset($incoterm_name) ? $incoterm_name : '0',
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
            '#description' => '%',
            '#title_display' => 'after',
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
            '#description' => '%',
            '#title_display' => 'after',
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
            '#rows' => 2,
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
        );


        $form['items']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            //'#limit_validation_errors' => array(),
            '#submit' => array(array($this, 'addForm')),
            '#prefix' => "<div id='add' class='right'>",
            '#suffix' => '</div>',
            '#attributes' => array('class' => array('button--add')),
            '#states' => array(
                'invisible' => array(
                    "select[name='head']" => array('value' => ''),
                ),
            ),
        );


        if ($this->moduleHandler->moduleExists('ek_products')) {
            $header = array(
                'description' => array(
                    'data' => $this->t('Item'),
                    'id' => ['tour-item1'],
                ),
                'priceType' => array(
                    'data' => $this->t('Price type'),
                    'id' => ['tour-item2'],
                ),
                'quantity' => array(
                    'data' => $this->t('Quantity'),
                    'id' => ['tour-item3'],
                ),
                'value' => array(
                    'data' => $this->t('Unit price'),
                    'id' => ['tour-item4'],
                ),
                'tax' => array(
                    'data' => $this->t('Tax'),
                    'id' => ['tour-item5'],
                ),
                'total' => array(
                    'data' => $this->t('Total'),
                    'id' => ['tour-item6'],
                ),
                'delete' => array(
                    'data' => $this->t('Delete'),
                    'id' => ['tour-item7'],
                ),
            );
        } else {
            $header = array(
                'description' => array(
                    'data' => $this->t('Item'),
                    'id' => ['tour-item1'],
                ),
                'quantity' => array(
                    'data' => $this->t('Quantity'),
                    'id' => ['tour-item3'],
                ),
                'value' => array(
                    'data' => $this->t('Unit price'),
                    'id' => ['tour-item4'],
                ),
                'tax' => array(
                    'data' => $this->t('Tax'),
                    'id' => ['tour-item5'],
                ),                
                'total' => array(
                    'data' => $this->t('Total'),
                    'id' => ['tour-item6'],
                ),
                'delete' => array(
                    'data' => $this->t('Delete'),
                    'id' => ['tour-item7'],
                ),
            );
        }

        $form['items']['itemTable'] = array(
            '#tree' => TRUE,
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => array(),
            '#attributes' => array('id' => 'itemTable'),
            '#empty' => '',
        );

        $rows = $form_state->getValue('itemTable');
        $z = 0;
        if (isset($detail)) {
            //edition mode
            //list current items
            $grandtotal = 0;
            
            while ($d = $detail->fetchObject()) {
                $n++;
                $z++;
                $link = NULL;
                $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';
                $trClass = 'tr' . $n;
                if($d->opt == 1) {
                  $taxable += ($d->value * $d->unit);
                }           
                $total = number_format($d->value * $d->unit, 2);
                $grandtotal += $d->value * $d->unit;

                if ($d->itemdetail == "" && $this->moduleHandler->moduleExists('ek_products') )  {
                    $item = \Drupal\ek_products\ItemData::item_bycode($d->itemid); 
                    if(isset($item)) {
                       $name = $item; 
                       $link = \Drupal\ek_products\ItemData::geturl_bycode($d->itemid, TRUE);
                    } else {
                       $name =  $d->item;
                    }
                  } else {
                    $name = $d->item;
                  }

                $form['description'] = array(
                    '#id' => 'description-' . $n,
                    '#type' => 'textfield',
                    '#size' => 40,
                    '#maxlength' => 255,
                    '#attributes' => array('placeholder' => t('item')),
                    '#default_value' => $name,
                    '#field_prefix' => "<span class='badge'>". $z ."</span>",
                    '#field_suffix' => isset($link) ? "<span class='badge'>". $link ."</span>" : '',
                    '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                );
                if ($this->moduleHandler->moduleExists('ek_products')) {
                    //default data is record

                    if (isset($rows[$n]['description'])) {
                        //default data is input
                        $itemDescription = $rows[$n]['description'];
                        $itemValue = $rows[$n]['value'];
                        $thisItemId = explode(' ', $itemDescription);
                        $thisItemId = trim($thisItemId[1]);
                        $priceType = $rows[$n]['priceType'];
                    } else {
                        //default data is record
                        $itemDescription = $name;
                        $itemValue = $d->value;
                        $thisItemId = explode(' ', $itemDescription);
                        if(isset($thisItemId[1])){
                            $thisItemId = trim($thisItemId[1]);
                            $priceType = \Drupal\ek_products\ItemData::item_sell_price_type($thisItemId, $itemValue);
                        } else {
                            $priceType = 0;
                        }
                        
                    }

                    if ($priceType != 0) {
                        $sellPrice = \Drupal\ek_products\ItemData::item_sell_price($thisItemId, $priceType);
                        $disabled = TRUE;
                    } else {
                        $sellPrice = isset($rows[$n]['value']) ? $rows[$n]['value'] : $d->value;
                        $disabled = FALSE;
                    }
                    $form['priceType'] = array(
                        '#id' => 'priceType-' . $n,
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => array('0' => '-', '1' => t('normal'), '2' => t('promo'), '3' => t('discount')),
                        '#attributes' => array('class' => array('amount')),
                        '#default_value' => $priceType,
                        //'#required' => TRUE,
                        '#ajax' => array(
                            'callback' => array($this, 'put_price'),
                            'wrapper' => 'v' . $n,
                        ),
                    );
                } else {
                    $form['priceType'] = '';
                }
                $form['quantity'] = array(
                    '#id' => 'quantity' . $n,
                    '#type' => 'textfield',
                    '#size' => 8,
                    '#maxlength' => 30,
                    '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                    '#default_value' => $d->unit,
                    '#required' => TRUE,
                );
                $form['value'] = array(
                    '#id' => 'value' . $n,
                    '#type' => 'textfield',
                    '#size' => 12,
                    '#maxlength' => 250,
                    '#default_value' => isset($sellPrice) ? $sellPrice : $d->value,
                    '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                    '#disabled' => $disabled,
                    '#prefix' => "<div class='cell' id='v$n'>",
                    '#suffix' => '</div>',
                );
                $form['tax'] = array(
                    '#id' => 'optax' . $n,
                    '#type' => 'checkbox',
                    '#default_value' => $d->opt,
                    '#attributes' => array(
                        'title' => t('tax include'),
                        'class' => array('amount'),
                    ),
                );
                $form['total'] = array(
                    '#id' => 'total' . $n,
                    '#type' => 'textfield',
                    '#size' => 12,
                    '#maxlength' => 250,
                    '#default_value' => $total,
                    '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
                );
                $form['delete'] = array(
                    '#id' => 'del' . $n,
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#attributes' => array(
                        'title' => t('delete on save'),
                        'onclick' => "jQuery('.tr" . $n . "').toggleClass('delete');",
                        'class' => array('amount'),
                    ),
                );
                //built edit rows for table
                $form['items']['itemTable'][$n] = array(
                    'description' => &$form['description'],
                    'priceType' => &$form['priceType'],
                    'quantity' => &$form['quantity'],
                    'value' => &$form['value'],
                    'tax' => &$form['tax'],
                    'total' => &$form['total'],
                    'delete' => &$form['delete'],
                );

                $form['items']['itemTable']['#rows'][$n] = array(
                    'data' => array(
                        array('data' => &$form['description']),
                        array('data' => &$form['priceType']),
                        array('data' => &$form['quantity']),
                        array('data' => &$form['value']),
                        array('data' => &$form['tax']),
                        array('data' => &$form['total']),
                        array('data' => &$form['delete']),
                    ),
                    'id' => array($n),
                    'class' => [$rowClass, $trClass],
                );
                unset($form['description']);
                unset($form['priceType']);
                unset($form['quantity']);
                unset($form['value']);
                unset($form['tax']);
                unset($form['total']);
                unset($form['delete']);

                //second row edit to be insterted based on settings
                if ($settings[2]->active == 1 || $settings[1]->active == 1) {
                    $n++;
                    $form['value'] = array(
                        '#value' => 'secondRow',
                        '#id' => 'value' . $n,
                        '#type' => 'hidden',
                        '#attributes' => ['id' => ['value' . $n]],
                    );
                    if ($settings[1]->active == 1) {
                        //add origin field
                        $form["column_2"] = array(
                            '#type' => 'textfield',
                            '#id' => 'column_2' . $n,
                            '#size' => 30,
                            '#maxlength' => 255,
                            '#default_value' => $d->column_2,
                            '#attributes' => array('placeholder' => $settings[1]->name),
                        );
                    } else {
                        $form["column_2"] = ['#type' => 'item'];
                    }
                    if ($settings[2]->active == 1) {
                        //add reference field
                        $form["column_3"] = array(
                            '#type' => 'textfield',
                            '#id' => 'column_3' . $n,
                            '#size' => 30,
                            '#maxlength' => 255,
                            '#default_value' => $d->column_3,
                            '#attributes' => array('placeholder' => $settings[2]->name),
                        );
                    } else {
                        $form["column_3"] = ['#type' => 'item'];
                    }
                    //built second rows for table
                    $form['items']['itemTable'][$n] = array(
                        'description' => &$form['column_2'],
                        'priceType' => ['#type' => 'item'],
                        'quantity' => &$form['column_3'],
                        'value' => &$form['value'],
                        'tax' => ['#type' => 'item'],
                        'total' => ['#type' => 'item'],
                        'delete' => ['#type' => 'item'],
                    );

                    $form['items']['itemTable']['#rows'][$n] = array(
                        'data' => array(
                            array('data' => &$form['column_2']),
                            array('data' => &$form['column_3'], 'colspan' => ['2']),
                            array('data' => &$form['value']),
                        ),
                        'id' => array($n),
                        'class' => [$rowClass, $trClass],
                    );
                    unset($form['column_2']);
                    unset($form['column_3']);
                    unset($form['value']);
                }
            }//while
        } //details of current records


        if (isset($detail)) {
            // reset the new rows items
            $max = $form_state->get('num_items') + $n;
            $next = $n + 1;
        } else {
            $max = $form_state->get('num_items');
            $next = 1;
            $n = 0;
        }

        for ($i = $next; $i <= $max; $i++) {
            $n++;
            $z++;
            $form['description'] = array(
                '#id' => 'description-' . $n,
                '#type' => 'textfield',
                '#size' => 40,
                '#maxlength' => 255,
                '#field_prefix' => "<span class='badge'>". $z ."</span>",
                '#attributes' => array('placeholder' => t('item')),
                '#autocomplete_route_name' => 'ek.look_up_item_ajax',
            );
            if ($this->moduleHandler->moduleExists('ek_products')) {

                //default data is input
                $itemDescription = $rows[$n]['description'];
                $itemValue = $rows[$n]['value'];
                $thisItemId = explode(' ', $itemDescription);
                $thisItemId = (isset($thisItemId[1])) ? trim($thisItemId[1]) : 0;
                $priceType = $rows[$n]['priceType'];
                if ($priceType != 0) {
                    $sellPrice = \Drupal\ek_products\ItemData::item_sell_price($thisItemId, $priceType);
                    $disabled = TRUE;
                } else {
                    $sellPrice = isset($rows[$n]['value']) ? $rows[$n]['value'] : 0;
                    $disabled = FALSE;
                }
                $form['priceType'] = array(
                    '#id' => 'priceType-' . $n,
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => array('0' => '-', '1' => t('normal'), '2' => t('promo'), '3' => t('discount')),
                    '#attributes' => array('class' => array('amount')),
                    '#ajax' => array(
                        'callback' => array($this, 'put_price'),
                        'wrapper' => 'v' . $n,
                    ),
                );
            } else {
                $form['priceType'] = '';
            }
            $form['quantity'] = array(
                '#id' => 'quantity' . $n,
                '#type' => 'textfield',
                '#size' => 8,
                '#maxlength' => 30,
                '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                '#required' => TRUE,
            );
            $form['value'] = array(
                '#id' => 'value' . $n,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => isset($sellPrice) ? $sellPrice : 0,
                '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                '#disabled' => $disabled,
                '#prefix' => "<div class='cell' id='v$n'>",
                '#suffix' => '</div>',
            );
            $form['tax'] = array(
                '#id' => 'optax' . $n,
                '#type' => 'checkbox',
                '#attributes' => array(
                    'title' => t('tax include'),
                    'class' => array('amount'),
                ),
            );
            $form['total'] = array(
                '#id' => 'total' . $n,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['delete'] = array();
            //built edit rows for table
            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'priceType' => &$form['priceType'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['priceType']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['tax']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['priceType']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);

            //second row to be insterted based on settings
            if ($settings[2]->active == 1 || $settings[1]->active == 1) {
                $n++;
                $form['value'] = array(
                    '#value' => 'secondRow',
                    '#id' => 'value' . $n,
                    '#type' => 'hidden',
                    '#attributes' => ['id' => ['value' . $n]],
                );
                if ($settings[1]->active == 1) {
                    //add origin field
                    $form["column_2"] = array(
                        '#type' => 'textfield',
                        '#id' => 'column_2' . $n,
                        '#size' => 30,
                        '#maxlength' => 255,
                        '#default_value' => '',
                        '#attributes' => array('placeholder' => $settings[1]->name),
                    );
                } else {
                    $form["column_2"] = ['#type' => 'item'];
                }
                if ($settings[2]->active == 1) {
                    //add reference field
                    $form["column_3"] = array(
                        '#type' => 'textfield',
                        '#id' => 'column_3' . $n,
                        '#size' => 30,
                        '#maxlength' => 255,
                        '#default_value' => '',
                        '#attributes' => array('placeholder' => $settings[2]->name),
                    );
                } else {
                    $form["column_3"] = ['#type' => 'item'];
                }
                //built second rows for table
                $form['items']['itemTable'][$n] = array(
                    'description' => &$form['column_2'],
                    'priceType' => ['#type' => 'item'],
                    'quantity' => &$form['column_3'],
                    'value' => &$form['value'],
                    'tax' => ['#type' => 'item'],
                    'total' => ['#type' => 'item'],
                    'delete' => ['#type' => 'item'],
                );

                $form['items']['itemTable']['#rows'][$n] = array(
                    'data' => array(
                        array('data' => &$form['column_2']),
                        array('data' => &$form['column_3'], 'colspan' => ['2']),
                        array('data' => &$form['value']),
                    ),
                    'id' => array($n),
                        //'class' => [$rowClass, $trClass],
                );
                unset($form['column_2']);
                unset($form['column_3']);
                unset($form['value']);
            }
        }

        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => $n - 1,
            '#attributes' => array('id' => 'itemsCount'),
        );


// FOOTER

        if (($form_state->get('num_items') > 0) || isset($detail)) {

            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    '#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                    '#attributes' => array('class' => array('button--remove')),
                    '#prefix' => "<div id='remove' class='right'>",
                    '#suffix' => '</div>',
                );
            }

            //total
            $n = $n + 2;
            $form['description'] = array(
                '#type' => 'item',
                '#markup' => t('Total') . " " . "<span id='convertedValue' class='badge'></span>"
            );
            $form['priceType'] = ['#type' => 'item'];
            $form['quantity'] = ['#type' => 'item'];
            $form['value'] = array('#type' => 'hidden', '#value' => 'footer', '#attributes' => ['id' => ['value' . $n]],);
            $form['tax'] = ['#type' => 'item'];
            $form['total'] = array(
                '#id' => 'itemsTotal',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => isset($grandtotal) ? number_format($grandtotal, 2) : 0,
                '#attributes' => array('placeholder' => t('total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['delete'] = array(
                '#item' => "",
            );
            //built total rows for table
            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'priceType' => &$form['priceType'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['priceType']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['tax']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['priceType']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);

        //incoterm       
            $n++;
            $form['description'] = array(
                '#type' => 'item',
                '#markup' => ($incoterm_name != 0) ? t('Incoterm applied') . " " . $incoterm_name : t('Incoterm applied'),
            );
            $form['priceType'] = ['#type' => 'item'];
            $form['quantity'] = array('#type' => 'hidden', '#value' => '1', '#attributes' => ['id' => ['quantity' . $n]],);
            $form['value'] = array('#type' => 'hidden', '#value' => 'incoterm', '#attributes' => ['id' => ['value' . $n]],);
            $form['tax'] = array(
                '#id' => 'optax' . $n,
                '#type' => 'checkbox',
                '#attributes' => array(
                    'title' => t('tax include'),
                    'class' => array('amount'),
                ),
            );
            $form['total'] = array(
                '#id' => 'incotermValue',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => number_format($grandtotal * $incoterm_rate / 100, 2),
                '#attributes' => array('placeholder' => t('incoterm'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['delete'] = ['#type' => 'item'];
            //built total rows for table

            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'priceType' => &$form['priceType'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['priceType']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['tax']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['priceType']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);

        //tax   
            $n++;
            $taxamount = round(($taxable * $tax_rate/100), 2) ;
            $form['description'] = array(
                '#type' => 'item',
                '#markup' => t('Tax applied')
            );
            $form['priceType'] = ['#type' => 'item'];
            $form['quantity'] = ['#type' => 'item'];
            $form['value'] = array('#type' => 'hidden', '#value' => 'footer', '#attributes' => ['id' => ['value' . $n]],);
            $form['total'] = array(
                '#id' => 'taxValue',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => number_format($taxamount, 2),
                '#attributes' => array('placeholder' => t('tax'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['delete'] = ['#type' => 'item'];
            //built total rows for table

            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'priceType' => &$form['priceType'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['priceType']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['tax']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['priceType']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);

            //grand total
            $n++;
            $form['description'] = array(
                '#type' => 'item',
                '#markup' => t('Total quotation value')
            );
            $form['priceType'] = ['#type' => 'item'];
            $form['quantity'] = ['#type' => 'item'];
            $form['value'] = array('#type' => 'hidden', '#value' => 'footer', '#attributes' => ['id' => ['value' . $n]]);
            $form['total'] = array(
                '#id' => 'totalWithTax',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => number_format($grandtotal + ($grandtotal * $incoterm_rate / 100) + $taxamount, 2),
                '#attributes' => array('placeholder' => t('total quotation'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['delete'] = ['#type' => 'item'];
            //built total rows for table

            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'priceType' => &$form['priceType'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['priceType']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['tax']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['priceType']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);

            $redirect = array(0 => t('view list'), 1 => t('print'));
            $form['actions']['redirect'] = array(
                '#type' => 'radios',
                '#title' => t('Next'),
                '#default_value' => 0,
                '#options' => $redirect,
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => array('class' => array('button--record')),
            );
        }


        $form['#attached'] = array(
            'drupalSettings' => array('currencies' => $currenciesList, 'baseCurrency' => $baseCurrency, 'action' => 'build'),
            'library' => array('ek_sales/ek_sales.quotation'),
        );

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
        $triggering_element = $form_state->getTriggeringElement();
        $i = explode('-', $triggering_element['#id']);
        return $form['items']['itemTable'][$i[1]]['value'];
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


        if ($form_state->getValue('incoterm') != '0' && ($form_state->getValue('term_rate') == '' || !is_numeric($form_state->getValue('term_rate')))) {
            $form_state->setErrorByName('term_rate', $this->t('Incoterm rate error'));
        }

        if (!$form_state->getValue('tax') != '' && ($form_state->getValue('tax_rate') == '' || !is_numeric($form_state->getValue('tax_rate')))) {
            $form_state->setErrorByName('taxvalue', $this->t('Tax value error'));
        }

        $rows = $form_state->getValue('itemTable');
        if (!empty($rows)) {
            foreach ($rows as $key => $row) {
                if ($row['value'] != 'footer' && $row['value'] != 'secondRow'&& $row['value'] != 'incoterm') {
                    if ($row['description'] == '') {
                        $form_state->setErrorByName("itemTable][$key][description", $this->t('Item @n is empty', array('@n' => $key)));
                    }
                    if ($row['quantity'] == '' || !is_numeric($row['quantity'])) {
                        $form_state->setErrorByName("itemTable][$key][quantity", $this->t('there is no quantity for item @n', array('@n' => $row['description'])));
                    }
                    if ($row['value'] == '' || !is_numeric($row['value'])) {
                        $form_state->setErrorByName("itemTable][$key][value", $this->t('there is no value for item @n', array('@n' => $row['description'])));
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $settings = unserialize($form_state->getValue('settings'));
        if ($form_state->getValue('new_quotation') && $form_state->getValue('new_quotation') == 1) {
            //create new serial No
            $quid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_sales_quotation}")
                    ->fetchField();
            $quid++;
            $short = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                    ->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))
                    ->fetchField();
            $serial = ucwords($short) . "-QU-" . $date . "-" . ucwords($sup) . "-" . $quid;
            $revision = 0;
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            // if new revision, keep current and insert new data, else delete current
            $query = "SELECT DISTINCT revision FROM {ek_sales_quotation_details} WHERE serial=:s order by revision DESC";
            $revision = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $serial))->fetchField();

            if ($form_state->getValue('revision') == 'new') {
                $revision++;
            } else {
                //keep current revision and change data only
                $delete = Database::getConnection('external_db', 'external_db')
                        ->delete('ek_sales_quotation_details')
                        ->condition('serial', $serial)
                        ->execute();
                $quid = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT id from {ek_sales_quotation} where serial=:s', array(':s' => $serial))
                        ->fetchField();
            }
        }


// Items  

        $line = 0;
        $total = 0;
        $sum = 0;

        $rows = $form_state->getValue('itemTable');
        if (!empty($rows)) {
            foreach ($rows as $key => $row) {
                if ($row['value'] != 'footer' && $row['value'] != 'secondRow'&& $row['value'] != 'incoterm') {
                    if ($row['delete'] != 1) {

                        $column_2 = '';
                        $column_3 = '';
                        if ($this->moduleHandler->moduleExists('ek_products')) {
                            //verify if item is in the DB if not just record input
                            $item = explode(" ", $row['description']);
                            $id = trim($item[0]);
                            if (isset($item[1])) {
                                $itemCode = trim($item[1]);
                            }
                            $check = \Drupal\ek_products\ItemData::item_description_bycode($itemCode, 1);

                            if ($check) {
                                $itemId = $itemCode;
                                $itemDetails = '';
                            } else {
                                $itemId = '';
                                $itemDetails = Xss::filter($row['description']);
                            }
                        } else {
                            //use input from user
                            $itemId = '';
                            $itemDetails = Xss::filter($row['description']);
                        }

                        $line = (round($row['quantity'] * $row['value'], 2));
                        $sum = $sum + $line;
                        if ($settings[2]->active == 1 || $settings[1]->active == 1) {
                            $nextRow = $key + 1;
                            $column_2 = Xss::filter($rows[$nextRow]['description']);
                            $column_3 = Xss::filter($rows[$nextRow]['quantity']);
                        }

                        $fields = array('serial' => $serial,
                            'itemid' => $itemId, // code if item exist 
                            'itemdetails' => $itemDetails,
                            'unit' => $row['quantity'],
                            'value' => $row['value'],
                            'total' => $line,
                            'opt' => $row['tax'],
                            'revision' => $revision,
                            'column_2' => $column_2,
                            'column_3' => $column_3,
                        );

                        $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_quotation_details')
                                ->fields($fields)
                                ->execute();
                    }//del
                }//foot
            }//foeach
        }//empty
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
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_sales_quotation')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_sales_quotation')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $quid;
        }

        Cache::invalidateTags(['project_page_view']);
        if (isset($insert) || isset($update)) {
            \Drupal::messenger()->addStatus(t('The quotation is recorded'));

            switch ($form_state->getValue('redirect')) {
                case 0 :
                    $form_state->setRedirect('ek_sales.quotations.list');
                    break;
                case 1 :
                    $form_state->setRedirect('ek_sales.quotations.print_share', ['id' => $reference]);
                    break;
            }
        }
    }

}
