<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\NewMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\FinanceSettings;


/**
 * Provides a form to create and edit finance memo.
 */
class NewMemo extends FormBase {

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
    $this->settings = new FinanceSettings();
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
    return 'ek_finance_new_memo';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $category = NULL, $tempSerial = NULL) {


  if(isset($id) && $id != NULL  ) {
 
  $chart = $this->settings->get('chart');   
  $baseCurrency = $this->settings->get('baseCurrency');
  
  //edit existing memo

  
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'memo');
    $query->fields('memo');
    $query->condition('id', $id);
    $data = $query->execute()->fetchObject();
   
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo_list', 'details');
    $query->fields('details');
    $query->condition('serial', $data->serial);
    $detail = $query->execute();


    $form['edit_memo'] = array(
      '#type' => 'item',
      '#markup' => t('Memo ref. @p', array('@p' => $data->serial)),

    );  
    
    $form['serial'] = array(
      '#type' => 'hidden',
      '#value' => $data->serial,
    );  
    $form['id'] = array(
      '#type' => 'hidden',
      '#value' => $id,
    );     
    
  $n = 0;
  $form_state->set('current_items', 0);
  if(!$form_state->get('num_items'))  {
      $form_state->set('num_items', 0); 
      }
  
  if(!$form_state->getValue('currency')) {
      $form_state->setValue('currency', $data->currency);
  }
  
  if($category == 'internal') {
      $AidOptions = AidList::listaid($data->entity, array($chart['expenses'], $chart['other_expenses']), 1 );
  } else {
      $AidOptions = AidList::listaid($data->entity_to, array($chart['expenses'], $chart['other_expenses']), 1 );
  }

  if ($category == 'personal' && $this->settings->get('authorizeMemo') == 1 ) {
    $auth_user = explode('|', $data->auth);
    $this->authorizer = $auth_user[1];
    //use flag to control form display options when opened by authorizer. Authorizer can't edit all data
    if(\Drupal::currentUser()->id() == $auth_user[1]) {
      $authorizer = TRUE;
    } else {
      $authorizer = FALSE;
    }
  } else {
    $authorizer = FALSE;
  }
  
  
  } else {
  //new
    $form['new_memo'] = array(
      '#type' => 'hidden',
      '#value' => 1,

    );

  $grandtotal = 0;
  $n = 0;
  $AidOptions = $form_state->get('AidOptions');
  $detail = NULL;
  $data = NULL;
  }
    

//
//Options
// 
  $CurrencyOptions = CurrencyData::listcurrency(1); 
  
  
    $form['tempSerial'] = array(
    //used for file uploaded
        '#type' => 'hidden',
        '#default_value' => $tempSerial,
        '#id' => 'tempSerial'
      );   
  
    $url = Url::fromRoute('ek_finance_manage_list_memo_'. $category, array(), array())->toString();
    $form['back'] = array(
      '#type' => 'item',
      '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

    );
    
    
    $form['options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Options'),
      '#open' => isset($id) ? FALSE : TRUE,
    );  

  if($category == 'internal') {
    $type = array(1 => "Internal invoice", 2 => "Purchase", 3 => "Claim", 4 => "Advance"); 
    $type_select = NULL;
  } else {
    $type = array(5 => "Personal claim");
    $type_select = 5;
  }
  
    $form['options']['category'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $type,
      '#required' => TRUE,
      '#default_value' => isset($data->category) ? $data->category : $type_select,
      '#title' => t('Memo type'),

    ); 

