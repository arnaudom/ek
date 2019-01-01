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
        $this->settings = new \Drupal\ek_finance\FinanceSettings();
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
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();
        $query = "SELECT * from {ek_sales_quotation_details} where serial=:id ORDER by id";
        $detail = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $data->serial));

        $tax = explode('|', $data->tax);
            if ($tax[0] <> '') {
                $tax_name = $tax[0];
                $tax_rate = $tax[1];
            } else {
                $tax_name = '';
                $tax_rate = 0;
            }
        $incotermValue = 0;
        $form['edit_invoice'] = array(
            '#type' => 'item',
            '#markup' => t('Quotation ref. @p', array('@p' => $data->serial)),
        );
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@url" >List</a>', array('@url' => Url::fromRoute('ek_sales.quotations.list', array(), array())->toString() ) ) ,

        ); 
        $form['quotation_serial'] = array(
            '#type' => 'hidden',
            '#value' => $data->serial,
        );
        $form['incoterm'] = array(
            '#type' => 'hidden',
            '#value' => $data->incoterm,
        );
        
        $n = 0;
        
        if (!$form_state->get('num_items')) {
            $form_state->set('num_items', 0);
        }
        $form_state->setValue('head', $data->head);

        $baseCurrency = '';
        $currenciesList = '';
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $CurrencyOptions = \Drupal\ek_finance\CurrencyData::listcurrency(1);
            $baseCurrency = $this->settings->get('baseCurrency'); 
            $currenciesList = \Drupal\ek_finance\CurrencyData::currencyRates();
            $chart = $this->settings->get('chart');
            $AidOptions = \Drupal\ek_finance\AidList::listaid($data->head, array($chart['income'], $chart['other_income']), 1);
            $baseCurrency = $this->settings->get('baseCurrency');
            if ($baseCurrency <> $data->currency) {
                $requireFx = TRUE;
            } else {
                $requireFx = FALSE;
            }
        }

        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => ($form_state->get('num_items') > 0) ? FALSE : TRUE,
        );

        $company = AccessCheck::CompanyListByUid();
        $form['options']['head'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#default_value' => isset($data->head) ? $data->head : NULL,
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

        $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'), '4' => t('Credit note'));
        $form['options']['title'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $options,
            '#required' => TRUE,
            '#default_value' => isset($data->title) ? $data->title : 1,
            '#title' => t('title'),
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
                '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>'
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
                    $fx = \Drupal\ek_finance\CurrencyData::rate($form_state->getValue('currency'));
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
                $settings = new CompanySettings($data->head);
                $aid = $settings->get('asset_account', $data->currency);

                if ($aid == '') {

                    $description = "<div id='fx' class='messages messages--warning'>"
                            . t('You need to select header first. You cannot proceed.') . "</div>";
                } else {
                    $fx = \Drupal\ek_finance\CurrencyData::rate($data->currency);
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
                $options['bank'] = \Drupal\ek_finance\BankData::listbankaccountsbyaid($form_state->getValue('head'));
            } else {
                $options['bank'] = \Drupal\ek_finance\BankData::listbankaccountsbyaid($data->head);
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
            '#id' => 'tax_rate',
            '#size' => 10,
            '#maxlength' => 6,
            '#default_value' => isset($tax[1]) ? $tax[1] : NULL,
            '#description' => '%',
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
      '#type' => 'submit' ,
      '#value' => $this->t('Add item'),
      '#limit_validation_errors' => [['head'],['itemTable']],
      '#submit' =>  array(array($this, 'addForm')) ,
      '#prefix' => "<div id='add' class='right'>",
      '#suffix' => '</div>',
      '#attributes' => array('class' => array('button--add')),
    ); 


        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $header = array(
                'description' => array(
                    'data' => $this->t('Description'),
                    'id' => ['tour-item1'],
                ),
                'account' => array(
                    'data' => $this->t('Account'),
                    'id' => ['tour-item2'],
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'quantity' => array(
                    'data' => $this->t('Quantity'),
                    'id' => ['tour-item3'],
                ),
                'value' => array(
                    'data' => $this->t('Value'),
                    'id' => ['tour-item4'],
                ),
                'tax' => array(
                    'data' => $this->t('Tax'),
                    'id' => ['tour-item6'],
                ),
                'total' => array(
                    'data' => $this->t('Total'),
                    'id' => ['tour-item7'],
                ),
                'delete' => array(
                    'data' => $this->t('Delete'),
                    'id' => ['tour-item5'],
                ),
            );

        } else {
            $header = array(
                'description' => array(
                    'data' => $this->t('Description'),
                    'id' => ['tour-item1'],
                ),
                'quantity' => array(
                    'data' => $this->t('Quantity'),
                    'id' => ['tour-item3'],
                ),
                'value' => array(
                    'data' => $this->t('Value'),
                    'id' => ['tour-item4'],
                ),
                'tax' => array(
                    'data' => $this->t('Tax'),
                    'id' => ['tour-item6'],
                ),
                'total' => array(
                    'data' => $this->t('Total'),
                    'id' => ['tour-item7'],
                ),
                'delete' => array(
                    'data' => $this->t('Delete'),
                    'id' => ['tour-item5'],
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
    if (isset($detail)) {
    //edition mode
    //list current items
    $taxable = 0;
    $grandtotal = 0;
        while ($d = $detail->fetchObject()) {
            $n++; 

            if ($d->itemid != "" && $this->moduleHandler->moduleExists('ek_products') )  {
                  $item = \Drupal\ek_products\ItemData::item_bycode($d->itemid);  
                  if(isset($item)) {
                     $name = $item; 
                  } else {
                     $name =  $d->itemdetails;
                  }
                } else {
                  $name = $d->itemdetails;
                }


            $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';
            $total = number_format($d->value * $d->unit,2);
            $grandtotal += ($d->value * $d->unit);
            if ($d->opt == 1) {
                $taxable += ($d->value * $d->unit);
            }


            $form['description'] = array(
                        '#id' => 'description-' . $n,
                        '#type' => 'textfield',
                        '#size' => 40,
                        '#maxlength' => 255,
                        '#attributes' => array('placeholder'=>t('item')),
                        '#default_value' => $name,
                        '#field_prefix' => "<span class='badge'>". $n ."</span>",
                        '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                    );
            if($this->moduleHandler->moduleExists('ek_finance')) { 
                $form['account'] = array(
                            '#id' => 'account-' . $n,
                            '#type' => 'select',
                            '#size' => 1,
                            '#options' => $AidOptions,
                            '#attributes' => array('style' => array('width:110px;')),
                            '#default_value' => '',
                            '#required' => TRUE,
                        );
            } else {
                $form['account'] = '';
            }
            $form['quantity'] = array(
                        '#id' => 'quantity' . $n,
                        '#type' => 'textfield',
                        '#size' => 8,
                        '#maxlength' => 30,
                        '#attributes' => array('placeholder'=>t('units'), 'class' => array('amount')),
                        '#default_value' => $d->unit,
                        '#required' => TRUE,
                    ); 
            $form['value'] = array(
                        '#id' => 'value' . $n,
                        '#type' => 'textfield',
                        '#size' => 12,
                        '#maxlength' => 250,
                        '#default_value' => $d->value,
                        '#attributes' => array('placeholder'=>t('unit price'), 'class' => array('amount')),
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
                        '#attributes' => array('placeholder'=>t('line total'), 'readonly' => 'readonly', 'class' => array('amount','right')),
                    );
            $form['delete'] = array(
                        '#id' => 'del' . $n,
                        '#type' => 'checkbox',
                        '#default_value' => 0,
                        '#attributes' => array(
                            'title' => t('delete on save'),
                            'onclick' => "jQuery('#".$n."').toggleClass('delete');",
                            'class' => ['amount']
                        ),
                    );
            //built edit rows for table
                $form['items']['itemTable'][$n] = array(
                        'description' => &$form['description'],
                        'account' => &$form['account'],
                        'quantity' => &$form['quantity'],
                        'value' => &$form['value'],
                        'tax' => &$form['tax'],
                        'total' => &$form['total'],
                        'delete' => &$form['delete'],
                );

                $form['items']['itemTable']['#rows'][$n] = array(
                        'data' => array(
                            array('data' => &$form['description']),
                            array('data' => &$form['account']),
                            array('data' => &$form['quantity']),
                            array('data' => &$form['value']),
                            array('data' => &$form['tax']),
                            array('data' => &$form['total']),
                            array('data' => &$form['delete']),
                        ),
                        'id' => array($n),
                        'class' => $rowClass,
                    );
                    unset($form['description']);
                    unset($form['account']);
                    unset($form['quantity']);
                    unset($form['value']);
                    unset($form['tax']);
                    unset($form['total']);
                    unset($form['delete']);
        }
    } //details of current records

  if(isset($detail)) {
  // reset the new rows items
    $max = $form_state->get('num_items')+$n;
    $n++;
      } else {
        $max = $form_state->get('num_items');
        $n = 1;
      }

        for ($i = $n; $i <= $max; $i++) {
            $form['description'] = array(
                        '#id' => 'description-' . $i,
                        '#type' => 'textfield',
                        '#size' => 40,
                        '#maxlength' => 255,
                        '#field_prefix' => "<span class='badge'>". $n ."</span>",
                        '#attributes' => array('placeholder'=>t('item')),
                        '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                    );
            if($this->moduleHandler->moduleExists('ek_finance')) { 
                $form['account'] = array(
                            '#id' => 'account-' . $i,
                            '#type' => 'select',
                            '#size' => 1,
                            '#options' => $form_state->get('AidOptions'),
                            '#attributes' => array('style' => array('width:110px;')),
                            '#required' => TRUE,
                        );
            } else {
                $form['account'] = '';
            }
            $form['quantity'] = array(
                        '#id' => 'quantity' . $i,
                        '#type' => 'textfield',
                        '#size' => 8,
                        '#maxlength' => 30,
                        '#attributes' => array('placeholder'=>t('units'), 'class' => array('amount')),
                        '#required' => TRUE,
                    ); 
            $form['value'] = array(
                        '#id' => 'value' . $i,
                        '#type' => 'textfield',
                        '#size' => 12,
                        '#maxlength' => 250,
                        '#attributes' => array('placeholder'=>t('unit price'), 'class' => array('amount')),
                    );
            $form['tax'] = array(
                        '#id' => 'optax' . $i,
                        '#type' => 'checkbox',
                        '#attributes' => array(
                            'title' => t('tax include'),
                            'class' => array('amount'),
                        ),
                    );
            $form['total'] = array(
                        '#id' => 'total' . $i,
                        '#type' => 'textfield',
                        '#size' => 12,
                        '#maxlength' => 250,
                        '#attributes' => array('placeholder'=>t('line total'), 'readonly' => 'readonly', 'class' => array('amount','right')),
                    );
            $form['delete'] = array( );
            //built edit rows for table
                $form['items']['itemTable'][$i] = array(
                        'description' => &$form['description'],
                        'account' => &$form['account'],
                        'quantity' => &$form['quantity'],
                        'value' => &$form['value'],
                        'tax' => &$form['tax'],
                        'total' => &$form['total'],
                        'delete' => &$form['delete'],
                );

                $form['items']['itemTable']['#rows'][$i] = array(
                        'data' => array(
                            array('data' => &$form['description']),
                            array('data' => &$form['account']),
                            array('data' => &$form['quantity']),
                            array('data' => &$form['value']),
                            array('data' => &$form['tax']),
                            array('data' => &$form['total']),
                            array('data' => &$form['delete']),
                        ),
                        'id' => array($i)
                );
                unset($form['description']);
                unset($form['account']);
                unset($form['quantity']);
                unset($form['value']);
                unset($form['tax']);
                unset($form['total']);
                unset($form['delete']);
                
            $n++;    
                
        } //added items
 
        $form['count'] = array(
            '#type' => 'hidden',
            '#value' => $n,
            '#attributes' => array('id' => 'itemsCount'),
        );
            
    //insert 1 more line if incoterm is set
        $incoterm = explode('|', $data->incoterm);
        $n++;
        if (isset($data->incoterm) && $incoterm[0] != '0') {
            $incotermValue = ($grandtotal * $incoterm[1] / 100);
            $form['description'] = array(
                '#id' => 'description-' . $n,
                '#type' => 'textfield',
                '#size' => 40,
                '#maxlength' => 255,
                '#default_value' => $incoterm[0] . ' ' . $incoterm[1] . '%'
            );
            if($this->moduleHandler->moduleExists('ek_finance')) { 
                $form['account'] = array(
                            '#id' => 'account-' . $n,
                            '#type' => 'select',
                            '#size' => 1,
                            '#options' => $AidOptions,
                            '#attributes' => array('style' => array('width:110px;')),
                            '#required' => TRUE,
                        );
            } else {
                $form['account'] = '';
            }
            
            $form['quantity'] = array(
                '#id' => 'quantity' . $n,
                '#type' => 'textfield',
                '#size' => 8,
                '#maxlength' => 30,
                '#default_value' => 1,
                '#attributes' => array('placeholder'=>t('units'), 'class' => array('amount')),
                '#required' => TRUE,
            ); 
            $form['value'] = array(
                '#id' => 'value' . $n,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => round($incotermValue, 2),
                '#attributes' => array('placeholder'=>t('unit price'), 'class' => array('amount')),
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
                '#default_value' => round($incotermValue, 2),
                '#attributes' => array('placeholder'=>t('line total'),  'class' => array('amount','right')),
            );
            $form['delete'] = array(
                '#id' => 'del' . $n,
                '#type' => 'checkbox',
                '#default_value' => 0,
                '#attributes' => array(
                    'title' => t('delete on save'),
                    'onclick' => "jQuery('#". $n ."').toggleClass('delete');",
                    'class' => array('amount'),
                ),
            );
            //built edit rows for table
                $form['items']['itemTable'][$n] = array(
                        'description' => &$form['description'],
                        'account' => &$form['account'],
                        'quantity' => &$form['quantity'],
                        'value' => &$form['value'],
                        'tax' => &$form['tax'],
                        'total' => &$form['total'],
                        'delete' => &$form['delete'],
                );
                    //built total rows for table
                    $form['items']['itemTable'][$n] = array(
                            'description' => &$form['description'],
                            'account' => &$form['account'],
                            'quantity' => &$form['quantity'],
                            'value' => &$form['value'],
                            'tax' => &$form['tax'],
                            'total' => &$form['total'],
                            'delete' => &$form['delete'],
                    );

                    $form['items']['itemTable']['#rows'][$n] = array(
                            'data' => array(
                                array('data' => &$form['description']),
                                array('data' => &$form['account']),
                                array('data' => &$form['quantity']),
                                array('data' => &$form['value']),
                                array('data' => &$form['tax']),
                                array('data' => &$form['total']),
                                array('data' => &$form['delete']),
                            ),
                            'id' => array($n)
                    );
                    unset($form['description']);
                    unset($form['account']);
                    unset($form['quantity']);
                    unset($form['value']);
                    unset($form['tax']);
                    unset($form['total']);
                    unset($form['delete']);
           
        } //incoterm line


            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    '#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                    '#prefix' => "<div id='remove' class='right'>",
                    '#suffix' => '</div>',
                    '#attributes' => array('class' => array('button--remove')),
                );
            }

            //FOOTER
            if(isset($id) && $baseCurrency != $data->currency) {
                $c = \Drupal\ek_finance\CurrencyData::currencyRates();
                $converted = round($grandtotal/$c[$data->currency],2) . " " . $baseCurrency;
            } else {
                $converted = '';
            }
            
        //items total
                    $n+=100;
                    $form['description'] = array(                    
                                '#type' => 'item',
                                '#markup' => t('Total')
                            );   
                    $form['account'] = array(
                                '#type' => 'item',
                            );
                    $form['quantity'] = array(
                                '#type' => 'item',
                            ); 
                    $form['value'] = array(
                                '#type' => 'hidden',
                                '#value' => 'footer',
                                '#attributes' => ['id' => ['value' . $n]],
                            );
                    $form['tax'] = array(
                                '#type' => 'item',
                            );
                    $form['total'] = array(
                                '#id' => 'itemsTotal',
                                '#type' => 'textfield',    
                                '#size' => 12,
                                '#maxlength' => 250,
                                '#value' => isset($grandtotal) ?  number_format($grandtotal + $incotermValue, 2) : 0,
                                '#attributes' => array('placeholder'=>t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
                            ); 
                    $form['delete'] = array(
                                '#type' => 'item',
                                '#markup' => "<div id='convertedValue' class='badge'>".$converted."</div>",
                            );
                    //built total rows for table
                    $form['items']['itemTable'][$n] = array(
                            'description' => &$form['description'],
                            'account' => &$form['account'],
                            'quantity' => &$form['quantity'],
                            'value' => &$form['value'],
                            'tax' => &$form['tax'],
                            'total' => &$form['total'],
                            'delete' => &$form['delete'],
                    );

                    $form['items']['itemTable']['#rows'][$n] = array(
                            'data' => array(
                                array('data' => &$form['description']),
                                array('data' => &$form['account']),
                                array('data' => &$form['quantity']),
                                array('data' => &$form['value']),
                                array('data' => &$form['tax']),
                                array('data' => &$form['total']),
                                array('data' => &$form['delete']),
                            ),
                            'id' => array($n)
                    );
                    unset($form['description']);
                    unset($form['account']);
                    unset($form['quantity']);
                    unset($form['value']);
                    unset($form['tax']);
                    unset($form['total']);
                    unset($form['delete']);

        //tax        
                    $n++;
                    $taxamount = isset($tax_rate) ? number_format(($taxable * $tax_rate/100), 2) : NULL ;
                    $form['description'] = array(                    
                                '#type' => 'item',
                                '#markup' => ($taxable > 0) ? t('Tax payable') . " " . $tax_name : NULL,
                            );   
                    $form['account'] = array(
                                '#type' => 'item',
                            );
                    $form['quantity'] = array(
                                '#type' => 'item',
                            ); 
                    $form['value'] = array(
                                '#type' => 'hidden',
                                '#value' => 'footer',
                                '#attributes' => ['id' => ['value' . $n]],
                            );
                    $form['tax'] = array(
                                '#type' => 'item',
                            );
                    $form['total'] = array(
                                '#id' => 'taxValue',
                                '#type' => 'textfield',    
                                '#size' => 12,
                                '#maxlength' => 250,
                                '#default_value' => $taxamount,
                                '#attributes' => array('placeholder'=>t('tax'), 'readonly' => 'readonly', 'class' => array('amount')),
                            ); 
                    $form['delete'] = array(
                                '#type' => 'item',
                            );
                    //built tax row for table
                    $form['items']['itemTable'][$n] = array(
                            'description' => &$form['description'],
                            'account' => &$form['account'],
                            'quantity' => &$form['quantity'],
                            'value' => &$form['value'],
                            'tax' => &$form['tax'],
                            'total' => &$form['total'],
                            'delete' => &$form['delete'],
                    );

                    $form['items']['itemTable']['#rows'][$n] = array(
                            'data' => array(
                                array('data' => &$form['description']),
                                array('data' => &$form['account']),
                                array('data' => &$form['quantity']),
                                array('data' => &$form['value']),
                                array('data' => &$form['tax']),
                                array('data' => &$form['total']),
                                array('data' => &$form['delete']),
                            ),
                            'id' => array($n)
                    );
                    unset($form['description']);
                    unset($form['account']);
                    unset($form['quantity']);
                    unset($form['value']);
                    unset($form['tax']);
                    unset($form['total']);
                    unset($form['delete']);

        //invoice total
                    $n++;
                    $form['description'] = array(                    
                                '#type' => 'item',
                                '#markup' => t('Total invoice'),
                            );   
                    $form['account'] = array(
                                '#type' => 'item',
                            );
                    $form['quantity'] = array(
                                '#type' => 'item',
                            ); 
                    $form['value'] = array(
                                '#type' => 'hidden',
                                '#value' => 'footer',
                                '#attributes' => ['id' => ['value' . $n]],
                            );
                    $form['tax'] = array(
                                '#type' => 'item',
                            );
                    $form['total'] = array(
                                '#id' => 'totalWithTax',
                                '#type' => 'textfield',    
                                '#size' => 12,
                                '#maxlength' => 250,
                                '#value' => number_format($grandtotal + $taxamount + $incotermValue, 2),
                                '#attributes' => array('placeholder'=>t('total invoice'), 'readonly' => 'readonly', 'class' => array('amount')),
                            ); 
                    $form['delete'] = array(
                                '#type' => 'item',
                            );
                    //built invoice total row for table
                    $form['items']['itemTable'][$n] = array(
                            'description' => &$form['description'],
                            'account' => &$form['account'],
                            'quantity' => &$form['quantity'],
                            'value' => &$form['value'],
                            'tax' => &$form['tax'],
                            'total' => &$form['total'],
                            'delete' => &$form['delete'],
                    );

                    $form['items']['itemTable']['#rows'][$n] = array(
                            'data' => array(
                                array('data' => &$form['description']),
                                array('data' => &$form['account']),
                                array('data' => &$form['quantity']),
                                array('data' => &$form['value']),
                                array('data' => &$form['tax']),
                                array('data' => &$form['total']),
                                array('data' => &$form['delete']),
                            ),
                            'id' => array($n)
                    );
                    unset($form['description']);
                    unset($form['account']);
                    unset($form['quantity']);
                    unset($form['value']);
                    unset($form['tax']);
                    unset($form['total']);
                    unset($form['delete']);



                $form['actions'] = array(
                    '#type' => 'actions',
                );
                $redirect = array(0 => t('view list'), 1 => t('print'), 2 => t('record payment'));
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


        $form['#attached'] = array(
            'drupalSettings' => array('currencies' => $currenciesList, 'baseCurrency' => $baseCurrency, 'action' => 'convert'),
            'library' => array('ek_sales/ek_sales.quotation'),
        );  


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
            $form_state->set('AidOptions', \Drupal\ek_finance\AidList::listaid($form_state->getValue('head'), array($chart['income'], $chart['other_income']), 1));
        }
        $form_state->setRebuild();
    }

    /**
     * Callback: Remove item to form
     */
    public function removeForm(array &$form, FormStateInterface $form_state) {
        $form_state->set('num_items', $form_state->get('num_items') - 1);
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if($this->moduleHandler->moduleExists('ek_finance')) {
            $settings = new CompanySettings($form_state->getValue('head'));
            $aid = $settings->get('asset_account', $form_state->getValue('currency') );
                if($aid == '') {
                    $form_state->setErrorByName('currency', t('There is no assets account defined for currency. Please contact administrator.'));
                }
        }

        //input used to update values set by user
        $input = $form_state->getUserInput();
        if($form_state->getValue('fx_rate') != '' && !is_numeric( $form_state->getValue('fx_rate') ) ) {
          $form_state->setErrorByName('fx_rate', $this->t('Exchange rate is wrong') );
        }

        if(!$form_state->getValue('tax') == '' && $form_state->getValue('taxvalue') == '') {
          $form_state->setErrorByName('taxvalue', $this->t('Tax value is empty') );
        }

        if($form_state->getValue('tax') == '' && !$form_state->getValue('taxvalue') == '') {
          $form_state->setErrorByName('tax',  $this->t('Tax description is empty') );
        }  

        if(!$form_state->getValue('tax') == '' && !is_numeric($form_state->getValue('taxvalue')) ) {
         $form_state->setErrorByName('taxvalue', $this->t('Tax value should be numeric') );
        } 

        if($form_state->getValue('terms') == 1  && $form_state->getValue('due') == '') {
          $form_state->setErrorByName('due', $this->t('Terms days is empty') );
        }      

        if($form_state->getValue('terms') == 1  && !is_numeric($form_state->getValue('due'))) {
          $form_state->setErrorByName('due',  $this->t('Terms days should be numeric') );
        }

        $rows = $form_state->getValue('itemTable');
        if(!empty($rows)){
            foreach ($rows as $key => $row) {
                if($row['value'] != 'footer' ) {
                    if($row['description'] == '') {
                        $form_state->setErrorByName("itemTable][$key][description", $this->t('Item @n is empty', array('@n'=> $key)) );
                    }
                    if($row['value'] != 'incoterm' ) {
                        if($row['quantity'] == '' || !is_numeric($row['quantity'])) {
                            $form_state->setErrorByName("itemTable][$key][quantity", $this->t('there is no quantity for item @n', array('@n'=> $key)) );
                        }
                        if($row['value'] == '' || !is_numeric($row['value'])) {
                            $form_state->setErrorByName("itemTable][$key][value",  $this->t('there is no value for item @n', array('@n'=> $key)) );
                        }  
                    }
                            
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        //create new serial No
        $iid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_sales_invoice}")
                    ->fetchField();
        $iid++;
        $short = Database::getConnection('external_db', 'external_db')
                ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                ->fetchField();
        $date = substr($form_state->getValue('date'), 2,5);
        $sup = Database::getConnection('external_db', 'external_db')
                ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))
                ->fetchField();      
        $serial = ucwords(str_replace('-', '', $short)) . "-I-" . $date . "-" .  ucwords(str_replace('-', '',$sup )) . "-" . $iid ;

        $fx_rate = round($form_state->getValue('fx_rate'),4);

        if($this->moduleHandler->moduleExists('ek_finance')) {
            // used to calculate currency gain/loss from rate at invoice record time
            // and linebase
              $baseCurrency = $this->settings->get('baseCurrency');
              if($baseCurrency != $form_state->getValue('currency')) { 

                if($fx_rate <> '' && is_numeric($fx_rate)) {
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
        $sum = 0;
        if($this->moduleHandler->moduleExists('ek_finance')) {
          $journal = new \Drupal\ek_finance\Journal();
        }
        $rows = $form_state->getValue('itemTable');
        if(!empty($rows)){
                foreach ($rows as $key => $row) {     
                    if($row['value'] != 'footer') {
                        if($row['delete'] != 1) { 

                            if($row['value'] == 'incoterm'){
                              //incoterm record as item

                                $incoterm = explode('|', $form_state->getValue('incoterm'));
                                if ($incoterm[0] == '0') {
                                    continue;
                                } else {
                                    $item = Xss::filter($row["description"]);
                                    $itemdetail = '';
                                    $row["quantity"] = 1;
                                    $row["value"] = round($sum * $incoterm[1] / 100, 2);
                                }

                            } elseif($this->moduleHandler->moduleExists('ek_products')) {
                              //verify if item is in the DB if not just record input
                              $item = explode(" ", $row["description"]);
                              $id = trim($item[0]);
                              if(isset($item[1])) {
                                  $code = trim($item[1]);
                              }
                              $description = \Drupal\ek_products\ItemData::item_description_byid($id, 1); 
                              if($description) {
                                  $item = $description;
                                  $itemdetail = $id;
                                } else {
                                  $item = Xss::filter($row["description"]);
                                  $itemdetail = '';
                                }

                            } else {
                              //use input from user
                              $item = Xss::filter($row["description"]);
                              $itemdetail = '';
                            }

                          $line = (round($row["quantity"] * $row["value"] , 2));
                          $linebase = (round($row["quantity"] * $row["value"] / $currencyRate, 2));
                          $sum = $sum + $line;
                          if($row["tax"] == 1) {
                            $taxable = $taxable + $line;
                          }
                          if(!$row["account"]) {
                            $account = 0;
                          } else {
                            $account = $row["account"];
                          } 

                          $fields=array('serial' => $serial,
                                        'item' => $item, // description used in displays
                                        'itemdetail' => $itemdetail, //add detail / id if item is in DB
                                        'quantity' => $row["quantity"],
                                        'value' => $row["value"],
                                        'total' => $line,
                                        'totalbase' => $linebase,
                                        'opt'  => $row["tax"],
                                        'aid' => $account
                                        );

                          $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_sales_invoice_details')
                            ->fields($fields)
                            ->execute();

                        }//if not delete
                }//if not footer
            }//for
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
            'comment' => Xss::filter($form_state->getValue('comment')),
            'client' => $form_state->getValue('client'),
            'amountreceived' => 0,
            'pay_date' => '',
            'amountbase' => $amountbc,
            'balancebase' => $amountbc,
            'terms' => Xss::filter($form_state->getValue('terms')),
            'due' => $due,
            'bank' => $form_state->getValue('bank_account'),
            'tax' => $form_state->getValue('tax'),
            'taxvalue' => $taxvalue,
            'reconcile' => 0,
        );

        $insert = Database::getConnection('external_db', 'external_db')
                ->insert('ek_sales_invoice')
                ->fields($fields1)
                ->execute();


        //
        // Record the accounting journal
        //
        if ($this->moduleHandler->moduleExists('ek_finance')) {

        foreach ($rows as $key => $row) {     
            if($row['value'] != 'footer') {
                if($row['delete'] != 1) {
                    
                  if($row['value'] == 'incoterm'){
                    //incoterm record as item
                      $incoterm = explode('|', $form_state->getValue('incoterm'));
                      if ($incoterm[0] == '0') {
                          continue;
                      } else {
                          $row["quantity"] = 1;
                          $row["value"] = round($sum * $incoterm[1] / 100, 2);
                      }

                  }   
                    
                  if ($form_state->getValue('taxvalue') > 0 && $row['tax'] == 1) {
                        $tax = round($row['value'] * $row['quantity'] * $form_state->getValue('taxvalue')/100,2);
                    } else {
                        $tax = 0;
                    }
                  $line = (round($row['quantity'] * $row['value'],2));
                  $journal->record(
                          array(
                          'source' => "invoice",
                          'coid' => $form_state->getValue('head'),
                          'aid' => $row['account'],
                          'reference' => $insert,
                          'date' => $form_state->getValue('date'),
                          'value' => $line,
                          'currency' => $form_state->getValue('currency'),
                          'fxRate' => $currencyRate,
                          'tax' => $tax,
                           )
                          );   

                }

            }
        } //for
          

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
                \Drupal::messenger()->addErrors(t('Error journal record (@aid)', ['@aid' => $msg]));
            }
            
        } //if finance  
        
        //change quotation status
        Database::getConnection('external_db', 'external_db')->update('ek_sales_quotation')
                ->fields(array('status' => 2))
                ->condition('serial', $form_state->getValue('quotation_serial'))
                ->execute();

        if (isset($insert)) {
            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            \Drupal::messenger()->addStatus(t('The invoice @r is recorded', ['@r' => $serial]));
            
                switch($form_state->getValue('redirect')) {
                case 0 :
                    $form_state->setRedirect('ek_sales.invoices.list');
                    break;
                case 1 :
                    $form_state->setRedirect('ek_sales.invoices.print_share', ['id' => $insert]);
                    break;
                case 2 :
                    $form_state->setRedirect('ek_sales.invoices.pay', ['id' => $insert]);
                    break;
                }
        }
    }

}
