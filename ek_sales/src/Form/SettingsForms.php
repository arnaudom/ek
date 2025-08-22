<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SettingsForms.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_sales\SalesSettings;

;

/**
 * Provides a form to upload files
 */
class SettingsForms extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;
    protected $settings;
    protected $tpls;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
        $this->settings = new SalesSettings();
        $this->tpls = $this->settings->get('templates');
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $form['p'] = [
            '#type' => 'details',
            '#title' => $this->t('Purchase forms'),
            '#collapsible' => true,
            '#open' => false,
        ];

        $form['p']['new_purchase'] = [
            '#type' => 'file',
            '#description' => $this->t('Upload a new purchase template. Only files with a ".inc" extension is allowed.'),
            '#upload_validators'  => [
                'FileExtension' => ['extensions' => 'inc'],
            ],
        ];

        $i = 0;
        if (!empty($this->tpls['purchase'])) {
            foreach ($this->tpls['purchase'] as $key => $name) {
                $form['p']['template' . $i] = [
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#return_value' => $name,
                    '#attributes' => ['title' => $this->t('delete')],
                    '#title' => $this->t('Delete purchase template <b>"@n"</b>', ['@n' => $name]),
                ];
                $i++;
            }
        }


        $form['q'] = [
            '#type' => 'details',
            '#title' => $this->t('Quotations forms'),
            '#collapsible' => true,
            '#open' => false,
        ];

        $form['q']['new_quotation'] = [
            '#type' => 'file',
            '#description' => $this->t('Upload a new quotation template. Only files with a ".inc" extension is allowed.'),
            '#upload_validators'  => [
                'FileExtension' => ['extensions' => 'inc'],
            ],
        ];
        
        if (!empty($this->tpls['quotation'])) {
            foreach ($this->tpls['quotation'] as $key => $name) {
                $form['q']['template' . $i] = [
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#return_value' => $name,
                    '#attributes' => ['title' => $this->t('delete')],
                    '#title' => $this->t('Delete quotation template <b>"@n"</b>', ['@n' => $name]),
                ];
                $i++;
            }
        }

        $form['i'] = [
            '#type' => 'details',
            '#title' => $this->t('Invoice forms'),
            '#collapsible' => true,
            '#open' => false,
        ];

        $form['i']['new_invoice'] = [
            '#type' => 'file',
            '#description' => $this->t('Upload a new invoice template. Only files with a ".inc" extension is allowed.'),
            '#upload_validators'  => [
                'FileExtension' => ['extensions' => 'inc'],
            ],
        ];
        
        if (!empty($this->tpls['invoice'])) {
            foreach ($this->tpls['invoice'] as $key => $name) {
                $form['i']['template' . $i] = [
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#return_value' => $name,
                    '#attributes' => ['title' => $this->t('delete')],
                    '#title' => $this->t('Delete invoice template <b>"@n"</b>', ['@n' => $name]),
                ];
                $i++;
            }
        }

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];


        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $field = "new_purchase";
        $file = _file_save_upload_from_form($form['p'][$field], $form_state, 0);
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
        $field = "new_quotation";
        $file = _file_save_upload_from_form($form['q'][$field], $form_state, 0);
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
        $field = "new_invoice";
        $file = _file_save_upload_from_form($form['i'][$field], $form_state, 0);
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

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        // if checkbox is selected, value return = file name
        // file is deleted and remove from settings
        if(!empty($this->tpls['purchase'])) {
            foreach ($form_state->getValue('p') as $key => $value) {
                if ($value != 0 || $value != '') {
                    $uri = "private://sales/templates/purchase/" . $value;
                    $query = Database::getConnection()->select('file_managed', 'f');
                    $query->fields('f', ['fid']);
                    $query->condition('uri', $uri);
                    $fid = $query->execute()->fetchField();
                    if (!$fid) {
                        unlink($uri);
                    } else {
                        $file = \Drupal\file\Entity\File::load($fid);
                        $file->delete();
                        \Drupal::messenger()->addStatus(t("Template @t deleted", ['@t' => $value]));
                    }

                    if (($key = array_search($value, $this->tpls['purchase'])) !== false) {
                        unset($this->tpls['purchase'][$key]);
                    }
                }
            }
        }

        if(!empty($this->tpls['quotation'])) {
            foreach ($form_state->getValue('q') as $key => $value) {
                if ($value != 0 || $value != '') {
                    $uri = "private://sales/templates/quotation/" . $value;
                    $query = Database::getConnection()->select('file_managed', 'f');
                    $query->fields('f', ['fid']);
                    $query->condition('uri', $uri);
                    $fid = $query->execute()->fetchField();
                    if (!$fid) {
                        unlink($uri);
                    } else {
                        $file = \Drupal\file\Entity\File::load($fid);
                        $file->delete();
                        \Drupal::messenger()->addStatus(t("Template @t deleted", ['@t' => $value]));
                    }
                    if (($key = array_search($value, $this->tpls['quotation'])) !== false) {
                        unset($this->tpls['quotation'][$key]);
                    }
                }
            }
        }

        if(!empty($this->tpls['invoice'])) {
            foreach ($form_state->getValue('i') as $key => $value) {
                if ($value != 0 || $value != '') {
                    $uri = "private://sales/templates/invoice/" . $value;
                    $query = Database::getConnection()->select('file_managed', 'f');
                    $query->fields('f', ['fid']);
                    $query->condition('uri', $uri);
                    $fid = $query->execute()->fetchField();
                    if (!$fid) {
                        unlink($uri);
                    } else {
                        $file = \Drupal\file\Entity\File::load($fid);
                        $file->delete();
                        \Drupal::messenger()->addStatus(t("Template @t deleted", ['@t' => $value]));
                    }
                    if (($key = array_search($value, $this->tpls['invoice'])) !== false) {
                        unset($this->tpls['invoice'][$key]);
                    }
                }
            }
        }

        
        $filesystem = \Drupal::service('file_system');
        $dir = "private://sales/templates/purchase/";
        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file = $form_state->get('new_purchase');
        if ($file) {
            $this->tpls['purchase'][] = $file->getFileName();
            \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
            \Drupal::messenger()->addStatus(t("New @f uploaded", ['@f' => $file->getFileName()]));
        }

        $dir = "private://sales/templates/quotation/";
        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file = $form_state->get('new_quotation');
        if ($file) {
            $this->tpls['quotation'][] = $file->getFileName();
            \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
            \Drupal::messenger()->addStatus(t("New @f uploaded", ['@f' => $file->getFileName()]));
        }

        $dir = "private://sales/templates/invoice/";
        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file = $form_state->get('new_invoice');
        if ($file) {
            $this->tpls['invoice'][] = $file->getFileName();
            \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
            \Drupal::messenger()->addStatus(t("New @f uploaded", ['@f' => $file->getFileName()]));
        }
        
        // save template
        $this->settings->set('templates', $this->tpls);
        $this->settings->save();
    }

}
