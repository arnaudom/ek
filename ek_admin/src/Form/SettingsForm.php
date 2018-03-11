<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\SettingsForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\GlobalSettings;

/**
 * Provides an global settings form.
 */
class SettingsForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_global_settings_form';
    }
    
    /**
     * Stores the state storage service.
     *
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;
  
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
    public function __construct(ModuleHandler $module_handler, StateInterface $state) {
        $this->moduleHandler = $module_handler;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler'),
                $container->get('state')
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $coid = NULL) {

        $settings = new GlobalSettings($coid);


        $form['coid'] = array(
            '#type' => 'hidden',
            '#value' => $coid,
        );
        
        //verify if the connection to system DB is secured
        //when system database is remote (from drupal installation server) the connection should be done via ssl
        $ssl = db_query("SHOW STATUS LIKE 'Ssl_cipher'")->fetchAll();
        
        $form['secure'] = array(
            '#type' => 'item',
            '#markup' => NULL !=($ssl[0]->Value) ? "<div class='messages messages--status'>" . t('Info: ssl database connection active') . '</div>'
            : "<div class='messages messages--warning'>" . t('Info: no ssl database connection settings') . '</div>',
        );
        $form['cronkey'] = array(
            '#type' => 'item',
            '#markup' => t('Use key for cron') . ': <b>' . $this->state->get('system.cron_key') . '</b>', 
                );
        
        $form['installation_id'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#size' => 30,
            '#maxlength' => 50,
            '#default_value' => ($settings->get('installation_id') != '') ? $settings->get('installation_id') : 'ek_default_validation',
            '#attributes' => array('placeholder' => t('installation id')),
            '#description' => t('system installation id'),
        );
        $form['validation_url'] = array(
            '#type' => 'textfield',
            '#required' => TRUE,
            '#size' => 50,
            '#maxlength' => 150,
            '#default_value' => $settings->get('validation_url'),
            '#attributes' => array('placeholder' => t('validation url')),
            '#description' => t('validation url address for support and installation'),
        );

        $form['protocol'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array('https' => 'https', 'http' => 'http'),
            '#default_value' => $settings->get('protocol'),
            '#description' => t('Connection type'),
        );
        
        $form['backup_directory'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 200,
            '#default_value' => $settings->get('backup_directory'),
            '#description' => t('Backup full path to directory'),
        );
        
        $form['backup_filename'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 200,
            '#default_value' => $settings->get('backup_filename'),
            '#description' => t('Backup file name(s) separated by comma'),
        );
         
        $form['backup_recipients'] = array(
            '#type' => 'textfield',
            '#size' => 60,
             '#default_value' => $settings->get('backup_recipients'),
            '#description' => t('Backup recipients email addresses separated by comma'),
        );  
        
        $form['library'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#default_value' => $settings->get('library'),
            '#description' => t('Relative path to external libraries; i.e. "/libraries"'),
        );   
        
        $form['excel'] = array(
            '#type' => 'item',
            '#markup' => (class_exists('PHPExcel')) ? "<div class='messages messages--status'>" 
                            . 'Excel ' .  t('library installed') . '</div>'
            : "<div class='messages messages--warning'>" . 'Excel ' .  t('library not available') . '</div>',
        );
         $form['fpdf'] = array(
            '#type' => 'item',
            '#markup' => (class_exists('FPDF')) ? "<div class='messages messages--status'>" 
                            . 'FPdf ' .  t('library installed') . '</div>'
            : "<div class='messages messages--warning'>" . 'Pdf ' .  t('library not available') . '</div>',
        );   
         $form['tcpdf'] = array(
            '#type' => 'item',
            '#markup' => (class_exists('TCPDF')) ? "<div class='messages messages--status'>" 
                            . 'TcPdf ' .  t('library installed') . '</div>'
            : "<div class='messages messages--warning'>" . 'Pdf ' .  t('library not available') . '</div>',
        );           
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

        return $form;
    }

    
    /**
     * {@inheritdoc}
     */    
    public function validateForm(array &$form, FormStateInterface $form_state) {

        
        $addresses = explode(',', $form_state->getValue('backup_recipients'));
        foreach ($addresses as $email) {
            if ($email != NULL) {
              $email = trim($email);
              if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $form_state->setErrorByName('backup_recipients', $this->t('Invalid email format "@mail"' , ['@mail' => $email]));

              }
            }
        }
    }


    /**
     * {@inheritdoc}
     */    
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $settings = new GlobalSettings($form_state->getValue('coid'));
        $settings->set('installation_id', $form_state->getValue('installation_id'));
        $settings->set('validation_url', $form_state->getValue('validation_url'));
        $settings->set('password_expire', $form_state->getValue('password_expire'));
        $settings->set('backup_directory', rtrim($form_state->getValue('backup_directory'), "/"));
        $settings->set('backup_filename', $form_state->getValue('backup_filename'));
        $settings->set('backup_recipients', $form_state->getValue('backup_recipients'));
        $settings->set('password_expire_action', $form_state->getValue('password_expire_action'));
        $settings->set('protocol', $form_state->getValue('protocol'));
        $settings->set('library', $form_state->getValue('library'));
        $settings->save();
        
        \Drupal::messenger()->addStatus(t('Data updated'));
    }

}