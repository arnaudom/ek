<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\DeletePurchase.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to delete purchase.
 */
class DeletePurchase extends FormBase {

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
        return 'ek_sales_delete_purchase';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
        $query = "SELECT status,serial from {ek_sales_purchase} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();


        $form['edit_purchase'] = array(
            '#type' => 'item',
            '#markup' => t('Purchase ref. @p', array('@p' => $data->serial)),
        );



        if ($data->status == 0) {

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $form['serial'] = array(
                '#type' => 'hidden',
                '#value' => $data->serial,
            );

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('Are you sure you want to delete this purchase ?'),
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete'),
            );
        } else {

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => t('This purchase cannot be deleted because it has been fully or partially paid'),
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


        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_sales_purchase')
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();
        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_sales_purchase_details')
                ->condition('serial', $form_state->getValue('serial'))
                ->execute();

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $delete = Database::getConnection('external_db', 'external_db')
                    ->delete('ek_journal')
                    ->condition('reference', $form_state->getValue('for_id'))
                    ->condition('source', 'purchase')
                    ->execute();
        }

        if ($delete) {
            \Drupal::messenger()->addStatus(t('The purchase has been deleted'));
            $form_state->setRedirect("ek_sales.purchases.list");
        }
    }

}

//class