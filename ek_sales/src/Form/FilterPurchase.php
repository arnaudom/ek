<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\FilterPurchase.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter purchases.
 */
class FilterPurchase extends FormBase {

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
    return 'purchase_filter';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  
  $to = Database::getConnection('external_db', 'external_db')
          ->query("SELECT date from {ek_sales_purchase} order by date DESC limit 1")
          ->fetchObject();
  $from = Database::getConnection('external_db', 'external_db')
          ->query("SELECT date from {ek_sales_purchase} WHERE status <> 1 order by date limit 1")
          ->fetchObject();
  //$date1= date('Y-m-d', strtotime($data2." -30 days")) ;
  $s = array(0 => t('Not paid'), 1 => t('Paid'), 3 => t('Any'));
  $filter_title = t('Filter');
  if(isset($_SESSION['pfilter']['status'])) {
      $filter_title .= ' (' . $s[$_SESSION['pfilter']['status']] . ')'; 
  }
  
    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $filter_title,
      '#open' => (isset($_SESSION['pfilter']['filter'])) ? FALSE : TRUE,
      //'#attributes' => array('class' => array('container-inline')),
    );  
            $form['filters']['filter'] = array(
              '#type' => 'hidden',
              '#value' => 'filter',
              
            );
            
            $form['filters']['keyword'] = array(
              '#type' => 'textfield',
              '#maxlength' => 75,
              '#size' => 30,
              '#attributes' => array('placeholder'=>t('Search with keyword, ref No.')),
              '#default_value' => isset($_SESSION['pfilter']['keyword']) ? $_SESSION['pfilter']['keyword'] : NULL,
            ); 
            
            $form['filters']['coid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => AccessCheck::CompanyListByUid(),
              '#default_value' => isset($_SESSION['pfilter']['coid']) ? $_SESSION['pfilter']['coid'] : 0,
              '#prefix' => "<div>",
              '#suffix' => '</div>',
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ),    
            ); 
            
            $form['filters']['from'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['pfilter']['from']) ? $_SESSION['pfilter']['from'] : $from->date,
              '#prefix' => "<div class='container-inline'>",
              '#title' => t('from'),
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ), 
            ); 

            $form['filters']['to'] = array(
              '#type' => 'date',
              '#size' => 12,
              '#default_value' => isset($_SESSION['pfilter']['to']) ? $_SESSION['pfilter']['to'] : $to->date,
              '#suffix' => '</div>',
              '#title' => t('to'),
              '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ), 
            ); 
            
              
  if ($this->moduleHandler->moduleExists('ek_address_book')) {
  
  $supplier = \Drupal\ek_address_book\AddressBookData::addresslist(2);

        if(!empty($supplier) ) {
            $supplier = array('%' => t('Any')) + $supplier;
              $form['filters']['supplier'] = array(
                  '#type' => 'select',
                  '#size' => 1,
                  '#options' => $supplier,
                  '#required' => TRUE,
                  '#default_value' => isset($_SESSION['pfilter']['client']) ? $_SESSION['pfilter']['client'] : '%',
                  '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                  '#title' => t('supplier'),
                  '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => TRUE),
                ),
              ), 
                );
        } else {
              $link =  Url::fromRoute('ek_address_book.new', array())->toString();
              
              $form['filters']['supplier'] = array(
                  '#markup' => t("You do not have any <a title='create' href='@cl'>supplier</a> in your record.", ['@cl' => $link]),
                  '#prefix' => "<div class='messages messages--warning'>",
                  '#suffix' => '</div>',
                );             
              
        }

  } else {

              $form['filters']['supplier'] = array(
                  '#markup' => t('You do not have any supplier list.'),
                  '#default_value' => 0,
                  
                );

  }           
  
    $form['filters']['status'] = array(
      '#type' => 'select',
      '#options' => $s,
      '#default_value' => isset($_SESSION['pfilter']['status']) ? $_SESSION['pfilter']['status'] : '0' ,
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

    if (!empty($_SESSION['pfilter'])) {
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
    if (!$form_state->getValue('supplier'))  {
      $form_state->setErrorByName('supplier', $this->t('You must select a supplier.'));
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $_SESSION['pfilter']['keyword'] = Xss::filter($form_state->getValue('keyword'));
    $_SESSION['pfilter']['coid'] = $form_state->getValue('coid');  
    $_SESSION['pfilter']['from'] = $form_state->getValue('from');
    $_SESSION['pfilter']['to'] = $form_state->getValue('to');
    $_SESSION['pfilter']['status'] = $form_state->getValue('status');
    $_SESSION['pfilter']['client'] = $form_state->getValue('supplier');
    $_SESSION['pfilter']['filter'] = 1;

  }
  
  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['pfilter'] = array();
  }
  
}