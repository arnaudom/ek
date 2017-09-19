<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\NewInvoice.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\CompanySettings;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\BankData;
use Drupal\ek_products\ItemData;

/**
 * Provides a form to create and edit invoices.
 */
class NewInvoice extends FormBase {

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
    if($this->moduleHandler->moduleExists('ek_finance')) {
        $this->settings = new FinanceSettings();
    }
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
    return 'ek_sales_new_invoice';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $clone = NULL) {
      
    $url = Url::fromRoute('ek_sales.invoices.list', array(), array())->toString();
    $form['back'] = array(
      '#type' => 'item',
      '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

    );
      
  
    if(isset($id) && $id != NULL  ) {

    //edit existing invoice

    If($clone != 'delivery') {
      $query = "SELECT * from {ek_sales_invoice} where id=:id";
      $data = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $id))
              ->fetchObject();
      $query = "SELECT * FROM {ek_sales_invoice_details} where serial=:id ORDER BY id";
      $detail = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $data->serial));
      $fx_rate =  round($data->amount/$data->amountbase, 4);
      $form_state->set('fx_rate', $fx_rate);
      if($fx_rate != '1') {
          $form_state->set('fx_rate_require', TRUE);
      } else {
          $form_state->set('fx_rate_require', FALSE);
      }
      
      $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'),'4' => t('Credit note'));

    } elseif($clone == 'delivery' && $this->moduleHandler->moduleExists('ek_logistics')) {
    //convert delivery order into invoice with new serial No

      $query = "SELECT * from {ek_logi_delivery} where id=:id";
      $data = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $id))
              ->fetchObject();
      $query = "SELECT * FROM {ek_logi_delivery_details} where serial=:id";
      $detail = Database::getConnection('external_db', 'external_db')
              ->query($query, array(':id' => $data->serial));

      $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'));
    }


    If($clone != 'clone' && $clone != 'delivery') {
        
        $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'), '4' => t('Credit note'));
        $form['edit_invoice'] = array(
          '#type' => 'item',
          '#markup' => t('Invoice ref. @p', array('@p' => $data->serial)),    
        );  

       $form['serial'] = array(
          '#type' => 'hidden',
          '#value' => $data->serial,

        ); 
    } elseif ($clone == 'clone') {
    //duplicate existing invoice with new serial No
        $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'), '4' => t('Credit note'));
        $form['clone_invoice'] = array(
          '#type' => 'item',
          '#markup' => t('Template invoice based on ref. @p . A new invoice will be generated.', array('@p' => $data->serial)),    
        );    

        $data->date = date('Y-m-d');

        $form['new_invoice'] = array(
          '#type' => 'hidden',
          '#value' => 1,
        );

    } elseif($clone == 'delivery') {
    //convert delivery order into invoice with new serial No
       $options = array('1' => t('Invoice'), '2' => t('Commercial invoice')); 
       
       $form['clone_invoice'] = array(
          '#type' => 'item',
          '#markup' => t('Convert delivery order ref. @p .', array('@p' => $data->serial)),    
        ); 

       $form['do'] = array(
          '#type' => 'hidden',
          '#value' => $data->serial,
        );   

       $form['new_invoice'] = array(
          '#type' => 'hidden',
          '#value' => 1,
       );      

      $data->date = $data->ddate;
      $data->comment = $data->serial;

    }



    $n = 0;
    $form_state->set('current_items', 0);
    if(!$form_state->get('num_items'))  {
        $form_state->set('num_items', 0);
    }

    if(!$form_state->getValue('head')) {
        $form_state->setValue('head', $data->head);
    }
    if(!$form_state->getValue('currency')) {
        $form_state->setValue('currency', $data->currency);
    }

      if($this->moduleHandler->moduleExists('ek_finance')) {
        $chart = $this->settings->get('chart');
        $AidOptions = AidList::listaid($data->head, array($chart['income'],$chart['other_income']), 1 );
        $baseCurrency = $this->settings->get('baseCurrency');
        if($baseCurrency <> $data->currency) { $requireFx = TRUE;} else { $requireFx = FALSE;}

      }  



    } else {
    //new
        $form['new_invoice'] = array(
            '#type' => 'hidden',
            '#value' => 1,
        );

        $grandtotal = 0;
        $taxable = 0;
        $n = 0;
        $AidOptions = array();
        $form_state->set('fx_rate_require', FALSE);
        $detail = NULL;
        $data = NULL;
        $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'), '4' => t('Credit note'));
    }
    

 
  if($this->moduleHandler->moduleExists('ek_finance')) {
    $CurrencyOptions = CurrencyData::listcurrency(1);   
    $chart = $this->settings->get('chart');
    if(empty($chart)) {
          $alert =   "<div id='fx' class='messages messages--warning'>" . t('You did not set the accounts chart structure. Go to <a href="@url">settings</a>.' ,
                    array('@url' => Url::fromRoute('ek_finance.admin.settings', array(), array())->toString())). "</div>";
            $form['alert'] = array(
                '#type' => 'item',
                '#weight' => -17,
                '#markup' => $alert,
            );          
        }
  } 
    
    $form['options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Options'),
      '#open' => ($form_state->getValue('count') > 2) ? FALSE : TRUE,
      '#attributes' => '',
      '#prefix' => "",
    );  
    
    $company = AccessCheck::CompanyListByUid(); 
    $form['options']['head'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#required' => TRUE,
      '#default_value' => isset($data->head) ? $data->head : NULL,
      '#title' => t('Header'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div>',
      '#ajax' => array(
          'callback' => array($this, 'set_coid'), 
          'wrapper' => 'debit',
          //will define the list of bank accounts by company below
      ),
    );  

    
    $form['options']['allocation'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $company,
      '#required' => TRUE,
      '#default_value' => isset($data->allocation) ? $data->allocation : NULL,
      '#title' => t('Allocated'),
      '#description' => t('select an entity for which the invoice is done'), 
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div></div>',
    ); 
    
      
  if ($this->moduleHandler->moduleExists('ek_address_book')) {
  $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);

        if(!empty($client) ) {
              $form['options']['client'] = array(
                  '#type' => 'select',
                  '#size' => 1,
                  '#options' => $client,
                  '#required' => TRUE,
                  '#default_value' => isset($data->client) ? $data->client : NULL,
                  '#title' => t('Client'),
                  '#prefix' => "<div class='row'><div class='cell'>",
                  '#suffix' => '</div></div></div>',
                  '#attributes' => array('style' => array('width:300px;white-space:nowrap')),
                );
        } else {
              $link =  Url::fromRoute('ek_address_book.new', array())->toString();
              
              $form['options']['client'] = array(
                  '#markup' => t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                  '#default_value' => 0,
                  '#prefix' => "<div class='row'><div class='cell'>",
                  '#suffix' => '</div></div></div>',                  
                );      
        
        }

  } else {

              $form['options']['client'] = array(
                  '#markup' => t('You do not have any client list.'),
                  '#default_value' => 0,
                  '#prefix' => "<div class='row'><div class='cell'>",
                  '#suffix' => '</div></div></div>',                  
                );

  } 

 
    
    
    $form['options']['date'] = array(
      '#type' => 'date',
      '#id' => 'edit-from',
      '#size' => 12,
      '#required' => TRUE,
      '#default_value' => isset($data->date) ? $data->date : date('Y-m-d'),
      '#title' => t('Date'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div>',
    );     

    
    $form['options']['title'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => isset($data->type) ? $data->type : 1,
      '#title' => t('Title'),
      '#prefix' => "<div class='cell'>",
      '#suffix' => '</div></div></div>',
    );  

if($this->moduleHandler->moduleExists('ek_projects')) {


    $form['options']['pcode'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => ProjectData::listprojects(0),
      '#required' => TRUE,
      '#default_value' => isset($data->pcode) ? $data->pcode : NULL,
      '#title' => t('Project'),
      '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
      );



} // project

if($this->moduleHandler->moduleExists('ek_finance')) {

      $form['options']['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $CurrencyOptions,
      '#required' => TRUE,
      '#default_value' => isset($data->currency) ? $data->currency : NULL,
      '#title' => t('Currency'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div>',
      '#ajax' => array(
            'callback' => array($this, 'check_aid'), 
            'wrapper' => 'fx',
            //will define if currency asset account exist and input the exchange rate against
            // base currency
        ),
      );

 
    $form['options']['fx_rate'] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#maxlength' => 15,
      //'#value' =>  $fx_rate,
      '#default_value' =>  $form_state->get('fx_rate'),
      '#required' => $form_state->get('fx_rate_require'),
      '#title' => t('Exchange rate'),
      '#description' => $form_state->get('fx_rate_desc'),
      '#prefix' => "<div id='fx' class='cell'>", 
      '#suffix' => '</div>', 
     
    );

//bank account
if( $form_state->getValue('head') )  { 
    $options['bank'] = BankData::listbankaccountsbyaid($form_state->getValue('head'));    
    }


      $form['options']['bank_account'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => isset($options['bank']) ? $options['bank'] : array(),
      '#default_value' => isset($data->bank) ? $data->bank : $form_state->getValue('bank_account'),
      '#required' => TRUE,
      '#title' => t('Account payment'),
      '#prefix' => "<div id='debit' class='cell'>",
      '#suffix' => '</div></div></div>',
      '#description' =>'',
      '#attributes' => array('style' => array('width:280px;;white-space:nowrap')),
      '#ajax' => array(
            'callback' => array($this, 'check_tax'), 
            'wrapper' => 'taxwrap',
        ),

      );   
     
 
/* */
} // finance

  else {
    $l = explode(',', file_get_contents(drupal_get_path('module', 'ek_sales') . '/currencies.inc'));
    foreach($l as $key => $val) {
       $val = explode(':', $val);
       $currency[$val[0]] = $val[1];
    }  
  
      $form['options']['currency'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => $currency,
      '#required' => TRUE,
      '#default_value' => isset($data->currency) ? $data->currency : NULL,
      '#title' => t('Currency'),
      '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
      '#suffix' => '</div></div></div>',
      );
      
       $form['options']['bank_account'] = array(
           '#type' => 'hidden',
           '#value' => 0,
           );
       $form['options']['fx_rate'] = array(
           '#type' => 'hidden',
           '#value' => 1,
       );
  
  }


    $form['options']['tax'] = array(
      '#type' => 'textfield',
      '#size' => 30,
      '#maxlength' => 255,
      '#default_value' => isset($data->tax) ? $data->tax : NULL,
      '#title' => t('Tax'),
      '#prefix' => "<div class='container-inline'>",
      '#attributes' => array('placeholder'=>t('ex. sales tax')),
    );  

    $form['options']['taxvalue'] = array(
      '#type' => 'textfield',
      '#id' => 'taxvalue',
      '#size' => 10,
      '#maxlength' => 6,
      '#default_value' => isset($data->taxvalue) ? $data->taxvalue : NULL,
      '#description' => t('Percent'),
      '#title_display' => 'after',
      '#prefix' => "<div id='taxwrap'>",
      '#suffix' => "</div></div>",
      '#attributes' => array('placeholder'=>t('%'), 'class' => array('amount') ),
    );  

    $form['options']['terms'] = array(
      '#type' => 'select',
      '#size' => 1,
      '#options' => array(t('on receipt'), t('due days')),
      '#default_value' => isset($data->terms) ? $data->terms : NULL,
      '#title' => t('Terms'),
      '#prefix' => "<div class='container-inline'>",
      '#ajax' => array(
            'callback' => array($this, 'check_day'), 
            'wrapper' => 'calday',
            'event' => 'change',
        ),
    );  

    $form['options']['due'] = array(
      '#type' => 'textfield',
      '#size' => 5,
      '#maxlength' => 3,
      '#default_value' => isset($data->due) ? $data->due : NULL,
      '#attributes' => array('placeholder' => t('days')),
      '#ajax' => array(
            'callback' => array($this, 'check_day'), 
            'wrapper' => 'calday',
            'event' => 'change',
        ),
    );
    $form['options']['day'] = array (
        '#type' => 'item',
        '#markup' => '',
        '#prefix' => "<div  id='calday'>",
        '#suffix' => "</div></div>",
    );
    
    $form['options']['comment'] = array(
      '#type' => 'textarea',
      '#rows' => 1,
      '#default_value' => isset($data->comment) ? $data->comment : NULL,
      '#prefix' => "<div class='container-inline'>",
      '#suffix' => "</div>",
      '#attributes' => array('placeholder'=>t('comment')),
    );  


    $form['items'] = array(
      '#type' => 'details',
      '#title' => $this->t('Items'),
      '#open' => TRUE,
      '#attributes' => '',
    ); 


    $form['items']['actions']['add'] = array(
      '#type' => 'submit' ,
      '#value' => $this->t('Add item'),
      //'#limit_validation_errors' => array(array('head', 'currency', 'fx_rate')),
      '#submit' =>  array(array($this, 'addForm')) ,
      '#prefix' => "<div id='add' class='right'>",
      '#suffix' => '</div>',
      '#attributes' => array('class' => array('button--add')),
    ); 


if($this->moduleHandler->moduleExists('ek_finance')) {
$headerline = "<div class='table'  id='invoice_form_items'>
                  <div class='row'>
                      <div class='cell cellborder' id='tour-item1'>" . t("Description") . "</div>
                      <div class='cell cellborder' id='tour-item2'>" . t("Account") . "</div>
                      <div class='cell cellborder' id='tour-item3'>" . t("Units") . "</div>
                      <div class='cell cellborder' id='tour-item4'>" . t("Value") . "</div>
                      <div class='cell cellborder' id='tour-item5'>" . t("delete") . "</div>
                      <div class='cell cellborder' id='tour-item6'>" . t("tax") . "</div>
                      <div class='cell cellborder right' id='tour-item7'>" . t("Line total") . "</div>
                   ";
} else {
$headerline = "<div class='table' id='invoice_form_items'><div class='row'><div class='cell cellborder'>" . t("Description") . "</div><div class='cell cellborder'>" . t("Units") . "</div><div class='cell cellborder'>" . t("Value") . "</div><div class='cell cellborder'>" . t("delete") . "</div><div class='cell cellborder'>" . t("tax") . "</div><div class='cell cellborder'>" . t("Line total") . "</div>";
}
  
    $form['items']["headerline"] = array(
      '#type' => 'item',
      '#markup' => $headerline,

        
    );  
    
    
if(isset($detail)) {
//edition mode
//list current items

  while ($d = $detail->fetchObject()) {

  $n++; 
  $c = $form_state->get('current_items')+1;
  $form_state->set('current_items', $c) ;

if($clone == 'delivery') {
  
  if ($d->itemcode != "" && $this->moduleHandler->moduleExists('ek_products') )  {
    $name = ItemData::item_bycode($d->itemcode);  
  } else {
    $name = $d->itemcode;
  }

} else {
  if ($d->itemdetail != "" && $this->moduleHandler->moduleExists('ek_products') )  {
    $item = ItemData::item_byid($d->itemdetail);  
    if(isset($item)) {
       $name = $item; 
    } else {
       $name =  $d->item;
    }
  } else {
    $name = $d->item;
  }
}

$cl = ($form_state->getValue("delete".$n) == 1) ? 'delete' : 'current';

        $form['items']["description$n"] = array(
        '#type' => 'textfield',
        '#size' => 50,
        '#maxlength' => 255,
        '#default_value' => $name,
        '#attributes' => array('placeholder'=>t('item')),
        '#prefix' => "<div class='row $cl' id='row$n'><div class='cell'>",
        '#suffix' => '</div>',
        '#autocomplete_route_name' => 'ek.look_up_item_ajax',
        ); 
    
    
        if($this->moduleHandler->moduleExists('ek_finance')) { 

        $form['items']["account$n"] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $AidOptions,
        '#required' => TRUE,
        '#default_value' => isset($d->aid) ? $d->aid : NULL,
        '#attributes' => array('style' => array('width:100px;white-space:nowrap')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',
        ); 

        } // finance    
    
        $form['items']["quantity$n"] = array(
        '#type' => 'textfield',
        '#id' => 'quantity'.$n,
        '#size' => 8,
        '#maxlength' => 255,
        '#default_value' => $d->quantity,
        '#attributes' => array('placeholder'=>t('units'), 'class' => array('amount')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',        
        );     

        $form['items']["value$n"] = array(
        '#type' => 'textfield',
        '#id' => 'value'.$n,        
        '#size' => 8,
        '#maxlength' => 255,
        '#default_value' => $d->value,
        '#attributes' => array('placeholder'=>t('price'), 'class' => array('amount')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',        
        ); 

        $form['items']["delete$n"] = array(
        '#type' => 'checkbox',
        '#id' => 'del' . $n ,
        '#attributes' => array('title'=>t('delete on save'), 'class' => array('amount')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',        
        ); 

        $form['items']["tax$n"] = array(
        '#type' => 'checkbox',
        '#id' => 'optax'.$n,  
        '#attributes' => array('title'=>t('tax include'), 'class' => array('amount') ),  
        '#default_value' => $d->opt, 
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',             
        ); 
        
        $total = number_format($d->value*$d->quantity,2);
        $grandtotal += ($d->value*$d->quantity);
        if($d->opt == 1) {
          $taxable += ($d->value*$d->quantity);
        }
        
        $form['items']["total$n"] = array(
        '#type' => 'textfield',
        '#id' => 'total'.$n,        
        '#size' => 12,
        '#maxlength' => 255,
        '#default_value' => $total,
        '#attributes' => array('placeholder'=>t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
        '#prefix' => "<div class='cell right'>",
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
    
        $form['items']["description$i"] = array(
        '#type' => 'textfield',
        '#size' => 50,
        '#maxlength' => 255,
        '#default_value' => $form_state->getValue("description$i") ? $form_state->getValue("description$i") : NULL,
        '#attributes' => array('placeholder'=>t('item')),
        '#prefix' => "<div class='container-inline'>",
        '#autocomplete_route_name' => 'ek.look_up_item_ajax',
        '#prefix' => "<div class='row'><div class='cell'>",
        '#suffix' => '</div>',         
        ); 
    
    
if($this->moduleHandler->moduleExists('ek_finance')) {

        $form['items']["account$i"] = array(
        '#type' => 'select',
        '#size' => 1,
        '#options' => $form_state->get('AidOptions'),
        '#required' => TRUE,
        '#default_value' => isset($d->aid) ? $d->aid : NULL,
        '#attributes' => array('style' => array('width:100px;white-space:nowrap')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',         
        ); 

} // finance    
    
        $form['items']["quantity$i"] = array(
        '#type' => 'textfield',
        '#id' => 'quantity'.$i,
        '#size' => 8,
        '#maxlength' => 255,
        '#default_value' => $form_state->getValue("quantity$i") ? $form_state->getValue("quantity$i") : NULL,
        '#attributes' => array('placeholder'=>t('units'), 'class' => array('amount')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',         
        );     

        $form['items']["value$i"] = array(
        '#type' => 'textfield',
        '#id' => 'value'.$i,        
        '#size' => 8,
        '#maxlength' => 255,
        '#default_value' => $form_state->getValue("value$i") ? $form_state->getValue("value$i") : NULL,
        '#attributes' => array('placeholder'=>t('price'), 'class' => array('amount')),
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>', 
        ); 

        $form['items']["delete$i"] = array(
        '#type' => 'item',
        '#attributes' => '',
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',        
        ); 

        $form['items']["tax$i"] = array(
        '#type' => 'checkbox',
        '#id' => 'optax'.$i,  
        '#attributes' => array('title'=>t('tax include'), 'class' => array('amount') ),  
        '#default_value' => 1,
        '#prefix' => "<div class='cell'>",
        '#suffix' => '</div>',      
        ); 

        $total = number_format($form_state->getValue("value$i")*$form_state->getValue("quantity$i"),2);
        $grandtotal += $form_state->getValue("value$i")*$form_state->getValue("quantity$i");
        $form['items']["total$i"] = array(
        '#type' => 'textfield',
        '#id' => 'total'.$i,        
        '#size' => 12,
        '#maxlength' => 255,
        '#attributes' => array('placeholder'=>t('line total'), 'readonly' => 'readonly', 'class' => array('amount', 'right')),
        '#prefix' => "<div class='cell right'>",
        '#suffix' => '</div></div>',
        );             
    }

      $form['items']['count'] = array(
        '#type' => 'hidden',
        '#value' => isset($detail) ? $n-1+$form_state->get('num_items') : $form_state->get('num_items'),
        '#attributes' => array('id' => 'itemsCount'),
      );
      
      $form['items']['closetable'] = array(
        '#type' => 'item',
        '#markup' => '</div>',

      );      
 

    if( ($form_state->get('num_items') && $form_state->get('num_items')>0) || isset($detail)  ) {
    
      if ($form_state->get('num_items') > 0) {
        $form['items']['remove'] = array(
          '#type' => 'submit',
          '#value' => $this->t('remove last item'),
          //'#limit_validation_errors' => array(),
          '#submit' => array(array($this, 'removeForm')),
          '#prefix' => "<div id='remove' class='right'>",
          '#suffix' => '</div>',
          '#attributes' => array('class' => array('button--remove')),
        );     
      } 
      
        $form['items']['foot'] = array(
        '#type' => 'item',
        '#markup' => '<hr>',
        '#prefix' => "<div class='table' id='invoice_form_footer'>",
        );
        
        $form['items']["grandtotal"] = array(
        '#type' => 'textfield',
        '#id' => 'grandtotal',        
        '#size' => 12,
        '#maxlength' => 255,
        '#value' => isset($grandtotal) ?  number_format($grandtotal, 2) : 0,
        '#title' => isset($grandtotal) ? t('Items total') : NULL,
        '#attributes' => array('placeholder'=>t('total'), 'readonly' => 'readonly', 'class' => array('amount')),
        '#prefix' => "<div class='row'><div class='cell right'>",
        '#suffix' => '</div></div>',        
        );       
        
        $taxamount = number_format(($taxable*$data->taxvalue/100), 2) ;
        
        $form['items']["taxamount"] = array(
        '#type' => 'textfield',
        '#id' => 'taxamount',   
        '#title' =>  '',    
        '#size' => 12,
        '#value' => $taxamount,
        '#title' => isset($taxamount) ? t('Tax payable') : NULL,
        '#maxlength' => 255,
        '#attributes' => array('placeholder'=>t('tax'), 'readonly' => 'readonly', 'class' => array('amount')),
        '#prefix' => "<div class='row'><div class='cell right'>",
        '#suffix' => '</div></div>',        
        );    
        
        $form['items']["totaltax"] = array(
        '#type' => 'textfield',
        '#id' => 'totaltax',        
        '#size' => 12,
        '#maxlength' => 255,
        '#value' => number_format($grandtotal+($taxable*$data->taxvalue/100), 2),
        '#title' => isset($grandtotal) ? t('Total invoice') : NULL,
        '#attributes' => array('placeholder'=>t('total invoice'), 'readonly' => 'readonly', 'class' => array('amount')),
        '#prefix' => "<div class='row'><div class='cell right'>",
        '#suffix' => '</div></div></div>',        
        );             
    
        $form['actions'] = array(
          '#type' => 'actions',
        );
        
        $redirect = array(0 => t('view list'), 1 => t('print'), 2 => t('record payment'));

        $form['actions']['redirect'] = array(
         '#type' => 'radios',
         '#title' => t('Next'),
         '#default_value' => 0,
         '#options' => $redirect,

       );        
        
        $form['actions']['record'] = array(
         '#type' => 'submit',
         '#value' => $this->t('Record'),
         '#attributes' => array('class' => array('button--record')),
       );    
    
    }
    
       $form['#attached']['library'][] = 'ek_sales/ek_sales.invoice';     
  
  return $form;
  
  } 

  /**
   * callback functions
   */  
  public function set_coid(array &$form, FormStateInterface $form_state) {

    return $form['options']['bank_account'] ;
    
  }

  /**
   * Callback
   */
  public function check_aid(array &$form, FormStateInterface $form_state) {
      
        $description = '';
        $fx_rate = '';
        $required = FALSE;
        if( $form_state->getValue('currency') ) { 

            if( !$form_state->getValue('head') ) {

                $description = "<div id='fx' class='messages messages--warning'>" 
                    .t('You need to select header first. You cannot proceed.'). "</div>"; 


            } else {  

                $description = '';
                $settings = new CompanySettings($form_state->getValue('head'));
                $aid = $settings->get('asset_account', $form_state->getValue('currency') );

            if($aid == '') {

                $description  = "<div id='fx' class='messages messages--warning'>" 
                    .t('There is no assets account defined for currency. Please contact administrator.'). "</div>"; 

              } else {

                    $fx_rate= CurrencyData::rate($form_state->getValue('currency'));
                    //$input = $form_state->getUserInput();
                    if($fx_rate == '1') {
                            //$required  = FALSE;
                            $form['options']['fx_rate']['#required'] = FALSE;
                            }
                            // not base currency 
                        else {
                            //$required  = TRUE;
                            $form['options']['fx_rate']['#required'] = TRUE;
                            }

              } //else -> aid
            }// else -> coid

        }
        
    $form['options']['fx_rate']['#description'] = $description;
    $form['options']['fx_rate']['#value'] = $fx_rate;    
    return $form['options']['fx_rate'];    
  
  }

  /**
   * Callback
   */
  public function check_tax(array &$form, FormStateInterface $form_state) {
  $settings = new CompanySettings($form_state->getValue('head'));
    if($settings->get('stax_collect') == 1) {
      $form['options']['taxvalue']['#value'] = $settings->get('stax_rate');
    }
  return $form['options']['taxvalue'];
  }

  /**
   * Callback
   */
  public function fx_rate(array &$form, FormStateInterface $form_state) {
  /* if add exchange rate
  */
  }
  
/**
   * Callback : calculate due date 
   */
  public function check_day(array &$form, FormStateInterface $form_state) {
     
      if($form_state->getValue('terms') == '1' && $form_state->getValue('due') != NULL) {
        $form['options']['day']["#markup"] = date('Y-m-d',strtotime(date("Y-m-d", strtotime($form_state->getValue('date')) ) . "+". $form_state->getValue('due') . ' ' . t("days") ));
      } else {
        $form['options']['day']["#markup"] = '';  
      }
      return $form['options']['day'];
  }
    
  /**
   * Callback: Add item to form
   */
  public function addForm(array &$form, FormStateInterface $form_state) {
  if(!$form_state->get('num_items') ) {
    $form_state->set('num_items', 1);
    
    } else {
    $c = $form_state->get('num_items')+1;
    $form_state->set('num_items', $c);
    }
  
  
  if ($this->moduleHandler->moduleExists('ek_finance')) {
      $chart = $this->settings->get('chart');
      $form_state->set('AidOptions', AidList::listaid($form_state->getValue('head'), array($chart['income'],$chart['other_income']), 1 ));
  
  }
  
  $input = $form_state->getUserInput();
  $form_state->setValue('fx_rate', $input['fx_rate']);
  
  $form_state->setRebuild();
  }

  /**
   * Callback: Remove item to form
   */
  public function removeForm(array &$form, FormStateInterface $form_state) {
  
  $c = $form_state->get('num_items')-1;
  $form_state->set('num_items', $c);
  $form_state->setRebuild();


  }
  
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    //input used to update values set by user
    $input = $form_state->getUserInput();
    if($form_state->getValue('fx_rate') != '' && !is_numeric( $form_state->getValue('fx_rate') ) ) {
      $form_state->setErrorByName('fx_rate', $this->t('Exchange rate is wrong') );
    }
  
    if(!$form_state->getValue('tax') == '' && $form_state->getValue('taxvalue') == '') {
      $form_state->setErrorByName('taxvalue', $this->t('Tax value is empty') );
    }

    if($form_state->getValue('tax') == '' && !$form_state->getValue('taxvalue') == '') {
      $form_state->setErrorByName('tax',  $this->t('Tax description is empty') );
    }  

    if(!$form_state->getValue('tax') == '' && !is_numeric($form_state->getValue('taxvalue')) ) {
     $form_state->setErrorByName('taxvalue', $this->t('Tax value should be numeric') );
    } 
    
    if($form_state->getValue('terms') == 1  && $form_state->getValue('due') == '') {
      $form_state->setErrorByName('due', $this->t('Terms days is empty') );
    }      

    if($form_state->getValue('terms') == 1  && !is_numeric($form_state->getValue('due'))) {
      $form_state->setErrorByName('due',  $this->t('Terms days should be numeric') );
    }
    
    for ($n=1;$n<=$form_state->get('num_items');$n++) {
    
            if($form_state->getValue("description$n") == '') {
            $form_state->setErrorByName("description$n", $this->t('Item @n is empty', array('@n'=> $n)) );
            }

            if($form_state->getValue("quantity$n") == '' || !is_numeric($form_state->getValue("quantity$n"))) {
            $form_state->setErrorByName("quantity$n", $this->t('there is no quantity for item @n', array('@n'=> $n)) );
            }
            if($form_state->getValue("value$n") == '' || !is_numeric($form_state->getValue("value$n"))) {
            $form_state->setErrorByName("value$n",  $this->t('there is no value for item @n', array('@n'=> $n)) );
            }            
            //if($this->moduleHandler->moduleExists('ek_finance')) {

                // validate account
                // @TODO
                

            //}          
                
    }
   
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
      
      $options = array('1' => t('Invoice'), '2' => t('Commercial invoice'), '4' => t('Credit note'));

      if ($form_state->getValue('new_invoice') == 1 ) {
        //create new serial No
          
        switch ($form_state->getValue('title')) {
            case '4':
                $type = "-CN-";
                break;
            default:
                $type = "-I-";
                break;
        }
        $iid = Database::getConnection('external_db', 'external_db')
                ->query("SELECT count(id) from {ek_sales_invoice}")
                ->fetchField();
        $iid++;
        $short = Database::getConnection('external_db', 'external_db')
                ->query("SELECT short from {ek_company} where id=:id", array(':id' => $form_state->getValue('head')))
                ->fetchField();
        $date = substr($form_state->getValue('date'), 2,5);
        $sup = Database::getConnection('external_db', 'external_db')
                ->query("SELECT shortname from {ek_address_book} where id=:id", array(':id' => $form_state->getValue('client')))
                ->fetchField();      
        $serial = ucwords(str_replace('-', '', $short)) . $type . $date . "-" .  ucwords(str_replace('-', '',$sup )) . "-" . $iid ;
      
      } else {
      //edit
      $serial = $form_state->getValue('serial');
      $delete = Database::getConnection('external_db', 'external_db')
              ->delete('ek_sales_invoice_details')
              ->condition('serial', $serial)
              ->execute();
      $iid = Database::getConnection('external_db', 'external_db')
              ->query('SELECT id from {ek_sales_invoice} where serial=:s', array(':s' => $serial))
              ->fetchField();
      }
  
  $fx_rate = round($form_state->getValue('fx_rate'),4);
  

  if($this->moduleHandler->moduleExists('ek_finance')) {
      // used to calculate currency gain/loss from rate at invoice record time
      // and linebase
        $baseCurrency = $this->settings->get('baseCurrency');
        if($baseCurrency != $form_state->getValue('currency')) { 
          
          if($fx_rate <> '' && is_numeric($fx_rate)) {
          $currencyRate = $fx_rate;
         
          } else {
           $currencyRate = CurrencyData::rate($form_state->getValue('currency'));
          }
          
        } else {
           $currencyRate = 1;  
        } 
  }
// Items  
  
  $line = 0;
  $total = 0;
  $taxable = 0;
  if($this->moduleHandler->moduleExists('ek_finance')) {
    $journal = new Journal();
    
  }
  for ($n=1;$n<=$form_state->getValue('count');$n++) {
  
    if(!$form_state->getValue("delete$n") == 1) { 
      if($this->moduleHandler->moduleExists('ek_products')) {
        //verify if item is in the DB if not just record input
      
        $item = explode(" ", $form_state->getValue("description$n"));

        $id = trim($item[0]);
        $code = trim($item[1]);
        $description = ItemData::item_description_byid($id, 1); 
        
        if($description) {
        $item = $description;
        $itemdetail = $id;
          } else {
            $item = $form_state->getValue("description$n");
            $itemdetail = '';
          }
      
      } else {
      //use input from user
        $item = $form_state->getValue("description$n");
        $itemdetail = '';
      }
      
    $line = (round($form_state->getValue("quantity$n")*$form_state->getValue("value$n") , 2));
    $linebase = (round($form_state->getValue("quantity$n")*$form_state->getValue("value$n")/$currencyRate, 2));
    $sum = $sum + $line;
    if($form_state->getValue("tax$n") == 1) {
      $taxable = $taxable + $line;
    }
    
    if(!$form_state->getValue("account$n")) {
      $account = 0;
    } else {
      $account = $form_state->getValue("account$n");
    } 

    $fields=array('serial' => $serial,
                  'item' => $item, // description used in displays
                  'itemdetail' => $itemdetail, //add detail / id if item is in DB
                  'quantity' => $form_state->getValue("quantity$n"),
                  'value' => $form_state->getValue("value$n"),
                  'total' => $line,
                  'totalbase' => $linebase,
                  'opt'  => $form_state->getValue("tax$n"),
                  'aid' => $account
                  );
      
    $insert = Database::getConnection('external_db', 'external_db')
      ->insert('ek_sales_invoice_details')
      ->fields($fields)
      ->execute();  
    

    
    }//if not delete
  }//for
  
//main
  
  if($form_state->getValue('due') == '') {$due = 0;
    } else {
      $due = $form_state->getValue('due');
    }
  if($form_state->getValue('pcode') == '') {$pcode = 'n/a';
    } else {
      $pcode = $form_state->getValue('pcode');
    }
  if($form_state->getValue('taxvalue') == '') {$taxvalue = 0;
    } else{
      $taxvalue = $form_state->getValue('taxvalue');
    } 

    if($this->moduleHandler->moduleExists('ek_finance')) {
      $sumwithtax = $sum+(round($taxable*$taxvalue /100, 2));
      if($baseCurrency <> $form_state->getValue('currency')) {
          //calculate the value in base currency of the amount without tax
          $amountbc = round($sum/$currencyRate, 2);
      
      } else {
          $amountbc = $sum;
      }
    
    } else {
        $amountbc = $sum;
    } 
    
    
    $fields1 = array (
                'serial' => $serial,
                'head' => $form_state->getValue('head'),
                'allocation' => $form_state->getValue('allocation'),
                'status' => 0,
                'amount' => $sum,
                'currency' => $form_state->getValue('currency'),
                'date' => $form_state->getValue('date'),
                'title' => $options[$form_state->getValue('title')],
                'type' => $form_state->getValue('title'),
                'pcode' => $pcode,
                'comment' => Xss::filter($form_state->getValue('comment')),
                'client' => $form_state->getValue('client'),
                'amountreceived' => 0,
                'pay_date' => '',
                'amountbase' => $amountbc,
                'balancebase' => $amountbc,
                'terms' => Xss::filter($form_state->getValue('terms')),
                'due' => $due,
                'bank' => $form_state->getValue('bank_account'),
                'tax' => $form_state->getValue('tax'),
                'taxvalue' => $taxvalue,
                'reconcile' => 0,
                );
                    
  if ($form_state->getValue('new_invoice') && $form_state->getValue('new_invoice') == 1 ) {
  $insert = Database::getConnection('external_db', 'external_db')->insert('ek_sales_invoice')
    ->fields($fields1)
    ->execute(); 
  $reference = $insert;
  
  } else {
  $update = Database::getConnection('external_db', 'external_db')->update('ek_sales_invoice')
    ->fields($fields1)
    ->condition('serial' , $serial)
    ->execute();
  $reference = $iid; 
  } 

    //
    // Edit delivery in DO conversion mode
    //
    if($form_state->getValue('do') != NULL) {
        //change  status
        Database::getConnection('external_db', 'external_db')->update('ek_logi_delivery')
        ->fields(array('status' => 2))
        ->condition('serial',$form_state->getValue('do'))
        ->condition('status', '1')
        ->execute();
    
    }
    //
    // Record the accounting journal
    // Credit  notes are not recorded in journal, only once assigned to sales
    // (a CN is deduction of receivable) 
    //
    if($form_state->getValue('title') < 4 
            && $this->moduleHandler->moduleExists('ek_finance')) {
        
        //
        // delete first
        //          
        if ( !$form_state->getValue('new_invoice') == 1 ) {
          $delete = Database::getConnection('external_db', 'external_db')
                  ->delete('ek_journal')
                  ->condition('reference', $iid)
                  ->condition('source', 'invoice')
                  ->execute();
        }
        
        
      for ($n=1;$n<=$form_state->getValue('count');$n++) {
      
        if(!$form_state->getValue("delete$n") == 1) {    
          if ($form_state->getValue('taxvalue') > 0 && $form_state->getValue("tax$n") == 1) {
            $tax = round($form_state->getValue("value$n")*$form_state->getValue("quantity$n")*$form_state->getValue('taxvalue')/100,2);
            
            } else {
             $tax = 0;
            }
          $line = (round($form_state->getValue("quantity$n")*$form_state->getValue("value$n"),2));
          $journal->record(
                  array(
                  'source' => "invoice",
                  'coid' => $form_state->getValue('head'),
                  'aid' => $form_state->getValue("account$n"),
                  'reference' => $reference,
                  'date' => $form_state->getValue('date'),
                  'value' => $line,
                  'currency' => $form_state->getValue('currency'),
                  'fxRate' => $currencyRate,
                  'tax' => $tax,
                   )
                  );   
        
        }

        
      } //for
      
        $journal->recordtax(
                  array(
                  'source' => "invoice",
                  'coid' => $form_state->getValue('head'),
                  'reference' => $reference,
                  'date' => $form_state->getValue('date'),
                  'currency' => $form_state->getValue('currency'),
                  'fxRate' => $currencyRate,
                  'type' => 'stax_collect_aid',  
                  )      
        
        );      
   
    }  
  
  Cache::invalidateTags(['project_page_view']);
  if (isset($insert) || isset($update) )  {
      drupal_set_message(t('The @doc is recorded. Ref. @r', array('@r' => $serial, '@doc' => $options[$form_state->getValue('title')])), 'status');
  }
        switch($form_state->getValue('redirect')) {
            case 0 :
                $form_state->setRedirect('ek_sales.invoices.list');
                break;
            case 1 :
                $form_state->setRedirect('ek_sales.invoices.print_share', ['id' => $reference]);
                break;
            case 2 :
                $form_state->setRedirect('ek_sales.invoices.pay', ['id' => $reference]);
                break;
        }
           
  }

  
}
 
