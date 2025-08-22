<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Settings.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\File\FileSystemInterface;
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
        $form['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => $this->t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? true : false,
            '#required' => true,
            '#prefix' => "<div class='container-inline'>",
        ];

        if (($form_state->getValue('coid')) == '') {
            $form['next'] = [
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#suffix' => '</div>',
                '#states' => [
                    'invisible' => [
                        "select[name='coid']" => ['value' => ''],
                    ],
                ],
            ];
        }

        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);
            $settings = new LogisticsSettings($form_state->getValue('coid'));

            $form['coid'] = [
                '#type' => 'hidden',
                '#value' => $form_state->getValue('coid'),
            ];
            
            $form['name'] = [
                '#type' => 'item',
                '#markup' => '<h1>' . \Drupal\ek_admin\Access\AccessCheck::CompanyList()[$form_state->getValue('coid')] .'</h1>',
            ];

            $form['edit'] = [
                '#type' => 'select',
                '#size' => 1,
                '#required' => true,
                '#options' => [0 => $this->t('before print only'), 1 => $this->t('after print only'), 2 => $this->t('after invoicing')],
                '#default_value' => $settings->get('edit'),
                '#title' => $this->t('Edit orders'),
            ];

            $form['custom_pdf_form'] = [
                '#type' => 'file',
                '#description' => $this->t('Upload a new pdf form template. Only files with a ".inc" extension is allowed.'),
                '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'inc'],
                ],
            ];

            $form['custom_excel_form'] = [
                '#type' => 'file',
                '#description' => $this->t('Upload a new excel form template. Only files with a ".inc" extension is allowed.'),
                '#upload_validators'  => [
                    'FileExtension' => ['extensions' => 'inc'],
                ],
            ]
            ;
            $tpls = $settings->get('templates');
            $i = 0;
            if (!empty($tpls['pdf'])) {
                foreach ($tpls['pdf'] as $key => $name) {
                    $form['pdf']['template_pdf' . $i] = [
                        '#type' => 'checkbox',
                        '#default_value' => 0,
                        '#return_value' => $name,
                        '#attributes' => ['title' => $this->t('delete')],
                        '#title' => $this->t('Delete pdf template <b>"@n"</b>', ['@n' => $name]),
                    ];
                    $i++;
                }
            }

          
            if (!empty($tpls['xls'])) {
                foreach ($tpls['xls'] as $key => $name) {
                    $form['pdf']['template_xls' . $i] = [
                        '#type' => 'checkbox',
                        '#default_value' => 0,
                        '#return_value' => $name,
                        '#attributes' => ['title' => $this->t('delete')],
                        '#title' => $this->t('Delete excel template <b>"@n"</b>', ['@n' => $name]),
                    ];
                    $i++;
                }
            }

            $form['#tree'] = true;
            $form['actions'] = ['#type' => 'actions'];
            $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Record')];
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
       
        if ($form_state->get('step') == 3) {
            $field = "custom_pdf_form";
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
            }
            $field = "custom_excel_form";
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
            }
        }
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
            $tpls = $settings->get('templates');
            foreach ((array) $form_state->getValue('pdf', []) as $key => $value) {
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
                    if (($key = array_search($value, $tpls['pdf'])) !== false) {
                        unset($tpls['pdf'][$key]);
                    }
                }
            }

            foreach ((array) $form_state->getValue('xls', []) as $key => $value) {
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
                    if (($key = array_search($value, $tpls['xls'])) !== false) {
                        unset($tpls['xls'][$key]);
                    }
                }
            }


            // upload the forms
            $dir = 'private://logistics/templates/' . $form_state->getValue('coid') . '/pdf/';
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $field = "custom_pdf_form";
            $file = $form_state->get($field);
            if ($file) {
                $tpls['pdf'][] = $file->getFileName();
                \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
                \Drupal::messenger()->addStatus(t("New @f uploaded", ['@f' => $file->getFileName()]));
            }

            $dir = 'private://logistics/templates/' . $form_state->getValue('coid') . '/xls/';
            \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            $field = "custom_excel_form";
            $file = $form_state->get($field);
            if ($file) {
                $tpls['xls'][] = $file->getFileName();
                \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
                \Drupal::messenger()->addStatus(t("New @f uploaded", ['@f' => $file->getFileName()]));
            }
            
            // save template
            $settings->set('templates', $tpls);
            $settings->save();

            if (isset($_SESSION['install']) && $_SESSION['install'] == '1') {
                unset($_SESSION['install']);
                $form_state->setRedirect('ek_admin.main');
            }
        }
    }

}
