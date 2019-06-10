<?php

/**
 * @file
 * Contains \Drupal\ek\Form\NewAccountForm
 */

namespace Drupal\ek_finance\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to create a new account in finance chart.
 */
class NewAccountForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_finance_new_chart';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $param = NULL) {

  $param = explode('-', $param);
  

    
    $form['coid'] = array(
          '#type' => 'hidden',
          '#value' => $param[0],

        ); 

    $form['for_class'] = array(
          '#type' => 'hidden',
          '#value' => str_replace('%', '', $param[1]),

        );     
    $form['class'] = array(
          '#type' => 'item',
          '#markup' => str_replace('%', '', $param[1]),
          '#prefix' => "<div class='container-inline'>",
        );    
      
    $form['aid'] = array(
          '#type' => 'textfield',
          '#size' => 8,
          '#maxlength' => 3,
          '#required' => TRUE,
          '#suffix' => '</div>',

            ); 

    $form['aname'] = array(
          '#type' => 'textfield',
          '#size' => 30,
          '#title' => t('account name'),
          '#maxlength' => 50,
          '#required' => TRUE,
            );
            
    $form['actions'] = array('#type' => 'actions');                
    $form['actions']['save'] = array(
            '#id' => 'buttonid',
            '#type' => 'submit',
            '#value' =>  t('Save') ,
            '#attributes' => array('class' => array('use-ajax-submit')),
      );

    
    if($form_state->get('message') <> '' ) {
    $form['message'] = array(
      '#markup' => "<div class='red'>" . $form_state->get('message') ."</div>",
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
  
  
  //filter errors
  if( strlen($form_state->getValue('aid')) < 3 || !is_numeric($form_state->getValue('aid')) ) {
   
   $form_state->set('message', t('Error: account value not valid') );
   $form_state->setRebuild();
  
    } else {
    
      $query = "SELECT id from {ek_accounts} WHERE coid=:c and aid=:a";
      $aid = $form_state->getValue('for_class').$form_state->getValue('aid');
      $a = array(':c'=> $form_state->getValue('coid'), ':a' => $aid);
      $id = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchObject();
      
        if($id > 0) {
            $form_state->set('message', t('Error: account already exist') );
            $form_state->setRebuild();
        }
    }
  
  
  if($form_state->get('message') == '' ) {
  
        $aid = $form_state->getValue('for_class').$form_state->getValue('aid');
        $aname = \Drupal\Component\Utility\Xss::filter($form_state->getValue('aname'));
        
        $fields = array(
          'aid' => $aid,
          'aname' => $aname,
          'atype' => 'detail',
          'astatus' => 1,
          'coid'=> $form_state->getValue('coid'),
          'balance' => 0,
          'balance_base' => 0,
          'balance_date' => date('Y-m-d')
          
        );            
        $insert = Database::getConnection('external_db', 'external_db')->insert('ek_accounts')->fields($fields)->execute();

    
  
   if($insert) {
   
   $form_state->set('message', t('Account created') . ': ' . $aid . ' ' . $aname . '. '  . t('Refresh list to view.') );
   $form_state->setRebuild();


   }
   
   }

  }


}
