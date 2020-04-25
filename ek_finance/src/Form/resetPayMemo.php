<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\resetPayMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to reset payment for finance memo
 */
class resetPayMemo extends FormBase {

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
        return 'ek_finance_reset_pay_memo';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        if ($data->category < 5) {
            $route = 'ek_finance_manage_list_memo_internal';
        } else {
            $route = 'ek_finance_manage_list_memo_personal';
        }

        $url = Url::fromRoute($route, array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => $url)),
        );

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_expenses_memo', 'm')
                ->fields('m', ['serial', 'entity', 'entity_to', 'status', 'post'])
                ->condition('id', $id, '=');
        $data = $query->execute()->fetchObject();

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_expenses', 'e')
                ->fields('e', ['id'])
                ->condition('company', $data->entity_to, '=')
                ->condition('comment', $data->serial . '%', 'like');
        $expense = $query->execute()->fetchCol();

        $form['edit_memo'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Memo ref. @p', array('@p' => $data->serial)),
        );

        if (empty($expense)) {
            //system can't locate the expense reference
            //the expense may exists but some fields (comment) may have been changed)
            $alert = "<div id='fx' class='messages messages--warning'>" . $this->t('Record not editable') . "</div>";
            $form['error'] = array(
                '#type' => 'item',
                '#markup' => $alert,
            );
            $error = 1;
        } else {
            $form['edit_expense'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Expense ref. @p', array('@p' => implode(',', $expense))),
            );
        }



        if ($data->status == '1' || $data->post != '2') {

            //check authorizations
            // can be deleted by user with admin privilege or owner or user with access
            if (\Drupal::currentUser()->hasPermission('admin_memos')) {
                $delete = true;
            } else {
                if ($data->category < 5) {
                    $access = AccessCheck::CompanyListByUid();
                    $delete = in_array($data->entity, $access) ? true : false;
                } else {
                    $delete = (\Drupal::currentUser()->id() == $data->entity) ? true : false;
                }
            }

            if ($delete) {
                $form['for_id'] = array(
                    '#type' => 'hidden',
                    '#value' => $id,
                );

                $form['coid'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->entity_to,
                );

                $form['category'] = array(
                    '#type' => 'hidden',
                    '#value' => $data->category,
                );

                $form['expense_id'] = array(
                    '#type' => 'hidden',
                    '#value' => $expense,
                );
                if (!isset($error)) {
                    $form['alert'] = array(
                        '#type' => 'item',
                        '#markup' => $this->t('Are you sure you want to reset this payment ?'),
                    );
                    $form['actions']['record'] = array(
                        '#type' => 'submit',
                        '#value' => $this->t('Reset'),
                    );
                }
            } else {
                $form['alert'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('You are not authorized to edit this memo.'),
                );
            }
        } else {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('This memo cannot be edited because it has been fully or partially paid'),
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
        $journal = new \Drupal\ek_finance\Journal();

        foreach ($form_state->getValue('expense_id') as $key => $id) {
            $journalId = $journal->delete('expense', $id, $form_state->getValue('coid'));
            $journal->resetCount($form_state->getValue('coid'), $journalId[1]);
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_expenses')
                    ->condition('id', $id)
                    ->execute();
        }

        if ($delete) {
            $fields = array(
                'status' => 0,
                'amount_paid' => 0,
                'amount_paid_base' => 0,
                'pdate' => '',
                'post' => 0,
            );

            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_expenses_memo')
                    ->fields($fields)
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();

            \Drupal::messenger()->addStatus(t('Payment reset; expenses deleted: @e', ['@e' => implode(',', $form_state->getValue('expense_id'))]));

            if ($form_state->getValue('category') < 5) {
                $form_state->setRedirect('ek_finance_manage_list_memo_internal');
            } else {
                $form_state->setRedirect('ek_finance_manage_list_memo_personal');
            }
        }
    }

}
