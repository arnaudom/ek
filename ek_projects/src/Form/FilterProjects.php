<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\FilterProjects.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;
/**
 * Provides a form to filter Projects.
 */
class FilterProjects extends FormBase {


    /**
     * {@inheritdoc}
     */
  public function getFormId() {
    return 'ek_projects_filter';
  }


    /**
     * {@inheritdoc}
     */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
   
  $access = AccessCheck::GetCountryByUser();
  $cid = implode(',',$access);
  $query = "SELECT id,name from {ek_country} where status=:t AND FIND_IN_SET (id, :c ) order by name";
  $country = Database::getConnection('external_db', 'external_db')->query($query, array(':t' => 1, ':c' => $cid))->fetchAllKeyed();
  $country_list = array( '0' => t('Any'));
  $country_list += $country;

     
            $form['filters']['filter'] = array(
              '#type' => 'hidden',
              '#value' => 'filter',
            );            

            $form['filters'][0]['keyword'] = array(
              '#type' => 'textfield',
              '#maxlength' => 150,
              '#attributes' => array('placeholder'=>t('Search with keyword, ref No.')),
              '#default_value' => isset($_SESSION['pjfilter']['keyword']) ? $_SESSION['pjfilter']['keyword'] : NULL,
            ); 
            
                        
            $form['filters'][1]['cid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => $country_list,
              '#default_value' => isset($_SESSION['pjfilter']['cid']) ? $_SESSION['pjfilter']['cid'] : 0,
              '#title' => t('country'),
              '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
              '#suffix' => '</div>',
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),           
            );               

              
              

  $client = array('%' => t('Any'));
  $query = "SELECT DISTINCT b.id,name from {ek_address_book} b "
          . "INNER JOIN {ek_project} p "
          . "ON b.id=p.client_id order by b.name";
  $client += Database::getConnection('external_db', 'external_db')
          ->query($query)->fetchAllKeyed();


            $form['filters'][3]['client'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $client,
                '#default_value' => isset($_SESSION['pjfilter']['client']) ? $_SESSION['pjfilter']['client'] :'%',
                '#title' => t('client'),
                '#attributes' => array('style' => array('width:200px;')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div>',
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                 
              );

  $suppliers = array('%' => t('Any'));
  $query = "SELECT supplieroffer from {ek_project_description} "
          . "WHERE supplieroffer <> :s";
  $data = Database::getConnection('external_db', 'external_db')
          ->query($query, [':s' => '']);
  $l = '';
  While($s = $data->fetchObject()) {
      $l .= $s->supplieroffer . ',';
  }
  $list  = array_unique(explode(',', $l));
  foreach($list as $key => $value) {
      if($value){
      $suppliers += [$value => \Drupal\ek_address_book\AddressBookData::getname($value)];
      }
  }
  
              $form['filters'][3]['supplier'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $suppliers,
                '#default_value' => isset($_SESSION['pjfilter']['supplier']) ? $_SESSION['pjfilter']['supplier'] :'%',
                '#title' => t('supplier'),
                '#attributes' => array('style' => array('width:200px;')),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div>',
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                 
              );
  
  
  
  
  $type = array('%' => t('Any'));
  $type += Database::getConnection('external_db', 'external_db')
          ->query("SELECT id,type from {ek_project_type} order by type" )
          ->fetchAllKeyed();


            $form['filters'][3]['type'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $type,
                '#default_value' => isset($_SESSION['pjfilter']['type']) ? $_SESSION['pjfilter']['type'] : '%',
                '#title' => t('category'),
                '#prefix' => "<div class='row'><div class='cell'>",
                '#suffix' => '</div>', 
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                                 
              );      
  

            $form['filters'][3]['status'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array('%' => t('Any'), 'open' => t('open'), 'awarded' => t('awarded'), 'completed' => t('completed'), 'closed' => t('closed')),
                '#default_value' => isset($_SESSION['pjfilter']['status']) ? $_SESSION['pjfilter']['status'] : '%',
                '#title' => t('status'),
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div></div>', 
                '#states' => array(
                  'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                  ),
                ),                                 
              ); 

          
            
            
    $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      //'#suffix' => "</div>",
    );

    if (!empty($_SESSION['pjfilter'])) {
      $form['filters']['actions']['reset'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => array(),
        '#submit' => array(array($this, 'resetForm')),
      ); 
    } 
  return $form;
 
  
  }

  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
    if($form_state->getValue('keyword') == '') {
    //@TODO check input if filter not by keyword
    
    
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  $_SESSION['pjfilter']['cid'] = $form_state->getValue('cid');
  $_SESSION['pjfilter']['status'] = $form_state->getValue('status');
  $_SESSION['pjfilter']['type'] = $form_state->getValue('type');
  $_SESSION['pjfilter']['client'] = $form_state->getValue('client');
  $_SESSION['pjfilter']['supplier'] = $form_state->getValue('supplier');
  $_SESSION['pjfilter']['keyword'] = $form_state->getValue('keyword');
  $_SESSION['pjfilter']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['pjfilter'] = array();
  }
  
}