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
        $form['P'] = array(
            '#type' => 'details',
            '#title' => $this->t('Purchase forms'),
            '#collapsible' => true,
            '#open' => false,
        );

        $form['P']['new_purchase'] = array(
            '#type' => 'file',
            '#description' => $this->t('Upload a new purchase template. Only files with a ".inc" extension is allowed.'),
        );

        $i = 0;
        if (!empty($this->tpls['purchase'])) {
            foreach ($this->tpls['purchase'] as $key => $name) {
                $form['P']['template' . $i] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#return_value' => $name,
                    '#attributes' => array('title' => $this->t('delete')),
                    '#title' => $this->t('Delete purchase template <b>"@n"</b>', array('@n' => $name)),
                );
                $i++;
            }
        }


        $form['Q'] = array(
            '#type' => 'details',
            '#title' => $this->t('Quotations forms'),
            '#collapsible' => true,
            '#open' => false,
        );

        $form['Q']['new_quotation'] = array(
            '#type' => 'file',
            '#description' => $this->t('Upload a new quotation template. Only files with a ".inc" extension is allowed.'),
        );
        
        if (!empty($this->tpls['quotation'])) {
            foreach ($this->tpls['quotation'] as $key => $name) {
                $form['Q']['template' . $i] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#return_value' => $name,
                    '#attributes' => array('title' => $this->t('delete')),
                    '#title' => $this->t('Delete quotation template <b>"@n"</b>', array('@n' => $name)),
                );
                $i++;
            }
        }

        $form['I'] = array(
            '#type' => 'details',
            '#title' => $this->t('Invoice forms'),
            '#collapsible' => true,
            '#open' => false,
        );

        $form['I']['new_invoice'] = array(
            '#type' => 'file',
            '#description' => $this->t('Upload a new invoice template. Only files with a ".inc" extension is allowed.'),
        );
        
        if (!empty($this->tpls['invoice'])) {
            foreach ($this->tpls['invoice'] as $key => $name) {
                $form['I']['template' . $i] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#return_value' => $name,
                    '#attributes' => array('title' => $this->t('delete')),
                    '#title' => $this->t('Delete invoice template <b>"@n"</b>', array('@n' => $name)),
                );
                $i++;
            }
        }
        
        $form['#tree'] = true;

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        );


        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';

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
        
        // if checkbox is selected, value return = file name
        // file is deleted and remove from settings
        foreach ($form_state->getValue('P') as $key => $value) {
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

        foreach ($form_state->getValue('Q') as $key => $value) {
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

        foreach ($form_state->getValue('I') as $key => $value) {
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

        $filesystem = \Drupal::service('file_system');
        $extensions = 'inc';
        $validators = array('file_validate_extensions' => array($extensions));


        $dir = "private://sales/templates/purchase/";
        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $file = file_save_upload("P", $validators, $dir, 0, FileSystemInterface::EXISTS_RENAME);
        if ($file) {
            $file->setPermanent();
            $file->save();
            $this->tpls['purchase'][] = $file->getFileName();
            \Drupal::messenger()->addStatus(t("New purchase file uploaded"));
        }

        $dir = "private://sales/templates/quotation/";
        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $file = file_save_upload("Q", $validators, $dir, 0, FileSystemInterface::EXISTS_RENAME);
        if ($file) {
            $file->setPermanent();
            $file->save();
            $this->tpls['quotation'][] = $file->getFileName();
            \Drupal::messenger()->addStatus(t("New quotation file uploaded"));
        }

        $dir = "private://sales/templates/invoice/";
        $filesystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $file = file_save_upload("I", $validators, $dir, 0, FileSystemInterface::EXISTS_RENAME);
        if ($file) {
            $file->setPermanent();
            $file->save();
            $this->tpls['invoice'][] = $file->getFileName();
            \Drupal::messenger()->addStatus(t("New invoice file uploaded"));
        }
        
        // save template
        $this->settings->set('templates', $this->tpls);
        $this->settings->save();
    }

}
