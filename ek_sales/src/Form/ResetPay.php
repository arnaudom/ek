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
    public function buildForm(array $form, FormStateInterface $form_state, $doc = null, $id = null, $head = null, $serial = null) {
        switch ($doc) {
            case 'invoice':
                $route = 'ek_sales.invoices.list';
                break;
            case 'purchase':
                $route = 'ek_sales.purchases.list';
                break;
        }

        $form['list'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => \Drupal\Core\Url::fromRoute($route, [], [])->toString())),
        );

        $form['edit'] = array(
            '#type' => 'item',
            '#markup' => $this->t("$doc ref. @p", array('@p' => $serial)),
        );
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
            '#value' => $head,
        );
        $form['serial'] = array(
            '#type' => 'hidden',
            '#value' => $serial,
        );

        $form['alert'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Are you sure you want to reset payment for this @doc ?', ['@doc' => $doc]),
        );

        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Reset'),
        );



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
                $i = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_invoice', 'i')
                        ->fields('i',)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute()
                        ->fetchObject();

                $fields = [
                    'status' => 0,
                    'amountreceived' => 0,
                    'balancebase' => $i->amountbase,
                    'pay_date' => '',
                    'pay_rate' => 0
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
                            ->condition('coid', $form_state->getValue('for_coid'));
                    if ($i->type == 4) {
                        // this is a CN
                        $delete->condition('source', 'invoice cn');
                    } else {
                        $delete->condition('source', 'receipt');
                    }

                    $delete->execute();
                }

                if ($i->type == 4) {
                    // reset the assigned invoice to CN
                    $code = explode(" ", $i->comment);
                    $iassigned = Database::getConnection('external_db', 'external_db')
                            ->select('ek_sales_invoice', 's')
                            ->fields('s')
                            ->condition('serial', $code[2])
                            ->execute()
                            ->fetchObject();
                    $updatei = Database::getConnection('external_db', 'external_db')
                            ->update('ek_sales_invoice')
                            ->condition('id', $iassigned->id);
                    if ($i->amount == $iassigned->amountreceived) {
                        $updatei->fields(['status' => 0, 'amountreceived' => 0, 'balancebase' => $iassigned->amountbase]);
                    } else {
                        $ar = $iassigned->amountreceived - $i->amount;
                        $bb = $iassigned->balancebase + $i->amountbase;
                        $updatei->fields(['status' => 2, 'amountreceived' => $ar, 'balancebase' => $bb]);
                    }

                    $updatei->execute();
                }

                break;

            case 'purchase':
                $p = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase', 'p')
                        ->fields('p',)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute()
                        ->fetchObject();

                $fields = [
                    'status' => 0,
                    'amountpaid' => 0,
                    'balancebase' => $p->balancebase,
                    'pdate' => '',
                    'pay_rate' => 0
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
                            ->condition('coid', $form_state->getValue('for_coid'));
                    
                    if ($p->type == 4) {
                        // this is a CN
                        $delete->condition('source', 'purchase dn');
                    } else {
                        $delete->condition('source', 'payment');
                    }
                            
                    $delete->execute();
                }
                
                if ($p->type == 4) {
                    // reset the assigned purchase to DN
                    $code = explode(" ", $p->comment);
                    $passigned = Database::getConnection('external_db', 'external_db')
                            ->select('ek_sales_purchase', 'p')
                            ->fields('p')
                            ->condition('serial', $code[2])
                            ->execute()
                            ->fetchObject();
                    $updatep = Database::getConnection('external_db', 'external_db')
                            ->update('ek_sales_purchase')
                            ->condition('id', $passigned->id);
                    if ($p->amount == $passigned->amountpaid) {
                        $updatep->fields(['status' => 0, 'amountpaid' => 0, 'balancebase' => $passigned->amountbase]);
                    } else {
                        $ar = $passigned->amountpaid - $p->amount;
                        $bb = $passigned->balancebase + $p->amountbase;
                        $updatep->fields(['status' => 2, 'amountpaid' => $ar, 'balancebase' => $bb]);
                    }

                    $updatep->execute();
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
