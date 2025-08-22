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
        $form['imp'] = [
            '#type' => 'details',
            '#title' => $this->t('Import'),
            '#open' => false,
        ];

        $company = \Drupal\ek_admin\Access\AccessCheck::CompanyListByUid();
        $form['imp']['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#title' => $this->t('company'),
            '#required' => true,
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
                . $this->t('Import data will erase all current data for selected company.') . "</div>";

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

            include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/excel_import_chart.inc';
            \Drupal::messenger()->addStatus(t('imported @n rows from file @f', ['@n' => $row, '@f' => $filename]));
             $file->delete();
        } else {
            \Drupal::messenger()->addError(t('error copying file'));
        }

        
    }

}
