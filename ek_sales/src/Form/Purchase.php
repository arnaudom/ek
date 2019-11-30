<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\Purchase.
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
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to create and edit purchases.
 */
class Purchase extends FormBase {
    
    /**
     * The file storage service.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $fileStorage;
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
    public function __construct(ModuleHandler $module_handler,EntityStorageInterface $file_storage) {
        $this->salesSettings = new \Drupal\ek_sales\SalesSettings();
        $this->moduleHandler = $module_handler;
        $this->fileStorage = $file_storage;
        if($this->moduleHandler->moduleExists('ek_finance')) {
            $this->Financesettings = new \Drupal\ek_finance\FinanceSettings();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler'),
                $container->get('entity.manager')->getStorage('file')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_new_purchase';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $clone = NULL) {
        
        $url = Url::fromRoute('ek_sales.purchases.list', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@url">List</a>', array('@url' => $url ) ) ,

        );

        if (isset($id) && !$id == NULL) {
            //edit
            $data = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * from {ek_sales_purchase} WHERE id=:id", array(':id' => $id))
                    ->fetchObject();
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * from {ek_sales_purchase_details} WHERE serial=:s ORDER BY id", array(':s' => $data->serial));

            If ($clone != 'clone') {
                $form['edit_purchase'] = array(
                    '#type' => 'item',
                    '#markup' => t('Purchase ref. @p', array('@p' => $data->serial)),
                );
                $form['serial'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                );
            } else {
                $form['clone_purchase'] = array(
                    '#type' => 'item',
                    '#markup' => "<div class='messages messages--warning'>"
                        . t('Template purchase based on ref. @p . A new purchase will be generated.', array('@p' => $data->serial))
                        . "</div>",
                );
                $data->date = date('Y-m-d');

                $form['new_purchase'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                );
            }
            $n = 0;
            $form_state->set('current_items', 0);
            if (!$form_state->get('num_items'))
                $form_state->set('num_items', 0);


            if (!$form_state->getValue('head'))
                $form_state->setValue('head', $data->head);

            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $chart = $this->Financesettings->get('chart');
                $AidOptions = \Drupal\ek_finance\AidList::listaid($data->head, array($chart['assets'],$chart['cos'],$chart['expenses'],$chart['other_expenses']), 1 );
                
            }

            $form_state->setRebuild();
            
        } else {
            //new
            $form['new_purchase'] = array(
                '#type' => 'hidden',
                '#value' => 1,
            );

            $grandtotal = 0;
            $taxable = 0;
            $n = 0;
            $taxamount = 0;
            $AidOptions = array();
            
        }

        $baseCurrency = '';
        $currenciesList = '';
        
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $CurrencyOptions = \Drupal\ek_finance\CurrencyData::listcurrency(1);
            $baseCurrency = $this->Financesettings->get('baseCurrency'); 
            $currenciesList = \Drupal\ek_finance\CurrencyData::currencyRates();
            $chart = $this->Financesettings->get('chart');
            if(empty($chart)) {
                $alert =   "<div id='fx' class='messages messages--warning'>" . t('You did not set the accounts chart structure. Go to <a href="@url">settings</a>.' ,
                        array('@url' => Url::fromRoute('ek_finance.admin.settings', array(), array())->toString())). "</div>";
                $form['alert'] = array(
                    '#type' => 'item',
                    '#weight' => -17,
                    '#markup' => $alert,
            );          
            }
        }

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
            '#default_value' => isset($data->head) ? $data->head : NULL,
            '#title' => t('header'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'set_coid'),
                'wrapper' => 'add',
            ),
        );

        if(count($company) > 1) {
            $form['options']['allocation'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $company,
                '#required' => TRUE,
                '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
                '#title' => t('allocated'),
                '#description' => t('select an entity for which the purchase is done'),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
            );
        } else {
            $form['options']['allocation'] = array(
              '#type' => 'hidden',
              '#value' => key($company), 
              '#suffix' => '</div>',
            );
        }


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $supplier = \Drupal\ek_address_book\AddressBookData::addresslist(2);

            if (!empty($supplier)) {
                $form['options']['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $supplier,
                    '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                    '#required' => TRUE,
                    '#default_value' => isset($data->client) ? $data->client : NULL,
                    '#title' => t('supplier'),
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div></div></div>',
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $form['options']['supplier'] = array(
                  '#markup' => t("You do not have any <a title='create' href='@cl'>supplier</a> in your record.", ['@cl' => $link]),
                  '#prefix' => "<div class='messages messages--warning'>",
                  '#suffix' => '</div></div>',
                );
            }
        } else {

            $form['options']['supplier'] = array(
                '#markup' => t('You do not have any supplier list.'),
                '#default_value' => 0,
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div></div></div>', 
            );
        }




        $form['options']['date'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
            //'#prefix' => "<div class='container-inline'>",
            '#title' => t('date'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $options = array('1' => t('Purchase'), '4' => t('Debit note'));

        $form['options']['title'] = array(
          '#type' => 'select',
          '#size' => 1,
          '#options' => $options,
          '#required' => TRUE,
          '#default_value' => isset($data->type) ? $data->type : 1,
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
                '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
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
                '#suffix' => '</div></div></div>',
                '#ajax' => array(
                    'callback' => array($this, 'check_aid'),
                    'wrapper' => 'alert',
                ),
            );


            $form['options']['alert'] = array(
                '#type' => 'item',
                '#markup' => '',
                '#description' => '',
                '#prefix' => "<div id='alert' class=''>",
                '#suffix' => '</div>',
            );

            
        } // finance
        else {
            $l = explode(',', file_get_contents(drupal_get_path('module', 'ek_sales') . '/currencies.inc'));
            foreach($l as $key => $val) {
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


        $form['options']['tax'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => isset($data->tax) ? $data->tax : NULL,
            '#title' => t('tax'),
            '#prefix' => "<div class='container-inline'>",
            '#attributes' => array('placeholder' => t('ex. sales tax')),
        );

        $form['options']['taxvalue'] = array(
            '#type' => 'textfield',
            '#id' => 'taxvalue',
            '#size' => 10,
            '#maxlength' => 6,
            '#default_value' => isset($data->taxvalue) ? $data->taxvalue : NULL,
            '#title' => t('percent'),
            '#title_display' => 'after',
            '#suffix' => "</div>",
            '#attributes' => array('placeholder' => t('%'), 'class' => array('amount')),
        );

        $form['options']['terms'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array(t('on receipt'), t('due days')),
            '#default_value' => isset($data->terms) ? $data->terms : NULL,
            '#title' => t('terms'),
            '#prefix' => "<div class='container-inline'>",
            '#ajax' => array(
                  'callback' => array($this, 'check_day'), 
                  'wrapper' => 'calday',
                  'event' => 'change',
              ),            
        );

        $form['options']['due'] = array(
            '#type' => 'textfield',
            '#size' => 5,
            '#maxlength' => 3,
            '#default_value' => isset($data->due) ? $data->due : NULL,
            '#attributes' => array('placeholder' => t('days')),
            '#ajax' => array(
                  'callback' => array($this, 'check_day'), 
                  'wrapper' => 'calday',
                  'event' => 'change',
              ),
          );
        
        $form['options']['day'] = array (
              '#type' => 'item',
              '#markup' => '',
              '#prefix' => "<div  id='calday'>",
              '#suffix' => "</div></div>",
          );

        $form['options']['comment'] = array(
            '#type' => 'textarea',
            '#rows' => 1,
            '#default_value' => isset($data->comment) ? $data->comment : NULL,
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
            //'#limit_validation_errors' => array(),
            '#submit' => array(array($this, 'addForm')),
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
        $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';

        while ($d = $detail->fetchObject()) {

                $n++;
                $c = $form_state->get('current_items') + 1;
                $form_state->set('current_items', $c);
                //$form_state->setRebuild();
                $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';
                $total = number_format($d->value * $d->quantity,2);
                $grandtotal += ($d->value * $d->quantity);
                if($d->opt == 1) {
                  $taxable += ($d->value * $d->quantity);
                }
                $link = NULL;
                
                if ($d->itemdetail != "" && $this->moduleHandler->moduleExists('ek_products') )  {
                    $item = \Drupal\ek_products\ItemData::item_byid($d->itemdetail);  
                    if(isset($item)) {
                       $name = $item; 
                       $link = \Drupal\ek_products\ItemData::geturl_byId($d->itemdetail, TRUE);
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
                            '#attributes' => array('placeholder'=>t('item')),
                            '#default_value' => $name,
                            '#field_prefix' => "<span class='badge'>". $n ."</span>",
                            '#field_suffix' => isset($link) ? "<span class='badge'>". $link ."</span>" : '',
                            '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                        );
                if($this->moduleHandler->moduleExists('ek_finance')) { 
                    $form['account'] = array(
                                '#id' => 'account-' . $n,
                                '#type' => 'select',
                                '#size' => 1,
                                '#options' => $AidOptions,
                                '#attributes' => array('style' => array('width:110px;')),
                                '#default_value' => $d->aid,
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
                            '#default_value' => $d->quantity,
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
            $form['description'] = array(
                        '#id' => 'description-' . $i,
                        '#type' => 'textfield',
                        '#size' => 40,
                        '#maxlength' => 255,
                        '#field_prefix' => "<span class='badge'>". $i ."</span>",
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
                        //'#default_value' => $d->value,
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
            
        }

        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => $n - 1,
            '#attributes' => array('id' => 'itemsCount'),
        );


        if (($form_state->get('num_items') && $form_state->get('num_items') > 0) || isset($detail)) {

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

        //Table footer
        //total
            $n++;
                if(isset($id) && $baseCurrency != $data->currency) {
                      $c = \Drupal\ek_finance\CurrencyData::currencyRates();
                      $converted = round($grandtotal/$c[$data->currency],2) . " " . $baseCurrency;
                  } else {
                      $converted = '';
                  }
                    $form['description'] = array(                    
                                '#type' => 'item',
                                '#markup' => t('Total') . " " . "<span id='convertedValue' class='badge'>".$converted."</span>"
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
                            );
                    $form['tax'] = array(
                                '#type' => 'item',
                            );
                    $form['total'] = array(
                                '#id' => 'itemsTotal',
                                '#type' => 'textfield',    
                                '#size' => 12,
                                '#maxlength' => 250,
                                '#value' => isset($grandtotal) ?  number_format($grandtotal, 2) : 0,
                                '#attributes' => array('placeholder'=>t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
                            ); 
                    $form['delete'] = array(
                                '#type' => 'item',
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
                $taxamount = isset($data->taxvalue) ? round(($taxable * $data->taxvalue/100), 2): Null ;
                $n++;
                $form['description'] = array(                    
                            '#type' => 'item',
                            '#markup' => t('Tax payable'),
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
                        );
                $form['tax'] = array(
                            '#type' => 'item',
                        );
                $form['total'] = array(
                            '#id' => 'taxValue',
                            '#type' => 'textfield',    
                            '#size' => 12,
                            '#maxlength' => 250,
                            '#value' => number_format($taxamount,2),
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

        //purchase total
                $n++;
                $form['description'] = array(                    
                            '#type' => 'item',
                            '#markup' => t('Total purchase'),
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
                        );
                $form['tax'] = array(
                            '#type' => 'item',
                        );
                $form['total'] = array(
                            '#id' => 'totalWithTax',
                            '#type' => 'textfield',    
                            '#size' => 12,
                            '#maxlength' => 250,
                            '#default_value' => number_format($grandtotal + $taxamount, 2),
                            '#attributes' => array('placeholder'=>t('total purchase'), 'readonly' => 'readonly', 'class' => array('amount')),
                        ); 
                $form['delete'] = array(
                            '#type' => 'item',
                        );
                //built purchase total row for table
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

        }

        if (isset($data->uri) && $data->uri != '') {
            
            $fparts = explode('/', $data->uri);
            $fparts = array_reverse($fparts);
            
            $attachment = "<div class='table' id='purchase_form_att'>"
                    . "<div class='row'>"
                    . "<div class='cell'>"
                    . "<a href='" . file_create_url($data->uri) . "' target='_blank'>" . t('Attached document') . ":<strong> " .$fparts[0]. "</strong></a></div>"
                    . "</div></div>";
            
            $form['file']['attachement'] = array(
                '#type' => 'item',
                '#markup' => $attachment,
            );
        }

        $form['file']['upload_doc'] = [
            '#title' => isset($data->uri) ? $this->t('Attach a new file') : $this->t('Attach a file'),
            '#type' => 'managed_file',
            '#upload_validators' => [
              'file_validate_extensions' => ['png jpg jpeg doc docx xls xlsx odt ods odp pdf rar rtf tiff zip'],
              ],
      
        ];

        $redirect = array(0 => t('view list'), 1 => t('print'), 2 => t('record payment'));

        $form['actions']['redirect'] = array(
         '#type' => 'radios',
         '#title' => t('Next'),
         '#default_value' => 0,
         '#options' => $redirect,
        );        

        if (($form_state->get('num_items') && $form_state->get('num_items') > 0) || isset($detail)) {
            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => array('class' => array('button--record')),
            );
        }

        $form['#attached'] = array(
                'drupalSettings' => array('currencies' => $currenciesList, 'baseCurrency' => $baseCurrency),
                'library' => array('ek_sales/ek_sales.purchase'),
        );

        return $form;
    }

//

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
    public function check_aid(array &$form, FormStateInterface $form_state) {

        //return alert
        $coid = $form_state->getValue('head');
        $currency = $form_state->getValue('currency');
        $settings = new CompanySettings($coid);
        $aid = $settings->get('liability_account', $currency);


        if ($aid == '') {
            $l = "../ek_admin/company/edit-settings/" . $coid;
            $form['options']['alert']['#prefix'] = "<div id='alert' class='messages messages--warning'>";
            $form['options']['alert']['#markup'] = t("There is no liability account set for this company and currency. Please <a href='@l'>edit settings</a> or contact administrator.",['@l' => $l]);
            $form['options']['alert']['#description'] = '';
        } else {
            $form['options']['alert']['#prefix'] = "<div id='alert' class=''>";
            $form['options']['alert']['#markup'] = '';
            $form['options']['alert']['#description'] = '';
        }
        return $form['options']['alert'];
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
            $form_state->set('AidOptions', \Drupal\ek_finance\AidList::listaid($form_state->getValue('head'),array($chart['assets'],$chart['cos'],$chart['expenses'],$chart['other_expenses']), 1));
        }

        $form_state->setRebuild();
    }

  
    /**
       * Callback : calculate due date 
       */
      public function check_day(array &$form, FormStateInterface $form_state) {

          if($form_state->getValue('terms') == '1' && $form_state->getValue('due') != NULL) {
            $form['options']['day']["#markup"] = date('Y-m-d',strtotime(date("Y-m-d", strtotime($form_state->getValue('date')) ) . "+". $form_state->getValue('due') . ' ' . t("days") ));
          } else {
            $form['options']['day']["#markup"] = '';  
          }
          return $form['options']['day'];
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
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

    //@TODO : filter wrong currency selection
        if($this->moduleHandler->moduleExists('ek_finance')) {
       
            $settings = new CompanySettings($form_state->getValue('head'));
            $aid = $settings->get('liability_account', $form_state->getValue('currency') );
            if($aid == '') {
                $l = "../ek_admin/company/edit-settings/" . $form_state->getValue('head');
                $form_state->setErrorByName('currency', t("There is no liability account set for this company and currency. Please <a href='@l'>edit settings</a> or contact administrator.",['@l' => $l]));
            }
        }
    
        if ($form_state->getValue('alert') == '1') {
            $form_state->setErrorByName('currency', $this->t('Currency accounts error'));
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
        if(!empty($rows)){
            foreach ($rows as $key => $row) {
                if($row['value'] != 'footer' ) {
                    if($row['description'] == '') {
                        $form_state->setErrorByName("itemTable][$key][description", $this->t('Item @n is empty', array('@n'=> $key)) );
                    }

                    if($row['quantity'] == '' || !is_numeric($row['quantity'])) {
                        $form_state->setErrorByName("itemTable][$key][quantity", $this->t('there is no quantity for item @n', array('@n'=> $key)) );
                    }
                    if($row['value'] == '' || !is_numeric($row['value'])) {
                        $form_state->setErrorByName("itemTable][$key][value",  $this->t('there is no value for item @n', array('@n'=> $key)) );
                    }            
                    //if($this->moduleHandler->moduleExists('ek_finance')) {
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
        
        $options = array('1' => 'Purchase', '4' => 'Debit note');

        if ($form_state->getValue('new_purchase') == 1) {
            //create new serial No
            switch ($form_state->getValue('title')) {
            case '4':
                $type = "DN";
                break;
            default:
                $type = "PO";
                break;
            }
               
            $short = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                    ->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('supplier')))
                    ->fetchField();
            $format = $this->salesSettings->get('serialFormat');
            if($format['code'] == '') {
                $format['code'] = [1,2,3,4,5];
            }
            if ($format['increment'] == '' || $format['increment'] < 1) {
                $format['increment'] = 1;
            }
            
            $poid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_sales_purchase}")
                    ->fetchField();
            $poid = $poid +1 +$format['increment'];
            $query = "SELECT id FROM {ek_sales_purchase} WHERE serial like :s";
            while (Database::getConnection('external_db', 'external_db')->query($query,[':s' => '%-' .$poid])->fetchField()) {
                //to prevent serial duplication after document have been deleted, increment until no match is found
                $poid++;
            }         
            
            $serial = '';
            //$serial = ucwords(str_replace('-', '', $short)) . $type . $date . "-" . ucwords(str_replace('-', '', $sup)) . "-" . $poid;
            foreach($format['code'] as $k => $v) {
                    switch ($v) {
                        case 0 :
                            break;
                        case 1 :
                            $serial .= ucwords(str_replace('-', '', $short)) . '-';
                            break;
                        case 2 :
                            $serial .= $type . '-';
                            break;
                        case 3 :
                            $serial .= $date . '-';
                            break;
                        case 4 :
                            $serial .= ucwords(str_replace('-', '', $sup)) . '-';
                            break;
                        case 5 :
                            $serial .= $poid;
                            break;
                        
                    }
            }            
            
            
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_sales_purchase_details')
                    ->condition('serial', $form_state->getValue('serial'))
                    ->execute();
            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_purchase', 'p')
                ->fields('p')
                ->condition('serial', $serial)
                ->execute();
            $init_data = $query->fetchAssoc();
            $poid = $init_data['id'];
            
        }


// Items  

        $line = 0;
        $total = 0;
        $taxable = 0;
        $sum = 0;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $journal = new \Drupal\ek_finance\Journal();
        }
        $rows = $form_state->getValue('itemTable');
        if(!empty($rows)){
            foreach ($rows as $key => $row) {     
                if($row['value'] != 'footer') {
                    if($row['delete'] != 1) { 
                                if ($this->moduleHandler->moduleExists('ek_products')) {
                                    //verify if item is in the DB if not just record input

                                    $item = explode(" ", $row["description"]);
                                    $id = trim($item[0]);
                                    if(isset($item[1])) {
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
                                    //use input from user
                                    $item = Xss::filter($row["description"]);
                                    $itemdetail = '';
                                }

                                $line = (round($row["quantity"] * $row["value"] , 2));
                               // $linebase = (round($row["quantity"] * $row["value"] / $currencyRate, 2));
                                $sum = $sum + $line;
                                if ($row["tax"] == 1) {
                                    $taxable = $taxable + $line;
                                }

                                if (!$row["account"]) {
                                    $account = 0;
                                } else {
                                    $account = $row["account"];
                                }

                                $fields = array('serial' => $serial,
                                    'item' => $item, // description used in displays
                                    'itemdetail' => $itemdetail, //add detail / id if item is in DB
                                    'quantity' => $row["quantity"],
                                    'value' => $row["value"],
                                    'total' => $line,
                                    'opt' => $row["tax"],
                                    'aid' => $account
                                );

                                $insert = Database::getConnection('external_db', 'external_db')
                                        ->insert('ek_sales_purchase_details')
                                        ->fields($fields)
                                        ->execute();
                        }//if not delete
                }//if not footer
            }//for
        }


        /*
          if($form_state->getValue('bank') == '')
          { $bank = 0;
          } else {
          $bank = $form_state->getValue('bank');
          }
         */
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
            // used to calculate currency gain/loss from rate at purchase record time
            $baseCurrency = $this->Financesettings->get('baseCurrency');
            $sumwithtax = $sum + (round($taxable * $form_state->getValue('taxvalue') / 100, 2));
            if ($baseCurrency <> $form_state->getValue('currency')) {
                $currencyRate = \Drupal\ek_finance\CurrencyData::rate($form_state->getValue('currency'));
                //calculate the value in base currency of the amount without tax
                $amountbc = round($sum / $currencyRate, 2);
            } else {
                $amountbc = $sum;
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
            'title' => $options[$form_state->getValue('title')],
            'type' => $form_state->getValue('title'),
            'pcode' => $pcode,
            'comment' => Xss::filter($form_state->getValue('comment')),
            'client' => $form_state->getValue('supplier'),
            'amountpaid' => 0,
            'amountbc' => $amountbc,
            'balancebc' => $amountbc,
            'pdate' => '',
            'bank' => '',
            'terms' => Xss::filter($form_state->getValue('terms')),
            'due' => $due,
            'reconcile' => 0,
            'tax' => $form_state->getValue('tax'),
            'taxvalue' => $taxvalue,
            'pay_ref' => '',
            'alert' => 0,
            'alert_who' => '',
        );

        if ($form_state->getValue('new_purchase') == 1) {
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_purchase')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')->update('ek_sales_purchase')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $poid;
        }

        //
        // Update attachment
        // File are recorded in file_managed and purchase table

        $fid = $form_state->getValue(['upload_doc', 0]);
        if ($fid) {

            if ($form_state->getValue('new_purchase') != 1) {
                //delete previous file if any
                $query = 'SELECT uri from {ek_sales_purchase} WHERE serial = :s';
                $uri = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':s' => $serial))
                        ->fetchField();

                if($uri != '') {
                    $query = "SELECT * FROM {file_managed} WHERE uri=:u";
                    $sysfile = db_query($query, [':u' => $uri])->fetchObject();
                    file_delete($sysfile->fid);
                    \Drupal::messenger()->addWarning(t('Previous attachment has been deleted.'));
                }
            }
           
