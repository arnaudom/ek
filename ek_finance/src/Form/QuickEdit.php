<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\QuickEdit.
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
        return 'ek_finance_quick_edit_expense';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


            $query = "SELECT * from {ek_expenses} where id=:id";
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
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if(!NULL == $form_state->getValue('pcode') && $form_state->getValue('pcode') != 'n/a'){  
            $p = explode(' ', $form_state->getValue('pcode'));
                $query = "SELECT id FROM {ek_project} WHERE pcode = :p ";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':p' => $p[1] ])
                        ->fetchField();
                if ($data) {
                    $form_state->setValue('pcode', $p[1]);

                } else {
                    $form_state->setValue('pcode', 'n/a');
                }
            } else {
                $form_state->setValue('pcode', 'n/a');
            }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $id = $form_state->getValue('id');
        
        if ($form_state->getValue('pcode') == '') {
            $pcode = 'n/a';
        } else {
            $pcode = $form_state->getValue('pcode');
        }

        $fields = array(
            'allocation' => $form_state->getValue('allocation'),
            'clientname' => $form_state->getValue('client'),
            'suppliername' => $form_state->getValue('supplier'),
            'pdate' => $form_state->getValue('date'),
            'pcode' => $form_state->getValue('pcode'),
            'comment' => \Drupal\Component\Utility\Xss::filter($form_state->getValue('comment'), ['em','b', 'strong']),
        );

        $update1 = Database::getConnection('external_db', 'external_db')
                ->update("ek_expenses")
                ->fields($fields)
                ->condition('id', $id)
                ->execute();

        $update2 = Database::getConnection('external_db', 'external_db')
                ->update("ek_journal")
                ->fields(['date' => $form_state->getValue('date')])
                ->condition('source', 'expense')
                ->condition('reference', $id)
                ->execute();
            
        Cache::invalidateTags(['project_page_view']);
        if (isset($update1) && isset($update2)) {
            \Drupal::messenger()->addStatus(t('The @doc is recorded. Ref. @r', ['@r' => $id, '@doc' => t('expense')]));
        }

        $form_state->setRedirect("ek_finance.manage.list_expense");
    }

}
