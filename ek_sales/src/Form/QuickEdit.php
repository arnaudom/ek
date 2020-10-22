<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\QuickEdit.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form for simple edit sales documents.
 */
class QuickEdit extends FormBase {

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
        return 'ek_sales_quick_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $doc = null) {
        $query = "SELECT * from {ek_sales_" . $doc . "} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();
        $query = "SELECT * FROM {ek_sales_" . $doc . "_details} where serial=:id ORDER BY id";
        $detail = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $data->serial));

        $edit = true;
        //filter status for no editable document
        if ($doc == 'quotation' && $data->status == 2) {
            $edit = false;
        }
        if (($doc == 'purchase' || $doc == 'invoice') && $data->status > 0) {
            $edit = false;
        }

        if (!$form_state->getValue('head')) {
            $form_state->setValue('head', $data->head);
        }
        $form['serial'] = [
            '#type' => 'hidden',
            '#value' => $data->serial,
        ];

        $form['doc'] = [
            '#type' => 'hidden',
            '#value' => $doc,
        ];

        $form['id'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];

        $form['edit'] = [
            '#type' => 'hidden',
            '#value' => $edit,
        ];
        $form['options']['ref'] = [
            '#markup' => "<h2>" . $data->serial . "</h2>",
        ];

        if ($edit) {

            $company = AccessCheck::CompanyListByUid();

            if ($doc == 'invoice') {
                $form['options']['head'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $company,
                    '#required' => true,
                    '#default_value' => isset($data->head) ? $data->head : null,
                    '#title' => $this->t('Header'),
                    '#prefix' => "",
                    '#suffix' => '',
                    '#ajax' => array(
                        'callback' => array($this, 'set_coid'),
                        'wrapper' => 'debit',
                    //will define the list of bank accounts by company below
                    ),
                ];
            } elseif ($doc == 'purchase' || $doc == 'quotation') {
                $form['options']['head'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $company,
                    '#required' => true,
                    '#default_value' => isset($data->head) ? $data->head : null,
                    '#title' => $this->t('Header'),
                ];
            }

            $form['options']['allocation'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $company,
                '#required' => true,
                '#default_value' => isset($data->allocation) ? $data->allocation : null,
                '#title' => $this->t('Allocated'),
                '#description' => $this->t('select an entity for which the @doc is done', ['@doc' => $doc]),
                '#prefix' => "",
                '#suffix' => '',
            ];


            if ($this->moduleHandler->moduleExists('ek_address_book')) {
                if ($doc == 'invoice' || $doc == 'quotation') {
                    $type = 1;
                    $title = $this->t('Client');
                } else {
                    $type = 2;
                    $title = $this->t('Supplier');
                }
                $client = \Drupal\ek_address_book\AddressBookData::addresslist($type);

                $form['options']['client'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => true,
                    '#default_value' => isset($data->client) ? $data->client : null,
                    '#title' => $title,
                    '#prefix' => "",
                    '#suffix' => '',
                    '#attributes' => ['style' => array('width:300px;white-space:nowrap')],
                ];
            } else {
                $form['options']['client'] = [
                    '#markup' => $this->t('You do not have any client list.'),
                    '#default_value' => 0,
                    '#prefix' => "",
                    '#suffix' => '',
                ];
            }

            $form['options']['date'] = [
                '#type' => 'date',
                '#size' => 12,
                '#required' => true,
                '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
                '#title' => $this->t('Date'),
                '#prefix' => "",
                '#suffix' => '',
            ];


            if ($this->moduleHandler->moduleExists('ek_projects')) {
                $form['options']['pcode'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => ProjectData::listprojects(0),
                    '#required' => true,
                    '#default_value' => isset($data->pcode) ? $data->pcode : null,
                    '#title' => $this->t('Project'),
                    '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                ];
            } // project

            if ($this->moduleHandler->moduleExists('ek_finance') && $doc == 'invoice') {
                $options['bank'] = \Drupal\ek_finance\BankData::listbankaccountsbyaid($form_state->getValue('head'));

                $form['options']['_currency'] = [
                    '#type' => 'item',
                    '#markup' => $this->t('Currency') . " : <strong>" . $data->currency . "</strong>",
                ];

                $form['options']['currency'] = [
                    '#type' => 'hidden',
                    '#value' => $data->currency,
                ];

                $form['options']['bank_account'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => isset($options['bank']) ? $options['bank'] : array(),
                    '#default_value' => isset($data->bank) ? $data->bank : $form_state->getValue('bank_account'),
                    '#required' => true,
                    '#title' => $this->t('Account payment'),
                    '#prefix' => "<div id='debit'>",
                    '#suffix' => '</div>',
                    '#description' => '',
                    '#attributes' => ['style' => array('width:280px;;white-space:nowrap')],
                ];
            } else {
                $form['options']['bank_account'] = [
                    '#type' => 'hidden',
                    '#value' => 0
                ];
                $form['options']['currency'] = [
                    '#type' => 'hidden',
                    '#value' => $data->currency,
                ];
            }

            if ($doc == 'purchase' || $doc == 'invoice') {
                $form['options']['terms'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => array(t('on receipt'), $this->t('due days')),
                    '#default_value' => isset($data->terms) ? $data->terms : null,
                    '#title' => $this->t('Terms'),
                    '#prefix' => "<div class='container-inline'>",
                    '#ajax' => array(
                        'callback' => array($this, 'check_day'),
                        'wrapper' => 'calday',
                        'event' => 'change',
                    ),
                ];

                $form['options']['due'] = [
                    '#type' => 'textfield',
                    '#size' => 5,
                    '#maxlength' => 3,
                    '#default_value' => isset($data->due) ? $data->due : null,
                    '#attributes' => array('placeholder' => $this->t('days')),
                    '#ajax' => array(
                        'callback' => array($this, 'check_day'),
                        'wrapper' => 'calday',
                        'event' => 'change',
                    ),
                ];

                $form['options']['day'] = [
                    '#type' => 'item',
                    '#markup' => '',
                    '#prefix' => "<div  id='calday'>",
                    '#suffix' => "</div></div>",
                ];
            } else {
                $form['options']['terms'] = [
                    '#type' => 'hidden',
                    '#value' => 0,
                ];
            }

            if ($doc == 'invoice') {
                $form['options']['po_no'] = [
                    '#type' => 'textfield',
                    '#maxlength' => 50,
                    '#size' => 25,
                    '#default_value' => isset($data->po_no) ? $data->po_no : null,
                    '#attributes' => ['placeholder' => $this->t('PO No.')],
                ];
            }
            $form['options']['comment'] = [
                '#type' => 'textarea',
                '#rows' => 1,
                '#default_value' => isset($data->comment) ? $data->comment : null,
                '#prefix' => "<div class='container-inline'>",
                '#suffix' => "</div>",
                '#attributes' => ['placeholder' => $this->t('comment')],
            ];

            $form['actions'] = [
                '#type' => 'actions',
            ];

            $form['actions']['record'] = [
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => array('class' => array('button--record')),
            ];
        } else {
            // document closed, only edit limited data

            if ($this->moduleHandler->moduleExists('ek_projects')) {
                $form['options']['pcode'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => ProjectData::listprojects(0),
                    '#required' => true,
                    '#default_value' => isset($data->pcode) ? $data->pcode : null,
                    '#title' => $this->t('Project'),
                    '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                ];

                $form['actions'] = [
                    '#type' => 'actions',
                ];

                $form['actions']['record'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('Record'),
                    '#attributes' => array('class' => array('button--record')),
                ];
            } else {
                $form['options']['alert'] = [
                    '#markup' => $this->t('There is no data to edit on this document'),
                    '#prefix' => "<div class='messages messages--warning'>",
                    '#suffix' => "</div>"
                ];
            }
        }

        $form['#attached']['library'][] = 'ek_sales/ek_sales.invoice';

        return $form;
    }

    /**
     * callback functions
     */
    public function set_coid(array &$form, FormStateInterface $form_state) {
        return $form['options']['bank_account'];
    }

    public function check_day(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('terms') == '1' && $form_state->getValue('due') != null) {
            $form['options']['day']["#markup"] = date('Y-m-d', strtotime(date("Y-m-d", strtotime($form_state->getValue('date'))) . "+" . $form_state->getValue('due') . ' ' . $this->t("days")));
        } else {
            $form['options']['day']["#markup"] = '';
        }
        return $form['options']['day'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('edit')) {
            if ($form_state->getValue('terms') == 1 && $form_state->getValue('due') == '') {
                $form_state->setErrorByName('due', $this->t('Terms days is empty'));
            }

            if ($form_state->getValue('terms') == 1 && !is_numeric($form_state->getValue('due'))) {
                $form_state->setErrorByName('due', $this->t('Terms days should be numeric'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $serial = $form_state->getValue('serial');
        $doc = $form_state->getValue('doc');
        $query = Database::getConnection('external_db', 'external_db')
                ->select("ek_sales_" . $doc, 's')
                ->fields('s')
                ->condition('serial', $serial)
                ->execute();
        $init_data = $query->fetchAssoc();

        if ($doc == 'quotation') {
            if ($form_state->getValue('pcode') == 'n/a') {
                $pcode = '';
            } else {
                $pcode = $form_state->getValue('pcode');
            }
            if ($form_state->getValue('edit') == false) {
                // document closed, limited edition
                $update = Database::getConnection('external_db', 'external_db')
                        ->update("ek_sales_" . $doc)
                        ->fields(['pcode' => $pcode])
                        ->condition('serial', $serial)
                        ->execute();
            } else {
                $fields = array(
                    'head' => $form_state->getValue('head'),
                    'allocation' => $form_state->getValue('allocation'),
                    'date' => date('Y-m-d', strtotime($form_state->getValue('date'))),
                    'pcode' => $pcode,
                    'comment' => Xss::filter($form_state->getValue('comment')),
                    'client' => $form_state->getValue('client'),
                    'status' => 0,
                );
                $update = Database::getConnection('external_db', 'external_db')
                        ->update("ek_sales_" . $doc)
                        ->fields($fields)
                        ->condition('serial', $serial)
                        ->execute();
            }
        } // Quot.

        if ($doc == 'invoice' || $doc == 'purchase') {

            if ($form_state->getValue('pcode') == '') {
                $pcode = 'n/a';
            } else {
                $pcode = $form_state->getValue('pcode');
            }

            if ($form_state->getValue('edit') == false) {
                // document closed, limited edition
                $update = Database::getConnection('external_db', 'external_db')
                        ->update("ek_sales_" . $doc)
                        ->fields(['pcode' => $pcode])
                        ->condition('serial', $serial)
                        ->execute();
            } else {
                if ($form_state->getValue('due') == '') {
                    $due = 0;
                } else {
                    $due = $form_state->getValue('due');
                }

                if ($form_state->getValue('taxvalue') == '') {
                    $taxvalue = 0;
                } else {
                    $taxvalue = $form_state->getValue('taxvalue');
                }

                $fields = array(
                    'head' => $form_state->getValue('head'),
                    'allocation' => $form_state->getValue('allocation'),
                    'date' => date('Y-m-d', strtotime($form_state->getValue('date'))),
                    'pcode' => $pcode,
                    'comment' => Xss::filter($form_state->getValue('comment')),
                    'client' => $form_state->getValue('client'),
                    'terms' => Xss::filter($form_state->getValue('terms')),
                    'due' => $due,
                );


                if ($doc == 'invoice') {
                    //add specific field for invoice
                    $fields['bank'] = $form_state->getValue('bank_account');
                    $fields['po_no'] = $form_state->getValue('po_no');
                }

                $update = Database::getConnection('external_db', 'external_db')
                        ->update("ek_sales_" . $doc)
                        ->fields($fields)
                        ->condition('serial', $serial)
                        ->execute();


                if ($this->moduleHandler->moduleExists('ek_finance') && $doc == 'invoice') {
                    //if coid changed, need to update the currency assets debit account in journal
                    $coSettings = new \Drupal\ek_admin\CompanySettings($form_state->getValue('head'));
                    $asset = $coSettings->get('asset_account', $form_state->getValue('currency'));
                    $update1 = Database::getConnection('external_db', 'external_db')
                            ->update("ek_journal")
                            ->fields(['aid' => $asset])
                            ->condition('source', 'invoice')
                            ->condition('type', 'debit')
                            ->condition('reference', $form_state->getValue('id'))
                            ->execute();
                    //Edit invoice header in journal
                    $update2 = Database::getConnection('external_db', 'external_db')
                            ->update("ek_journal")
                            ->fields(['coid' => $form_state->getValue('head')])
                            ->condition('source', 'invoice')
                            ->condition('reference', $form_state->getValue('id'))
                            ->execute();
                }

                if ($this->moduleHandler->moduleExists('ek_finance') && $doc == 'purchase') {
                    //if coid changed, need to update the currency liability account in journal
                    $coSettings = new \Drupal\ek_admin\CompanySettings($form_state->getValue('head'));
                    $liability = $coSettings->get('liability_account', $form_state->getValue('currency'));

                    $update1 = Database::getConnection('external_db', 'external_db')
                            ->update("ek_journal")
                            ->fields(['aid' => $liability])
                            ->condition('source', 'purchase')
                            ->condition('type', 'credit')
                            ->condition('reference', $form_state->getValue('id'))
                            ->execute();
                    //Edit purchase header in journal
                    $update2 = Database::getConnection('external_db', 'external_db')
                            ->update("ek_journal")
                            ->fields(['coid' => $form_state->getValue('head')])
                            ->condition('source', 'purchase')
                            ->condition('reference', $form_state->getValue('id'))
                            ->execute();
                }
            }
        } // Inv. Pur.

        if (isset($update)) {
            Cache::invalidateTags(['project_page_view']);
            \Drupal::messenger()->addStatus(t('The @doc is recorded. Ref. @r', ['@r' => $serial, '@doc' => $doc]));

            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if invoice is linked to a project
                if ($pcode && $pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $pcode])
                            ->fetchField();
                    $inputs = [];
                    foreach ($fields as $key => $value) {
                        if ($value != $init_data[$key]) {
                            $inputs[] = $key;
                        }
                    }

                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => $doc . '_edit',
                                'input' => $inputs,
                                'value' => $serial,
                                'pcode' => $pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
        } // if updated

        $form_state->setRedirect("ek_sales." . $doc . "s.list");
        
    } // submit
}
