<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\BankAccountForm.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to create and edit bank account.
 */
class BankAccountForm extends FormBase {

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
    return 'ek_finance_bank_account';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
  
    if($id != NULL || $form_state->get('id') != NULL ) {
    
      if($id == NULL) $id = $form_state->getValue('id');
      $form_state->set('step', 2);
      
      if($id > 0) {
      $query = "SELECT * FROM {ek_bank_accounts} a
        LEFT JOIN {ek_bank} b
        ON a.bid = b.id
        WHERE a.id=:id";
      $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
      }

      $form['id'] = array(
        '#type' => 'hidden',
        '#value' => $id,
      );
      
    
    } elseif($form_state->get('step') == '') {
    $form_state->set('step', 1);
    $company = AccessCheck::GetCompanyByUser();
    $company = implode(',',$company);   
    $query = "SELECT a.id, a.account_ref , b.name FROM {ek_bank_accounts} a "
            . "INNER JOIN {ek_bank} b ON a.bid=b.id "
            . "INNER JOIN {ek_company} c ON b.coid=c.id "
            . "WHERE FIND_IN_SET(coid, :c ) ORDER by a.id";
    $list = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $company));
    $options = array();
    While($l = $list->fetchObject()) {
      $options[$l->id] = $l->account_ref . ' - ' . $l->name;
    }
    $options['0'] = t('create a new account');
 
     $form['id'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $options,
      '#title' => t('Bank account'),
      '#required' => TRUE, 
      '#prefix' => "<div class='container-inline'>",    
    ); 

     $form['next'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#limit_validation_errors' => array(array('id')),
      '#states' => array(
        'invisible' => array(
           "select[name='id']" => array('value' => ''),
        ),
      ),
      '#suffix' => '</div>',
    );
    }
    
    if ($form_state->get('step') == 2  ) {

    $back = Url::fromRoute('ek_finance.manage.bank_accounts_manage', array(), array())->toString() ;
    $table = Url::fromRoute('ek_finance.manage.bank_accounts_list', array(), array())->toString() ;
      
    $form_state->set('step', 3);
    
      $form["back"] = array(
      '#markup' => "<a href='" . $back . "' >" . t('select another account') . "</a> " . t('or') . '  ',
      '#prefix' => '<div class="container-inline">',
      );
      $form["list"] = array(
      '#markup' => "<a href='" . $table . "' >" . t('view list') . "</a>",
      '#suffix' => '</div>'
      );    

      $form["account_ref"] = array(
      '#type' => 'textfield',
      '#size' => 40,
      '#default_value' => $data->account_ref,
      '#maxlength' => 100,
      '#description' => '',
      '#required' => TRUE,
      '#attributes' => array('placeholder'=>t('Account No., IBAN') ),
      );

      $form['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => CurrencyData::listcurrency(1),
      '#required' => TRUE,
      '#default_value' => isset($data->currency) ? $data->currency : NULL,
      '#title' => t('Currency'),
      );


      $form['bid'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => BankData::listBank(),
      '#default_value' => isset($data->bid) ? $data->bid : NULL,
      '#required' => TRUE,
      '#title' => t('Bank reference'),
      '#attributes' => array('style' => array()),
      '#ajax' => array(
            'callback' => array($this, 'aid'), 
            'wrapper' => 'aidwrap',
        ),

      );   

if($form_state->getValue('bid') != NULL) {
    $query = "SELECT coid FROM {ek_bank} WHERE id=:id";
    $coid = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $form_state->getValue('bid') ))->fetchField();

} else {
    $coid = $data->coid;
}

    $settings = new FinanceSettings(); 
    $chart = $settings->get('chart');
    if(empty($chart)) {
      $alert =   "<div id='fx' class='messages messages--warning'>" . t('You did not set the accounts chart structure. Go to <a href="@url">settings</a>.' ,
                    array('@url' => Url::fromRoute('ek_finance.admin.settings', array(), array())->toString())). "</div>";
      $form['alert'] = array(
            '#type' => 'item',
            '#weight' => -17,
            '#markup' => $alert,
            );          
        }

      $form['aid'] = array(
        '#type' => 'select',
        '#size' => 1,
        '#title' => t('Journal reference'),
        '#options' => AidList::listaid($coid , [$chart['assets']], 1 ),
        '#required' => TRUE,
        '#default_value' => isset($data->aid) ? $data->aid : NULL,
        '#prefix' => "<div id='aidwrap' >",
        '#suffix' => '</div>',
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
   * callback functions
  */  
  public function aid(array &$form, FormStateInterface $form_state) {
  
    return $form['aid'] ;
    
  }

  /**
   * {@inheritdoc}
   * 
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
    if($form_state->get('step') == 1){
    $form_state->set('step', 2);
    $form_state->set('id', $form_state->getValue('id') );
    $form_state->setRebuild();
    } 
    
    if($form_state->get('step') == 3) {
    
    }
  
  }
  
  
  
  /**
   * {@inheritdoc}
   * 
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if($form_state->get('step') == 3){
      
            $fields = array (
                'account_ref' => Xss::filter($form_state->getValue('account_ref')),
                'currency' => $form_state->getValue('currency'),
                'bid' => $form_state->getValue('bid'),
                'aid' => $form_state->getValue('aid'),
                );


        if ( $form_state->getValue('id') == 0)  {
          $insert = Database::getConnection('external_db', 'external_db')
                  ->insert('ek_bank_accounts')->fields($fields)->execute();
          drupal_set_message(t('Bank account data recorded'), 'status');
        } else {
          //update existing
              $update = Database::getConnection('external_db', 'external_db')->update('ek_bank_accounts')
                ->condition('id', $form_state->getValue('id'))
                ->fields($fields)
                ->execute();   
          drupal_set_message(t('Bank account data updated'), 'status');     
        }   
    
        $form_state->setRedirect('ek_finance.manage.bank_accounts_list');

    }


  }

}
