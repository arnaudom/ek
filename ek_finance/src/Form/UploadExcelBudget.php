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
        $form['imp'] = [
            '#type' => 'details',
            '#title' => $this->t('Import'),
            '#open' => false,
        ];

        $here = $this->getRouteMatch();
        if ($here->getRouteName() == 'ek_finance_budgeting' && isset($_SESSION['repfilter']['filter'])) {
            $form['imp']['year'] = [
                '#type' => 'hidden',
                '#value' => $_SESSION['repfilter']['year'],
            ];

            $form['imp']['coid'] = [
                '#type' => 'hidden',
                '#value' => $_SESSION['repfilter']['coid'],
            ];

            $form['imp']['upload_doc'] = [
                '#type' => 'file',
                '#title' => $this->t('Select file'),
                '#description' => $this->t('Excel format'),
                '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'xlsx'],
                ],
            ];

            $form['imp']['actions'] = ['#type' => 'actions'];
            $form['imp']['actions']['upload'] = [
                '#id' => 'importbutton',
                '#type' => 'submit',
                '#value' => $this->t('Import'),
            ];

            $alert = "<div id='alert' class='messages messages--warning'>"
                    . $this->t('Import data will erase all current data for year @y and selected company.', ['@y' => $_SESSION['repfilter']['year']]) . "</div>";
        } elseif ($here->getRouteName() == 'ek_finance_budgeting') {
            $alert = "<div id='alert' class='messages messages--warning'>"
                    . $this->t('Select company and year before import.') . "</div>";
        } else {
            
        }

        $form['imp']['alert'] = [
            '#type' => 'markup',
            '#markup' => $alert,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $field = "upload_doc";
        $file = _file_save_upload_from_form($form[$field], $form_state, 0);
        if ($file) {
            
            if($errors = $form_state->getErrors()) {
           
                foreach ($errors as $error) {
                    $form_state->setErrorByName($field, $error);
                }
                
                $file->delete();
            } else {
                $form_state->set($field, $file) ;
            }           

            
        } else {            
                $form_state->setErrorByName($field, 'error with upload');
        }
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $field = "upload_doc";
        $file = $form_state->get($field);
        if ($file) {
            $filename = $file->getFileName();
            $uri = \Drupal::service('file_system')->realpath($file->getFileUri());
            $coid = $form_state->getValue('coid');
            $year = $form_state->getValue('year');
            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/excel_import_budget.inc';
            \Drupal::messenger()->addStatus(t('imported @n rows from file @f', ['@n' => $row, '@f' => $filename]));
            $file->delete();
        } else {
            \Drupal::messenger()->addError(t('error copying file'));
        }

        return $form['doc_upload_message'];
    }


}
