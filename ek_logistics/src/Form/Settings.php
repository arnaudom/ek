<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Settings.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_logistics\LogisticsSettings;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides an company settings form.
 */
class Settings extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_logistics_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => $this->t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? true : false,
            '#required' => true,
            '#prefix' => "<div class='container-inline'>",
        );

        if (($form_state->getValue('coid')) == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#suffix' => '</div>',
                '#states' => array(
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
        }

        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);
            $settings = new LogisticsSettings($form_state->getValue('coid'));

            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $form_state->getValue('coid'),
            );

            $form['edit'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#required' => true,
                '#options' => array(0 => $this->t('before print only'), 1 => $this->t('after print only'), 2 => $this->t('after invoicing')),
                '#default_value' => $settings->get('edit'),
                '#title' => $this->t('Edit orders'),
            );

            $form['custom_pdf_form'] = array(
                '#type' => 'file',
                '#description' => $this->t('Upload a new pdf form template. Only files with a ".inc" extension is allowed.'),
            );

            $form['custom_excel_form'] = array(
                '#type' => 'file',
                '#description' => $this->t('Upload a new excel form template. Only files with a ".inc" extension is allowed.'),
            );



            if (file_exists('private://logistics/templates/' . $form_state->getValue('coid') . '/pdf/')) {
                $list_pdf_forms = array();
                $handle = opendir('private://logistics/templates/' . $form_state->getValue('coid') . '/pdf/');
                while ($file = readdir($handle)) {
                    if ($file != '.' and $file != '..') {
                        $list_pdf_forms[$file] = $file;
                    }
                }

                $i = 0;

                foreach ($list_pdf_forms as $key => $name) {
                    $form['pdf']['template_pdf' . $i] = array(
                        '#type' => 'checkbox',
                        '#default_value' => 0,
                        '#return_value' => $name,
                        '#attributes' => array('title' => $this->t('delete')),
                        '#title' => $this->t('Delete pdf template <b>"@n"</b>', array('@n' => $name)),
                    );
                    $i++;
                }
            }

            if (file_exists('private://logistics/templates/' . $form_state->getValue('coid') . '/xls/')) {
                $list_xls_forms = array();
                $handle = opendir('private://logistics/templates/' . $form_state->getValue('coid') . '/xls/');
                while ($file = readdir($handle)) {
                    if ($file != '.' and $file != '..') {
                        $list_pdf_forms[$file] = $file;
                    }
                }

                $i = 0;

                foreach ($list_xls_forms as $key => $name) {
                    $form['xls']['template_xls' . $i] = array(
                        '#type' => 'checkbox',
                        '#default_value' => 0,
                        '#return_value' => $name,
                        '#attributes' => array('title' => $this->t('delete')),
                        '#title' => $this->t('Delete excel template <b>"@n"</b>', array('@n' => $name)),
                    );
                    $i++;
                }
            }


            $form['#tree'] = true;
            $form['actions'] = array('#type' => 'actions');
            $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
        /*
          if ($form_state->get('step') == 3) {

          $extensions = 'inc';
          $validators = array('file_validate_extensions' => array($extensions));

          $field = "custom_pdf_form";
          $file = file_save_upload($field, $validators, FALSE, 0, FILE_EXISTS_REPLACE);
          if ($file) {
          $form_state->set('new_pdf_form', $file);
          }

          $field = "custom_xls_form";
          $file = file_save_upload($field, $validators, FALSE, 0, FILE_EXISTS_REPLACE);
          if ($file) {
          $form_state->set('new_xls_form', $file);
          }
          } */
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {

            //verify coid exist first
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_settings', 's')
                    ->fields('s', ['coid'])
                    ->condition('coid', $form_state->getValue('coid'));
            $coid = $query->execute()->fetchField();
            /*
              $query = 'SELECT coid from {ek_logi_settings} WHERE coid=:c';
              $coid = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':c' => $form_state->getValue('coid')))
              ->fetchField();
             */
            if (!$coid) {
                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_logi_settings')
                        ->fields(array('coid' => $form_state->getValue('coid')))
                        ->execute();
            }


            $settings = new LogisticsSettings($form_state->getValue('coid'));
            $settings->set('edit', $form_state->getValue('edit'));
            $save = $settings->save();

            if ($save) {
                \Drupal::messenger()->addStatus($this->t('The settings are recorded'));
                \Drupal\Core\Cache\Cache::invalidateTags(['ek_admin.settings']);
            }


            // delete the forms

            foreach ($form_state->getValue('pdf') as $key => $value) {
                if ($value != 0 || $value != '') {
                    $uri = 'private://logistics/templates/' . $form_state->getValue('coid') . '/pdf/' . $value;
                    $query = Database::getConnection()->select('file_managed', 'f');
                    $query->fields('f', ['fid']);
                    $query->condition('uri', $uri);
                    $fid = $query->execute()->fetchField();
                    if (!$fid) {
                        unlink($uri);
                    } else {
                        $file = \Drupal\file\Entity\File::load($fid);
                        $file->delete();
                        \Drupal::messenger()->addStatus($this->t("Template @t deleted", ['@t' => $value]));
                    }
                }
            }

            foreach ($form_state->getValue('xls') as $key => $value) {
                if ($value != 0 || $value != '') {
                    $uri = 'private://logistics/templates/' . $form_state->getValue('coid') . '/xls/' . $value;
                    $query = Database::getConnection()->select('file_managed', 'f');
                    $query->fields('f', ['fid']);
                    $query->condition('uri', $uri);
                    $fid = $query->execute()->fetchField();
                    if (!$fid) {
                        unlink($uri);
                    } else {
                        $file = \Drupal\file\Entity\File::load($fid);
                        $file->delete();
                        \Drupal::messenger()->addStatus($this->t("Template @t deleted", ['@t' => $value]));
                    }
                }
            }
            // upload the forms


            $extensions = 'inc';
            $validators = array('file_validate_extensions' => [$extensions]);
            $dir = 'private://logistics/templates/' . $form_state->getValue('coid') . '/pdf/';
            \Drupal::service('file_system')->prepareDirectory($dir, 'FILE_CREATE_DIRECTORY' | 'FILE_MODIFY_PERMISSIONS');
            $file = file_save_upload("custom_pdf_form", $validators, $dir, 0, 'FILE_EXISTS_REPLACE');
            if ($file) {
                $file->setPermanent();
                $file->save();
                \Drupal::messenger()->addStatus(t("New pdf form uploaded"));
            }

            $dir = 'private://logistics/templates/' . $form_state->getValue('coid') . '/xls/';
            \Drupal::service('file_system')->prepareDirectory($dir, 'FILE_CREATE_DIRECTORY' | 'FILE_MODIFY_PERMISSIONS');
            $file = file_save_upload("custom_xls_form", $validators, $dir, 0, 'FILE_EXISTS_REPLACE');
            if ($file) {
                $file->setPermanent();
                $file->save();
                \Drupal::messenger()->addStatus(t("New excel form uploaded"));
            }

            if ($_SESSION['install'] == '1') {
                unset($_SESSION['install']);
                $form_state->setRedirect('ek_admin.main');
            }
        }//step 3
    }

}
