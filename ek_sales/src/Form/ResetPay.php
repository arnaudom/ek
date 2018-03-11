<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\ResetPay.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to reset a payment record.
 */
class ResetPay extends FormBase {

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
        return 'ek_sales_reset_pay';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $doc = NULL, $id = NULL) {

        switch ($doc) {
            case 'invoice':
                $query = "SELECT head,status,serial from {ek_sales_invoice} where id=:id";
                break;
            case 'purchase' :
                $query = "SELECT head,status,serial from {ek_sales_purchase} where id=:id";
                break;
        }

        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();

        $read = 1;
        $access = AccessCheck::GetCompanyByUser();
        if (!in_array($data->head, $access)) {
            $read = 0;
        }

        $reco = 0;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if($doc == 'invoice') {
                $source = 'receipt';
            }
            if($doc == 'purchase') {
                $source = 'payment';
            }
            $query = "SELECT count(id) from {ek_journal} "
                    . "WHERE coid=:coid AND source=:s "
                    . "AND reference=:r "
                    . "AND reconcile=:c";
            $reco = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':coid' => $data->head, ':s' => $source, ':r' => $id, ':c' => 1))
                ->fetchField();
            
        }
        $form['edit_pay'] = array(
            '#type' => 'item',
            '#markup' => t('Document ref. @p', array('@p' => $data->serial)),
        );
        if ($read == 0) {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('This @doc cannot be reset. Acess denied.', ['@doc' => $doc]),
            );
        } elseif ($reco > 0) {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('This @doc cannot be reset. Data already reconciled.', ['@doc' => $doc]),
            );
        }
         elseif ($data->status == 1 && $read == 1) {

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );
            $form['for_doc'] = array(
                '#type' => 'hidden',
                '#value' => $doc,
            );
            $form['for_coid'] = array(
                '#type' => 'hidden',
                '#value' => $data->head,
            );
            $form['serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('Are you sure you want to reset payment for this @doc ?', ['@doc' => $doc]),
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
            );

        
        } else {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('This @doc cannot be reset because it has not been paid', ['@doc' => $doc]),
            );
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        switch ($form_state->getValue('for_doc')) {
            case 'invoice':
                $query = "SELECT amountbase from {ek_sales_invoice} where id=:id";
                $ab = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $form_state->getValue('for_id')))
                        ->fetchField();

                $fields = [
                    'status' => 0,
                    'amountreceived' => 0,
                    'balancebase' => $ab,
                    'pay_date' => ''
                ];

                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_sales_invoice')
                        ->condition('id', $form_state->getValue('for_id'))
                        ->fields($fields)
                        ->execute();

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    $delete = Database::getConnection('external_db', 'external_db')
                            ->delete('ek_journal')
                            ->condition('reference', $form_state->getValue('for_id'))
                            ->condition('coid', $form_state->getValue('for_coid'))
                            ->condition('source', 'receipt')
                            ->execute();
                }

                break;
                
            case 'purchase' :
                $query = "SELECT amountbc from {ek_sales_purchase} where id=:id";
                $ab = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $form_state->getValue('for_id')))
                        ->fetchField();

                $fields = [
                    'status' => 0,
                    'amountpaid' => 0,
                    'balancebc' => $ab,
                    'pdate' => ''
                ];

                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_sales_purchase')
                        ->condition('id', $form_state->getValue('for_id'))
                        ->fields($fields)
                        ->execute();

                if ($this->moduleHandler->moduleExists('ek_finance')) {
                    $delete = Database::getConnection('external_db', 'external_db')
                            ->delete('ek_journal')
                            ->condition('reference', $form_state->getValue('for_id'))
                            ->condition('coid', $form_state->getValue('for_coid'))
                            ->condition('source', 'payment')
                            ->execute();
                }               
                break;
        }

        if ($update && $delete) {
            \Drupal::messenger()->addStatus(t('The @doc has been reset', ['@doc' => $form_state->getValue('for_doc')]));
            switch ($form_state->getValue('for_doc')) {
                case 'invoice':
                    $form_state->setRedirect("ek_sales.invoices.list");
                    break;
                case 'purchase':
                    $form_state->setRedirect("ek_sales.purchases.list");
                    break;
            }
        } else {
            \Drupal::messenger()->addError(t('Error while trying to reset payment.'));
        }
    }

}