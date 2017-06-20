<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\FilterCompany.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a standard form to filter companies by id.
 */
class FilterCompany extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'company_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  

    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => isset($_SESSION['coidfilter']['filter'] ) ? FALSE : TRUE,

    ); 
           
            $form['filters']['coid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => AccessCheck::CompanyListByUid(),
              '#default_value' => isset($_SESSION['coidfilter']['coid']) ? $_SESSION['coidfilter']['coid'] : 0,
              '#prefix' => "<div>",
              '#suffix' => '</div>',
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
  
  $_SESSION['coidfilter']['coid'] = $form_state->getValue('coid');
  $_SESSION['coidfilter']['filter'] = 1;

  }

  
}