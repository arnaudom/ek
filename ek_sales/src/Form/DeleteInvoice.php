<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\DeleteInvoice.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to delete invoice.
 */
class DeleteInvoice extends FormBase {

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
        return 'ek_sales_delete_invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_invoice', 'i');
        $query->fields('i', ['status', 'serial', 'title', 'head']);
        $query->condition('id', $id, '=');

        $data = $query->execute()->fetchObject();

        $form['edit_invoice'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Invoice ref. @p', array('@p' => $data->serial)),
        );

        if ($data->status > 0) {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('This @doc cannot be deleted because it has been fully or partially paid', ['@doc' => $data->title]),
            );
        } else {
            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $data->head,
            );

            $form['serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Are you sure you want to delete this @doc ?', ['@doc' => $data->title]),
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete'),
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
     */ public function submitForm(array &$form, FormStateInterface $form_state) {
        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_sales_invoice')
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();
        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_sales_invoice_details')
                ->condition('serial', $form_state->getValue('serial'))
                ->execute();

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $journal = new \Drupal\ek_finance\Journal();
            $journalId = $journal->delete('invoice', $form_state->getValue('for_id'), $form_state->getValue('coid'));
            //count field sequence must be restored
            $journal->resetCount($form_state->getValue('coid'), $journalId[1]);
        }

        if ($delete) {

            //////////////////////////
            //    WATCHDOG          //
            //////////////////////////
            $a = array('@u' => \Drupal::currentUser()->getAccountName(), '@d' => $form_state->getValue('serial'));
            $log = $this->t("User @u has deleted document @d", $a);
            \Drupal::logger('ek_sales')->notice($log);
            \Drupal::messenger()->addStatus($this->t('The invoice has been deleted'));
            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            $form_state->setRedirect("ek_sales.invoices.list");
        }
    }

}
