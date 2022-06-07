<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\UploadChart
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
        $form['imp'] = array(
            '#type' => 'details',
            '#title' => $this->t('Import'),
            '#open' => false,
        );

        $company = \Drupal\ek_admin\Access\AccessCheck::CompanyListByUid();
        $form['imp']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#title' => $this->t('company'),
            '#required' => true,
        );

        $form['imp']['upload_doc'] = array(
            '#type' => 'file',
            '#title' => $this->t('Select file'),
            '#description' => $this->t('Excel format'),
        );
        $form['imp']['actions'] = array('#type' => 'actions');
        $form['imp']['actions']['upload'] = array(
            '#id' => 'importbutton',
            '#type' => 'submit',
            '#value' => $this->t('Import'),
        );


        $alert = "<div id='alert' class='messages messages--warning'>"
                . $this->t('Import data will erase all current data for selected company.') . "</div>";

        $form['imp']['alert'] = array(
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
        \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file = file_save_upload("upload_doc", $validators, $dir, 0, FileSystemInterface::EXISTS_RENAME);

        if ($file) {
            $filename = $file->getFileName();
            $uri = \Drupal::service('file_system')->realpath($file->getFileUri());
            $coid = $form_state->getValue('coid');

            include_once drupal_get_path('module', 'ek_finance') . '/excel_import_chart.inc';
            \Drupal::messenger()->addStatus(t('imported @n rows from file @f', ['@n' => $row, '@f' => $filename]));
        } else {
            \Drupal::messenger()->addError(t('error copying file'));
        }
    }

}