if ($this->settings->get('companyMemo') == 1){
    $company = AccessCheck::CompanyListByUid();
} else {
    $company = AccessCheck::CompanyList();
}
  if($category != 'internal') {
     
     if(\Drupal::currentUser()->hasPermission('admin_memos')) {
        $query = Database::getConnection()
                    ->select('users_field_data', 'users');
        $query->fields('users', ['uid', 'name']);
        $query->condition('status', 1);
        $query->orderBy('name', 'ASC');
        $entity = $query->execute()->fetchAllKeyed();
         
     } else {
          $entity = array(
            \Drupal::currentUser()->id() => \Drupal::currentUser()->getUsername()
          );
          $data->entity = \Drupal::currentUser()->id();
     }
     
  } else {
    $entity = $company;
  } 
   
    
    $form['options']['entity'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $entity,
      '#required' => TRUE,
      '#default_value' => isset($data->entity) ? $data->entity : NULL,
      '#title' => t('From entity'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div>',
    );  

    $form['options']['entity_to'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#required' => TRUE,
      '#default_value' => isset($data->entity_to) ? $data->entity_to : NULL,
      '#title' => t('To entity'),
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div></div></div>',
    ); 
    
    
      
  if ($this->moduleHandler->moduleExists('ek_address_book')) {
  $client = array(0 => t('not applicable'));
  $client += \Drupal\ek_address_book\AddressBookData::addresslist();

        if(!empty($client) ) {
              $form['options']['client'] = array(
                  '#type' => 'select',
                  '#size' => 1,
                  '#options' => $client,
                  '#required' => TRUE,
                  '#default_value' => isset($data->client) ? $data->client : NULL,
                  '#title' => t('Client or supplier'),
                  '#attributes' => array('style' => array('width:300px;')),
                );
        } else {
              $link =  Url::fromRoute('ek_address_book.new', array())->toString();
              
              $form['options']['client'] = array(
                  '#markup' => t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                  '#default_value' => 0,
                );      
        
        }

  } else {

              $form['options']['client'] = array(
                  '#markup' => t('You do not have any client or supplier list.'),
                  '#default_value' => 0,
                );

  } 

    $form['options']['date'] = array(
      '#type' => 'date',
      '#id' => 'edit-from',
      '#size' => 12,
      '#required' => TRUE,
      '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
      '#title' => t('date'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div>',
    );     

    $form['options']['mission'] = array(
      '#type' => 'textfield',
      '#size' => 30,
      '#required' => TRUE,
      '#default_value' => isset($data->mission) ? $data->mission : NULL,
      '#title' => t('Memo object'),
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div></div></div>',
      );
      
if($this->moduleHandler->moduleExists('ek_projects')) {
// project

    $form['options']['pcode'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => ProjectData::listprojects(0),
      '#required' => TRUE,
      '#default_value' => isset($data->pcode) ? $data->pcode : NULL,
      '#title' => t('Project'),
      '#attributes' => array('style' => array('width:250px;')),
      );



} else {
    $form['options']['pcode'] = array(
      '#type' => 'textfield',
      '#size' => 30,
      '#required' => FALSE,
      '#default_value' => isset($data->pcode) ? $data->pcode : NULL,
      '#title' => t('Project tag'),
      );

}
      $form['options']['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $CurrencyOptions,
      '#required' => TRUE,
      '#default_value' => isset($data->currency) ? $data->currency : NULL,
      '#title' => t('currency'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div>',
      );


    

    $form['options']['budget'] = array(
      '#type' => 'radios',
      '#options' => array('1' => t('yes'), '0' => t('no') ),
      '#default_value' => isset($data->budget) ? $data->budget : NULL,
      '#attributes' => array('title'=>t('budget')),
      '#title' => t('Budgeted'),
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div>',
      
    );     

    $form['options']['refund'] = array(
      '#type' => 'checkbox',
      '#default_value' => isset($data->refund) ? $data->refund : 0,
      '#attributes' => array('title'=>t('action')),
      '#description' => t('Refund'),
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div>',
      
    );    

    $form['options']['invoice'] = array(
      '#type' => 'checkbox',
      '#default_value' => isset($data->invoice) ? $data->invoice : 0,
      '#attributes' => array('title'=>t('action')),
      '#description' => t('Invoice client'),
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div></div></div>',      
    );      
 
    $form['options']['comment'] = array(
    '#type' => 'textarea',
    '#rows' => 1,
    '#default_value' => isset($data->comment) ? $data->comment : NULL,
    '#attributes' => array('placeholder'=>t('comment')),
  ); 

//
// Authorization
//

if ($category == 'personal' && $this->settings->get('authorizeMemo') == 1 ) {
    
    $user_name = '';
    if(isset($auth_user)) {
        $query = Database::getConnection()
                        ->select('users_field_data', 'users');
            $query->fields('users', ['name']);
            $query->condition('uid', $auth_user[1]);
            $user_name = $query->execute()->fetchField();
    }

    $form['autho'] = array(
      '#type' => 'details',
      '#title' => $this->t('Authorization'),
      '#open' => TRUE,
    ); 
    
    $form['autho']['user'] = array(
      '#type' => 'textfield',
      '#size' => 30,
      '#required' => TRUE,
      '#default_value' => $user_name,
      '#title' => t('Authorizer'),
      '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
      );

    if(isset($id) && $id != NULL  ) {
     //implement authorization
     
     if($auth_user[1] == \Drupal::currentUser()->id() || \Drupal::currentUser()->hasPermission('admin_memos')) {
      //authorizer need to approve
      $form['autho']['action'] = array(
      '#type' => 'radios',
      '#options' => array('read' =>t('read only (no action taken)'), 1 => t('request more data or receipts'), 2 => t('authorize'), 3 => t('reject') ),
      '#default_value' => $auth_user[0],
      '#attributes' => array('title'=>t('action')),
      '#title' => t('Authorization'),      
       );     
     
     } elseif(\Drupal::currentUser()->id() == $data->entity) {
      //display status to owner
      $action = array(0 =>t('not required'), 1 => t('pending approval'), 2 => t('authorized'), 3 => t('rejected') );
        $form['autho']['info'] = array(
          '#type' => 'item',
          '#markup' => $this->t('Authorization status') . ': ' . $action[$auth_user[0]],
        );      

        $form['autho']['action'] = array(
          '#type' => 'hidden',
          '#value' =>  $auth_user[0],
        );       
      
     }


    }

}

//
// Items
//

    $form['items'] = array(
      '#type' => 'details',
      '#title' => $this->t('Items'),
      '#open' => TRUE,
    ); 


    $form['items']['actions']['add'] = array(
      '#type' => 'submit' ,
      '#value' => $this->t('Add item'),
      '#submit' =>  array(array($this, 'addForm')) ,
      //'#limit_validation_errors' => [['category', 'entity', 'entity_to']],
      '#prefix' => "<div id='add'>",
      '#suffix' => '</div>',
    ); 



$headerline = "<div class='table'  id='memo_form_items'>
                  <div class='row'>
                      <div class='cell cellborder' id='tour-item1'>" . t("Account") . "</div>
                      <div class='cell cellborder' id='tour-item2'>" . t("Description") . "</div>
                      <div class='cell cellborder' id='tour-item3'>" . t("Amount") . "</div>
                      <div class='cell cellborder' id='tour-item4'>" . t("Receipt") . "</div>
                      <div class='cell cellborder' id='tour-item5'>" . t("delete") . "</div>
                   ";

  
    $form['items']["headerline"] = array(
      '#type' => 'item',
      '#markup' => $headerline,

        
    );  
    
    
if(isset($detail)) {
//edition mode
//list current items

$grandtotal = 0;

  while ($d = $detail->fetchObject()) {

  $n++; 
  $c = $form_state->get('current_items')+1;
  $form_state->set('current_items', $c) ;
  $grandtotal += $d->amount;
  
  
        $form['items']["aid$n"] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $AidOptions,
        '#required' => TRUE,
        '#default_value' => isset($d->aid) ? $d->aid : NULL,
        '#attributes' => array('style' => array('width:110px;white-space: nowrap')),
        '#prefix' => "<div class='row current'><div class='cell'>",
        '#suffix' => '</div>',
        );   
        
        $form['items']["description$n"] = array(
        '#type' => 'textfield',
        '#size' => 38,
        '#maxlength' => 200,
        '#default_value' => $d->description,
        '#attributes' => array('placeholder'=>t('description')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
        ); 
    
        $form['items']["amount$n"] = array(
        '#type' => 'textfield',
        '#id' => 'amount'.$n,        
        '#size' => 8,
        '#maxlength' => 30,
        '#default_value' => number_format($d->amount, 2),
        '#attributes' => array('placeholder'=>t('amount'), 'class' => array('amount')),
        '#prefix' => "<div class='cell right'>",
        '#suffix' => '</div>',        
        ); 

        $form['items']["receipt$n"] = array(
        '#type' => 'textfield',
        '#size' => 8,
        '#maxlength' => 100,
        '#default_value' => $d->receipt,
        '#attributes' => array('placeholder'=>t('ref.'), ),
        '#prefix' => "<div class='cell center'>",
        '#suffix' => '</div>',    
        );             

        $form['items']["delete$n"] = array(
        '#type' => 'checkbox',
        '#id' => 'del' . $n ,
        '#attributes' => array('title'=>t('delete'), 'class' => array('amount')),
        '#prefix' => "<div class='cell center'>",
        '#suffix' => '</div></div>',    
        );   
  
  } 



} //details of current records


  if(isset($detail)) {
  // reset the new rows items
    $max = $form_state->get('num_items')+$n;
    $n++;
      } else {
        $max = $form_state->get('num_items');
        $n = 1;
      }
  
    for ($i=$n;$i<=$max;$i++)
    {

        $form['items']["aid$i"] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $AidOptions,
        '#required' => TRUE,
        '#default_value' => $form_state->getValue("aid$i") ? $form_state->getValue("aid$i") : NULL,
        '#attributes' => array('style' => array('width:110px; white-space: nowrap')),
        '#prefix' => "<div class='row'><div class='cell'>",
        '#suffix' => '</div>',
        ); 
           
        $form['items']["description$i"] = array(
        '#type' => 'textfield',
        '#size' => 38,
        '#maxlength' => 200,
        '#default_value' => $form_state->getValue("description$i") ? $form_state->getValue("description$i") : NULL,
        '#attributes' => array('placeholder'=>t('description')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',         
        ); 
    

        $form['items']["amount$i"] = array(
        '#type' => 'textfield',
        '#id' => 'amount'.$i,        
        '#size' => 8,
        '#maxlength' => 30,
        '#default_value' => $form_state->getValue("amount$i") ? number_format($form_state->getValue("amount$i"),2) : NULL,
        '#attributes' => array('placeholder'=>t('amount'), 'class' => array('amount')),
        '#prefix' => "<div class='cell right'>",
        '#suffix' => '</div>', 
        ); 


        $form['items']["receipt$i"] = array(
        '#type' => 'textfield',
        '#size' => 8,
        '#maxlength' => 100,
        '#default_value' => $form_state->getValue("receipt$i") ? $form_state->getValue("receipt$i") : NULL,
        '#attributes' => array('placeholder'=>t('ref.'), ),
        '#prefix' => "<div class='cell center'>",
        '#suffix' => '</div>',    
        );

        $form['items']["delete$i"] = array(
        '#type' => 'item',        
        '#prefix' => "<div class='cell center'>",
        '#suffix' => '</div></div>',
    
        );             
    }

      

 
      $form['items']['count'] = array(
        '#type' => 'hidden',
        '#value' => isset($detail) ? $n-1+$form_state->get('num_items') : $form_state->get('num_items'),
        '#attributes' => array('id' => 'itemsCount'),
      );
 
    

    if( ($form_state->get('num_items') && $form_state->get('num_items')>0) || isset($detail)  ) {
    
    
      $form['items']['1'] = array(
        '#type' => 'item',
        '#markup' => t('Total'),
        '#prefix' => "<div class='row' id='memo_form_footer'><div class='cell'>",
        '#suffix' => '</div>',
        );
 
      $form['items']['2'] = array(
        '#type' => 'item',
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
      );             
              
      $form['items']["grandtotal"] = array(
        '#type' => 'textfield',
        '#id' => 'grandtotal',        
        '#size' => 12,
        '#maxlength' => 255,
        '#value' => isset($grandtotal) ?  number_format($grandtotal, 2) : 0,
        '#attributes' => array('placeholder'=>t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
        '#prefix' => "<div class='cell right'>",
        '#suffix' => '</div></div></div>',        
        );       
        
      if ($form_state->get('num_items') > 0) {
        $form['items']['remove'] = array(
          '#type' => 'submit',
          '#value' => $this->t('remove last item'),
          '#limit_validation_errors' => array(),
          '#submit' => array(array($this, 'removeForm')),
        );     
      } 
    
    
 
    
    }

//
// Attachments
//
    $form['attach'] = array(
      '#type' => 'details',
      '#title' => $this->t('Attachments'),
      '#open' => TRUE,
    );
    $form['attach']['upload_doc'] = array(
      '#type' => 'file',
      '#title' => t('Select file'),
      '#prefix' => '<div class="container-inline">',
      
    );
    
    $form['attach']['upload'] = array(
        '#id' => 'upbuttonid',
        '#type' => 'button',
        '#value' =>  t('Attach') ,
        '#suffix' => '</div>',  
        '#ajax' => array(
          'callback' => array($this, 'uploadFile'), 
          'wrapper' => 'new_attachments',
          'effect' => 'fade',
          'method' => 'append',
          
         ),
    );     
    
        $form['attach']['attach_new'] = array(
            '#type' => 'container',
            '#attributes' => array(
              'id' => 'attachments',
              'class' => 'table'
            ),
        ); 
        
        $form['attach']['attach_error'] = array(
            '#type' => 'container',
            '#attributes' => array(
              'id' => 'error',
            ),
        ); 
    
    $form['actions'] = array('#type' => 'actions');
      $form['actions']['record'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Record'),
      );              
        
    //$form['#tree'] = TRUE;
     $form['#attached'] = array(
                'drupalSettings' => array('id' => $id, 'serial' => $tempSerial),
                'library' => array('ek_finance/ek_finance.memo_form'),
            );
     
  return $form;
  
  } //


    
  /**
   * Callback to Add item to form
  */
  public function addForm(array &$form, FormStateInterface $form_state) {
    
    if(!$form_state->get('num_items') ) {
      $form_state->set('num_items', 1);

      } else {
        $c = $form_state->get('num_items')+1;
        $form_state->set('num_items', $c);
      }

      $chart = $this->settings->get('chart');
      if($form_state->getValue('category') < 5) {
        $form_state->set('AidOptions', AidList::listaid($form_state->getValue('entity'), array($chart['expenses'], $chart['other_expenses']), 1 ));
      } else {
        $form_state->set('AidOptions', AidList::listaid($form_state->getValue('entity_to'), array($chart['expenses'], $chart['other_expenses']), 1 ));
      }

    $form_state->setRebuild();
  
  }

  /**
   * Callback to Remove item to form
  */
  public function removeForm(array &$form, FormStateInterface $form_state) {
  
    $c = $form_state->get('num_items')-1;
    $form_state->set('num_items', $c);
    $form_state->setRebuild();

  }


  /**
   * Callback for the ajax upload file 
   * 
   */  
  public function uploadFile(array &$form, FormStateInterface $form_state) {
  
     //upload
      $extensions = 'png jpg jpeg';
      $validators = array( 'file_validate_extensions' => array($extensions));
      $file = file_save_upload("upload_doc" , $validators, FALSE, 0);
          
      if ($file) {
        
          $dir = "private://finance/memos"   ;
          file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
          $dest = $dir . '/'  . $file->getFilename();
          $filename = file_unmanaged_copy($file->getFileUri(), $dest);
          
        $fields = array(
          'serial' => $form_state->getValue('tempSerial'),
          'uri' => $filename,
          'doc_date' => time(),
        );            
        $insert = Database::getConnection('external_db', 'external_db')
                ->insert('ek_expenses_memo_documents')->fields($fields)->execute();
        
        $response = new AjaxResponse();
        if($insert) {
            return $response->addCommand(new HtmlCommand('#error', ""));   
        } else {
            $msg = "<div aria-label='Error message' class='messages messages--error'>" 
           . t('Error') . "</div>";
            return $response->addCommand(new HtmlCommand('#error', $msg));  
        }
        
      } else {
          $msg = "<div aria-label='Error message' class='messages messages--error'>" 
           . t('Error') . ". " . t('Allowed extensions') . ": " . 'png jpg jpeg'
            . "</div>";
          $response = new AjaxResponse();
            return $response->addCommand(new HtmlCommand('#error', $msg));   

      }
   
  }  


  
  /**
   * {@inheritdoc}
   * 
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    //input used to update values set by user
    //$input = $form_state->getUserInput();
    
    // validate authorizer
    if ($form_state->getValue('user')) {
        $query = Database::getConnection()
                    ->select('users_field_data', 'users');
        $query->fields('users', ['uid']);
        $query->condition('name', $form_state->getValue('user'));
        $uid = $query->execute()->fetchField();

        if(!$uid || ($uid == \Drupal::currentUser()->id() && $uid != $this->authorizer) ) {
           $form_state->setErrorByName("user", $this->t('Authorizer is not valid or unknowned'));
        } else {
            //save data for submission
            $form_state->setValue('user_uid', $uid);
        }        

    
    }
   $triggering_element = $form_state->getTriggeringElement();
    //enforce data input
    /**/
    if($triggering_element['#id'] != 'edit-add' && $form_state->getValue('new_memo') == '1' && 
            !$form_state->get('num_items')) {
        
            $form['options']['#open'] = FALSE;
            $form_state->setErrorByName("add", $this->t('No data'));
            
    }
    
    for ($n=1;$n<=$form_state->get('num_items');$n++) {
    
            if($form_state->getValue("description$n") == '') {
             $form_state->setErrorByName("description$n", $this->t('Item @n is empty', array('@n'=> $n)) );
            }

            if($form_state->getValue("amount$n") == '' || !is_numeric($form_state->getValue("amount$n"))) {
             $form_state->setErrorByName("amount$n",  $this->t('there is no value for item @n', array('@n'=> $n)) );
            }            

                // validate account
                // @TODO          
                
    }
   
  }

  /**
   * {@inheritdoc}
   */  
  public function submitForm(array &$form, FormStateInterface $form_state) {

      if ($form_state->getValue('new_memo') == 1 ) {
        //create new serial No
        
        $iid = Database::getConnection('external_db', 'external_db')
                ->query("SELECT count(id) from {ek_expenses_memo}")
                ->fetchField();
        $iid++;
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'co');
        $query->fields('co', ['short']);
        $query->condition('id', $form_state->getValue('entity_to'));
        $short = $query->execute()->fetchField();
        
        //$short = Database::getConnection('external_db', 'external_db')
        //        ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('entity_to')))
        //        ->fetchField();
        $date = substr($form_state->getValue('date'), 2,5);
        $serial = ucwords(str_replace('-','',$short) ) . "-EM-" . $date . "-"  . $iid ;
      
      } else {
        //edit
        $serial = $form_state->getValue('serial');
        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_expenses_memo_list')->condition('serial', $serial)
                ->execute();
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'memo');
        $query->fields('memo', ['id']);
        $query->condition('serial', $serial);
        $iid = $query->execute()->fetchField();
        //$iid = Database::getConnection('external_db', 'external_db')
          //      ->query('SELECT id from {ek_expenses_memo} where serial=:s', array(':s' => $serial))
            //    ->fetchField();
      }
  
  $currencyRate = CurrencyData::rate($form_state->getValue('currency'));

// Items  
  
  $line = 0;
  $total = 0;

  for ($n=1;$n<=$form_state->getValue('count');$n++) {
  
    if(!$form_state->getValue("delete$n") == 1) { 
    
    $item = Xss::filter($form_state->getValue("description$n"));  
    $amount = str_replace(',','', $form_state->getValue("amount$n") );
    $linebase = (round($amount/$currencyRate, 2));
    $total = $total + $amount;
    
    if(!$form_state->getValue("aid$n")) {
      $aid = 0;
    } else {
      $aid = $form_state->getValue("aid$n");
    } 

    $fields=array('serial' => $serial,
                  'aid' => $aid, 
                  'description' => $item, 
                  'amount' => $amount,
                  'value_base' => $linebase,
                  'receipt' => Xss::filter($form_state->getValue("receipt$n")),
                  );
      
    $insert = Database::getConnection('external_db', 'external_db')
            ->insert('ek_expenses_memo_list')
            ->fields($fields)
            ->execute();  
    

    
    }//if not delete
  }//for
  
//main
 
  if($form_state->getValue('pcode') == '') {$pcode = 'n/a';
    } else {
      $pcode = $form_state->getValue('pcode');
    }

    if ($form_state->getValue('category') == 5 && $this->settings->get('authorizeMemo') == 1 ) {
        
        $uid = $form_state->getValue('user_uid');
        
        if(!$form_state->getValue('action')) {
          $auth = '1|'. $uid;
        } else {
          
          switch ( $form_state->getValue('action') ) {
            case 'read' :
              $auth = Database::getConnection('external_db', 'external_db')
                    ->query('SELECT auth from {ek_expenses_memo} where serial=:s', array(':s' => $serial))
                    ->fetchField();
            break;
            
            case 1:
            case 2:
            case 3:
              $auth = $form_state->getValue('action') . '|' . $uid;
            break;
          
          }
          
        }
        
        
    } else {
        $auth = '0|0';
    }
 
    
      $fields1 = array (
                'serial' => $serial,
                'category' => $form_state->getValue('category'),
                'entity' => $form_state->getValue('entity'),
                'entity_to' => $form_state->getValue('entity_to'),
                'client' => $form_state->getValue('client'),
                'pcode' => $pcode,
                'mission' => Xss::filter($form_state->getValue('mission')),
                'budget' => $form_state->getValue('budget'),
                'refund' => $form_state->getValue('refund'),
                'invoice' => $form_state->getValue('invoice'),
                'date' => $form_state->getValue('date'),
                'status' => '0',
                'value' => $total,
                'currency' => $form_state->getValue('currency'),
                'value_base' => round($total/$currencyRate, 2),
                'amount_paid' => 0,
                'amount_paid_base' => 0,
                'comment' => Xss::filter($form_state->getValue('comment')),
                'reconcile' => 0,
                'post' => 0,
                'auth' => $auth
                
                );
                 
  if ($form_state->getValue('new_memo') && $form_state->getValue('new_memo') == 1 ) {
    $insert = Database::getConnection('external_db', 'external_db')->insert('ek_expenses_memo')
      ->fields($fields1)
      ->execute(); 
    $reference = $insert;
  
  } else {
    $update = Database::getConnection('external_db', 'external_db')->update('ek_expenses_memo')
      ->fields($fields1)
      ->condition('serial' , $serial)
      ->execute();
    $reference = $iid; 
  } 


  
  if (isset($insert) || isset($update) ){
      \Drupal::messenger()->addStatus(t('The memo is recorded'));
  }
  
    //update the documents table
    Database::getConnection('external_db', 'external_db')->update('ek_expenses_memo_documents')
    ->fields(array('serial' => $serial))
    ->condition('serial' , $form_state->getValue('tempSerial'))
    ->execute();
  
  if ($form_state->getValue('category') == 5 && $this->settings->get('authorizeMemo') == 1 ) {
  // send a notification
    $query = "SELECT name,mail from {users_field_data} WHERE uid=:u";
  
    $user_memo = db_query($query, array(':u' => \Drupal::currentUser()->id() ))->fetchObject();
  
    $authorizer_mail = db_query($query, array(':u' => $uid ))->fetchObject();
  
    $entity_mail = db_query($query, array(':u' => $form_state->getValue('entity') ))->fetchObject();
  
    $action = array( 1 => t('pending approval'), 2 => t('authorized'), 3 => t('rejected') );
  
    $params['subject'] = t("Authorization notification") . ' - ' . $action[$form_state->getValue('action')];
    $url = $GLOBALS['base_url'] . Url::fromRoute('ek_finance_manage_personal_memo', array('id' => $reference))->toString();
    $params['options']['url'] = "<a href='". $url ."'>" . $serial . "</a>";
    $params['options']['user'] = $user_memo->name;
    $body = '';
    $body .= t('Memo ref.') . ': ' . $serial ; 
    $body .= '<br/>' . t('Status') . ': ' . $action[$form_state->getValue('action')];
    $error = [];
    if(\Drupal::currentUser()->id() == $form_state->getValue('entity')){
    //current user is accessing his/her own claim
        
        $body .= '<br/>' .  t('Authorization action is required. Thank you.');
        $params['body'] = $body;
        if ($target_user = user_load_by_mail($authorizer_mail->mail)) {
            $target_langcode = $target_user->getPreferredLangcode();
        } else {
            $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
        }
         $send = \Drupal::service('plugin.manager.mail')->mail(
                'ek_finance',
                'key_memo_note',
                $authorizer_mail->mail ,
                $target_langcode,
                $params,
                $user_memo->mail,
                TRUE
              );
            
              if($send['result'] == FALSE) {
                $error[] = $authorizer_mail->mail ;
              }   
    
    } elseif(\Drupal::currentUser()->id() == $uid) {
    //authorizer editing
        
        $body .= '<br/>' .  t('Authorization has been reviewed. Thank you.');
        $params['body'] = $body;
        if ($target_user = user_load_by_mail($entity_mail->mail)) {
            $target_langcode = $target_user->getPreferredLangcode();
        } else {
            $target_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
        }        
         $send = \Drupal::service('plugin.manager.mail')->mail(
                'ek_finance',
                'key_memo_note',
                $entity_mail->mail,
                $target_langcode,
                $params,
                $user_memo->mail,
                TRUE
              );
            
              if($send['result'] == FALSE) {
                $error[] = $user_memo->mail ;
              }      
    
    }
  
    if(!empty($error)) {
     $errors = implode(',', $error);
     \Drupal::messenger()->addError(t('Error sending notification to :t', [':t' => $errors]));
    } else {
        \Drupal::messenger()->addStatus(t('Notification message sent'));
    }  
  
  }//notification
  
  
  
  if ($form_state->getValue('category') < 5) {
          $form_state->setRedirect('ek_finance_manage_list_memo_internal' ) ;
        } else {
          $form_state->setRedirect('ek_finance_manage_list_memo_personal' ) ;
        }
  
  }

}