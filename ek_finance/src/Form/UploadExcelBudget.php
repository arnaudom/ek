<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\UploadExcelBudget
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to upload and excel file and convert it in budget entries.
 */
class UploadExcelBudget extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_import_budget';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $here = $this->getRouteMatch();
        if ($here->getRouteName() == 'ek_finance_budgeting' && isset($_SESSION['repfilter']['filter'])) {
            $form['year'] = array(
                '#type' => 'hidden',
                '#value' => $_SESSION['repfilter']['year'],
            );

            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $_SESSION['repfilter']['coid'],
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
                    /*
                      '#ajax' => array(
                      'callback' => array($this, 'saveFile'),
                      'wrapper' => 'doc_upload_message',
                      'method' => 'replace',
                      ),

                     */
            );
        } else {
            $alert = "<div id='alert' class='messages messages--warning'>"
                    . t('Select company and year before import.') . "</div>";
            $form['alert'] = array(
                '#type' => 'markup',
                '#markup' => $alert,
            );
        }

        $alert = "<div id='alert' class='messages messages--warning'>"
                . t('Import data will erase all current data for year @y and selected company.', ['@y' => $_SESSION['repfilter']['year']]) . "</div>";

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
            $year = $form_state->getValue('year');
            include_once drupal_get_path('module', 'ek_finance') . '/excel_import_budget.inc';


            drupal_set_message(t('imported @n rows from file @f', ['@n' => $row, '@f' => $filename]));
        } else {
            drupal_set_message(t('error copying file'));
        }

        return $form['doc_upload_message'];
    }

    public function saveFile(array &$form, FormStateInterface $form_state) {
        
    }


}
