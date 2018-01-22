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
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $doc = NULL) {


            $query = "SELECT * from {ek_sales_" . $doc . "} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchObject();
            $query = "SELECT * FROM {ek_sales_" . $doc . "_details} where serial=:id ORDER BY id";
            $detail = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $data->serial));

            if (!$form_state->getValue('head')) {
                $form_state->setValue('head', $data->head);
            }
            $form['serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );

            $form['doc'] = array(
                '#type' => 'hidden',
                '#value' => $doc,
            );

            $form['id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $company = AccessCheck::CompanyListByUid();
            $form['options']['ref'] = array(
                '#markup' => "<h2>" . $data->serial . "</h2>",
            );

            if ($doc == 'invoice') {
                $form['options']['head'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $company,
                    '#required' => TRUE,
                    '#default_value' => isset($data->head) ? $data->head : NULL,
                    '#title' => t('Header'),
                    '#prefix' => "",
                    '#suffix' => '',
                    '#ajax' => array(
                        'callback' => array($this, 'set_coid'),
                        'wrapper' => 'debit',
                    //will define the list of bank accounts by company below
                    ),
                );
            } else {
                $form['options']['head'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $company,
                    '#required' => TRUE,
                    '#default_value' => isset($data->head) ? $data->head : NULL,
                    '#title' => t('header'),
                );
            }

            $form['options']['allocation'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $company,
                '#required' => TRUE,
                '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
                '#title' => t('Allocated'),
                '#description' => t('select an entity for which the invoice is done'),
                '#prefix' => "",
                '#suffix' => '',
            );


            if ($this->moduleHandler->moduleExists('ek_address_book')) {

                if ($doc == 'invoice') {
                    $type = 1;
                    $title = t('Client');
                } else {
                    $type = 2;
                    $title = t('Supplier');
                }
                $client = \Drupal\ek_address_book\AddressBookData::addresslist($type);

                $form['options']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => TRUE,
                    '#default_value' => isset($data->client) ? $data->client : NULL,
                    '#title' => $title,
                    '#prefix' => "",
                    '#suffix' => '',
                    '#attributes' => array('style' => array('width:300px;white-space:nowrap')),
                );
            } else {

                $form['options']['client'] = array(
                    '#markup' => t('You do not have any client list.'),
                    '#default_value' => 0,
                    '#prefix' => "",
                    '#suffix' => '',
                );
            }

            $form['options']['date'] = array(
                '#type' => 'date',
                '#size' => 12,
                '#required' => TRUE,
                '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
                '#title' => t('Date'),
                '#prefix' => "",
                '#suffix' => '',
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

            if ($this->moduleHandler->moduleExists('ek_finance') && $doc == 'invoice') {
                
                $options['bank'] = \Drupal\ek_finance\BankData::listbankaccountsbyaid($form_state->getValue('head'));
                
                $form['options']['_currency'] = array(
                    '#type' => 'item',
                    '#markup' => t('Currency') . " : <strong>" . $data->currency . "</strong>",
                );
                
                $form['options']['currency'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->currency,
                );
                /**/
                $form['options']['bank_account'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => isset($options['bank']) ? $options['bank'] : array(),
                    '#default_value' => isset($data->bank) ? $data->bank : $form_state->getValue('bank_account'),
                    '#required' => TRUE,
                    '#title' => t('Account payment'),
                    '#prefix' => "<div id='debit'>",
                    '#suffix' => '</div>',
                    '#description' => '',
                    '#attributes' => array('style' => array('width:280px;;white-space:nowrap')),
                );
            } else {
                $form['options']['bank_account'] = array(
                    '#type' => 'hidden',
                    '#value' => 0
                );
                $form['options']['currency'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->currency,
                );
            }

            $form['options']['terms'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array(t('on receipt'), t('due days')),
                '#default_value' => isset($data->terms) ? $data->terms : NULL,
                '#title' => t('Terms'),
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
            $form['options']['day'] = array(
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



            $form['actions'] = array(
                '#type' => 'actions',
            );



            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => array('class' => array('button--record')),
            );
        

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

        if ($form_state->getValue('terms') == '1' && $form_state->getValue('due') != NULL) {
            $form['options']['day']["#markup"] = date('Y-m-d', strtotime(date("Y-m-d", strtotime($form_state->getValue('date'))) . "+" . $form_state->getValue('due') . ' ' . t("days")));
        } else {
            $form['options']['day']["#markup"] = '';
        }
        return $form['options']['day'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {


        if ($form_state->getValue('terms') == 1 && $form_state->getValue('due') == '') {
            $form_state->setErrorByName('due', $this->t('Terms days is empty'));
        }

        if ($form_state->getValue('terms') == 1 && !is_numeric($form_state->getValue('due'))) {
            $form_state->setErrorByName('due', $this->t('Terms days should be numeric'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $serial = $form_state->getValue('serial');
        $doc = $form_state->getValue('doc');


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
                $update = Database::getConnection('external_db', 'external_db')
                        ->update("ek_journal")
                        ->fields(['aid' => $asset])
                        ->condition('source', 'invoice')
                        ->condition('type', 'debit')
                        ->condition('reference', $form_state->getValue('id'))
                        ->execute();
            
        }
        
        if ($this->moduleHandler->moduleExists('ek_finance') && $doc == 'purchase') {
            //if coid changed, need to update the currency liability account in journal
            $coSettings = new \Drupal\ek_admin\CompanySettings($form_state->getValue('head'));
            $liability = $coSettings->get('liability_account', $form_state->getValue('currency'));
            
            /**/
                $update = Database::getConnection('external_db', 'external_db')
                        ->update("ek_journal")
                        ->fields(['aid' => $liability])
                        ->condition('source', 'purchase')
                        ->condition('type', 'credit')
                        ->condition('reference', $form_state->getValue('id'))
                        ->execute();
            
        }

        Cache::invalidateTags(['project_page_view']);
        if (isset($update)) {
            drupal_set_message(t('The @doc is recorded. Ref. @r', array('@r' => $serial, '@doc' => $doc)), 'status');
            
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if invoice is linked to a project
                if ($pcode && $pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $pcode])
                            ->fetchField();
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => $doc . '_edit',
                                'value' => $serial,
                                'pcode' => $pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
        }

        $form_state->setRedirect("ek_sales." . $doc . "s.list");
    }

}
