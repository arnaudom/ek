<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\UploadChart
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to upload and excel file and convert it in accounts chart.
 */
class UploadChart extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_import_chart';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {


        $company = \Drupal\ek_admin\Access\AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#title' => t('company'),
            '#required' => TRUE,
            
        );

        $form['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#description' => $this->t('Excel format'),
        );
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['upload'] = array(
            '#id' => 'importbutton',
            '#type' => 'submit',
            '#value' => t('Import'),
        );


        $alert = "<div id='alert' class='messages messages--warning'>"
                . t('Import data will erase all current data for selected company.') . "</div>";

        $form['alert'] = array(
            '#type' => 'markup',
            '#markup' => $alert,
        );


        return $form;


        //buildForm
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
        //upload
        /* TODO set settings for extensions allowed */
        $extensions = 'xls xlsx';
        $validators = array('file_validate_extensions' => array($extensions));
        $dir = "private://tmp/";
        file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        $file = file_save_upload("upload_doc", $validators, $dir, 0, FILE_EXISTS_RENAME);

        if ($file) {

            $filename = $file->getFileName();
            $uri = drupal_realpath($file->getFileUri());
            $coid = $form_state->getValue('coid');
            
            include_once drupal_get_path('module', 'ek_finance') . '/excel_import_chart.inc';

            drupal_set_message(t('imported @n rows from file @f', ['@n' => $row, '@f' => $filename]));
        } else {
            drupal_set_message(t('error copying file'));
        }

        
    }

}
