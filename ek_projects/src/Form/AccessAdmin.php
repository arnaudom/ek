<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\AccessAdmin.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

use Drupal\ek_admin\Access\AccessCheck;
/**
 * Provides a form to filter Projects by user for administration control.
 */
class AccessAdmin extends FormBase {


    /**
     * {@inheritdoc}
     */
  public function getFormId() {
    return 'ek_projects_access_admin';
  }


    /**
     * {@inheritdoc}
     */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
   
  $access = AccessCheck::GetCountryByUser();
  $cid = implode(',',$access);
  $query = "SELECT id,name from {ek_country} where status=:t AND FIND_IN_SET (id, :c ) order by name";
  $country = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':t' => 1, ':c' => $cid))
          ->fetchAllKeyed();
  $country_list = array( '0' => t('Any'));
  $country_list += $country;

     
        

            $form['filters']['username'] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#required' => TRUE,
                '#default_value' => isset($_SESSION['paccessadmin']['username']) ? $_SESSION['paccessadmin']['username']  : NULL,
                '#attributes' => array('placeholder' => t('Enter user name')),
                '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
            );            
                        
            $form['filters'][1]['cid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => $country_list,
              '#default_value' => isset($_SESSION['paccessadmin']['cid']) ? $_SESSION['paccessadmin']['cid'] : 0,
              '#title' => t('country'),
              '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
              '#suffix' => '</div>',
         
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
                '#default_value' => isset($_SESSION['paccessadmin']['client']) ? $_SESSION['paccessadmin']['client'] :'%',
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
                '#default_value' => isset($_SESSION['paccessadmin']['supplier']) ? $_SESSION['paccessadmin']['supplier'] :'%',
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
                '#default_value' => isset($_SESSION['paccessadmin']['type']) ? $_SESSION['paccessadmin']['type'] : '%',
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
                '#default_value' => isset($_SESSION['paccessadmin']['status']) ? $_SESSION['paccessadmin']['status'] : '%',
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

    if (!empty($_SESSION['paccessadmin'])) {
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
    
    $query = "SELECT uid FROM {users_field_data} WHERE name = :n";
    $data = db_query($query, [':n' => $form_state->getValue('username')])
            ->fetchField();
    if ($data) {
        $form_state->setValue('uid', $data);

    } else {
        $form_state->setErrorByName('username', $this->t('Unknown user'));
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  
  $_SESSION['paccessadmin']['cid'] = $form_state->getValue('cid');
  $_SESSION['paccessadmin']['status'] = $form_state->getValue('status');
  $_SESSION['paccessadmin']['type'] = $form_state->getValue('type');
  $_SESSION['paccessadmin']['client'] = $form_state->getValue('client');
  $_SESSION['paccessadmin']['supplier'] = $form_state->getValue('supplier');
  $_SESSION['paccessadmin']['username'] = $form_state->getValue('username');
  $_SESSION['paccessadmin']['uid'] = $form_state->getValue('uid');
  $_SESSION['paccessadmin']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['paccessadmin'] = array();
  }
  
}