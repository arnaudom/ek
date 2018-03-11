<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\BankForm.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Url;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\BankData;


/**
 * Provides a form to create and edit bank reference.
 */
class BankForm extends FormBase {

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  public function __construct(CountryManagerInterface $country_manager) {
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ek_finance_bank';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
  
    if($id != NULL || $form_state->get('bid') != NULL ) {
    
      if($id == NULL) $id = $form_state->getValue('bid');
      $form_state->set('step', 2);
      
      if($id > 0) {
      $query = "SELECT * FROM {ek_bank} WHERE id=:id";
      $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
      }
      
      $form['bid'] = array(
        '#type' => 'hidden',
        '#value' => $id,
      );
      
    
    } elseif($form_state->get('step') == '') {
    $form_state->set('step', 1);
    $options = BankData::listBank();
    $options['0'] = t('create a new bank');
 
     $form['bid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $options,
      '#title' => t('Bank'),
      '#required' => TRUE, 
      '#prefix' => "<div class='container-inline'>",    
    ); 

     $form['next'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#limit_validation_errors' => array(array('bid')),
      '#submit' =>  array(array($this, 'get_accounts')) ,
      '#states' => array(
        'invisible' => array(
           "select[name='bid']" => array('value' => ''),
        ),
      ),
      '#suffix' => '</div>',
    );
    }
    
    if ($form_state->get('step') == 2  ) {

    $back = Url::fromRoute('ek_finance.manage.bank_manage', array(), array())->toString() ;
    $table = Url::fromRoute('ek_finance.manage.bank_list', array(), array())->toString() ;
      
    $form_state->set('step', 3);
    
      $form["back"] = array(
      '#markup' => "<a href='" . $back . "' >" . t('select another bank') . "</a> " . t('or') . '  ',
      '#prefix' => '<div class="container-inline">',
      );
      $form["list"] = array(
      '#markup' => "<a href='" . $table . "' >" . t('view list') . "</a>",
      '#suffix' => '</div>'
      );    

      $form["name"] = array(
      '#type' => 'textfield',
      '#size' => 40,
      '#default_value' => $data->name,
      '#maxlength' => 100,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('bank name') ),
      );

      $form["address1"] = array(
      '#type' => 'textfield',
      '#size' => 40,
      '#default_value' => $data->address1,
      '#maxlength' => 255,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('address line 1') ),
      ); 

      $form["address2"] = array(
      '#type' => 'textfield',
      '#size' => 40,
      '#default_value' => $data->address2,
      '#maxlength' => 255,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('address line 2') ),
      );    

      $form["postcode"] = array(
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $data->postcode,
      '#maxlength' => 30,
      '#description' => '',
      '#attributes' => array('placeholder'=>t('postcode') ),
      );     
    
    
$countries = $this->countryManager->getList();             
      $form['country'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => array_combine($countries, $countries),
      '#required' => TRUE,    
      '#default_value' => isset($data->country) ? $data->country: NULL,
          
        );
    
      $form["contact"] = array(
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $data->contact,
      '#maxlength' => 30,
      '#description' => t('contact name'),
      '#attributes' => array('placeholder'=>t('contact') ),
      );     
    
      $form['telephone'] = array(
      '#type' => 'textfield',
      '#size' => 25,
      '#maxlength' => 20,
      '#default_value' => isset($data->telephone) ? $data->telephone :null,
      '#attributes' => array('placeholder'=>t('telephone')),
      );

      $form['fax'] = array(
      '#type' => 'textfield',
      '#size' => 25,
      '#maxlength' => 20,
      '#default_value' => isset($data->fax) ? $data->fax :null,
      '#attributes' => array('placeholder'=>t('fax')),
      );
    
      $form['email'] = array(
      '#type' => 'textfield',
      '#size' => 25,
      '#maxlength' => 160,
      '#default_value' => isset($data->email) ? $data->email :null,
      '#attributes' => array('placeholder'=>t('email')),
      );    
    
      $form['account1'] = array(
      '#type' => 'textfield',
      '#size' => 25,
      '#maxlength' => 20,
      '#default_value' => isset($data->account1) ? $data->account1 :null,
      '#attributes' => array('placeholder'=>t('account 1')),
      '#description' => t('account no.'),
      );    
    
      $form['account2'] = array(
      '#type' => 'textfield',
      '#size' => 25,
      '#maxlength' => 20,
      '#default_value' => isset($data->account2) ? $data->account2 :null,
      '#attributes' => array('placeholder'=>t('account 2')),
      '#description' => t('account no.'),
      );      
      
      $form['swift'] = array(
      '#type' => 'textfield',
      '#size' => 25,
      '#maxlength' => 20,
      '#default_value' => isset($data->swift) ? $data->swift :null,
      '#attributes' => array('placeholder'=>t('swift code')),
      '#description' => t('swift, BIC'),
      ); 
      
      $form['coid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => AccessCheck::CompanyListByUid(),
      '#default_value' => isset($data->coid) ? $data->coid :null,
      '#title' => t('Company reference'),
      '#required' => TRUE,
   
      ); 

      $form['actions'] = array(
        '#type' => 'actions',
        '#attributes' => array('class' => array('container-inline')),
      );     
     $form['actions']['save'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      );         
    }
  
  return $form;
  }



  /**
   * {@inheritdoc}
   * 
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    if($form_state->get('step') == 1){
    $form_state->set('step', 2);
    $form_state->set('bid', $form_state->getValue('bid') );
    $form_state->setRebuild();
    } 
    
    if($form_state->get('step') == 3) {
    
        if($form_state->getValue('email') != NULL) {
              if(!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {    
              $form_state->setErrorByName('email', $this->t('Invalid email'));
    }

        }
    
    }
  
  }
  
  
  
  /**
   * {@inheritdoc}
   * 
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if($form_state->get('step') == 3){
      
            $fields = array (
                'name' => SafeMarkup::checkPlain($form_state->getValue('name')),
                'address1' => SafeMarkup::checkPlain($form_state->getValue('address1')),
                'address2' => SafeMarkup::checkPlain($form_state->getValue('address2')),
                'postcode' => SafeMarkup::checkPlain($form_state->getValue('postcode')),
                'country' => $form_state->getValue('country'),
                'telephone' => SafeMarkup::checkPlain($form_state->getValue('telephone2')),
                'fax' => SafeMarkup::checkPlain($form_state->getValue('fax')),
                'email' => $form_state->getValue('email'),
                'contact' => SafeMarkup::checkPlain($form_state->getValue('contact')),
                'account1' => SafeMarkup::checkPlain($form_state->getValue('account1')),
                'account2' => SafeMarkup::checkPlain($form_state->getValue('account2')),
                'swift' => SafeMarkup::checkPlain($form_state->getValue('swift')),
                'coid' => $form_state->getValue('coid'),
                
                );


        if ( $form_state->getValue('bid') == 0)  {
          $insert = Database::getConnection('external_db', 'external_db')->insert('ek_bank')->fields($fields)->execute();
        } else {
          //update existing
              $update = Database::getConnection('external_db', 'external_db')->update('ek_bank')
                ->condition('id', $form_state->getValue('bid'))
                ->fields($fields)
                ->execute();   
              
        }
        
        \Drupal::messenger()->addStatus(t('Bank data recorded'));

    }


  }

}
