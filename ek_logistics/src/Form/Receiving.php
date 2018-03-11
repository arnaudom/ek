<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\receiving.
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

/**
 * Provides a form to create and edit receiving/return report.
 */
class receiving extends FormBase {

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
        return 'ek_logistics_receving_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $clone = NULL) {

        if (isset($id) && !$id == NULL) {

            //edit existing DO

            $query = "SELECT * from {ek_logi_receiving} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
            $query = "SELECT * from {ek_logi_receiving_details} where serial=:id";
            $detail = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data->serial));


            If ($clone != 'clone') {
                $form['edit_receiving'] = array(
                    '#type' => 'item',
                    '#markup' => t('Receiving order ref. @p', array('@p' => $data->serial)),
                );

                $form['serial'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                );
            } else {
                $form['clone_invoice'] = array(
                    '#type' => 'item',
                    '#markup' => t('Template receiving order based on ref. @p . A new order will be generated.', array('@p' => $data->serial)),
                );

                $data->date = date('Y-m-d');

                $form['new_receiving'] = array(
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
                $baseCurrency = $this->settings->get('baseCurrency');
            }
        } else {
            //new
            $form['new_receiving'] = array(
                '#type' => 'hidden',
                '#value' => 1,
            );

            $grandtotal = 0;
            $n = 0;
            $detail = NULL;
            $data = NULL;
        }

        if (strpos(\Drupal::request()->getRequestUri(), 'receiving')) {
            $type = 'RR';
        } else {
            $type = 'RT';
        }
        $form['type'] = array(
            '#type' => 'hidden',
            '#value' => $type,
        );

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
            '#description' => t('select an entity for which the receiving is done'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {

            if (strpos(\Drupal::request()->getRequestUri(), 'receiving')) {
                $client = \Drupal\ek_address_book\AddressBookData::addresslist(2);
                $parent = t('supplier');
            } else {
                $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);
                $parent = t('client');
            }

            if (!empty($client)) {
                $form['options']['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => TRUE,
                    '#default_value' => isset($data->supplier) ? $data->supplier : NULL,
                    '#title' => $parent,
                    '#attributes' => array('style' => array('width:300px;white-space:nowrap')),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $new = "<a title='" . t('new') . "' href='" . $link . "'>" . $parent . "</a>";
                $form['options']['supplier'] = array(
                    '#markup' => t("You do not have any <a title='create' href='@cl'>@p</a> in your record.", ['@cl' => $link, '@p' => $parent]),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => '</div>',
                );
            }
        } else {

            $form['options']['supplier'] = array(
                '#markup' => t('You do not have any @p list.', array('@p' => $parent)),
                
            );
        }




        $form['options']['date'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => isset($data->ddate) ? $data->ddate : date('Y-m-d'),
            '#title' => t('receiving date'),
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

        $form['options']['do'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => isset($data->do) ? $data->do : NULL,
            '#title' => t('DO ref.'),
            '#prefix' => "<div class='container-inline'>",
        );

        /*
          $form['options']['logistic_cost'] = array(
          '#type' => 'textfield',
          '#size' => 6,
          '#maxlength' => 25,
          '#default_value' => isset($data->logistic_cost) ? $data->logistic_cost : NULL,
          '#title' => t('Logistic cost'),
          '#attributes' => array('placeholder'=>t('value')),
          '#suffix' => '</div>',
          );
         */

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



        $headerline = "<div class='table'  id='purchase_form_items'>
                  <div class='row'>
                      <div class='cell'>" . t("Items") . "</div>
                      <div class='cell'>" . t("Quantities") . "</div>
                      <div class='cell'>" . t("delete") . "</div>
                   ";



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

                if ($this->moduleHandler->moduleExists('ek_products') && 
                        $name = ItemData::item_bycode($d->itemcode) ) {
                    //item exist in database
                    $url = ItemData::geturl_bycode($d->itemcode, TRUE);
                } else {
                    $name = $d->itemcode;
                    $url = '';
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



                $form['items']["unit$n"] = array(
                    '#type' => 'textfield',
                    '#id' => 'quantity' . $n,
                    '#size' => 8,
                    '#maxlength' => 255,
                    '#default_value' => $d->quantity,
                    '#attributes' => array('placeholder' => t('units'), 'class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div>',
                );


                $form['items']["delete$n"] = array(
                    '#type' => 'checkbox',
                    '#attributes' => array('title' => t('delete'), 'class' => array()),
                    '#prefix' => "<div class='cell'>",
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



            $form['items']["delete$i"] = array(
                '#type' => 'item',
                '#attributes' => '',
                '#prefix' => "<div class='cell'>",
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

        //input used to update values set by user
        $input = $form_state->getUserInput();

        for ($n = 1; $n <= $form_state->getValue('count'); $n++) {

            if ($form_state->getValue("itemid$n") == '') {
                $form_state->setErrorByName("itemid$n", $this->t('Item @n is empty', array('@n' => $n)));
            }

            if ($form_state->getValue("unit$n") == '' || !is_numeric($form_state->getValue("unit$n"))) {
                $form_state->setErrorByName("unit$n", $this->t('there is no quantity for item @n', array('@n' => $n)));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('new_receiving') == 1) {
            //create new serial No
            $iid = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT count(id) from {ek_logi_receiving}")
                    ->fetchField();
            $iid++;
            $short = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                    ->fetchField();
            $date = substr($form_state->getValue('date'), 2, 5);
            $sup = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('supplier')))
                    ->fetchField();



            $serial = ucwords(str_replace('-', '', $short))
                    . "-" . $form_state->getValue('type')
                    . "-" . $date . "-"
                    . ucwords(str_replace('-', '', $sup))
                    . "-" . $iid;
        } else {
            //edit
            $serial = $form_state->getValue('serial');
            $delete = Database::getConnection('external_db', 'external_db')
                            ->delete('ek_logi_receiving_details')
                            ->condition('serial', $serial)->execute();
            $iid = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT id from {ek_logi_receiving} where serial=:s', array(':s' => $serial))
                    ->fetchField();
        }


// Items  

        $line = 0;
        $total = 0;
        $sum = 0;

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
                    'date' => $form_state->getValue('date'),
                );

                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_logi_receiving_details')
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
        if ($form_state->getValue('logistic_cost') == '' || !is_numeric($form_state->getValue('logistic_cost'))) {
            $logistic_cost = 0;
        } else {
            $logistic_cost = $form_state->getValue('logistic_cost');
        }
        $fields1 = array(
            'serial' => $serial,
            'head' => $form_state->getValue('head'),
            'allocation' => $form_state->getValue('allocation'),
            'date' => date('Y-m-d'),
            'ddate' => $form_state->getValue('date'),
            'title' => Xss::filter($form_state->getValue('title')),
            'do' => Xss::filter($form_state->getValue('do')),
            'pcode' => $pcode,
            'supplier' => $form_state->getValue('supplier'),
            'status' => 0,
            'amount' => 0,
            'type' => $form_state->getValue('type'),
            'logistic_cost' => $logistic_cost,
            'post' => 0
        );

        if ($form_state->getValue('new_receiving') && $form_state->getValue('new_receiving') == 1) {
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_logi_receiving')
                    ->fields($fields1)
                    ->execute();
            $reference = $insert;
        } else {
            $update = Database::getConnection('external_db', 'external_db')->update('ek_logi_receiving')
                    ->fields($fields1)
                    ->condition('serial', $serial)
                    ->execute();
            $reference = $iid;
        }


        if (isset($insert) || isset($update)) {
            \Drupal::messenger()->addStatus(t('The receiving is recorded. Ref. @r', ['@r' => $serial]));
            Cache::invalidateTags(['logistics_receiving_block']);
        }

        if ($form_state->getValue('type') == 'RR') {
            $form_state->setRedirect('ek_logistics_list_receiving');
        }
        if ($form_state->getValue('type') == 'RT') {
            $form_state->setRedirect('ek_logistics_list_returning');
        }
    }

}
