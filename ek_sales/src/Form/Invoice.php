<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\Invoice.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to create and edit invoices.
 */
class Invoice extends FormBase {

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
        $this->salesSettings = new \Drupal\ek_sales\SalesSettings();
        $this->moduleHandler = $module_handler;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $this->Financesettings = new \Drupal\ek_finance\FinanceSettings();
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
        return 'ek_sales_new_invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $clone = null) {
        $url = Url::fromRoute('ek_sales.invoices.list', [], [])->toString();
        $form['back'] = [
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', ['@url' => $url]),
        ];


        if (isset($id) && $id != null) {

            if ($clone != 'delivery') {
            // edit existing invoice
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 'i')
                    ->fields('i')
                    ->condition('id', $id)
                    ->execute();
                $data = $query->fetchObject();
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice_details', 'd')
                    ->fields('d')
                    ->condition('serial', $data->serial)
                    ->OrderBy('id');
                $detail = $query->execute();
                $fx_rate = round($data->amount / $data->amountbase, 4);
                $form_state->set('fx_rate', $fx_rate);
                if ($fx_rate != '1') {
                    $form_state->set('fx_rate_require', true);
                } else {
                    $form_state->set('fx_rate_require', false);
                }
                if($data->type == 5){
                    $options = array('1' => $this->t('Invoice'), '2' => $this->t('Commercial invoice'), '4' => $this->t('Credit note'),'5' => $this->t('Proforma invoice'));
                } else {
                    // can't change type with receivable
                    $options = array('1' => $this->t('Invoice'), '2' => $this->t('Commercial invoice'), '4' => $this->t('Credit note'));
                }
                
            } elseif ($clone == 'delivery' && $this->moduleHandler->moduleExists('ek_logistics')) {
                // convert delivery order into invoice with new serial No
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_delivery', 'd')
                    ->fields('d')
                    ->condition('id', $id)
                    ->execute();
                $data = $query->fetchObject();
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_delivery_details', 'dd')
                    ->fields('dd')
                    ->condition('serial', $data->serial)
                    ->OrderBy('id');
                $detail = $query->execute();
                $options = array('1' => $this->t('Invoice'), '2' => $this->t('Commercial invoice'));
            } 


            if ($clone != 'clone' && $clone != 'delivery') {
                //$options = array('1' => $this->t('Invoice'), '2' => $this->t('Commercial invoice'), '4' => $this->t('Credit note'));
                $form['edit_invoice'] = [
                    '#type' => 'item',
                    '#markup' => $this->t('Invoice ref. @p', ['@p' => $data->serial]),
                ];

                $form['serial'] = [
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                ];
            } elseif ($clone == 'clone') {
                // duplicate existing invoice with new serial No
                $form['clone_invoice'] = [
                    '#type' => 'item',
                    '#markup' => "<div class='messages messages--warning'>"
                    . $this->t('Template invoice based on ref. @p . A new invoice will be generated.', ['@p' => $data->serial])
                    . "</div>",
                ];

                $data->date = date('Y-m-d');

                $form['new_invoice'] = [
                    '#type' => 'hidden',
                    '#value' => 1,
                ];
            } elseif ($clone == 'delivery') {
                // convert delivery order into invoice with new serial No
                $options = ['1' => $this->t('Invoice'), '2' => $this->t('Commercial invoice')];

                $form['clone_invoice'] = [
                    '#type' => 'item',
                    '#markup' => $this->t('Convert delivery order ref. @p .', ['@p' => $data->serial]),
                ];

                $form['do'] = [
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                ];

                $form['new_invoice'] = [
                    '#type' => 'hidden',
                    '#value' => 1,
                ];

                $data->date = $data->ddate;
                $data->comment = $data->serial;
            }



            $n = 0;
            $form_state->set('current_items', 0);
            if (!$form_state->get('num_items')) {
                $form_state->set('num_items', 0);
            }

