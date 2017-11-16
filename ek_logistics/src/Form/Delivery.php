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
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_products\ItemData;
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
        $this->finance = new FinanceSettings();
        $this->items = new ItemSettings();
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
            if (!$form_state->get('num_items'))
                $form_state->set('num_items', 0);

            if (!$form_state->getValue('head'))
                $form_state->setValue('head', $data->head);

            if ($this->moduleHandler->moduleExists('ek_finance')) {
                $baseCurrency = $this->finance->get('baseCurrency');
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
            '#attributes' => '',
        );


        $form['items']['actions']['add'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add item'),
            //'#limit_validation_errors' => array(),
            '#submit' => array(array($this, 'addForm')),
            '#prefix' => "<div id='add'>",
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(
                    "select[name='head']" => array('value' => ''),
                ),
            ),
        );


        if ($this->moduleHandler->moduleExists('ek_products')) {

            $headerline = "<div class='table'  id='purchase_form_items'>
                  <div class='row'>
                      <div class='cell'>" . t("Items") . "</div>
                      <div class='cell'>" . t("Price type") . "</div>
                      <div class='cell'>" . t("Quantities") . "</div>
                      <div class='cell'>" . t("Unit price") . "</div>
                      <div class='cell'>" . t("delete") . "</div>

                      <div class='cell right'>" . t("Line total") . "</div>
                   ";
            
            $options = [
                        0 => '-',
                        1 => $this->items->get('selling_price_label'),
                        2 => $this->items->get('promo_price_label'),
                        3 => $this->items->get('discount_price_label'),
                        4 => $this->items->get('exp_selling_price_label'),
                        5 => $this->items->get('exp_promo_price_label'),
                        6 => $this->items->get('exp_discount_price_label'),
                    ];
            
        } else {
            $headerline = "<div class='table'  id='purchase_form_items'>
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
                $form_state->setValue("itemid$n", $d->itemcode);
               
                if(NULL === $form_state->getValue("value$n")) { 
                    if ($this->moduleHandler->moduleExists('ek_products') 
                            && $name = ItemData::item_bycode($d->itemcode)) { 
                        //item exist in database
                        $url = ItemData::geturl_bycode($d->itemcode, TRUE);
                        $t = ItemData::item_sell_price_type($d->itemcode, $d->value);
   
                        if($t) {
                            //The item is listed in DB and price is selected from list (DB)
                            $form_state->setValue("price_type-$n", $t);
                            $v = ItemData::item_sell_price($form_state->getValue("itemid$n"), $t);
                            $form_state->setValue("value$n", $v);
                            $disabled = TRUE;
                        } else {
                           //The item is listed but price input is custom
                            $form_state->setValue("price_type-$n", 0);
                            $form_state->setValue("value$n", $d->value);
                            $disabled = FALSE;
                        }
           
                    } else {
                        //custom description
                        $url = '';
                        $name = $d->itemcode;
                        $form_state->setValue("price_type-$n", 0);
                        $form_state->setValue("value$n", $d->value);
                        $disabled = FALSE;
                    }
                } else { 
  
                   if ($this->moduleHandler->moduleExists('ek_products') 
                           && $name = ItemData::item_bycode($form_state->getValue("itemid$n"))) { 
                        //item exist in database
                       if($form_state->getValue("price_type-$n") != '0') {
                        $v = ItemData::item_sell_price($form_state->getValue("itemid$n"), $form_state->getValue("price_type-$n"));
                        $form_state->setValue("value$n", $v);
                        $disabled = TRUE;
                    
                       } else {
                           $disabled = FALSE;
                       }
           
                    } else {
                        $disabled = FALSE;
                    } 
                }
                

                $form['items']["itemid$n"] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 255,
                    '#default_value' => $name,
                    '#description' => isset($url) ? $url : '',
                    '#attributes' => array('placeholder' => t('item code, barcode, description')),
                    '#prefix' => "<div class='row current'><div class='cell'>",
                    '#suffix' => '</div>',
                    '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                );

                if ($this->moduleHandler->moduleExists('ek_products')) {
                   
                    $form['items']["price_type-$n"] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $options,
                        //'#value' => $item,
                        '#default_value' => $form_state->getValue('price_type-' . $n) ,
                        '#prefix' => "<div class='cell'>",
                        '#suffix' => '</div>',
                        '#attributes' => array('class' => array('amount'),'style' => array('width:100px')),
                        '#ajax' => array(
                            'callback' => array($this, 'put_price'),
                            'wrapper' => 'v' . $n,
                        ),
                    );
                }

                $form['items']["unit" . $n] = array(
                    '#type' => 'textfield',
                    '#id' => 'quantity' . $n,
                    '#size' => 8,
                    '#maxlength' => 255,
                    '#default_value' => $d->quantity,
                    '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );

                $form['items']["value" . $n] = array(
                    '#type' => 'textfield',
                    '#id' => 'value' . $n,
                    '#size' => 8,
                    '#maxlength' => 20,
                    '#default_value' => $form_state->getValue('value' . $n) ? $form_state->getValue('value' . $n) : 0,
                    '#attributes' => array('placeholder' => t('price'), 'class' => array('amount')),
                    '#disabled' => $disabled,
                    '#prefix' => "<div class='cell' id='v$n'>",
                    '#suffix' => '</div>',
                );

                $form['items']["delete" . $n] = array(
                    '#type' => 'checkbox',
                    '#attributes' => array('title' => t('delete'), 'class' => array()),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );


                $total = number_format($d->value * $d->quantity, 2);
                $grandtotal += $d->value * $d->quantity;
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

            $form['items']["itemid$i"] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => $form_state->getValue("itemid$i") ? $form_state->getValue("itemid$i") : NULL,
                '#attributes' => array('placeholder' => t('item code, barcode, description')),
                '#prefix' => "<div class='container-inline'>",
                '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>',
            );


            if ($this->moduleHandler->moduleExists('ek_products')) {

                if (($form_state->getValue("price_type-" . $i) != '0' && $form_state->getValue("price_type-" . $i) != NULL)
                ) {
                    $thisitemid = explode(' ', $form_state->getValue("itemid" . $i));
                    $thisitemid = $thisitemid[1];


                    $item = ItemData::item_sell_price($thisitemid, $form_state->getValue("price_type-" . $i));
                    $form_state->setValue("value" . $i, $item);
                    $form_state->set("price_type-" . $i, $form_state->getValue("price_type-" . $i));

                    $disabled = TRUE;
                } else {
                    $disabled = FALSE;
                }

                $form['items']["price_type-" . $i] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $options,
                    '#default_value' => $form_state->getValue("price_type-" . $i) ? $form_state->getValue("price_type-" . $i) : 0,
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                    '#attributes' => array('class' => array('amount'),'style' => array('width:100px')),
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
                '#default_value' => $form_state->getValue("unit" . $i) ? $form_state->getValue("unit" . $i) : NULL,
                '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );


            $form['items']["value" . $i] = array(
                '#type' => 'textfield',
                '#id' => 'value' . $i,
                '#size' => 8,
                '#maxlength' => 20,
                '#default_value' => $form_state->getValue("value" . $i) ? $form_state->getValue("value" . $i) : 0,
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
        //
        if (($form_state->get('num_items') > 0) || isset($detail)) {

            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    //'#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                );
            }

            $form['items']['foot'] = array(
                '#type' => 'item',
                '#markup' => '<hr>',
                '#prefix' => "<div class='table' id='purchase_form_footer'>",
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
                '#suffix' => '</div></div></div>',
            );




            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
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
        //input used to update values set by user
        $input = $form_state->getUserInput();

        for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

            if ($form_state->getValue("itemid$n") == '') {
                $form_state->setErrorByName("itemid$n", $this->t('Item @n is empty', array('@n' => $n)));
            }

            if ($form_state->getValue("unit$n") == '' || !is_numeric($form_state->getValue("unit$n"))) {
                $form_state->setErrorByName("unit$n", $this->t('there is no quantity for item @n', array('@n' => $n)));
            }
            if ($form_state->getValue("value$n") == '' || !is_numeric($form_state->getValue("value$n"))) {
                $form_state->setErrorByName("value$n", $this->t('there is no value for item @n', array('@n' => $n)));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('new_delivery') == 1) {
            //create new serial No
            $iid = Database::getConnection('external_db', 'external_db')->query("SELECT count(id) from {ek_logi_delivery}")->fetchField();
            $iid++;
            $short = Database::getConnection('external_db', 'external_db')->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))->fetchField();
            $serial = ucwords(str_replace('-', '', $short)) . "-DO-" . $date . "-" . ucwords(str_replace('-', '', $sup)) . "-" . $iid;
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')->delete('ek_logi_delivery_details')->condition('serial', $serial)->execute();
            $iid = Database::getConnection('external_db', 'external_db')->query('SELECT id from {ek_logi_delivery} where serial=:s', array(':s' => $serial))->fetchField();
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
                } else {
                    //use input from user
                    $code = $form_state->getValue("itemid$n");
                }

                $line = (round($form_state->getValue("unit$n") * $form_state->getValue("value$n"), 2));
                $sum = $sum + $line;


                $fields = array('serial' => $serial,
                    'itemcode' => $code,
                    'quantity' => $form_state->getValue("unit$n"),
                    'value' => $form_state->getValue("value$n"),
                    'amount' => $line,
                    'currency' => '',
                    'date' => $form_state->getValue('date'),
                );

                $insert = Database::getConnection('external_db', 'external_db')->insert('ek_logi_delivery_details')
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


        if (isset($insert) || isset($update))
            drupal_set_message(t('The delivery is recorded. Ref. @r', array('@r' => $serial)), 'status');
            Cache::invalidateTags(['logistics_delivery_block']);
            $form_state->setRedirect('ek_logistics_list_delivery');
    }

}
