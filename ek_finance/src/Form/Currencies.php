<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\Currencies.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a currencies management form.
 */
class Currencies extends FormBase {

  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_manage_currencies';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form,  FormStateInterface $form_state, $id = NULL) {
    
    $param = serialize(['id' => 'currency']);
    $url = Url::fromRoute('ek_finance_modal',array('param' => $param));
    
    $form['add'] = array(
                '#type' => 'link', 
                '#title' => $this->t('New currency'),
                '#attributes' => ['class' => ['button','use-ajax']],
                '#url' => $url,     
           );
    
    $settings = new FinanceSettings(); 
    $baseCurrency = $settings->get('baseCurrency'); 

    $query = "SELECT * from {ek_currency} order by currency";
    $data = Database::getConnection('external_db', 'external_db')->query($query);

          $form['1'] = array(
                '#type' => 'details', 
                '#title' => t('Active'), 
                '#collapsible' => TRUE, 
                '#open' => TRUE,
                '#prefix' => "<div class='table'>",
            
           );
 
          $form['0'] = array(
                '#type' => 'details', 
                '#title' => t('Non Active'), 
                '#collapsible' => TRUE, 
                '#open' => FALSE,
                '#prefix' => "<div class='table'>",
            
           );  

    while($r = $data->fetchObject()) {
    
    $id = $r->id;
    if($baseCurrency == $r->currency) {
    $value = 1;
    $disabled = TRUE;
    $description = t('selected base currency');
    } else {
    $value = $r->rate;
    $disabled = FALSE;
    $description ='';
    }    
      if ($r->active == 1) { 
      
          $form['1']['id'.$id] = array(
                '#type' => 'hidden', 
                '#value' => $r->id , 
            
           );           

          $form['1']['currency'.$id] = array(
              '#type' => 'item',
              '#markup' => $r->currency,
              '#prefix' => "<div class='row'><div class='cell100'>",
              '#suffix' => " </div>",

          );   

      
          $form['1']['name'.$id] = array(
                '#type' => 'item',
                '#markup' => $r->name,
                '#prefix' => "<div class='cell150'>",
                '#suffix' => "</div>",            
           );

          $form['1']['fx'.$id] = array(
              '#type' => 'textfield',
              '#size' => 15,
              '#disabled' => $disabled,
              '#maxlength' => 30,
              '#default_value' => $r->rate,
              '#attributes' => array('placeholder'=>t('rate')),
              '#description' => $description,
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div>",

          );
                   
          $form['1']['active'.$id] = array(
              '#type' => 'checkbox',
              '#default_value' => 1,
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div></div>",
              '#title' => t('active'),

          );              
      
      } else {
      
          $form['0']['id'.$id] = array(
                '#type' => 'hidden', 
                '#value' => $r->id , 
            
           );           

          $form['0']['currency'.$id] = array(
              '#type' => 'item',
              '#markup' => $r->currency,
              '#prefix' => "<div class='row'><div class='cell100'>",
              '#suffix' => " </div>",

          );       
          $form['0']['name'.$id] = array(
                '#type' => 'item',
                '#markup' => $r->name,
                '#prefix' => "<div class='cell150'>",
                '#suffix' => "</div>",            
           );
  

          $form['0']['fx'.$id] = array(
              '#type' => 'textfield',
              '#size' => 15,
              '#disabled' => $disabled,
              '#maxlength' => 30,
              '#default_value' => $r->rate,
              '#attributes' => array('placeholder'=>t('rate')),
              '#description' => $description,
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div>",

          );
                   
          $form['0']['active'.$id] = array(
              '#type' => 'checkbox',
              '#default_value' => 0,
              '#prefix' => "<div class='cell'>",
              '#suffix' => "</div></div>",
              '#title' => t('select to activate'), 
          );     
      }
    
    }
    
          $form['1']['close'] = array(
                '#type' => 'item', 
                '#markup' =>  '' , 
                '#suffix' => "</div>",            
           );
           
          $form['0']['close'] = array(
                '#type' => 'item', 
                '#markup' =>  '' , 
                '#suffix' => "</div>",            
           );
    
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

    $form['#attached']['library'][] = 'ek_finance/ek_finance.dialog';
    
        return $form;    
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function validateForm(array &$form,  FormStateInterface $form_state) {
  
  
  }
  
  /**
   * {@inheritdoc}
   */  
  public function submitForm(array &$form,  FormStateInterface $form_state) {
  
    $query = "SELECT * from {ek_currency}";
    $data = Database::getConnection('external_db', 'external_db')->query($query);

    while($r = $data->fetchAssoc()) {
   
    $fx = 'fx'.$r['id'];
    if(is_numeric($form_state->getValue($fx))) {
    $rate = round($form_state->getValue($fx), 4); 
    } else {
    $rate = 0;
    }
    $status = 'active'.$r['id'];
    
      $update = Database::getConnection('external_db', 'external_db')->update('ek_currency')
               ->condition('id', $r['id'])
               ->fields(array('rate' => $rate , 'active' => $form_state->getValue($status)) )
               ->execute(); 
    

    }
    
    \Drupal::messenger()->addStatus(t('Currency data updated'));
          
 
  }
  
  
}