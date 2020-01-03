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
            
        $form['cronkey'] = array(
            '#type' => 'item',
            '#markup' => t('Use key for cron') . ': <b>' . $this->state->get('system.cron_key') . '</b>', 
                );
        
        $master = $this->currentUser()->hasPermission('administer site configuration');
        
        if($master){
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
        }
        
        $form['protocol'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array('https' => 'https', 'http' => 'http'),
            '#default_value' => $settings->get('protocol'),
            '#description' => t('Connection type'),
        );
         
        $form['backup_recipients'] = array(
            '#type' => 'textfield',
            '#size' => 60,
             '#default_value' => $settings->get('backup_recipients'),
            '#description' => t('Backup recipients email addresses separated by comma'),
        );  
        
        
        $form['cron'] = array(
            '#type' => 'details',
            '#title' => t('Cron tasks'),
            '#open' => TRUE,
        );
        
        $form['cron']['sale_tasks'] = array(
                '#type' => 'checkbox',
                '#title' => t('Sales tasks'),
                '#default_value' => $settings->get('sale_tasks'),
            );
        
        $form['cron']['sale_status'] = array(
                '#type' => 'checkbox',
                '#title' => t('Sales status'),
                '#default_value' => $settings->get('sale_status'),
            );
        
        $form['cron']['purchase_tasks'] = array(
                '#type' => 'checkbox',
                '#title' => t('Purchases tasks'),
                '#default_value' => $settings->get('purchase_tasks'),
            );        
        
        $form['cron']['project_tasks'] = array(
                '#type' => 'checkbox',
                '#title' => t('Project tasks'),
                '#default_value' => $settings->get('project_tasks'),
            );
        
        $form['cron']['project_status'] = array(
                '#type' => 'checkbox',
                '#title' => t('Projects status'),
                '#default_value' => $settings->get('project_status'),
            );             
        
        $form['cron']['hr_tasks'] = array(
                '#type' => 'checkbox',
                '#title' => t('HR tasks'),
                '#default_value' => $settings->get('hr_tasks'),
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
        $master = $this->currentUser()->hasPermission('administer site configuration');
        if($master){
            $settings->set('installation_id', $form_state->getValue('installation_id'));
            $settings->set('validation_url', $form_state->getValue('validation_url'));
            $settings->set('backup_directory', rtrim($form_state->getValue('backup_directory'), "/"));
            $settings->set('backup_filename', $form_state->getValue('backup_filename'));
        }
        
        $settings->set('backup_recipients', $form_state->getValue('backup_recipients'));
        $settings->set('protocol', $form_state->getValue('protocol'));
        $settings->set('sale_tasks', $form_state->getValue('sale_tasks'));
        $settings->set('purchase_tasks', $form_state->getValue('purchase_tasks'));
        $settings->set('project_tasks', $form_state->getValue('project_tasks'));
        $settings->set('hr_tasks', $form_state->getValue('hr_tasks'));
        $settings->set('sale_status', $form_state->getValue('sale_status'));
        $settings->set('project_status', $form_state->getValue('project_status'));
        
        $settings->save();
        
        \Drupal::messenger()->addStatus(t('Data updated'));
    }

}