                $file = $this->fileStorage->load($fid);
        
                $dir = "private://sales/purchase/" . $reference . "";
                file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                
                $move = file_copy($file, $dir, FILE_EXISTS_RENAME);
                $move->setPermanent();
                $move->save();
                
                $fields = array(
                    'uri' =>$move->getFileUri(),
                );

                $update = Database::getConnection('external_db', 'external_db')
                                ->update('ek_sales_purchase')
                                ->condition('id', $reference)
                                ->fields($fields)->execute();
            
        }

       

    // Record the accounting journal
    // Debit  notes are not recorded in journal, only once assigned to purchase
    // (a DN is deduction of payable) 
    //
    if($form_state->getValue('title') < 4         
        && $this->moduleHandler->moduleExists('ek_finance')) {

            /*
             * delete first
             */
            if (!$form_state->getValue('new_purchase') == 1) {
                $delete = Database::getConnection('external_db', 'external_db')
                        ->delete('ek_journal')
                        ->condition('reference', $poid)
                        ->condition('source', 'purchase')
                        ->execute();
            }


            foreach ($rows as $key => $row) {     
                if($row['value'] != 'footer') {

                    if ($row['delete'] != 1) {
                        if ($form_state->getValue('taxvalue') > 0 && $row['tax'] == 1) {
                            $tax = round($row['value'] * $form_state->getValue('taxvalue') / 100, 2);
                        } else {
                            $tax = 0;
                        }
                        $line = (round($row['quantity'] * $row['value'], 2));
                        $journal->record(
                                array(
                                    'source' => "purchase",
                                    'coid' => $form_state->getValue('head'),
                                    'aid' => $row['account'],
                                    'reference' => $reference,
                                    'date' => $form_state->getValue('date'),
                                    'value' => $line,
                                    'currency' => $form_state->getValue('currency'),
                                    'tax' => $tax,
                                )
                        );
                    }
                }
            } //for

            $journal->recordtax(
                    array(
                        'source' => "purchase",
                        'coid' => $form_state->getValue('head'),
                        'reference' => $reference,
                        'date' => $form_state->getValue('date'),
                        'currency' => $form_state->getValue('currency'),
                        'type' => 'stax_deduct_aid',
                    )
            );
            
            if(round($journal->credit,4) <> round($journal->debit,4)) {
                $msg = 'debit: ' . $journal->debit . ' <> ' . 'credit: ' . $journal->credit;
                \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $msg]));
            }
        }

        Cache::invalidateTags(['project_page_view']);
        if (isset($insert) || isset($update)) {
            Cache::invalidateTags(['reporting']);
            \Drupal::messenger()->addStatus(t('The purchase is recorded. Ref @r', ['@r' => $serial]));
  
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if purchase is linked to a project
                if ($pcode && $pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $pcode])
                            ->fetchField();
                    $inputs = [];
                    if(isset($init_data)){
                        foreach($fields1 as $key => $value) {
                            if ($value != $init_data[$key]) {
                                $inputs[] = $key;
                            }
                        }
                    }
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => 'purchase_edit',
                                'input' => $inputs,
                                'value' => $serial,
                                'pcode' => $pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
            if(isset($_SESSION['pfilter']['to'])) {
                //if filter is set for list adjust date to last created date to enforce document display
                $_SESSION['pfilter']['to'] = $form_state->getValue('date');
            }
            switch($form_state->getValue('redirect')) {
            case 0 :
                $form_state->setRedirect('ek_sales.purchases.list');
                break;
            case 1 :
                $form_state->setRedirect('ek_sales.purchases.print_share', ['id' => $reference]);
                break;
            case 2 :
                $form_state->setRedirect('ek_sales.purchases.pay', ['id' => $reference]);
                break;
            }
            
            
            
        
        }
    }

}
