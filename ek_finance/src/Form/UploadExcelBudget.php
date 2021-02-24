<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\UploadExcelBudget
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
        $form['imp'] = array(
            '#type' => 'details',
            '#title' => $this->t('Import'),
            '#open' => false,
        );

        $here = $this->getRouteMatch();
        if ($here->getRouteName() == 'ek_finance_budgeting' && isset($_SESSION['repfilter']['filter'])) {
            $form['imp']['year'] = array(
                '#type' => 'hidden',
                '#value' => $_SESSION['repfilter']['year'],
            );

            $form['imp']['coid'] = array(
                '#type' => 'hidden',
                '#value' => $_SESSION['repfilter']['coid'],
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
                    /*
                      '#ajax' => array(
                      'callback' => array($this, 'saveFile'),
                      'wrapper' => 'doc_upload_message',
                      'method' => 'replace',
                      ),

                     */
            );

            $alert = "<div id='alert' class='messages messages--warning'>"
                    . $this->t('Import data will erase all current data for year @y and selected company.', ['@y' => $_SESSION['repfilter']['year']]) . "</div>";
        } elseif ($here->getRouteName() == 'ek_finance_budgeting') {
            $alert = "<div id='alert' class='messages messages--warning'>"
                    . $this->t('Select company and year before import.') . "</div>";
        } else {
            
        }

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
        $file = file_save_upload("upload_doc", $validators, $dir, 0, 'FILE_EXISTS_RENAME');

        if ($file) {
            $filename = $file->getFileName();
            $uri = \Drupal::service('file_system')->realpath($file->getFileUri());
            $coid = $form_state->getValue('coid');
            $year = $form_state->getValue('year');
            include_once drupal_get_path('module', 'ek_finance') . '/excel_import_budget.inc';

            \Drupal::messenger()->addStatus(t('imported @n rows from file @f', ['@n' => $row, '@f' => $filename]));
        } else {
            \Drupal::messenger()->addError(t('error copying file'));
        }

        return $form['doc_upload_message'];
    }

    public function saveFile(array &$form, FormStateInterface $form_state) {
        
    }

}