            if (!$form_state->getValue('head')) {
                $form_state->setValue('head', $data->head);
            }
            if (!$form_state->getValue('currency')) {
                $form_state->setValue('currency', $data->currency);
            }

            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $chart = $this->Financesettings->get('chart');
                $AidOptions = \Drupal\ek_finance\AidList::listaid($data->head, [$chart['income'], $chart['other_income']], 1);
                $baseCurrency = $this->Financesettings->get('baseCurrency');
                if ($baseCurrency <> $data->currency) {
                    $requireFx = true;
                } else {
                    $requireFx = false;
                }
            }
        } else {
            // new
            $form['new_invoice'] = [
                '#type' => 'hidden',
                '#value' => 1,
            ];
            $grandtotal = 0;
            $taxable = 0;
            $n = 0;
            $AidOptions = [];
            $form_state->set('fx_rate_require', false);
            $detail = null;
            $data = null;
            $options = ['1' => $this->t('Invoice'), '2' => $this->t('Commercial invoice'), '4' => $this->t('Credit note'), '5' => $this->t('Proforma invoice')];
        }


        $baseCurrency = '';
        $currenciesList = '';
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $CurrencyOptions = \Drupal\ek_finance\CurrencyData::listcurrency(1);
            $baseCurrency = $this->Financesettings->get('baseCurrency');
            $currenciesList = \Drupal\ek_finance\CurrencyData::currencyRates();
            $chart = $this->Financesettings->get('chart');
            if (empty($chart)) {
                $alert = "<div id='fx' class='messages messages--warning'>" . $this->t('You did not set the accounts chart structure. Go to <a href="@url">settings</a>.', array('@url' => Url::fromRoute('ek_finance.admin.settings', array(), array())->toString())) . "</div>";
                $form['alert'] = [
                    '#type' => 'item',
                    '#weight' => -17,
                    '#markup' => $alert,
                ];
            }
        }

        $form['options'] = [
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => (isset($id) || $form_state->get('num_items') > 0) ? false : true,
        ];

        $company = AccessCheck::CompanyListByUid();
        $form['options']['head'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => true,
            '#default_value' => isset($data->head) ? $data->head : null,
            '#title' => $this->t('Header'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
            '#ajax' => [
                'callback' => [$this, 'set_coid'],
                'wrapper' => 'debit',
            // will define the list of bank accounts by company below
            ],
        ];

        if (count($company) > 1) {
            $form['options']['allocation'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $company,
                '#required' => true,
                '#default_value' => isset($data->allocation) ? $data->allocation : null,
                '#title' => $this->t('Allocated'),
                '#description' => $this->t('select an entity for which the invoice is done'),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            ];
        } else {
            $form['options']['allocation'] = [
                '#type' => 'hidden',
                '#value' => key($company),
                '#suffix' => '</div>',
            ];
        }


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);

            if (!empty($client)) {
                $form['options']['client'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => true,
                    '#default_value' => isset($data->client) ? $data->client : null,
                    '#title' => $this->t('Client'),
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div></div></div>',
                    '#attributes' => ['style' => array('width:200px;white-space:nowrap')],
                ];
            } else {
                $link = Url::fromRoute('ek_address_book.new', [])->toString();

                $form['options']['client'] = [
                    '#markup' => $this->t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                    '#default_value' => 0,
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div></div></div>',
                ];
            }
        } else {
            $form['options']['client'] = [
                '#markup' => $this->t('You do not have any client list.'),
                '#default_value' => 0,
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div></div></div>',
            ];
        }

        $form['options']['date'] = [
            '#type' => 'date',
            '#size' => 12,
            '#required' => true,
            '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
            '#title' => $this->t('Date'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        ];

        $form['options']['title'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $options,
            '#required' => true,
            '#default_value' => isset($data->type) ? $data->type : 1,
            '#title' => $this->t('Title'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        ];

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $form['options']['pcode'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => \Drupal\ek_projects\ProjectData::listprojects(0),
                '#required' => true,
                '#default_value' => isset($data->pcode) ? $data->pcode : null,
                '#title' => $this->t('Project'),
                '#attributes' => ['style' => ['width:200px;white-space:nowrap']],
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            ];
        } // project

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $form['options']['currency'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $CurrencyOptions,
                '#required' => true,
                '#default_value' => isset($data->currency) ? $data->currency : null,
                '#title' => $this->t('Currency'),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div>',
                '#ajax' => [
                    'callback' => [$this, 'check_aid'],
                    'wrapper' => 'fx',
                // will define if currency asset account exist and input the exchange rate against
                // base currency
                ],
            ];


            $form['options']['fx_rate'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 15,
                //'#value' =>  $fx_rate,
                '#default_value' => $form_state->get('fx_rate'),
                '#required' => $form_state->get('fx_rate_require'),
                '#title' => $this->t('Exchange rate'),
                '#description' => $form_state->get('fx_rate_desc'),
                '#prefix' => "<div id='fx' class='cell'>",
                '#suffix' => '</div>',
            ];

            //bank account
            if ($form_state->getValue('head')) {
                $options['bank'] = \Drupal\ek_finance\BankData::listbankaccountsbyaid($form_state->getValue('head'));
            }


            $form['options']['bank_account'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => isset($options['bank']) ? $options['bank'] : [],
                '#default_value' => isset($data->bank) ? $data->bank : $form_state->getValue('bank_account'),
                '#required' => true,
                '#title' => $this->t('Account payment'),
                '#prefix' => "<div id='debit' class='cell'>",
                '#suffix' => '</div></div></div>',
                '#description' => '',
                '#attributes' => ['style' => ['width:280px;;white-space:nowrap']],
                '#ajax' => [
                    'callback' => [$this, 'check_tax'],
                    'wrapper' => 'taxwrap',
                ],
            ];
        } // finance
        else {
            $l = explode(',', file_get_contents(\Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/currencies.inc'));
            foreach ($l as $key => $val) {
                $val = explode(':', $val);
                $currency[$val[0]] = $val[1];
            }

            $form['options']['currency'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $currency,
                '#required' => true,
                '#default_value' => isset($data->currency) ? $data->currency : null,
                '#title' => $this->t('Currency'),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div></div></div>',
            ];

            $form['options']['bank_account'] = [
                '#type' => 'hidden',
                '#value' => 0,
            ];
            $form['options']['fx_rate'] = [
                '#type' => 'hidden',
                '#value' => 1,
            ];
        }


        $form['options']['tax'] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => isset($data->tax) ? $data->tax : null,
            '#title' => $this->t('Tax'),
            '#prefix' => "<div class='container-inline'>",
            '#attributes' => ['placeholder' => $this->t('ex. sales tax')],
        ];

        $form['options']['taxvalue'] = [
            '#type' => 'textfield',
            '#id' => 'taxvalue',
            '#size' => 10,
            '#maxlength' => 6,
            '#default_value' => isset($data->taxvalue) ? $data->taxvalue : null,
            '#description' => '%',
            '#title_display' => 'after',
            '#prefix' => "<div id='taxwrap'>",
            '#suffix' => "</div></div>",
            '#attributes' => ['placeholder' => '%', 'class' => ['amount']],
        ];

        $form['options']['terms'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => [('on receipt'), $this->t('due days')],
            '#default_value' => isset($data->terms) ? $data->terms : null,
            '#title' => $this->t('Terms'),
            '#prefix' => "<div class='container-inline'>",
            '#ajax' => [
                'callback' => [$this, 'check_day'],
                'wrapper' => 'calday',
                'event' => 'change',
            ],
        ];

        $form['options']['due'] = [
            '#type' => 'textfield',
            '#size' => 5,
            '#maxlength' => 3,
            '#default_value' => isset($data->due) ? $data->due : null,
            '#attributes' => ['placeholder' => $this->t('days')],
            '#ajax' => [
                'callback' => [$this, 'check_day'],
                'wrapper' => 'calday',
                'event' => 'change',
            ],
        ];
        $form['options']['day'] = [
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => "<div  id='calday'>",
            '#suffix' => "</div></div>",
        ];

        $form['options']['po_no'] = [
            '#type' => 'textfield',
            '#maxlength' => 50,
            '#size' => 25,
            '#default_value' => isset($data->po_no) ? $data->po_no : null,
            '#description' => $this->t('optional purchase order number'),
            '#attributes' => ['placeholder' => $this->t('PO No.')],
        ];
        $form['options']['comment'] = [
            '#type' => 'textarea',
            '#rows' => 3,
            '#default_value' => isset($data->comment) ? $data->comment : null,
            '#prefix' => "<div class='container-inline'>",
            '#suffix' => "</div>",
            '#attributes' => ['placeholder' => $this->t('comment')],
        ];

        $form['items'] = [
            '#type' => 'details',
            '#title' => $this->t('Items'),
            '#open' => true,
        ];

        $form['items']['actions']['add'] = [
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            '#limit_validation_errors' => [['head'], ['itemTable']],
            '#submit' => [[$this, 'addForm']],
            '#prefix' => "<div id='add'>",
            '#suffix' => '</div>',
            '#attributes' => ['class' => ['button--add']],
        ];

        $header = [
            'description' => [
                'data' => $this->t('Description'),
                'id' => ['tour-item1'],
                'class' => [RESPONSIVE_PRIORITY_MEDIUM],
            ],
            'account' => [
                'data' => $this->t('Account'),
                'id' => ['tour-item2'],
                
            ],
            'quantity' => [
                'data' => $this->t('Quantity'),
                'id' => ['tour-item3'],
            ],
            'value' => [
                'data' => $this->t('Value'),
                'id' => ['tour-item4'],
            ],
            'tax' => [
                'data' => $this->t('Tax'),
                'id' => ['tour-item6'],
            ],
            'total' => [
                'data' => $this->t('Total'),
                'id' => ['tour-item7'],
                'class' => [RESPONSIVE_PRIORITY_MEDIUM],
            ],
            'delete' => [
                'data' => $this->t('Delete'),
                'id' => ['tour-item5'],
            ],
            'weight' => ['id' => ['weigt']],
        ];

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $header['account']['data'] = $this->t('Account');
        }

        $form['items']['itemTable'] = [
            '#tree' => true,
            '#type' => 'table',
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => [],
            '#attributes' => ['id' => 'itemTable'],
            '#empty' => '',
            '#tabledrag' => [
                [
                    'action' => 'order',
                    'relationship' => 'sibling',
                    'group' => 'group-order-weight',
                ]
            ],
        ];


        $rows = $form_state->getValue('itemTable');
        if (isset($detail)) {
            // edition mode : list current items
            $taxable = 0;
            $grandtotal = 0;

            while ($d = $detail->fetchObject()) {
                $n++;
                $c = $form_state->get('current_items') + 1;
                $form_state->set('current_items', $c);
                $link = null;
                if ($clone == 'delivery') {
                    if ($d->itemcode != "" && $this->moduleHandler->moduleExists('ek_products')) {
                        $name = \Drupal\ek_products\ItemData::item_bycode($d->itemcode);
                    } else {
                        $name = $d->itemcode;
                    }
                } else {
                    if ($d->itemdetail != "" && $this->moduleHandler->moduleExists('ek_products')) {
                        $item = \Drupal\ek_products\ItemData::item_byid($d->itemdetail);
                        if (isset($item)) {
                            $name = $item;
                            $link = \Drupal\ek_products\ItemData::geturl_byId($d->itemdetail, true);
                        } else {
                            $name = $d->item;
                        }
                    } else {
                        $name = $d->item;
                    }
                }

                
                $total = number_format($d->value * $d->quantity, 2);
                $grandtotal += ($d->value * $d->quantity);
                if ($d->opt == 1) {
                    $taxable += ($d->value * $d->quantity);
                }

                $form['description'] = [
                    '#id' => 'description-' . $n,
                    '#type' => 'textfield',
                    '#size' => 38,
                    '#maxlength' => 255,
                    '#attributes' => ['placeholder' => $this->t('item'), 'class' => ['expand']],
                    '#default_value' => $name,
                    '#field_prefix' => "<span class='s-s-badge'>" . $n . "</span>",
                    '#field_suffix' => isset($link) ? "<span class='s-s-badge'>" . $link . "</span>" : '',
                    '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                ];
                
                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    $form['account'] = [
                        '#id' => 'account-' . $n,
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $AidOptions,
                        '#attributes' => ['style' => ['width:100px;']],
                        '#default_value' => $d->aid,
                        '#required' => true,
                    ];
                } else {
                    $form['account'] = '';
                }
                $form['quantity'] = [
                    '#id' => 'quantity' . $n,
                    '#type' => 'textfield',
                    '#size' => 8,
                    '#maxlength' => 28,
                    '#attributes' => ['placeholder' => $this->t('units'), 'class' => ['amount']],
                    '#default_value' => $d->quantity,
                    '#required' => true,
                ];
                $form['value'] = [
                    '#id' => 'value' . $n,
                    '#type' => 'textfield',
                    '#size' => 12,
                    '#maxlength' => 250,
                    '#default_value' => $d->value,
                    '#attributes' => ['placeholder' => $this->t('unit price'), 'class' => ['amount']],
                ];
                $form['tax'] = [
                    '#id' => 'optax' . $n,
                    '#type' => 'checkbox',
                    '#default_value' => $d->opt,
                    '#attributes' => [
                        'title' => $this->t('tax include'),
                        'class' => ['amount'],
                    ],
                ];
                $form['total'] = [
                    '#id' => 'total' . $n,
                    '#type' => 'textfield',
                    '#size' => 12,
                    '#maxlength' => 250,
                    '#default_value' => $total,
                    '#attributes' => ['placeholder' => $this->t('line total'), 'readonly' => 'readonly', 'class' => ['amount', 'right']],
                ];
                $form['delete'] = [
                    '#id' => 'del-' . $n,
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#attributes' => [
                        'title' => $this->t('delete on save'),
                        'class' => ['amount','rowdelete']
                    ],
                ];
                $form['weight'] = [
                    '#type' => 'weight',
                    '#title' => t('Weight'),
                    '#title_display' => 'invisible',
                    '#default_value' => $n,
                    '#attributes' => ['class' => ['group-order-weight']],
                ];
                // built edit rows for table
                $form['items']['itemTable'][$n] =[
                    'description' => &$form['description'],
                    'account' => &$form['account'],
                    'quantity' => &$form['quantity'],
                    'value' => &$form['value'],
                    'tax' => &$form['tax'],
                    'total' => &$form['total'],
                    'delete' => &$form['delete'],
                    'weight' => &$form['weight'],
                ];
                
                $form['items']['itemTable'][$n]['#attributes']['class'][] = 'draggable';
                if($d->quantity == 0 && $d->value == 0) {
                    $form['items']['itemTable'][$n]['#attributes']['class'][] = 'rowheader';
                }
                $form['items']['itemTable'][$n]['#weight'] = $n;                
                unset($form['description']);
                unset($form['account']);
                unset($form['quantity']);
                unset($form['value']);
                unset($form['tax']);
                unset($form['total']);
                unset($form['delete']);
                unset($form['weight']);
            }
        } // details of current records


        if (isset($detail)) {
            // reset the new rows items
            $max = $form_state->get('num_items') + $n;
            $n++;
        } else {
            $max = $form_state->get('num_items');
            $n = 1;
        }

        for ($i = $n; $i <= $max; $i++) {
            $n++;
            $rowClass = '';
            if($i < $max
                && $form_state->getValue('itemTable')[$i]['quantity'] == 0 
                && $form_state->getValue('itemTable')[$i]['value'] == 0) {
                $rowClass = 'rowheader';
            }
            $form['description'] = [
                '#id' => 'description-' . $i,
                '#type' => 'textfield',
                '#size' => 38,
                '#maxlength' => 255,
                '#attributes' => ['placeholder' => $this->t('item'), 'class' => ['expand']],
                '#field_prefix' => "<span class='s-badge'>" . $i . "</span>",
                '#autocomplete_route_name' => 'ek.look_up_item_ajax',
            ];
            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $form['account'] = [
                    '#id' => 'account-' . $i,
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $form_state->get('AidOptions'),
                    '#attributes' =>['style' => ['width:100px;']],
                    '#required' => true,
                ];
            } else {
                $form['account'] = '';
            }
            $form['quantity'] = [
                '#id' => 'quantity' . $i,
                '#type' => 'textfield',
                '#size' => 8,
                '#maxlength' => 28,
                '#attributes' => ['placeholder' => $this->t('units'), 'class' => ['amount']],
                '#required' => true,
            ];
            $form['value'] = [
                '#id' => 'value' . $i,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#attributes' => ['placeholder' => $this->t('unit price'), 'class' => ['amount']],
            ];
            $form['tax'] = [
                '#id' => 'optax' . $i,
                '#type' => 'checkbox',
                '#attributes' => [
                    'title' => $this->t('tax include'),
                    'class' => ['amount'],
                ],
            ];
            $form['total'] = [
                '#id' => 'total' . $i,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#attributes' => ['placeholder' => $this->t('line total'), 'readonly' => 'readonly', 'class' => ['amount', 'right']],
            ];
            $form['delete'] = [
                '#type' => 'hidden',
                '#value' => 0,
            ];
            $form['weight'] = [
                    '#type' => 'weight',
                    '#title' => t('Weight'),
                    '#title_display' => 'invisible',
                    '#default_value' => $i,
                    '#attributes' => ['class' => ['group-order-weight']],
            ];
            // built edit rows for table
            $form['items']['itemTable'][$i] = [
                'description' => &$form['description'],
                'account' => &$form['account'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
                'weight' => &$form['weight'],
            ];

            $form['items']['itemTable'][$i]['#attributes']['class'][] = 'draggable';
            $form['items']['itemTable'][$i]['#attributes']['class'][] = $rowClass;
            $form['items']['itemTable'][$i]['#weight'] = $i;
            unset($form['description']);
            unset($form['account']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);
            unset($form['weight']);
        }

        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => $n - 1,
            '#attributes' => array('id' => 'itemsCount'),
        );
        
        if (($form_state->get('num_items') && $form_state->get('num_items') > 0) || isset($detail)) {
            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    '#limit_validation_errors' => [],
                    '#submit' => [[$this, 'removeForm']],
                    '#prefix' => "<div id='remove' class='right'>",
                    '#suffix' => '</div>',
                    '#attributes' => ['class' => ['button--remove']],
                ];
            }

            // Table footer
            // total items
            if (isset($id) && $baseCurrency != $data->currency) {
                $c = \Drupal\ek_finance\CurrencyData::currencyRates();
                $converted = round($grandtotal / $c[$data->currency], 2) . " " . $baseCurrency;
            } else {
                $converted = '';
            }
            $n++;
            $form['description'] = [
                '#type' => 'item',
                '#markup' => $this->t('Total') . " " . "<span id='convertedValue' class='s-badge'>" . $converted . "</span>"
            ];
            $form['account'] = ['#type' => 'item',];
            $form['quantity'] = ['#type' => 'item',];
            $form['value'] = [
                '#type' => 'hidden',
                '#value' => 'footer',
            ];
            $form['tax'] = ['#type' => 'item',];
            $form['total'] = [
                '#id' => 'itemsTotal',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => isset($grandtotal) ? number_format($grandtotal, 2) : 0,
                '#attributes' => ['placeholder' => $this->t('total'), 'readonly' => 'readonly', 'class' => ['amount']],
            ];
            $form['delete'] = ['#type' => 'item',];
            $form['weight'] = ['#type' => 'item',];
            // built total rows for table
            $form['items']['itemTable'][$n] = [
                'description' => &$form['description'],
                'account' => &$form['account'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
                'weight' => &$form['weight'],
            ];
            unset($form['description']);
            unset($form['account']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);
            unset($form['weight']);

            // tax row
            $n++;
            $taxamount = isset($data->taxvalue) ? round($taxable * $data->taxvalue / 100, 2) : null;
            $form['description'] = [
                '#type' => 'item',
                '#markup' => $this->t('Tax payable'),
            ];
            $form['account'] = ['#type' => 'item',];
            $form['quantity'] = ['#type' => 'item',];
            $form['value'] = [
                '#type' => 'hidden',
                '#value' => 'footer',
            ];
            $form['tax'] = ['#type' => 'item',];
            $form['total'] = [
                '#id' => 'taxValue',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => number_format($taxamount, 2),
                '#attributes' => ['placeholder' => $this->t('tax'), 'readonly' => 'readonly', 'class' => ['amount']],
            ];
            $form['delete'] = ['#type' => 'item',];
            $form['weight'] = ['#type' => 'item',];
            
            // built tax row for table
            $form['items']['itemTable'][$n] = [
                'description' => &$form['description'],
                'account' => &$form['account'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
                'weight' => &$form['weight'],
            ];
            
            unset($form['description']);
            unset($form['account']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);
            unset($form['weight']);
            
            // total with tax
            $n++;
            $form['description'] = [
                '#type' => 'item',
                '#markup' => $this->t('Total invoice'),
            ];
            $form['account'] = ['#type' => 'item',];
            $form['quantity'] = ['#type' => 'item',];
            $form['value'] = [
                '#type' => 'hidden',
                '#value' => 'footer',
            ];
            $form['tax'] = ['#type' => 'item',];
            $form['total'] = [
                '#id' => 'totalWithTax',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => number_format($grandtotal + $taxamount, 2),
                '#attributes' => ['placeholder' => $this->t('total invoice'), 'readonly' => 'readonly', 'class' => ['amount']],
            ];
            $form['delete'] = ['#type' => 'item',];
            $form['weight'] = ['#type' => 'item',];
            // built invoice total row for table
            $form['items']['itemTable'][$n] = [
                'description' => &$form['description'],
                'account' => &$form['account'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'tax' => &$form['tax'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
                'weight' => &$form['weight'],
            ];            
            unset($form['description']);
            unset($form['account']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['tax']);
            unset($form['total']);
            unset($form['delete']);
            unset($form['weight']);

            $form['actions'] = ['#type' => 'actions',];
            $redirect = [0 => $this->t('view list'), 1 => $this->t('print'), 2 => $this->t('record payment')];
            $form['actions']['redirect'] = [
                '#type' => 'radios',
                '#title' => $this->t('Next'),
                '#default_value' => 0,
                '#options' => $redirect,
            ];

            $form['actions']['record'] = [
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => ['class' => ['button--record']],
            ];
        }

        $form['#attached'] = [
            'drupalSettings' => ['currencies' => $currenciesList, 'baseCurrency' => $baseCurrency],
            'library' => ['ek_sales/ek_sales.invoice'],
        ];

        return $form;
    }

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
        $description = '';
        $fx_rate = '';
        $required = false;
        if ($form_state->getValue('currency')) {
            if (!$form_state->getValue('head')) {
                $description = "<div id='fx' class='messages messages--warning'>"
                        . $this->t('You need to select header first. You cannot proceed.') . "</div>";
            } else {
                $description = '';
                $settings = new CompanySettings($form_state->getValue('head'));
                $aid = $settings->get('asset_account', $form_state->getValue('currency'));

                if ($aid == '') {
                    $l = "../ek_admin/company/edit-settings/" . $form_state->getValue('head');
                    $description = "<div id='fx' class='messages messages--warning'>"
                            . $this->t("There is no assets account defined for currency. Please <a href='@l'>edit settings</a> or contact administrator.", ['@l' => $l]) . "</div>";
                } else {
                    $fx_rate = \Drupal\ek_finance\CurrencyData::rate($form_state->getValue('currency'));
                    // $input = $form_state->getUserInput();
                    if ($fx_rate == '1') {
                        //$required  = FALSE;
                        $form['options']['fx_rate']['#required'] = false;
                    }
                    // not base currency
                    else {
                        // $required  = TRUE;
                        $form['options']['fx_rate']['#required'] = true;
                    }
                } // else -> aid
            }// else -> coid
        }

        $form['options']['fx_rate']['#description'] = $description;
        $form['options']['fx_rate']['#value'] = $fx_rate;
        return $form['options']['fx_rate'];
    }

    /**
     * Callback
     */
    public function check_tax(array &$form, FormStateInterface $form_state) {
        $settings = new CompanySettings($form_state->getValue('head'));
        if ($settings->get('stax_collect') == 1) {
            $form['options']['taxvalue']['#value'] = $settings->get('stax_rate');
        }
        return $form['options']['taxvalue'];
    }

    /**
     * Callback
     */
    public function fx_rate(array &$form, FormStateInterface $form_state) {
        /* if add exchange rate
         */
    }

    /**
     * Callback : calculate due date
     */
    public function check_day(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('terms') == '1' && $form_state->getValue('due') != null) {
            $form['options']['day']["#markup"] = date('Y-m-d', strtotime(date("Y-m-d", strtotime($form_state->getValue('date'))) . "+" . $form_state->getValue('due') . ' ' . $this->t("days")));
        } else {
            $form['options']['day']["#markup"] = '';
        }
        return $form['options']['day'];
    }

    /**
     * Callback: Add item to form
     */
    public function addForm(array &$form, FormStateInterface $form_state) {
        if (!$form_state->get('num_items')) {
            $form_state->set('num_items', 1);
        } else {
            $c = $form_state->get('num_items') + 1;
            $form_state->set('num_items', $c);
        }


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $chart = $this->Financesettings->get('chart');
            $fin = new CompanySettings($form_state->getValue('head'));
            if($fin->get('sales_liabilities') == 1) {
                $form_state->set('AidOptions', \Drupal\ek_finance\AidList::listaid($form_state->getValue('head'), [$chart['income'], $chart['other_income'],$chart['liabilities']], 1));
            } else {
                $form_state->set('AidOptions', \Drupal\ek_finance\AidList::listaid($form_state->getValue('head'), [$chart['income'], $chart['other_income']], 1));
            }
        }

        $input = $form_state->getUserInput();
        $form_state->setValue('fx_rate', $input['fx_rate']);

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
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $settings = new CompanySettings($form_state->getValue('head'));
            $aid = $settings->get('asset_account', $form_state->getValue('currency'));

            if ($aid == '') {
                $l = "../ek_admin/company/edit-settings/" . $form_state->getValue('head');
                $form_state->setErrorByName('currency', $this->t("There is no assets account defined for currency. Please <a href='@l'>edit settings</a> or contact administrator.", ['@l' => $l]));
            }
        }

        //input used to update values set by user
        $input = $form_state->getUserInput();
        if ($form_state->getValue('fx_rate') != '' && !is_numeric($form_state->getValue('fx_rate'))) {
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

        $rows = $form_state->getValue('itemTable');
        if (!empty($rows)) {
            foreach ($rows as $key => $row) {
                if ($row['value'] != 'footer') {
                    if ($row['description'] == '') {
                        $form_state->setErrorByName("itemTable][$key][description", $this->t('Item @n is empty', array('@n' => $key)));
                    }

                    if ($row['quantity'] == '' || !is_numeric($row['quantity'])) {
                        $form_state->setErrorByName("itemTable][$key][quantity", $this->t('there is no quantity for item @n', array('@n' => $key)));
                    }
                    if ($row['value'] == '' || !is_numeric($row['value'])) {
                        $form_state->setErrorByName("itemTable][$key][value", $this->t('there is no value for item @n', array('@n' => $key)));
                    }
                    // if($this->moduleHandler->moduleExists('ek_finance')) {
                    // validate account
                    // @TODO
                    //}
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $options = array('1' => 'Invoice', '2' => 'Commercial invoice', '4' => 'Credit note', '5' => 'Proforma invoice');

        if ($form_state->getValue('new_invoice') == 1) {
            //create new serial No
            switch ($form_state->getValue('title')) {
                case '4':
                    $type = "CN";
                    break;
                default:
                    $type = "I";
                    break;
            }

            $short = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                    ->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))
                    ->fetchField();

            $format = $this->salesSettings->get('serialFormat');
            if ($format['code'] == '') {
                $format['code'] = [1, 2, 3, 4, 5];
            }
            if ($format['increment'] == '' || $format['increment'] < 1) {
                $format['increment'] = 0;
            }

            $iid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_sales_invoice}")
                    ->fetchField();

            $iid = $iid + 1 + $format['increment'];
            $query = "SELECT id FROM {ek_sales_invoice} WHERE serial like :s";
            while (Database::getConnection('external_db', 'external_db')->query($query, [':s' => '%-' . $iid])->fetchField()) {
                // to prevent serial duplication after document have been deleted, increment until no match is found
                $iid++;
            }

            $serial = '';
            foreach ($format['code'] as $k => $v) {
                switch ($v) {
                    case 0:
                        break;
                    case 1:
                        $serial .= ucwords(str_replace('-', '', $short)) . '-';
                        break;
                    case 2:
                        $serial .= $type . '-';
                        break;
                    case 3:
                        $serial .= $date . '-';
                        break;
                    case 4:
                        $serial .= ucwords(str_replace('-', '', $sup)) . '-';
                        break;
                    case 5:
                        $serial .= $iid;
                        break;
                }
            }
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_sales_invoice_details')
                    ->condition('serial', $serial)
                    ->execute();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 's')
                    ->fields('s')
                    ->condition('serial', $serial)
                    ->execute();
            $init_data = $query->fetchAssoc();
            $iid = $init_data['id'];
        }

        $fx_rate = round($form_state->getValue('fx_rate'), 4);

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            // used to calculate currency gain/loss from rate at invoice record time
            // and linebase
            $baseCurrency = $this->Financesettings->get('baseCurrency');
            if ($baseCurrency != $form_state->getValue('currency')) {
                if ($fx_rate <> '' && is_numeric($fx_rate)) {
                    $currencyRate = $fx_rate;
                } else {
                    $currencyRate = \Drupal\ek_finance\CurrencyData::rate($form_state->getValue('currency'));
                }
            } else {
                $currencyRate = 1;
            }
        }

        // Items
        $line = 0;
        $total = 0;
        $taxable = 0;
        $values = [];
        $sum = 0;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $journal = new \Drupal\ek_finance\Journal();
        }
        $rows = $form_state->getValue('itemTable');
        if (!empty($rows)) {
            foreach ($rows as $key => $row) {
                if ($row['value'] != 'footer') {
                    if (isset($row['delete']) && $row['delete'] != 1) {
                        if ($this->moduleHandler->moduleExists('ek_products')) {
                            // verify if item is in the DB if not just record input
                            $item = explode(" ", $row["description"]);
                            $id = trim($item[0]);
                            if (isset($item[1])) {
                                $code = trim($item[1]);
                            }
                            $description = \Drupal\ek_products\ItemData::item_description_byid($id, 1);
                            if ($description) {
                                $item = $description;
                                $itemdetail = $id;
                            } else {
                                $item = Xss::filter($row["description"]);
                                $itemdetail = '';
                            }
                        } else {
                            // use input from user
                            $item = Xss::filter($row["description"]);
                            $itemdetail = '';
                        }

                        $line = (round($row["quantity"] * $row["value"], 2));
                        $linebase = (round($row["quantity"] * $row["value"] / $currencyRate, 2));
                        $sum = $sum + $line;
                        if ($row["tax"] == 1) {
                            $taxable = $taxable + $line;
                        }
                        if (!$row["account"]) {
                            $account = 0;
                        } else {
                            $account = $row["account"];
                        }

                        $values[] = [
                            'serial' => $serial,
                            'item' => $item, // description used in displays
                            'itemdetail' => $itemdetail, //add detail / id if item is in DB
                            'quantity' => $row["quantity"],
                            'value' => $row["value"],
                            'total' => $line,
                            'totalbase' => $linebase,
                            'opt' => $row["tax"],
                            'aid' => $account
                        ];

                        
                    } 
                } 
            } 
            // record details in DB
            $insert = Database::getConnection('external_db', 'external_db')
                      ->insert('ek_sales_invoice_details')
                      ->fields(['serial','item','itemdetail','quantity','value','total','totalbase','opt','aid']);
                foreach ($values as $record) {
                    $insert->values($record);
                }  
            $insert->execute();
        }
        
        // main
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
                // calculate the value in base currency of the amount without tax
                $amountbase = round($sum / $currencyRate, 2);
            } else {
                $amountbase = $sum;
            }
        } else {
            $amountbase = $sum;
        }

        $fields1 = array(
            'serial' => $serial,
            'head' => $form_state->getValue('head'),
            'allocation' => $form_state->getValue('allocation'),
            'status' => 0,
            'amount' => $sum,
            'currency' => $form_state->getValue('currency'),
            'date' => $form_state->getValue('date'),
            'title' => $options[$form_state->getValue('title')],
            'type' => $form_state->getValue('title'),
            'pcode' => $pcode,
            'comment' => Xss::filter($form_state->getValue('comment')),
            'po_no' => Xss::filter($form_state->getValue('po_no')),
            'client' => $form_state->getValue('client'),
            'amountreceived' => 0,
            'pay_date' => '',
            'amountbase' => $amountbase,
            'balancebase' => $amountbase,
            'terms' => Xss::filter($form_state->getValue('terms')),
            'due' => $due,
            'bank' => $form_state->getValue('bank_account'),
            'tax' => $form_state->getValue('tax'),
            'taxvalue' => $taxvalue,
            'reconcile' => 0,
        );

        if ($form_state->getValue('new_invoice') && $form_state->getValue('new_invoice') == 1) {
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_invoice')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')->update('ek_sales_invoice')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $iid;
        }

        //
        // Edit delivery in DO conversion mode
        //
        if ($form_state->getValue('do') != null) {
            //change  status
            Database::getConnection('external_db', 'external_db')->update('ek_logi_delivery')
                    ->fields(array('status' => 2))
                    ->condition('serial', $form_state->getValue('do'))
                    ->condition('status', '1')
                    ->execute();
        }

        // Record the accounting journal
        // Credit  notes are not recorded in journal, only once assigned to sales
        // (a CN is deduction of receivable)
        //
        if ($form_state->getValue('title') < 4 && $this->moduleHandler->moduleExists('ek_finance')) {

            //
            // delete first
            //
            if (!$form_state->getValue('new_invoice') == 1) {
                $delete = Database::getConnection('external_db', 'external_db')
                        ->delete('ek_journal')
                        ->condition('reference', $iid)
                        ->condition('source', 'invoice')
                        ->execute();
            }

            foreach ($rows as $key => $row) {
                if ($row['value'] != 'footer') {
                    if ($row['delete'] != 1) {
                        if ($form_state->getValue('taxvalue') > 0 && $row['tax'] == 1) {
                            $tax = round($row['value'] * $row['quantity'] * $form_state->getValue('taxvalue') / 100, 4);
                        } else {
                            $tax = 0;
                        }
                        $line = (round($row['quantity'] * $row['value'], 2));
                        $journal->record(
                                array(
                                    'source' => "invoice",
                                    'coid' => $form_state->getValue('head'),
                                    'aid' => $row['account'],
                                    'reference' => $reference,
                                    'date' => $form_state->getValue('date'),
                                    'value' => $line,
                                    'currency' => $form_state->getValue('currency'),
                                    'fxRate' => $currencyRate,
                                    'tax' => $tax,
                                )
                        );
                    }
                }
            }

            $rec = $journal->recordtax(
                    array(
                        'source' => "invoice",
                        'coid' => $form_state->getValue('head'),
                        'reference' => $reference,
                        'date' => $form_state->getValue('date'),
                        'currency' => $form_state->getValue('currency'),
                        'fxRate' => $currencyRate,
                        'type' => 'stax_collect_aid',
                    )
            );

            if (round($journal->credit, 4) <> round($journal->debit, 4)) {
                $msg = 'debit: ' . $journal->debit . ' <> ' . 'credit: ' . $journal->credit;
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            }
        }

        Cache::invalidateTags(['project_page_view']);
        if (isset($insert) || isset($update)) {
            Cache::invalidateTags(['reporting']);
            \Drupal::messenger()->addStatus(t('The @doc is recorded. Ref. @r', ['@r' => $serial, '@doc' => $options[$form_state->getValue('title')]]));

            if ($this->moduleHandler->moduleExists('ek_projects')) {
                // notify user if invoice is linked to a project
                if ($pcode && $pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $pcode])
                            ->fetchField();
                    $inputs = [];
                    if (isset($init_data)) {
                        foreach ($fields1 as $key => $value) {
                            if ($value != $init_data[$key]) {
                                $inputs[] = $key;
                            }
                        }
                    }
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => 'invoice_edit',
                                'input' => $inputs,
                                'value' => $serial,
                                'pcode' => $pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
            if (isset($_SESSION['ifilter']['to'])) {
                // if filter is set for list adjust date to last created date to enforce document display
                $_SESSION['ifilter']['to'] = $form_state->getValue('date');
            }

            switch ($form_state->getValue('redirect')) {
                case 0:
                    $form_state->setRedirect('ek_sales.invoices.list');
                    break;
                case 1:
                    $form_state->setRedirect('ek_sales.invoices.print_share', ['id' => $reference]);
                    break;
                case 2:
                    $form_state->setRedirect('ek_sales.invoices.pay', ['id' => $reference]);
                    break;
            }
        }
    }
}