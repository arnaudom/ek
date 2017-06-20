<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SettingsUsers
 */

namespace Drupal\ek_projects\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to record access control to sections (1to5)
 */
class SettingsUsers extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_projects_edit_access_section';
  }



  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

  $users = db_query('SELECT uid,name,status FROM {users_field_data} WHERE uid>:u order by name', array(':u' => 0));
  
  $headerline = "<div class='table'  id='users_items'>
                  <div class='row'>
                      <div class='cell cellborder' id='tour-item1'>" . t("Login") . "</div>
                      <div class='cell cellborder' id='tour-item2'>" . t("Section 1") . "</div>
                      <div class='cell cellborder' id='tour-item3'>" . t("Section 2") . "</div>
                      <div class='cell cellborder' id='tour-item4'>" . t("Section 3") . "</div>
                      <div class='cell cellborder' id='tour-item5'>" . t("Section 4") . "</div>
                      <div class='cell cellborder' id='tour-item6'>" . t("Section 5") . "</div>
                   ";
  
  $form['t']["headerline"] = array(
      '#type' => 'item',
      '#markup' => $headerline,       
    ); 
    
  while ($u = $users->fetchObject()) {
  
  $status = ($u->status == '0') ? ' (' .t('Blocked'). ')' : '';
  $form['t']["user-".$u->uid] = array(
      '#type' => 'item',
      '#markup' => $u->name . $status , 
      '#prefix' => "<div class='row'><div class='cell'>",
      '#suffix' => '</div>',
     
    );   

  $access = ProjectData::validate_section_access($u->uid);

  $form['t']["section_1-".$u->uid] = array(
      '#type' => 'checkbox',
      '#default_value' => in_array(1,$access) ? 1:0, 
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div>',
     
    );   
  
  $form['t']["section_2-".$u->uid] = array(
      '#type' => 'checkbox',
      '#default_value' => in_array(2,$access) ? 1:0, 
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div>',
     
    );  

  $form['t']["section_3-".$u->uid] = array(
      '#type' => 'checkbox',
      '#default_value' => in_array(3,$access) ? 1:0, 
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div>',
     
    );  
 
  $form['t']["section_4-".$u->uid] = array(
      '#type' => 'checkbox',
      '#default_value' => in_array(4,$access) ? 1:0, 
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div>',
     
    ); 

  $form['t']["section_5-".$u->uid] = array(
      '#type' => 'checkbox',
      '#default_value' => in_array(5,$access) ? 1:0, 
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div></div>',
     
    ); 
      
  }
  
  $form['#tree'] = TRUE;

       
    $form['access'] = array(
            '#id' => 'accessbutton',
            '#type' => 'submit',
            '#value' =>  t('Save') ,
      );

    $form['#attached']['library'][] = 'ek_projects/ek_projects_css';


 
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

    foreach($form_state->getValue('t') as $key => $value) {

      $str = strstr($key, 'section');

      if($str) {

        $key = explode('-', $key);

        $query ='SELECT uid from {ek_project_users} WHERE uid=:u';
        $uid = Database::getConnection('external_db', 'external_db')->query($query, array(':u' => $key[1] ))->fetchField();

        if(!$uid && $key[1] > 0) { 
          Database::getConnection('external_db', 'external_db')->insert('ek_project_users')->fields(array('uid' => $key[1]))->execute();
        }

        $update = Database::getConnection('external_db', 'external_db')->update('ek_project_users')->fields(array($key[0] => $value))->condition('uid', $key[1])->execute();

      }


    }


  }


}
