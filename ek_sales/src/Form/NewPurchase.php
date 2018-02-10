<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\NewPurchase.
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
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_products\ItemData;

/**
 * Provides a form to create and edit purchases.
 */
class NewPurchase extends FormBase {

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
        if($this->moduleHandler->moduleExists('ek_finance')) {
            $this->settings = new FinanceSettings();
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
        return 'ek_sales_new_purchase';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $clone = NULL) {
        
        $url = Url::fromRoute('ek_sales.purchases.list', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

        );

        if (isset($id) && !$id == NULL) {
            //edit
            $data = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * from {ek_sales_purchase} where id=:id", array(':id' => $id))->fetchObject();
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT * from {ek_sales_purchase_details} where serial=:s", array(':s' => $data->serial));

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
                    '#markup' => t('Template purchase based on ref. @p . A new purchase will be generated.', array('@p' => $data->serial)),
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
                $chart = $this->settings->get('chart');
                $AidOptions = AidList::listaid($data->head, array($chart['assets'],$chart['cos'],$chart['expenses'],$chart['other_expenses']), 1 );
                
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
            $AidOptions = array();
            
        }



        if ($this->moduleHandler->moduleExists('ek_finance')) {

            $CurrencyOptions = CurrencyData::listcurrency(1);
            $chart = $this->settings->get('chart');
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
            '#open' => (isset($id)) ? FALSE : TRUE,
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


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $supplier = \Drupal\ek_address_book\AddressBookData::addresslist(2);

            if (!empty($supplier)) {
                $form['options']['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $supplier,
                    '#required' => TRUE,
                    '#default_value' => isset($data->client) ? $data->client : NULL,
                    '#title' => t('supplier'),
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div></div></div>',
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $new = "<a title='" . t('new') . "' href='" . $link . "'>" . t('client') . "</a>";
                $form['options']['supplier'] = array(
                    '#markup' => t("You do not have any @n in your record.", ['@n' => $new]),
                    '#default_value' => 0,
                    '#prefix' => "<div class='row'><div class='cell'>",
                    '#suffix' => '</div></div></div>',
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
                '#options' => ProjectData::listprojects(0),
                '#required' => TRUE,
                '#default_value' => isset($data->pcode) ? $data->pcode : NULL,
                '#title' => t('Project'),
                '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
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
            $headerline = "<div class='table'  id='purchase_form_items'>
                  <div class='row'>
                      <div class='cell'>" . t("Description") . "</div>
                      <div class='cell'>" . t("Account") . "</div>
                      <div class='cell'>" . t("Units") . "</div>
                      <div class='cell'>" . t("Value") . "</div>
                      <div class='cell'>" . t("delete") . "</div>
                      <div class='cell'>" . t("tax") . "</div>
                      <div class='cell right'>" . t("Line total") . "</div>
                   ";
        } else {
            $headerline = "<div class='table' id='purchase_form_items'><div class='row'><div class='cell'>" . t("Description") . "</div><div class='cell'>" . t("Units") . "</div><div class='cell'>" . t("Value") . "</div><div class='cell'>" . t("delete") . "</div><div class='cell'>" . t("tax") . "</div><div class='cell'>" . t("Total") . "</div>";
        }

        $form['items']["headerline"] = array(
            '#type' => 'item',
            '#markup' => $headerline,
        );


        if (isset($detail)) {
        //edition mode
        //list current items
        $taxable = 0;
        $grandtotal = 0;
        $cl = ($form_state->getValue("delete".$n) == 1) ? 'delete' : 'current';

            while ($d = $detail->fetchObject()) {

                $n++;
                $c = $form_state->get('current_items') + 1;
                $form_state->set('current_items', $c);
                $form_state->setRebuild();

                if (!$d->itemdetail == "" && $this->moduleHandler->moduleExists('ek_products')) {
                    $name = ItemData::item_byid($d->itemdetail);
                } else {
                    $name = $d->item;
                }



                $form['items']["description$n"] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 255,
                    '#default_value' => $name,
                    '#attributes' => array('placeholder' => t('item')),
                    '#prefix' => "<div class='row $cl' id='row$n'><div class='cell'>",
                    '#suffix' => '</div>',
                    '#autocomplete_route_name' => 'ek.look_up_item_ajax',
                );


                if ($this->moduleHandler->moduleExists('ek_finance')) {

                    // build purchase account select list from service
                    // TODO

                    $form['items']["account$n"] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $AidOptions,
                        '#required' => TRUE,
                        '#default_value' => isset($d->aid) ? $d->aid : NULL,
                        '#attributes' => array('style' => array('width:100px;white-space:nowrap')),
                        '#prefix' => "<div class='cell'>",
                        '#suffix' => '</div>',
                    );
                } // finance    

                $form['items']["quantity$n"] = array(
                    '#type' => 'textfield',
                    '#id' => 'quantity' . $n,
                    '#size' => 8,
                    '#maxlength' => 255,
                    '#default_value' => $d->quantity,
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
                    '#id' => 'del' . $n ,
                    '#attributes' => array('title'=>t('delete on save'), 'class' => array('amount')),
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

                $total = number_format($d->value * $d->quantity, 2);
                $grandtotal += ($d->value * $d->quantity);
                if ($d->opt == 1) {
                    $taxable += ($d->value * $d->quantity);
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
                '#default_value' => $form_state->getValue("description$i") ? $form_state->getValue("description$i") : NULL,
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
                    '#options' => $form_state->get('AidOptions'),
                    '#required' => TRUE,
                    '#default_value' => isset($d->aid) ? $d->aid : NULL,
                    '#attributes' => array('style' => array('width:100px;white-space:nowrap')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );
            } // finance    

            $form['items']["quantity$i"] = array(
                '#type' => 'textfield',
                '#id' => 'quantity' . $i,
                '#size' => 8,
                '#maxlength' => 255,
                '#default_value' => $form_state->getValue("quantity$i") ? $form_state->getValue("quantity$i") : NULL,
                '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
            );

            $form['items']["value$i"] = array(
                '#type' => 'textfield',
                '#id' => 'value' . $i,
                '#size' => 8,
                '#maxlength' => 255,
                '#default_value' => $form_state->getValue("value$i") ? $form_state->getValue("value$i") : NULL,
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

        if (($form_state->get('num_items') && $form_state->get('num_items') > 0) || isset($detail)) {

            if ($form_state->get('num_items') > 0) {
                $form['items']['remove'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('remove last item'),
                    //'#limit_validation_errors' => array(),
                    '#submit' => array(array($this, 'removeForm')),
                    '#prefix' => "<div id='remove' class='right'>",
                    '#suffix' => '</div>',
                    '#attributes' => array('class' => array('button--remove')),
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
                '#title' => isset($grandtotal) ? t('Total purchase') : NULL,
                '#attributes' => array('placeholder' => t('total purchase'), 'readonly' => 'readonly', 'class' => array('amount')),
                '#prefix' => "<div class='row'><div class='cell right'>",
                '#suffix' => '</div></div></div>',
            );
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

        $form['file']['upload_doc'] = array(
            '#type' => 'file',
            '#title' => isset($data->uri) ? t('Attach a new file') : t('Attach a file'),
            '#description' => isset($data->uri) ? t('Current file will be deleted') : NULL,
        );


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

        $form['#attached']['library'][] = 'ek_sales/ek_sales.purchase';

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
            $form['options']['alert']['#prefix'] = "<div id='alert' class='messages messages--warning'>";
            $form['options']['alert']['#markup'] = t('Warning, you do not have liability account set for this company and currency. You cannot proceed. Please contact administrator');
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
            $chart = $this->settings->get('chart'); 
            $form_state->set('AidOptions', AidList::listaid($form_state->getValue('head'),array($chart['assets'],$chart['cos'],$chart['expenses'],$chart['other_expenses']), 1));
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
                $form_state->setErrorByName('currency', t('Warning, you do not have liability account set for this company and currency. You cannot proceed. Please contact administrator'));
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
                // @TODO
            } // finance            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $options = array('1' => t('Purchase'), '4' => t('Debit note'));

        if ($form_state->getValue('new_purchase') == 1) {
            //create new serial No
            switch ($form_state->getValue('title')) {
            case '4':
                $type = "-DN-";
                break;
            default:
                $type = "-PO-";
                break;
            }
            $poid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_sales_purchase}")
                    ->fetchField();
            $poid++;
            $short = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                    ->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('supplier')))
                    ->fetchField();
            $serial = ucwords(str_replace('-', '', $short)) . $type . $date . "-" . ucwords(str_replace('-', '', $sup)) . "-" . $poid;
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_sales_purchase_details')
                    ->condition('serial', $form_state->getValue('serial'))
                    ->execute();
            $poid = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT id from {ek_sales_purchase} where serial=:s', array(':s' => $serial))
                    ->fetchField();
        }


// Items  

        $line = 0;
        $total = 0;
        $taxable = 0;
        $sum = 0;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $journal = new Journal();
        }
        for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

            if (!$form_state->getValue("delete$n") == 1) {
                if ($this->moduleHandler->moduleExists('ek_products')) {
                    //verify if item is in the DB if not just record input

                    $item = explode(" ", $form_state->getValue("description$n"));
                    $id = trim($item[0]);
                    if(isset($item[1])) {
                        $code = trim($item[1]);
                    }
                    $description = ItemData::item_description_byid($id, 1);

                    if ($description) {
                        $item = $description;
                        $itemdetail = $id;
                    } else {
                        $item = $form_state->getValue("description$n");
                        $itemdetail = '';
                    }
                } else {
                    //use input from user
                    $item = $form_state->getValue("description$n");
                    $itemdetail = '';
                }

                $line = (round($form_state->getValue("quantity$n") * $form_state->getValue("value$n"), 2) );
                $sum = $sum + $line;
                if ($form_state->getValue("tax$n") == 1) {
                    $taxable = $taxable + $line;
                }

                if (!$form_state->getValue("account$n")) {
                    $account = 0;
                } else {
                    $account = $form_state->getValue("account$n");
                }

                $fields = array('serial' => $serial,
                    'item' => $item, // description used in displays
                    'itemdetail' => $itemdetail, //add detail / id if item is in DB
                    'quantity' => $form_state->getValue("quantity$n"),
                    'value' => $form_state->getValue("value$n"),
                    'total' => $line,
                    'opt' => $form_state->getValue("tax$n"),
                    'aid' => $account
                );

                $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_purchase_details')
                        ->fields($fields)
                        ->execute();
            }//if not delete
        }//for
//main

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
            $baseCurrency = $this->settings->get('baseCurrency');
            $sumwithtax = $sum + (round($taxable * $form_state->getValue('taxvalue') / 100, 2));
            if ($baseCurrency <> $form_state->getValue('currency')) {
                $currencyRate = CurrencyData::rate($form_state->getValue('currency'));
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
        
        $extensions = 'png jpg jpeg doc docx xls xlsx odt ods odp pdf rar rtf tiff zip';
        $validators = array('file_validate_extensions' => array($extensions));
        $dir = "private://sales/purchase/" . $reference . "";
        file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        $file = file_save_upload("upload_doc", $validators, $dir , 0 , FILE_EXISTS_RENAME);

        if ($file) {

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
                    drupal_set_message(t('Previous attachment has been deleted.'), 'warning');
                }
            }
           
                $file->setPermanent();
                $file->save();
                $uri = $file->getFileUri();
                $filename = $file->getFileName();

                $fields = array(
                    'uri' => $uri,
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


            for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

                if (!$form_state->getValue("delete$n") == 1) {
                    if ($form_state->getValue('taxvalue') > 0 && $form_state->getValue("tax$n") == 1) {
                        $tax = round($form_state->getValue("value$n") * $form_state->getValue('taxvalue') / 100, 2);
                    } else {
                        $tax = 0;
                    }
                    $line = (round($form_state->getValue("quantity$n") * $form_state->getValue("value$n"), 2));
                    $journal->record(
                            array(
                                'source' => "purchase",
                                'coid' => $form_state->getValue('head'),
                                'aid' => $form_state->getValue("account$n"),
                                'reference' => $reference,
                                'date' => $form_state->getValue('date'),
                                'value' => $line,
                                'currency' => $form_state->getValue('currency'),
                                'tax' => $tax,
                            )
                    );
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
        }

        Cache::invalidateTags(['project_page_view']);
        if (isset($insert) || isset($update)) {
            drupal_set_message(t('The purchase is recorded. Ref @r', array('@r' => $serial)), 'status');
            
  
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if invoice is linked to a project
                if ($pcode && $pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $pcode])
                            ->fetchField();
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => 'purchase_edit',
                                'value' => $serial,
                                'pcode' => $pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
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
