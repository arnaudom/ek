<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\moveData.
 */

namespace Drupal\ek_finance\Form;

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
 * Provides a form for simple edit for expenses.
 */
class moveData extends FormBase {

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
        return 'ek_finance_move_account_data';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        if ($form_state->getValue('step') == '') {
            $form_state->setValue('step', 1);
        }

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#title' => t('company'),
            '#required' => TRUE,
            '#ajax' => array(
                'callback' => array($this, 'get_from_class'),
                'wrapper' => 'from_class',
            ),
            
        );

        if ($form_state->getValue('coid')) {
            $coid = $form_state->getValue('coid');
            $classoptions = \Drupal\ek_finance\AidList::listaid($coid);
        }

        $form['fromClass'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => isset($classoptions) ? $classoptions : array(),
            '#required' => TRUE,
            '#title' => t('From account'),
            '#validated' => TRUE,
            '#prefix' => "<div id='from_class'>",
            '#suffix' => '</div>',
            '#ajax' => array(
                'callback' => array($this, 'get_to_class'),
                'wrapper' => 'to_class',
            ),
        );

        $form['toClass'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => isset($classoptions) ? $classoptions : array(),
            '#required' => TRUE,
            '#title' => t('To account'),
            '#validated' => TRUE,
            '#prefix' => "<div id='to_class'>",
            '#suffix' => '</div>',
        );        
        
        
        
            /*$query = "SELECT * from {ek_expenses} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
                    ->fetchObject();
            $query = Database::getConnection('external_db', 'external_db')
                          ->select('ek_journal');
            $query->addExpression('SUM(reconcile)', 'reconcile');
            $query->condition('coid', $data->company, '=');
            $query->condition('date', $data->pdate, '=');
            $query->condition('source', 'expense%', 'like');
            $query->condition('reference', $id, '=');
            $reconcile_flag = $query->execute()->fetchObject()->reconcile;

            if (!$form_state->getValue('head')) {
                $form_state->setValue('head', $data->head);
            }
            $form['id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );


            $company = AccessCheck::CompanyListByUid();
            $form['options']['ref'] = array(
                '#markup' => "<h2>" . t('Reference') . ': ' . $data->id . ', ' . $company[$data->company]. "</h2>",
            );
            
            $form['options']['allocation'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $company,
                '#required' => TRUE,
                '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
                '#title' => t('Allocated'),
                '#description' => t('select an entity for which the expense is done'),
                '#prefix' => "",
                '#suffix' => '',
            );

            if($reconcile_flag == 0) {
                $form['options']['date'] = array(
                    '#type' => 'date',
                    '#size' => 12,
                    '#required' => TRUE,
                    '#default_value' => isset($data->pdate) ? $data->pdate : date('Y-m-d'),
                    '#title' => t('Date'),
                    '#prefix' => "",
                    '#suffix' => '',
                );
            } else $form['date'] = array(
                '#type' => 'hidden',
                '#value' => $data->pdate,
            );
            
            if ($this->moduleHandler->moduleExists('ek_address_book')) {
                
                $client = array('n/a' => t('not applicable'));
                $client += \Drupal\ek_address_book\AddressBookData::addresslist(1);

                $form['options']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => TRUE,
                    '#default_value' => isset($data->clientname) ? $data->clientname : NULL,
                    '#title' => t('client'),
                    '#prefix' => "",
                    '#suffix' => '',
                    '#attributes' => array('style' => array('width:300px;white-space:nowrap')),
                );
                
                $client = array('n/a' => t('not applicable'));
                $client += \Drupal\ek_address_book\AddressBookData::addresslist(2);

                $form['options']['supplier'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => TRUE,
                    '#default_value' => isset($data->suppliername) ? $data->suppliername : NULL,
                    '#title' => t('supplier'),
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


            if ($this->moduleHandler->moduleExists('ek_projects')) {

                if(isset($data->pcode) && $data->pcode != 'n/a') {
                    $thisPcode = t('code') . ' ' . $data->pcode;
                } else {
                    $thisPcode = NULL;
                }
                $form['reference']['pcode'] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 150,
                    //'#required' => TRUE,
                    '#default_value' => $thisPcode,
                    '#attributes' => array('placeholder' => t('Ex. 123')),
                    '#title' => t('Project'),
                    '#autocomplete_route_name' => 'ek_look_up_projects',
                    '#autocomplete_route_parameters' => array('level' => 'all', 'status' => '0'),
                );
            } // project
            
            $form['debit']["comment"] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => $data->comment,
                '#attributes' => array('placeholder' => t('comment'),),
                '#prefix' => "",
                '#suffix' => '',
            );
            */
            $form['actions'] = array(
                '#type' => 'actions',
            );
            
            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Record'),
                '#attributes' => array('class' => array('button--record')),
            );
        

        $form['#attached']['library'][] = 'ek_finance/ek_finance.expenses_form';

        return $form;
    }

    /**
     * Callback 
     */
    public function get_from_class(array &$form, FormStateInterface $form_state) {
        return [$form['fromClass']];
    }

    /**
     * Callback 
     */
    public function get_to_class(array &$form, FormStateInterface $form_state) {
        return [$form['toClass']];
    }
    
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if($form_state->getValue('fromClass') == $form_state->getValue('toClass')){  
                $form_state->setErrorByName('toClass', $this->t('target account is same as origin account'));
            }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $log = date('Y-m-d H:i') . "\r\n";
        $log .= \Drupal::currentUser()->getUsername() . "\r\n";
        
        $class = substr($form_state->getValue("toClass"), 0, 2);
        //Journal
        $log .= 'Journal -----------------' . "\r\n";
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->fields('j',['id']);
            $query->condition('coid', $form_state->getValue('coid'));
            $query->condition('aid', $form_state->getValue('fromClass'));
            $Obj = $query->execute();
            
        While($d = $Obj->fetchObject()) {
           $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_journal')
                        ->condition('id', $d->id)
                        ->condition('coid', $form_state->getValue('coid'))
                        ->fields(['aid' => $form_state->getValue("toClass")])
                        ->execute();
           $log .= 'entry ' . $d->id . ' from ' .$form_state->getValue('fromClass'). ' to ' . $form_state->getValue("toClass") . "\n";
        }
       
        //expenses
        $log .= 'Expenses ----------------' . "\r\n";
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e');
            $query->fields('e',['id']);
            $query->condition('company', $form_state->getValue('coid'));
            $query->condition('type', $form_state->getValue('fromClass'));
            $Obj = $query->execute();
            
         While($d = $Obj->fetchObject()) {
           $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_expenses')
                        ->condition('id', $d->id)
                        ->condition('company', $form_state->getValue('coid'))
                        ->fields(['type' => $form_state->getValue("toClass"),'class' => $class])
                        ->execute();
           $log .= 'entry ' . $d->id . ' from ' .$form_state->getValue('fromClass'). ' to ' . $form_state->getValue("toClass") . "\n";
        }           

        //memo
        $log .= 'Expense memo internal ---' . "\r\n";
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo_list', 'l');
            $query->fields('l',['id']);
            $query->leftJoin('ek_expenses_memo', 'm', 'm.serial = l.serial');
            $query->condition('entity', $form_state->getValue('coid'));
            $query->condition('category', 5, '<');
            $query->condition('aid', $form_state->getValue('fromClass'));
            $Obj = $query->execute();
            
         While($d = $Obj->fetchObject()) {
           $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_expenses_memo_list')
                        ->condition('id', $d->id)
                        ->fields(['aid' => $form_state->getValue("toClass")])
                        ->execute();
           $log .= 'entry ' . $d->id . ' from ' .$form_state->getValue('fromClass'). ' to ' . $form_state->getValue("toClass") . "\n";
        }   

        $log .= 'Expense memo claim ------' . "\r\n";
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo_list', 'l');
            $query->fields('l',['id']);
            $query->leftJoin('ek_expenses_memo', 'm', 'm.serial = l.serial');
            $query->condition('entity_to', $form_state->getValue('coid'));
            $query->condition('category', 5, '=');
            $query->condition('aid', $form_state->getValue('fromClass'));
            $Obj = $query->execute();
            
         While($d = $Obj->fetchObject()) {
           $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_expenses_memo_list')
                        ->condition('id', $d->id)
                        ->fields(['aid' => $form_state->getValue("toClass")])
                        ->execute();
           $log .= 'entry ' . $d->id . ' from ' .$form_state->getValue('fromClass'). ' to ' . $form_state->getValue("toClass") . "\n";
        }  
            
       if ($this->moduleHandler->moduleExists('ek_sales')) {
           
        $log .= 'Sales invoices ----------' . "\r\n";
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice_details', 'l');
            $query->fields('l',['id']);
            $query->leftJoin('ek_sales_invoice', 'm', 'm.serial = l.serial');
            $query->condition('head', $form_state->getValue('coid'));
            $query->condition('aid', $form_state->getValue('fromClass'));
            $Obj = $query->execute();
            
        While($d = $Obj->fetchObject()) {
           $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_sales_invoice_details')
                        ->condition('id', $d->id)
                        ->fields(['aid' => $form_state->getValue("toClass")])
                        ->execute();
           $log .= 'entry ' . $d->id . ' from ' .$form_state->getValue('fromClass'). ' to ' . $form_state->getValue("toClass") . "\n";
        }             
           
        $log .= 'Sales purchases ---------' . "\r\n";
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_purchase_details', 'l');
            $query->fields('l',['id']);
            $query->leftJoin('ek_sales_purchase', 'm', 'm.serial = l.serial');
            $query->condition('head', $form_state->getValue('coid'));
            $query->condition('aid', $form_state->getValue('fromClass'));
            $Obj = $query->execute();
            
        While($d = $Obj->fetchObject()) {
           $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_sales_purchase_details')
                        ->condition('id', $d->id)
                        ->fields(['aid' => $form_state->getValue("toClass")])
                        ->execute();
           $log .= 'entry ' . $d->id . ' from ' .$form_state->getValue('fromClass'). ' to ' . $form_state->getValue("toClass") . "\n";
        }            
       }
       
       $dir = "private://finance/log";
        if(!file_exists()) {
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        }
        $file = $dir . '/log_' . date('Y-m-d_H-i'). '_' .$form_state->getValue('coid'). '_'.$form_state->getValue('fromClass'). '_' .$form_state->getValue('toClass').  '.txt';
        $fp = fopen($file, 'w');
        $written = fwrite($fp, $log);
        fclose($fp);
        $_SESSION['moveLog'] = $file;
       
    }

}
