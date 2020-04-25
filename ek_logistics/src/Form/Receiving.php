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
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

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
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $clone = null) {
        if (isset($id) && !$id == null) {

            //edit existing DO

            $query = "SELECT * from {ek_logi_receiving} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchObject();
            $query = "SELECT * from {ek_logi_receiving_details} where serial=:id";
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data->serial));

            $doc_type = $data->type;
            $route = ($doc_type == 'RR') ? 'ek_logistics_list_receiving' : 'ek_logistics_list_returning';

            if ($clone != 'clone') {
                $form['edit_receiving'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('Receiving order ref. @p', array('@p' => $data->serial)),
                );

                $form['serial'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->serial,
                );
            } else {
                $form['clone_doc'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('Template receiving order based on ref. @p . A new order will be generated.', array('@p' => $data->serial)),
                );

                $data->date = date('Y-m-d');

                $form['new_receiving'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                );
            }

            $n = 0;
            $grandtotal = 0;
            if (!$form_state->get('num_items')) {
                $form_state->set('num_items', 0);
            }

            if (!$form_state->getValue('head')) {
                $form_state->setValue('head', $data->head);
            }
        } else {
            //new
            $form['new_receiving'] = array(
                '#type' => 'hidden',
                '#value' => 1,
            );

            $grandtotal = 0;
            $n = 0;
            $detail = null;
            $data = null;
        }
        if (!isset($doc_type)) {
            if (strpos(\Drupal::request()->getRequestUri(), 'receiving')) {
                $doc_type = 'RR';
                $route = 'ek_logistics_list_receiving';
            } else {
                $doc_type = 'RT';
                $route = 'ek_logistics_list_returning';
            }
        }
        $form['type'] = array(
            '#type' => 'hidden',
            '#value' => $doc_type,
        );
        $url = Url::fromRoute($route)->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => $url)),
        );
        $form['options'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => ($id != null || $form_state->get('num_items') > 0) ? false : true,
        );

        $company = AccessCheck::CompanyListByUid();
        $form['options']['head'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => true,
            '#default_value' => isset($data->head) ? $data->head : null,
            '#title' => $this->t('Header'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );


        $form['options']['allocation'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => true,
            '#default_value' => isset($data->allocation) ? $data->allocation : null,
            '#title' => $this->t('Allocated'),
            '#description' => $this->t('select an entity for which the receiving is done'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            if (strpos(\Drupal::request()->getRequestUri(), 'receiving')) {
                $client = \Drupal\ek_address_book\AddressBookData::addresslist(2);
                $parent = $this->t('supplier');
            } else {
                $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);
                $parent = $this->t('client');
            }

            if (!empty($client)) {
                $form['options']['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => true,
                    '#default_value' => isset($data->supplier) ? $data->supplier : null,
                    '#title' => $parent,
                    '#attributes' => array('style' => array('width:300px;white-space:nowrap')),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $new = "<a title='" . $this->t('new') . "' href='" . $link . "'>" . $parent . "</a>";
                $form['options']['supplier'] = array(
                    '#markup' => $this->t("You do not have any <a title='create' href='@cl'>@p</a> in your record.", ['@cl' => $link, '@p' => $parent]),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => '</div>',
                );
            }
        } else {
            $form['options']['supplier'] = array(
                '#markup' => $this->t('You do not have any @p list.', array('@p' => $parent)),
            );
        }




        $form['options']['date'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#required' => true,
            '#default_value' => isset($data->ddate) ? $data->ddate : date('Y-m-d'),
            '#title' => $this->t('receiving date'),
        );


        $form['options']['title'] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#default_value' => isset($data->title) ? $data->title : null,
            '#attributes' => array('placeholder' => $this->t('comment')),
        );



        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $form['options']['pcode'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => \Drupal\ek_projects\ProjectData::listprojects(0),
                '#required' => true,
                '#default_value' => isset($data->pcode) ? $data->pcode : null,
                '#title' => $this->t('Project'),
            );
        } // project

        $form['options']['do'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => isset($data->do) ? $data->do : null,
            '#title' => $this->t('DO ref.'),
            '#prefix' => "<div class='container-inline'>",
        );

        $form['items'] = array(
            '#type' => 'details',
            '#title' => $this->t('Items'),
            '#open' => true,
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

        $header = array(
            'description' => array(
                'data' => $this->t('Items'),
                'id' => ['tour-item1'],
            ),
            'quantity' => array(
                'data' => $this->t('Quantity'),
                'id' => ['tour-item2'],
            ),
            'delete' => array(
                'data' => $this->t('Delete'),
                'id' => ['tour-item3'],
            ),
        );

        $form['items']['itemTable'] = array(
            '#tree' => true,
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

            while ($d = $detail->fetchObject()) {
                $n++;
                $z++;
                $link = null;
                $grandtotal += $d->quantity;
                $rowClass = ($rows[$n]['delete'] == 1) ? 'delete' : 'current';
                $trClass = 'tr' . $n;
                if ($this->moduleHandler->moduleExists('ek_products') &&
                        $name = \Drupal\ek_products\ItemData::item_bycode($d->itemcode)) {
                    //item exist in database
                    $link = \Drupal\ek_products\ItemData::geturl_bycode($d->itemcode, true);
                } else {
                    $name = $d->itemcode;
                    $link = '';
                }


                $form['description'] = array(
                    '#id' => 'description-' . $n,
                    '#type' => 'textfield',
                    '#size' => 60,
                    '#maxlength' => 255,
                    '#attributes' => array('placeholder' => $this->t('item')),
                    '#default_value' => $name,
                    '#field_prefix' => "<span class='badge'>" . $n . "</span>",
                    '#field_suffix' => isset($link) ? "<span class='badge'>" . $link . "</span>" : '',
                    '#autocomplete_route_name' => $this->moduleHandler->moduleExists('ek_products') ? 'ek.look_up_item_ajax' : '',
                );

                $form['quantity'] = array(
                    '#id' => 'quantity' . $n,
                    '#type' => 'textfield',
                    '#size' => 12,
                    '#maxlength' => 40,
                    '#attributes' => array('placeholder' => $this->t('units'), 'class' => array('amount')),
                    '#default_value' => $d->quantity,
                    '#required' => true,
                );

                $form['delete'] = array(
                    '#id' => 'del' . $n,
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#attributes' => array(
                        'title' => $this->t('delete on save'),
                        'onclick' => "jQuery('#" . $n . "').toggleClass('delete');",
                        'class' => array('amount')
                    ),
                );

                //built edit rows for table
                $form['items']['itemTable'][$n] = array(
                    'description' => &$form['description'],
                    'quantity' => &$form['quantity'],
                    'delete' => &$form['delete'],
                );

                $form['items']['itemTable']['#rows'][$n] = array(
                    'data' => array(
                        array('data' => &$form['description']),
                        array('data' => &$form['quantity']),
                        array('data' => &$form['delete']),
                    ),
                    'id' => array($n),
                    'class' => $rowClass,
                );
                unset($form['description']);
                unset($form['quantity']);
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
            $z++;
            $form['description'] = array(
                '#id' => 'description-' . $n,
                '#type' => 'textfield',
                '#size' => 60,
                '#maxlength' => 255,
                '#attributes' => array('placeholder' => $this->t('item')),
                '#default_value' => '',
                '#field_prefix' => "<span class='badge'>" . $z . "</span>",
                '#autocomplete_route_name' => $this->moduleHandler->moduleExists('ek_products') ? 'ek.look_up_item_ajax' : '',
            );
            $form['quantity'] = array(
                '#id' => 'quantity' . $n,
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 30,
                '#attributes' => array('placeholder' => $this->t('units'), 'class' => array('amount')),
                '#default_value' => '',
                '#required' => true,
            );
            $form['delete'] = array(
                '#item' => '',
            );
            //built edit rows for table
            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'quantity' => &$form['quantity'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['quantity']);
            unset($form['delete']);
            $n++;
        }

        $form['items']['count'] = array(
            '#type' => 'hidden',
            '#value' => $n - 1,
            '#attributes' => array('id' => 'itemsCount'),
        );

//
        // FOOTER
//

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


            $n = $n + 2;
            $form['description'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Total'),
            );

            $form['quantity'] = array(
                '#id' => 'itemsTotal',
                '#type' => 'textfield',
                '#size' => 12,
                '#maxlength' => 40,
                '#default_value' => isset($grandtotal) ? number_format($grandtotal, 2) : 0,
                '#attributes' => array('placeholder' => $this->t('total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
            );
            $form['value'] = array('#type' => 'hidden', '#value' => 'footer', '#attributes' => ['id' => ['value' . $n]],);

            $form['delete'] = array(
                '#item' => "",
            );
            //built total rows for table
            $form['items']['itemTable'][$n] = array(
                'description' => &$form['description'],
                'quantity' => &$form['quantity'],
                'value' => &$form['value'],
                'delete' => &$form['delete'],
            );

            $form['items']['itemTable']['#rows'][$n] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['quantity']),
                    array('data' => &$form['delete']),
                ),
                'id' => array($n),
            );
            unset($form['description']);
            unset($form['quantity']);
            unset($form['value']);
            unset($form['delete']);


            $form['actions'] = array(
                '#type' => 'actions',
            );

            $redirect = array(0 => $this->t('preview'), 1 => $this->t('list'), 2 => $this->t('print'));

            $form['actions']['redirect'] = array(
                '#type' => 'radios',
                '#title' => $this->t('Next'),
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
                }
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
        $sum = 0;
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

                        $sum = $sum + $row["quantity"];

                        $fields = array('serial' => $serial,
                            'itemcode' => $code,
                            'quantity' => $row["quantity"],
                            'date' => $form_state->getValue('date'),
                        );

                        $insert = Database::getConnection('external_db', 'external_db')
                                ->insert('ek_logi_receiving_details')
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
            $route = 'ek_logistics_list_receiving';
        }
        if ($form_state->getValue('type') == 'RT') {
            $route = 'ek_logistics_list_returning';
        }

        switch ($form_state->getValue('redirect')) {
            case 0:
                $form_state->setRedirect('ek_logistics.receiving.print_html', ['id' => $reference]);
                break;
            case 1:
                $form_state->setRedirect($route);
                break;
            case 2:
                $form_state->setRedirect('ek_logistics_receiving_print_share', ['id' => $reference]);
                break;
        }
    }

}
