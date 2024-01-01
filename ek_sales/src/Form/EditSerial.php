<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\EditSerial.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to edit serial number.
 */
class EditSerial extends FormBase {

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
        return 'ek_sales_edit_serial';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $doc = null, $id = null, $tb = null, $serial = null) {
        switch ($doc) {
            case 'invoice':
                $route = 'ek_sales.invoices.list';
                break;
            case 'purchase':
                $route = 'ek_sales.purchases.list';
                break;
        }

        $form['list'] = [
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url">List</a>', array('@url' => \Drupal\Core\Url::fromRoute($route, [], [])->toString())),
        ];

        $form['edit'] = [
            '#type' => 'item',
            '#markup' => $this->t("$doc ref. @p", array('@p' => $serial)),
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];

        $form['for_doc'] = [
            '#type' => 'hidden',
            '#value' => $doc,
        ];

        $form['tb'] = [
            '#type' => 'hidden',
            '#value' => $tb,
        ];

        $form['serial'] = [
            '#type' => 'hidden',
            '#value' => $serial,
        ];

        $form['new_serial'] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#required' => true,
            '#default_value' => $serial,
            '#title' => $this->t('New serial number'),
        ];

        $form['actions']['record'] =[
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
        ];


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->getValue('tb'), 'd')
                    ->fields('d', ['serial'])
                    ->condition('serial', $form_state->getValue('new_serial'))
                    ->execute();
        $data = $query->fetchObject();

        if($data){
            $form_state->setErrorByName('new_serial', $this->t('This reference is already in use'));
        }
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $fields = [
            'serial' => $form_state->getValue('new_serial'),
        ];

        $update = Database::getConnection('external_db', 'external_db')
                ->update($form_state->getValue('tb'))
                ->condition('id', $form_state->getValue('for_id'))
                ->fields($fields)
                ->execute();

        $update_ = Database::getConnection('external_db', 'external_db')
                ->update($form_state->getValue('tb') . '_details')
                ->condition('serial', $form_state->getValue('serial'))
                ->fields($fields)
                ->execute();

        

        if ($update && $update_) {
            \Drupal::messenger()->addStatus(t('The @doc has been updated', ['@doc' => $form_state->getValue('for_doc')]));
            $log = t("User @u has edited serial No. @d", ['@u' => \Drupal::currentUser()->getAccountName(), '@d' => $form_state->getValue('new_serial')]);
            \Drupal::logger('ek_sales')->notice($log);
            switch ($form_state->getValue('for_doc')) {
                case 'invoice':
                    $form_state->setRedirect("ek_sales.invoices.list");
                    break;
                case 'purchase':
                    $form_state->setRedirect("ek_sales.purchases.list");
                    break;
            }
        } else {
            \Drupal::messenger()->addError(t('Error while trying to change serial No.'));
        }
    }

}
