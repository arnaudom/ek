<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditRank.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to view and upload ranks file
 */
class EditRank extends FormBase {

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
    return 'hr_rank_edit';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

  
  if ( $form_state->get('step') == '' ) {
    $form_state->set('step', 1);  
  } 
  
  
  $company = AccessCheck::CompanyListByUid();
  $form['coid'] = array(
    '#type' => 'select',
    '#size' => 1,
    '#options' => $company,
    '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
    '#title' => t('company'),
    '#disabled' => ($form_state->getValue('coid')) ? TRUE : FALSE,
    '#required' => TRUE, 
    
    ); 

  if (($form_state->getValue('coid')) == '' ) {
  $form['next'] = array(
    '#type' => 'submit',
    '#value' => t('Next'). ' >>',
    '#states' => array(
        // Hide data fieldset when class is empty.
        'invisible' => array(
           "select[name='coid']" => array('value' => ''),
        ),
      ),    
  );
  } 
 
  if ( $form_state->get('step') == 2 ) {
   
  $form_state->set('step', 3); 

    $dir = "private://hr/data/" . $form_state->getValue('coid')  ."/ranks/ranks.txt";
    if(file_exists($dir)) {
        $ranks = file_get_contents($dir);
        //$ranks = str_replace("\r\n","<br/>",$ranks);

        $form['file'] = array(
        '#type' => 'details',
        '#title' => t('Current file'),
          '#collapsible' => TRUE, 
          '#open' => TRUE,    
        );    
        $form['file']['rank'] = array(
            '#type' => 'textarea',
            '#default_value' => $ranks,
            '#rows' => 10,
            
        );
            
    } else {
       $form['info1'] = array(
            '#type' => 'item',
            '#markup' => t('You do not have any rank definition yet. You can create one directly by typing your structure or alternatively upload a text file.') 
        ); 
       $form['info2'] = array(
            '#type' => 'item',
            '#markup' => t('1) indicate ranks titles by preceeding the name with character "@" and terminated with comma "," 2) indicate rank within a title separated by comma.') 
        ); 
       
       $sample = "@ADMINISTRATION,"
               . "\r\n A1 General manager,"
               . "\r\n A2 Executive,"
               . "\r\n A3 Clerk,"
               . "\r\n@SALES, "
               . "\r\n S1 Manager,"
               . "\r\n@LOGISTICS,"
               . "\r\n L1 Manager, "
               . "\r\n L2 Assistant,"
               . "\r\n L3 Clerk,"
               . "\r\n@";
       
       $form['rank'] = array(
            '#type' => 'textarea',
            '#default_value' => $sample,
            '#rows' => 10,
            
        );
       
    }
    
    $form['info3'] = array(
      '#type' => 'item',
      '#markup' => t('You can also upload any text file (with .txt extension) with your structure.') 
    
    );

    $form['upload'] = array(
      '#type' => 'file',
      '#description' => t('Upload a new file'),
    );    

    $form['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#suffix' => ''
    );  
    
    
    $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';



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

        $extensions = 'txt';
        $validators = array( 'file_validate_extensions' => array($extensions));
        $field = "upload";
        //$form_state->setValue('image', '');
            
        $file = file_save_upload($field , $validators, FALSE, 0, FILE_EXISTS_REPLACE);  
        
        if($file) {
            $form_state->set('new_upload', $file);
            //$form_state->setRebuild();
        
        }
  
  } 
  

  }

  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
 
  
  if ($form_state->get('step') == 3) {

    if($form_state->get('new_upload')) {

            $dir = "private://hr/data/" . $form_state->getValue('coid') . '/ranks' ;
            \Drupal::service('file_system')->prepareDirectory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            $dest = $dir . '/ranks.txt';
            $filename = \Drupal::service('file_system')->copy($form_state->get('new_upload')->getFileUri(), $dest , FILE_EXISTS_REPLACE);
            \Drupal::messenger()->addStatus(t("New file uploaded"));

    } elseif($form_state->getValue('rank')) {        
        //write the data to the file
            $dir = "private://hr/data/" . $form_state->getValue('coid')  ."/ranks";
            if(!file_exists()) {
                \Drupal::service('file_system')->prepareDirectory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            }
            $file = $dir . '/ranks.txt';
            $fp = fopen($file, 'w');
            $text = \Drupal\Component\Utility\Xss::filter($form_state->getValue('rank'));
            $written = fwrite($fp, $text);
            fclose($fp);
            \Drupal::messenger()->addStatus(t("New file created"));
    } else {
        \Drupal::messenger()->addWarning(t("No file uploaded"));
    
    }
  
  
   
  }//step 3

  
  }
  

  
  
}