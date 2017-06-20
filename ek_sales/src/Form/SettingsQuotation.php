<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SettingsQuotation.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to manage quotation settings.
 */
class SettingsQuotation extends FormBase {

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
        return 'quotations_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $query = "SELECT * from {ek_quotation_settings} ";
        $data = Database::getConnection('external_db', 'external_db')->query($query);

        $form['setting']['#tree'] = TRUE;

        while ($d = $data->fetchObject()) {

            $form['setting'][$d->id]['field'] = array(
                '#type' => 'item',
                '#markup' => t($d->field),
                '#value' => $d->field,
                '#prefix' => "<div class='container-inline'>",
            );

            $form['setting'][$d->id]['name'] = array(
                '#type' => 'textfield',
                '#default_value' => $d->name,
                '#size' => 20,
                '#maxlength' => 50,
                '#required' => TRUE,
            );

            $form['setting'][$d->id]['active'] = array(
                '#type' => 'select',
                '#options' => array('0' => t('hide'), '1' => t('display')),
                '#default_value' => $d->active,
                '#suffix' => '</div>',
            );
        }



        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
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



        foreach ($form_state->getValue('setting') as $key => $fields) {

            Database::getConnection('external_db', 'external_db')
                    ->update('ek_quotation_settings')
                    ->condition('id', $key)
                    ->fields($fields)
                    ->execute();
        }

        drupal_set_message(t('Settings recorded'), 'status');
    }

}
