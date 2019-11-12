<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Delivery.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_products\ItemSettings;

/**
 * Provides a form to create delivery order.
 */
class Delivery extends FormBase {

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
        return 'ek_logistics_delivery_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $clone = NULL) {

        $url = Url::fromRoute('ek_logistics_list_delivery', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@url">List</a>', array('@url' => $url)),
        );
        if (isset($id) && $id != NULL) {

            //edit existing DO
            $query = "SELECT * from {ek_logi_delivery} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchObject();
            $query = "SELECT * from {ek_logi_delivery_details} where serial=:id";
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data->serial));


            If ($clone != 'clone') {
                $form['edit_delivery'] = array(
                    '#type' => 'item',
                    '#markup' => t('Delivery order ref. @p', array('@p' => $data->serial)),
                );

                $form['serial'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                );
            } else {
                $form['clone_invoice'] = array(
                    '#type' => 'item',
                    '#markup' => t('Template delivery order based on ref. @p . A new DO will be generated.', array('@p' => $data->serial)),
                );

                $data->date = date('Y-m-d');

                $form['new_delivery'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                );
            }

            $grandtotal = 0;
            $n = 0;
            $form_state->set('current_items', 0);
            if (!$form_state->get('num_items')) {
                $form_state->set('num_items', 0);
            }

            if (!$form_state->getValue('head')) {
                $form_state->setValue('head', $data->head);
            }

            if ($this->moduleHandler->moduleExists('ek_finance')) {
                //$baseCurrency = $this->finance->get('baseCurrency');
            }
        } else {
            //new
            $form['new_delivery'] = array(
                '#type' => 'hidden',
                '#value' => 1,
            );
            $grandtotal = 0;
            $n = 0;
            $detail = NULL;
            $data = NULL;
        }



        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => ($id != NULL || $form_state->get('num_items') > 0) ? FALSE : TRUE,
            
        );

        $company = AccessCheck::CompanyListByUid();
        $form['options']['head'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#default_value' => isset($data->head) ? $data->head : NULL,
            '#title' => t('Header'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );


        $form['options']['allocation'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
            '#title' => t('Allocated'),
            '#description' => t('select an entity for which the delivery is done'),
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
                    '#attributes' => array('style' => array('width:300px;white-space:nowrap')),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();

                $form['options']['client'] = array(
                    '#markup' => t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => '</div>',
                );
            }
        } else {

            $form['options']['client'] = array(
                '#markup' => t('You do not have any client list.'),
                '#prefix' => "<div class='messages messages--warning'>",
                '#suffix' => '</div>',
            );
        }




        $form['options']['date'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => isset($data->ddate) ? $data->ddate : date('Y-m-d'),
            '#title' => t('Delivery date'),
        );


        $form['options']['title'] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#default_value' => isset($data->title) ? $data->title : NULL,
            '#attributes' => array('placeholder' => t('comment')),
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

        $form['options']['po'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => isset($data->po) ? $data->po : NULL,
            '#title' => t('Client PO ref.'),
            '#prefix' => "<div class='container-inline'>",
        );


        $form['options']['ordered_quantity'] = array(
            '#type' => 'textfield',
            '#size' => 6,
            '#maxlength' => 10,
            '#default_value' => isset($data->ordered_quantity) ? $data->ordered_quantity : NULL,
            '#title' => t('Ordered quantities'),
            '#attributes' => array('placeholder' => t('units')),
            '#suffix' => '</div>',
        );


        $form['items'] = array(
            '#type' => 'details',
            '#title' => $this->t('Items'),
            '#open' => TRUE,
        );


        $form['items']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            '#limit_validation_errors' => [['head'], ['itemTable']],
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
                    'data' => $this->t('Items'),
                    'id' => ['tour-item1'],
                ),
                'price_type' => array(
                    'data' => $this->t('Price type'),
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
                'total' => array(
                    'data' => $this->t('Total'),
                    'id' => ['tour-item7'],
                ),
                'delete' => array(
                    'data' => $this->t('Delete'),
                    'id' => ['tour-item5'],
                ),
            );
            $itemSettings = new ItemSettings();
            $options = [
                0 => '-',
                1 => $itemSettings->get('selling_price_label'),
                2 => $itemSettings->get('promo_price_label'),
                3 => $itemSettings->get('discount_price_label'),
                4 => $itemSettings->get('exp_selling_price_label'),
                5 => $itemSettings->get('exp_promo_price_label'),
                6 => $itemSettings->get('exp_discount_price_label'),
            ];
        } else {

            $header = array(
                'description' => array(
                    'data' => $this->t('Items'),
                    'id' => ['tour-item1'],
                ),
                'price' => array(
                    'data' => $this->t('Price type'),
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
        $z = 0;
        if (isset($detail)) {
            //edition mode - list current items
            
            while ($d = $detail->fetchObject()) {

                $n++;
                $z++;
                $link = NULL;
                $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';
                $trClass = 'tr' . $n;

                $total = number_format($d->value * $d->quantity, 2);
                $grandtotal += $d->value * $d->quantity;

                if ($d->itemcode != "" && $this->moduleHandler->moduleExists('ek_products')) {
                    $item = \Drupal\ek_products\ItemData::item_bycode($d->itemcode);
                    if (isset($item)) {
                        $name = $item;
                        $link = \Drupal\ek_products\ItemData::geturl_bycode($d->itemcode, TRUE);
                    } else {
                        $name = $d->itemcode;
                    }
                } else {
                    $name = $d->itemcode;
                }

                $form['description'] = array(
                    '#id' => 'description-' . $n,
                    '#type' => 'textfield',
                    '#size' => 40,
                    '#maxlength' => 255,
                    '#attributes' => array('placeholder' => t('item')),
                    '#default_value' => $name,
                    '#field_prefix' => "<span class='badge'>" . $n . "</span>",
                    '#field_suffix' => isset($link) ? "<span class='badge'>" . $link . "</span>" : '',
                    '#autocomplete_route_name' => $this->moduleHandler->moduleExists('ek_products') ? 'ek.look_up_item_ajax' : '',
                );

                if ($this->moduleHandler->moduleExists('ek_products')) {
                    if (isset($rows[$n]['description'])) {
                        //default data is input
                        $itemDescription = $rows[$n]['description'];
                        $itemValue = $rows[$n]['value'];
                        $thisItemId = explode(' ', $itemDescription);
                        $thisItemId = trim($thisItemId[1]);
                        $priceType = $rows[$n]['price_type'];
                    } else {
                        //default data : record
                        $itemDescription = $name;
                        $itemValue = $d->value;
                        $thisItemId = explode(' ', $itemDescription);
                        if (isset($thisItemId[1])) {
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
                    $form['price_type'] = array(
                        '#id' => 'price_type-' . $n,
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $options,
                        '#attributes' => array('style' => array('width:110px;'), 'class' => array('amount')),
                        '#default_value' => $priceType,
                        '#required' => TRUE,
                        '#ajax' => array(
                            'callback' => array($this, 'put_price'),
                            'wrapper' => 'v' . $n,
                        ),
                    );
                } else {
                    $form['price_type'] = '';
                }
                $form['quantity'] = array(
                    '#id' => 'quantity' . $n,
                    '#type' => 'textfield',
                    '#size' => 8,
                    '#maxlength' => 30,
                    '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                    '#default_value' => $d->quantity,
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
                    '#prefix' => "<div id='v$n'>",
                    '#suffix' => '</div>',
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
                        'onclick' => "jQuery('#" . $n . "').toggleClass('delete');",
                        'class' => array('amount')
                    ),
                );
                //built edit rows for table
                $form['items']['itemTable'][$n] = array(
                    'description' => &$form['description'],
                    'price_type' => &$form['price_type'],
                    'quantity' => &$form['quantity'],
                    'value' => &$form['value'],
                    'total' => &$form['total'],
                    'delete' => &$form['delete'],
                );

                $form['items']['itemTable']['#rows'][$n] = array(
                    'data' => array(
                        array('data' => &$form['description']),
                        array('data' => &$form['price_type']),
                        array('data' => &$form['quantity']),
                        array('data' => &$form['value']),
                        array('data' => &$form['total']),
                        array('data' => &$form['delete']),
                    ),
                    'id' => array($n),
                    'class' => $rowClass,
                );
                unset($form['description']);
                unset($form['price_type']);
                unset($form['quantity']);
                unset($form['value']);
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
            
            $z++;

            $form['description'] = array(
                '#id' => 'description-' . $n,
                '#type' => 'textfield',
                '#size' => 40,
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => t('item')),
                '#default_value' => '',
                '#field_prefix' => "<span class='badge'>" . $z . "</span>",
                '#autocomplete_route_name' => $this->moduleHandler->moduleExists('ek_products') ? 'ek.look_up_item_ajax' : '',
            );
            if ($this->moduleHandler->moduleExists('ek_products')) {
                //default data is input
                $itemDescription = $rows[$n]['description'];
                $itemValue = $rows[$n]['value'];
                $thisItemId = explode(' ', $itemDescription);
                $thisItemId = (isset($thisItemId[1])) ? trim($thisItemId[1]) : 0;
                $priceType = $rows[$n]['price_type'];
                if ($priceType != 0) {
                    $sellPrice = \Drupal\ek_products\ItemData::item_sell_price($thisItemId, $priceType);
                    $disabled = TRUE;
                } else {
                    $sellPrice = isset($rows[$n]['value']) ? $rows[$n]['value'] : 0;
                    $disabled = FALSE;
                }

                $form['price_type'] = array(
                    '#id' => 'price_type-' . $n,
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $options,
                    '#attributes' => array('style' => array('width:110px;'), 'class' => array('amount')),
                    '#default_value' => '',
                    '#required' => TRUE,
                    '#ajax' => array(
                        'callback' => array($this, 'put_price'),
                        'wrapper' => 'v' . $n,
                    ),
                );
            } else {
                $form['price_type'] = '';
            }
            $form['quantity'] = array(
                '#id' => 'quantity' . $n,
                '#type' => 'textfield',
                '#size' => 8,
                '#maxlength' => 30,
                '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                '#default_value' => '',
                '#required' => TRUE,
            );
            $form['value'] = array(
                '#id' => 'value' . $n,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 50,
                '#default_value' => isset($sellPrice) ? $sellPrice : 0,
                '#disabled' => $disabled,
                '#attributes' => array('placeholder' => t('unit price'), 'class' => array('amount')),
                '#prefix' => "<div id='v$n'>",
                '#suffix' => '</div>',
            );

            $form['total'] = array(
                '#id' => 'total' . $n,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 250,
                '#default_value' => '',
                '#attributes' => array('placeholder' => t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['delete'] = array(
                '#item' => '',
            );
            //built edit rows for table
            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'price_type' => &$form['price_type'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['price_type']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['price_type']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['total']);
            unset($form['delete']);
            $n++;
        }

        $form['items']['count'] = array(
        '#type' => 'hidden',
        '#value' => $n-1,
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
                '#markup' => t('Total'),
            );
            $form['price_type'] = ['#type' => 'item'];
            $form['quantity'] = ['#type' => 'item'];
            $form['value'] = array('#type' => 'hidden', '#value' => 'footer', '#attributes' => ['id' => ['value' . $n]],);
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
                'priceType' => &$form['price_type'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'total' => &$form['total'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['price_type']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['value']),
                    array('data' => &$form['total']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['price_type']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['total']);
            unset($form['delete']);
            
            $form['actions'] = array(
              '#type' => 'actions',
            );

            $redirect = array(0 => t('preview'),1 => t('list'), 2 => t('print'));

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
       
        $form['#attached']['library'][] = 'ek_logistics/ek_logistics';


        return $form;
    }

//

    /**
     * callback : return price setting
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
     * Callback : Remove item from form
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

        if ($form_state->getValue('ordered_quantity') != "" && (!is_numeric($form_state->getValue('ordered_quantity')) || $form_state->getValue('ordered_quantity') < 0
                )
        ) {
            $form_state->setErrorByName('ordered_quantity', $this->t('Input a positive numeric value for quantity.'));
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
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('new_delivery') == 1) {
            //create new serial No
            $iid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_logi_delivery}")
                    ->fetchField();
            $iid++;
            $short = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                    ->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))
                    ->fetchField();
            $serial = ucwords(str_replace('-', '', $short)) . "-DO-" . $date . "-" . ucwords(str_replace('-', '', $sup)) . "-" . $iid;
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_logi_delivery_details')
                    ->condition('serial', $serial)
                    ->execute();
            $iid = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT id from {ek_logi_delivery} where serial=:s', array(':s' => $serial))
                    ->fetchField();
        }


// Items  

        $rows = $form_state->getValue('itemTable');
        if (!empty($rows)) {
            foreach ($rows as $key => $row) {
                if ($row['value'] != 'footer') {
                    if ($row['delete'] != 1) {
                        if ($this->moduleHandler->moduleExists('ek_products')) {
                            //verify if item is in the DB if not just record input
                            $item = explode(" ", $row["description"]);
                            $id = trim($item[0]);
                            if (isset($item[1]) && \Drupal\ek_products\ItemData::item_bycode($item[1])) {
                                $code = trim($item[1]);
                            } else {
                                $code = Xss::filter($row["description"]);
                            }
                        } else {
                            //use input from user
                            $code = Xss::filter($row["description"]);
                        }

                        $line = (round($row["quantity"] * $row["value"], 2));
                        $sum = $sum + $line;

                        $fields = array('serial' => $serial,
                            'itemcode' => $code,
                            'quantity' => $row["quantity"],
                            'value' => $row["value"],
                            'amount' => $line,
                            'currency' => '',
                            'date' => $form_state->getValue('date'),
                        );

                        $insert = Database::getConnection('external_db', 'external_db')
                                ->insert('ek_logi_delivery_details')
                                ->fields($fields)
                                ->execute();
                    }//if not delete
                }//if not footer
            }//for
        }
       
//main
        if ($form_state->getValue('pcode') == '') {
            $pcode = 'n/a';
        } else {
            $pcode = $form_state->getValue('pcode');
        }
        if ($form_state->getValue('ordered_quantity') == "") {
            $oq = 0;
        } else {
            $oq = $form_state->getValue('ordered_quantity');
        }
        $fields1 = array(
            'serial' => $serial,
            'head' => $form_state->getValue('head'),
            'allocation' => $form_state->getValue('allocation'),
            'date' => date('Y-m-d'),
            'ddate' => $form_state->getValue('date'),
            'title' => Xss::filter($form_state->getValue('title')),
            'po' => Xss::filter($form_state->getValue('po')),
            'pcode' => $pcode,
            'client' => $form_state->getValue('client'),
            'status' => 0,
            'amount' => $sum,
            'ordered_quantity' => $oq,
            'post' => 0
        );

        if ($form_state->getValue('new_delivery') && $form_state->getValue('new_delivery') == 1) {
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_logi_delivery')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')->update('ek_logi_delivery')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $iid;
        }


        if (isset($insert) || isset($update)) {
            \Drupal::messenger()->addStatus(t('The delivery is recorded. Ref. @r', ['@r' => $serial]));
            Cache::invalidateTags(['logistics_delivery_block']);
            switch($form_state->getValue('redirect')) {
                case 0 :
                    $form_state->setRedirect('ek_logistics.delivery.print_html', ['id' => $reference]);
                    break;
                case 1 :
                    $form_state->setRedirect('ek_logistics_list_delivery');
                    break;
                case 2 :
                    $form_state->setRedirect('ek_logistics_delivery_print_share', ['id' => $reference]);
                    break;
                
            }           
        }
    }

}
