<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\DocAccessEdit
 */

namespace Drupal\ek_admin\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form.
 */
class DocAccessEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_admin_doc_edit_access';
  }


/**
 * 
 * {@inheritdo}
 * 
 */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $type = NULL) {

  if($type == 'company_doc') {
    $query = "SELECT share,deny,coid FROM {ek_company_documents} WHERE id=:id";
    
  } 
  $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
  $users = db_query('SELECT uid,name FROM {users_field_data} WHERE uid<>0 order by name')->fetchAllKeyed();
    
    if($data->share == 0) {
    //no custom settings
    //default users are selected
    $default_users = AccessCheck::GetCompanyAccess($data->cid);
    $default_users = $default_users[$data->cid];
    
    } else {
      $default_users = explode(',',$data->share);
      $deny = explode(',', $data->deny);
    
    }

    $form['item'] = array(
      '#type' => 'item',
      '#markup' => '<span class="help">'. t('By default access is given to users who have access to the company unless custom access has been defined by owner. Use "Ctrl C" to select multiple users in the box below.') . '</span>',
    );
    
    $form['users'] = array(
      '#type' => 'select',
      '#options' => $users,
      '#multiple' => TRUE, 
      '#size' => 8,
      '#default_value' => $default_users,
      
      );

   
    $form['for_id'] = array(
          '#type' => 'hidden',
          '#default_value' =>$id,
    ); 

    $form['type'] = array(
          '#type' => 'hidden',
          '#default_value' =>$type,
    );
    
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['access'] = array(
            '#id' => 'accessbutton',
            '#type' => 'submit',
            '#value' =>  t('Save') ,
            '#attributes' => array('class' => array('use-ajax-submit')),

      );

    $form['#attached']['css'][] = array(
        'data' => drupal_get_path('module', 'ek_projects') . '/css/ek_admin.css',
        'type' => 'file',

    );

    if($form_state->get('message') <> '' ) {
    $form['message'] = array(
      '#markup' => "<div class='red'>" . t('Data') . ": " . $form_state->get('message') . "</div>",
    ); 
    $form_state->set('message', '');
    $form_state->setRebuild();
    }
 
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
  
  //set a security check in order to prevent any user to change data except the owner
  if($form_state->getValue('type') == 'company_doc') {
    $query = "SELECT share,deny,id FROM {ek_company_documents} WHERE id=:id";
  } 
    $data = Database::getConnection('external_db', 'external_db')
    ->query($query, array(':id' => $form_state->getValue('for_id')))->fetchObject();
    
    
    //owner can edit data
    $users = db_query('SELECT uid,name FROM {users_field_data} WHERE uid<>0');
    $share = explode(',',$data->share);
    $deny = explode(',', $data->deny);
    $new_share = array();
    $new_deny = array();

      while ($u = $users->fetchObject()) {
      
        if(in_array($u->uid, $form_state->getValue('users') ) ) {
            array_push($new_share,$u->uid);
        } else {
            array_push($new_deny,$u->uid);
        }
      
      }
      
      if(empty($new_deny)) $new_deny = '0';
    
    
   
  $fields = array(
    'share' => implode(',', $new_share),
    'deny' => implode(',', $new_deny),
  );       
  
  if($form_state->getValue('type') == 'company_doc') {     
  $update = Database::getConnection('external_db', 'external_db')
  ->update('ek_company_documents')->fields($fields)
  ->condition('id',$form_state->getValue('for_id') )->execute();

  } 

   
   
   if($update) {
   $form_state->set('message', t('saved') );
   $form_state->setRebuild();

   } else {
   $form_state->set('message', t('error') );
   $form_state->setRebuild();
   }

  }


}
