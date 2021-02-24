<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\UploadForm.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

/**
 * Provides a form to upload file.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_logistics_upload_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['up'] = array(
            '#type' => 'details',
            '#title' => $this->t('Upload file'),
            '#collapsible' => true,
            '#open' => true,
        );


        $form['up']['upload_doc'] = array(
            '#type' => 'file',
            '#prefix' => "<div class='container-inline'>",
            '#required' => true,
        );

        $form['up']['upload'] = array(
            '#id' => 'sharebuttonid',
            '#type' => 'button',
            '#value' => $this->t('Upload'),
            '#ajax' => array(
                'callback' => array($this, 'submitForm'),
                'wrapper' => 'logistics_table_forms',
                'effect' => 'fade',
                'method' => 'append'
            ),
            '#suffix' => '</div>',
        );

        $form['up']['info'] = array(
            '#markup' => $this->t("use file format name 'name_output format_.inc'. Ex. delivery-abc_pdf.inc"),
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
        $extensions = 'inc';
        $validators = array('file_validate_extensions' => array($extensions));
        $file = file_save_upload("upload_doc", $validators, false, 0);

        if ($file) {
            $dir = "private://logistics/templates";
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $doc = $dir . '/' . $file->getFileName();
            $filename = \Drupal::service('file_system')->copy($file->getFileUri(), $doc, 'FILE_EXISTS_REPLACE');


            $route = Url::fromRoute('ek_logistics_delete_form', ['name' => $file->getFileName()])->toString();

            $id = explode(".", $file->getFileName());
            $id = $id[0];

            $link = "<a href=" . $route . " class='use-ajax red'  onclick=\"jQuery('#" . $id . "').fadeOut();\">[x]</a>";

            return "<tr class='' id='" . $id . "'>
               <td class='priority-medium'><a href='" . file_create_url($doc) . "' target='_blank'>" . $file->getFileName() . "</a></td>
               <td class='priority-medium' title=''>" . date('Y-m-d') . "</td>
               <td >" . $link . "</td>
             </tr>";
        }
    }

}
