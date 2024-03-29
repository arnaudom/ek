<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\UploadForm.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to upload file.
 */
class UploadForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_products_upload';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $form['upload_image'] = array('#type' => 'file');

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#default_value' => $id,
        );
        $form['upload'] = array(
            '#id' => 'upbuttonid',
            '#type' => 'button',
            '#value' => t('Upload'),
            '#ajax' => array(
                'callback' => array($this, 'submitForm'),
                'wrapper' => 'product_images',
                'effect' => 'fade',
                'method' => 'prepend'
            ),
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
        $validators = array('file_validate_is_image' => array());
        $field = "upload_image";

        $file = file_save_upload($field, $validators, false, 0);
        if (isset($file)) {
            // File upload was attempted.
            if ($file) {
                // Put the temporary file in form_values so we can save it on submit.
                $form_state->setValue($field, $file);
            } else {
                // File upload failed.
                //return an error
            }
        } else {
            $form_state->getValue($field, 0);
        }

        if (!$form_state->getValue('upload_image') == 0) {
            if ($file = $form_state->getValue('upload_image')) {
                $filesystem = \Drupal::service('file_system');
                $dir = "private://products/images/" . $form_state->getValue('for_id');
                $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $filename = $filesystem->copy($file->getFileUri(), $dir);

                $query = "SELECT itemcode from {ek_items} where id=:id";
                $itemcode = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $form_state->getValue('for_id')))
                        ->fetchField();
                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_item_images')
                        ->fields(array('itemcode' => $itemcode, 'uri' => $filename))
                        ->execute();

                //create thumbs
                $thumb = "private://products/images/" . $form_state->getValue('for_id') . "/40/40x40_" . basename($filename);
                $dir = "private://products/images/" . $form_state->getValue('for_id') . "/40/";
                $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $filesystem->copy($filename, $thumb, 'FILE_EXISTS_REPLACE');
                //Resize after copy
                $image_factory = \Drupal::service('image.factory');
                $image = $image_factory->get($thumb);
                $image->scale(40);
                $image->save();

                $thumb = "private://products/images/" . $form_state->getValue('for_id') . "/100/100x100_" . basename($filename);
                $dir = "private://products/images/" . $form_state->getValue('for_id') . "/100/";
                $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $filesystem->copy($filename, $thumb, 'FILE_EXISTS_REPLACE');
                //Resize after copy
                $image_factory = \Drupal::service('image.factory');
                $image = $image_factory->get($thumb);
                $image->scale(100);
                $image->save();
            }
        }

        $img = "<div class='grid'><a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($filename)
                . "' target='_blank'><img class='thumbnail' src="
                . \Drupal::service('file_url_generator')->generateAbsoluteString($filename) . "></a></div>";
        $response = new AjaxResponse();
        return $response->addCommand(new InsertCommand('#product_images', $img));
    }

}
