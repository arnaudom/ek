<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SettingsForms.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

;

/**
 * Provides a form to upload files
 */
class SettingsForms extends FormBase {

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
        return 'ek_sales_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        $form['P'] = array(
            '#type' => 'details',
            '#title' => t('Purchase forms'),
            '#collapsible' => TRUE,
            '#open' => FALSE,
        );

        $form['P']['new_purchase'] = array(
            '#type' => 'file',
            '#description' => t('Upload a new purchase template. Only files with a ".inc" extension is allowed.'),
        );

        $list_purchase = array();
        $handle = opendir('private://sales/templates/purchase/');
        while ($file = readdir($handle)) {
            if ($file != '.' AND $file != '..') {
                $list_purchase[$file] = $file;
            }
        }

        $i = 0;

        foreach ($list_purchase as $key => $name) {

            $form['P']['template' . $i] = array(
                '#type' => 'checkbox',
                '#default_value' => 0,
                '#return_value' => $name,
                '#attributes' => array('title' => t('delete')),
                '#title' => t('Delete purchase template <b>"@n"</b>', array('@n' => $name)),
            );
            $i++;
        }


        $form['Q'] = array(
            '#type' => 'details',
            '#title' => t('Quotations forms'),
            '#collapsible' => TRUE,
            '#open' => FALSE,
        );

        $form['Q']['new_quotation'] = array(
            '#type' => 'file',
            '#description' => t('Upload a new quotation template. Only files with a ".inc" extension is allowed.'),
        );

        $list_quotation = array();
        $handle = opendir('private://sales/templates/quotation/');
        while ($file = readdir($handle)) {
            if ($file != '.' AND $file != '..') {
                $list_quotation[$file] = $file;
            }
        }

        foreach ($list_quotation as $key => $name) {

            $form['Q']['template' . $i] = array(
                '#type' => 'checkbox',
                '#default_value' => 0,
                '#return_value' => $name,
                '#attributes' => array('title' => t('delete')),
                '#title' => t('Delete quotation template <b>"@n"</b>', array('@n' => $name)),
            );
            $i++;
        }


        $form['I'] = array(
            '#type' => 'details',
            '#title' => t('Invoice forms'),
            '#collapsible' => TRUE,
            '#open' => FALSE,
        );

        $form['I']['new_invoice'] = array(
            '#type' => 'file',
            '#description' => t('Upload a new invoice template. Only files with a ".inc" extension is allowed.'),
        );

        $list_invoice = array();
        $handle = opendir('private://sales/templates/invoice/');
        while ($file = readdir($handle)) {
            if ($file != '.' AND $file != '..') {
                $list_invoice[$file] = $file;
            }
        }

        foreach ($list_invoice as $key => $name) {

            $form['I']['template' . $i] = array(
                '#type' => 'checkbox',
                '#default_value' => 0,
                '#return_value' => $name,
                '#attributes' => array('title' => t('delete')),
                '#title' => t('Delete invoice template <b>"@n"</b>', array('@n' => $name)),
            );
            $i++;
        }

        $form['#tree'] = TRUE;

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        );


        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {


        $extensions = 'inc';
        $validators = array('file_validate_extensions' => array($extensions));

        $field = "P";
        $file = file_save_upload($field, $validators, FALSE, 0, FILE_EXISTS_REPLACE);
        if ($file) {
            $form_state->set('new_purchase', $file);
        }

        $field = "Q";
        $file = file_save_upload($field, $validators, FALSE, 0, FILE_EXISTS_REPLACE);
        if ($file) {
            $form_state->set('new_quotation', $file);
        }

        $field = "I";
        $file = file_save_upload($field, $validators, FALSE, 0, FILE_EXISTS_REPLACE);
        if ($file) {
            $form_state->set('new_invoice', $file);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


        if ($form_state->get('new_purchase')) {

            $dir = "private://sales/templates/purchase/" . $form_state->getValue('coid') . '/';
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $dest = $dir;
            $filename = file_unmanaged_copy($form_state->get('new_purchase')
                            ->getFileUri(), $dest, FILE_EXISTS_REPLACE);
            drupal_set_message(t("New purchase file uploaded"), 'status');
        }

        if ($form_state->get('new_quotation')) {

            $dir = "private://sales/templates/quotation/" . $form_state->getValue('coid') . '/';
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $dest = $dir;
            $filename = file_unmanaged_copy($form_state->get('new_quotation')
                            ->getFileUri(), $dest, FILE_EXISTS_REPLACE);
            drupal_set_message(t("New quotation file uploaded"), 'status');
        }

        if ($form_state->get('new_invoice')) {

            $dir = "private://sales/templates/invoice/" . $form_state->getValue('coid') . '/';
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $dest = $dir;
            $filename = file_unmanaged_copy($form_state->get('new_invoice')
                            ->getFileUri(), $dest, FILE_EXISTS_REPLACE);
            drupal_set_message(t("New invoice file uploaded"), 'status');
        }


        foreach ($form_state->getValue('P') as $key => $value) {

            if ($value != 0 || $value != '') {
                unlink("private://sales/templates/purchase/" . $value);
                drupal_set_message(t("Template @t deleted", array('@t' => $value)), 'status');
            }
        }

        foreach ($form_state->getValue('Q') as $key => $value) {

            if ($value != 0 || $value != '') {
                unlink("private://sales/templates/quotation/" . $value);
                drupal_set_message(t("Template @t deleted", array('@t' => $value)), 'status');
            }
        }

        foreach ($form_state->getValue('I') as $key => $value) {

            if ($value != 0 || $value != '') {
                unlink("private://sales/templates/invoice/" . $value);
                drupal_set_message(t("Template @t deleted", array('@t' => $value)), 'status');
            }
        }
    }

}
