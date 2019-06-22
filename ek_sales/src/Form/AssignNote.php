<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\AssignNote.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_address_book\AddressBookData;

/**
 * Provides a form to record a credit or debit note value into sales.
 * A credit note is a discount given to client on sales.
 * A debit note is a discount given by client to purchaser
 */
class AssignNote extends FormBase {

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
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $this->settings = new FinanceSettings();
            $this->journal = new Journal();
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
        return 'ek_sales_assign_note';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $note = NULL, $id = NULL) {
        
        if($note == "CT") {
           $form_state->set('table',"ek_sales_invoice");
           $form_state->set('table_details',"ek_sales_invoice_details");
           $url = Url::fromRoute('ek_sales.invoices.list', array(), array())->toString(); 
            
        } else {
           $form_state->set('table',"ek_sales_purchase");
           $form_state->set('table_details',"ek_sales_purchase_details");            
           $url = Url::fromRoute('ek_sales.purchases.list', array(), array())->toString();
        }
        
       
        $form['back'] = array(
          '#type' => 'item',
          '#markup' => t('<a href="@url" >List</a>', array('@url' => $url ) ) ,

        );
    
        //collect this note info
        $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table'), 't');
        
        $query->fields('t');
        $query->condition('id', $id);
        $Obj = $query->execute();
        $data = $Obj->fetchObject();
        $form_state->set('cn_data', $data);//store note data
        
        if ($data->taxvalue > 0) {
            $query = "SELECT sum(quantity*value) from {$form_state->get('table_details')} WHERE serial=:s and opt=:o";
            $details = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':s' => $data->serial, ':o' => 1))
                    ->fetchField();
            $cn_amount = $data->amount + ($details * $data->taxvalue / 100) - $data->amountreceived;
            $title = t('Amount with taxes (@c)', array('@c' => $data->currency));
        } else {
            $cn_amount = $data->amount - $data->amountreceived;
            $title = t('Amount (@c)', array('@c' => $data->currency));
        }
        $form['balance'] = array(
            '#type' => 'hidden',
            '#value' => $cn_amount,
        );
        
        //collect invoices / purchases to which CN / DN can be assigned
        $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table'), 't');
            $or = $query->orConditionGroup();
                $or->condition('status', '2', '=');
                $or->condition('status', '0', '=');
            $query->fields('t', ['id', 'serial']);
            $query->condition('head', $data->head, '=');
            $query->condition($or);
            $query->condition('type', 3, '<');
            $query->condition('currency', $data->currency, '=');
            $query->condition('client', $data->client, '=');
            
            $Obj = $query->execute();
            

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $baseCurrency = $this->settings->get('baseCurrency');
        }

        $form['edit_invoice'] = array(
            '#type' => 'item',
            '#markup' => t('@doc ref. @p', array('@doc' => $data->title, '@p' => $data->serial)),
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );
        
        $form['assign'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $Obj->fetchAllKeyed(),
                '#required' => TRUE,
                '#title' => t('Credit invoice assignment'),
                '#ajax' => array(
                    'callback' => array($this, 'note_details'),
                    'wrapper' => 'div_note_details',
                ),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => '</div></div>',
            
        ); 
        $form['note_details'] = array(
                '#type' => 'item',
                '#prefix' => "<div class='row'><div class = 'cell current blue' id='div_note_details'>",
                '#suffix' => '</div></div>',
 
            );        

        $form['date'] = array(
            '#type' => 'date',
            '#id' => 'edit-from',
            '#size' => 12,
            '#required' => TRUE,
            '#default_value' => date('Y-m-d'),
            '#title' => t('Payment date'),
            '#prefix' => "<div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );
        
    

        $form['amount'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#disabled' => TRUE,
            '#default_value' => number_format($cn_amount, 2),
            '#title' => $title,
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div>',
        );

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if ($data->currency != $baseCurrency) {
                $form['fx_rate'] = array(
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#maxlength' => 255,
                    '#default_value' => CurrencyData::rate($data->currency),
                    '#required' => TRUE,
                    '#title' => t('Base currency exchange rate'),
                    '#description' => '',
                    '#attributes' => array('class' => array('amount')),
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div></div>',
                );
            } else {
                $form['fx_rate'] = array(
                    '#type' => 'hidden',
                    '#value' => 1,
                    '#prefix' => "<div class='cell'>",
                    '#suffix' => '</div></div></div>',
                );
            }
        } else {
            $form['fx_rate'] = array(
                '#type' => 'hidden',
                '#value' => 1,
                '#prefix' => "<div class='cell'>",
                '#suffix' => '</div></div></div>',
            );
        }

               

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );

        $form['#attached']['library'][] = 'ek_sales/ek_sales_css';


        return $form;
    }



    /**
     * Callback: on selected note provide document details
     */
    public function note_details(array &$form, FormStateInterface $form_state) {


        $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table'), 't');
            
            $query->fields('t');
            $query->condition('id', $form_state->getValue('assign'), '=');
            $Obj = $query->execute();
            
          
        $data = $Obj->fetchObject(); 
        $form_state->set('assign_data', $data);//store data of assigned invoice/purchase
        

        if ($data->taxvalue > 0) {
            
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table_details'), 't');
            
            $query->condition('serial', $data->serial, '=');
            $query->condition('opt', '1', '=');
            $query->addExpression('SUM(quantity*value)', 'sumq');
            $items = $query->execute();
            $sum = $items->fetchField();
            
            if($form_state->get('table') == 'ek_sales_invoice'){
                $balance = $data->amount + ($sum * $data->taxvalue / 100) - $data->amountreceived;
            } else {
                $balance = $data->amount + ($sum * $data->taxvalue / 100) - $data->amountpaid;
            }
            
            $val = t('Amount with taxes @c', array('@c' => number_format($balance,2)));
        } else {
            if($form_state->get('table') == 'ek_sales_invoice'){
                $balance = $data->amount - $data->amountreceived;
            } else {
                $balance = $data->amount - $data->amountpaid;
            }
            
            $val = t('Amount @c', array('@c' => number_format($balance,2)));
        }
      
        $markup = t('Value') . " " . number_format($data->amount,2) . " " . $data->currency . "<br/>";
        $markup .= t('Balance') . ": " . $val  . " " . $data->currency . "<br/>";
        if(isset($data->pcode) && $data->pcode != 'n/a') {
            $markup .= t('Project') . ": " . \Drupal\ek_projects\ProjectData::geturl($data->pcode) . "<br/>";
        }
        
        $client_name = AddressBookData::getname($data->client); 
        $markup .= t('Client') . ': ' . AddressBookData::geturl($data->client);
        $form['note_details']['#description'] = '';
        if($form_state->getValue('amount') > $balance) {
            $form['note_details']['#description'] = "<br/><div class='red'>" . t('This credit is higher than the invoice balance.') . '</div>';
            
        }
        
       
        $form['note_details']['#markup'] = $markup;

        return $form['note_details'];
        
    }

    
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($this->moduleHandler->moduleExists('ek_finance')) {
            if ($form_state->getValue('fx_rate') <= 0 || !is_numeric($form_state->getValue('fx_rate'))) {
                $form_state->setErrorByName("fx_rate", $this->t('the base exchange rate value input is wrong'));
            }
        }
        
        //verify amount paid does not exceed amount due or partially paid
        //
        $this_cn = str_replace(",", "", $form_state->getValue('amount'));
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table'), 't');    
            $query->fields('t');
            $query->condition('id', $form_state->getValue('assign'), '=');
            $Obj = $query->execute();
            
        $assign_data = $Obj->fetchObject(); 
        
        if($assign_data->taxvalue != $form_state->get('cn_data')->taxvalue) {
            //force to Credit note payment with tax value if original assigned invoice as tax included
            if($form_state->get('table') == 'ek_sales_invoice'){
                $form_state->setErrorByName("amount", 
                    $this->t('Tax rate of invoice and credit note are different. Edit credit note or select another invoice.'));
            } else {
                $form_state->setErrorByName("amount", 
                    $this->t('Tax rate of purchase and debit note are different. Edit debit note or select another purchase.'));
            }
        }
        
        $form_state->setValue('assign_data', $assign_data);//store data of assigned note
  
        if ($assign_data->taxvalue > 0) {
            
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table_details'), 't');
            
            $query->condition('serial', $assign_data->serial, '=');
            $query->condition('opt', '1', '=');
            $query->addExpression('SUM(quantity*value)', 'sumq');
            $items = $query->execute();
            $sum = $items->fetchField();
            if($form_state->get('table') == 'ek_sales_invoice'){
                $balance = $assign_data->amount + ($sum * $assign_data->taxvalue / 100) - $assign_data->amountreceived;
            } else {
                $balance = $assign_data->amount + ($sum * $assign_data->taxvalue / 100) - $assign_data->amountpaid;
            }
            
        } else {
            if($form_state->get('table') == 'ek_sales_invoice'){
                $balance = $assign_data->amount - $assign_data->amountreceived;
            } else {
                $balance = $assign_data->amount - $assign_data->amountpaid;
            }
            
        }       
        
        $form_state->set('max_receivable', $balance);
//        $form_state->set('sum_received', $details);
     
        if (round($this_cn,5) > round($balance,5)) {
            $form_state->setErrorByName('amount', $this->t('Note value exceeds document amount'));
        }
        
        //validate against partial payments      
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            /*
             * TODO check against journal
             */
            /*
            $companysettings = new CompanySettings($assign_data->head);
            $assetacc = $companysettings->get('asset_account', $assign_data->currency);
            
            $a = array(
                'source_dt' => 'invoice',
                'source_ct' => 'receipt',
                'reference' => $form_state->getValue('assign'),
                'account' => $assetacc,
            );
            $value = round($this->journal->checktransactioncredit($a), 4);
            if (round($value + $this_cn, 4) > 0) {
                $a = ['@a' => $value, '@b' => $this_cn, '@c' => $assetacc];
                $form_state->setErrorByName('amount', $this->t('this payment exceeds receivable balance amount in journal (@a, @b, @c).', $a));
            }
             
             */
        } else {
            /*
            if (($this_cn + $data->amountpaid) > $max_pay) {
                $form_state->setErrorByName('amount', $this->t('payment exceeds invoiced amount'));
            }
            
             */
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        
        $assign_data  = $form_state->getValue('assign_data');
        $cn_data  = $form_state->get('cn_data');
        $this_cn = $cn_data->amount; //This value is WHITOUT tax if note was recorded with tax
        $this_cn_tax = round($this_cn + ($this_cn * $cn_data->taxvalue / 100),2);
        
        //get sales account from items details
        //limitation: credit/debit note should have only one item line for debit
        //if multiple lines, we take only the account of the first line for journal record
        
         $query = Database::getConnection('external_db', 'external_db')
                    ->select($form_state->get('table_details'), 't');    
            $query->fields('t', ['aid']);
            $query->condition('serial', $cn_data->serial, '=');
            $query->orderBy('id', 'ASC');
            $Obj = $query->execute();
            $aid = $Obj->fetchField();
  
        $max_receivable = round($form_state->get('max_receivable'), 2);
        $taxable = round($form_state->get('sum_received') * $this_cn / $max_receivable, 4);
        $original_fx_rate = round($cn_data->amount / $cn_data->amountbase, 4); //original rate used to calculate currency gain/loss


        if ($this->moduleHandler->moduleExists('ek_finance') && $this_cn > 0) {
            $currencyRate = CurrencyData::rate($cn_data->currency);
            $baseCurrency = $this->settings->get('baseCurrency');
            if($form_state->get('table') == 'ek_sales_invoice'){
                $source = "credit note";
                $comment = 'CN invoice ' . $assign_data->serial;
            } else {
                $source = "debit note";
                $comment = 'DN purchase ' . $assign_data->serial;
            }
            
            /**/
            $this->journal->record(
                    array(
                        'source' => $source,
                        'coid' => $cn_data->head,
                        'aid' => $aid,
                        'reference' => $cn_data->id,
                        'date' => $form_state->getValue('date'),
                        'value' => $this_cn,
                        'taxable' => $taxable,
                        'tax' => $cn_data->taxvalue,
                        'currency' => $cn_data->currency,
                        'rate' => $original_fx_rate,
                        'fxRate' => $form_state->getValue('fx_rate'),
                        'comment' => $comment,
                    )
            );
            
             
             
        }

        
        if ($this_cn_tax == $max_receivable 
                || $form_state->getValue('close') == 1) {
            $paid = 1; //full payment
            
        } else {
            $paid = 2; // partial payment (can't edit anymore)
        }

        $balancebase = round($assign_data->balancebase - ($this_cn / $original_fx_rate), 2);
        if($form_state->get('table') == 'ek_sales_invoice'){
            $fields = array(
                'status' => $paid,
                'amountreceived' => $assign_data->amountreceived + $this_cn_tax,
                'balancebase' => $balancebase,
                'pay_date' => $form_state->getValue('date'),
            );
        } else {
           $fields = array(
                'status' => $paid,
                'amountpaid' => $assign_data->amountpaid + $this_cn_tax,
                'balancebc' => $balancebase,
                'pdate' => $form_state->getValue('date'),
            ); 
        }

        $update = Database::getConnection('external_db', 'external_db')
                ->update($form_state->get('table'))->fields($fields)
                ->condition('id', $assign_data->id)
                ->execute();
        /*
         * note value is applied 100%. Thus status and balance are 1 & 0
         */
        if($form_state->get('table') == 'ek_sales_invoice'){
            $fields = array(
                'status' => 1,
                'amountreceived' => $this_cn,
                'balancebase' => 0,
                'pay_date' => $form_state->getValue('date'),
            ); 
        } else {
            $fields = array(
                'status' => 1,
                'amountpaid' => $this_cn,
                'balancebc' => 0,
                'pdate' => $form_state->getValue('date'),
            );             
        }

       /**/
        $update = Database::getConnection('external_db', 'external_db')
                ->update($form_state->get('table'))->fields($fields)
                ->condition('id', $cn_data->id)
                ->execute();
        
        if ($update) {
            
            /*
            if ($this->moduleHandler->moduleExists('ek_projects')) {
                //notify user if invoice is linked to a project
                if ($data->pcode && $data->pcode != 'n/a') {
                    $pid = Database::getConnection('external_db', 'external_db')
                            ->query('SELECT id from {ek_project} WHERE pcode=:p', [':p' => $data->pcode])
                            ->fetchField();
                    $param = serialize(
                            array(
                                'id' => $pid,
                                'field' => 'invoice_payment',
                                'value' => $data->serial,
                                'pcode' => $data->pcode
                            )
                    );
                    \Drupal\ek_projects\ProjectData::notify_user($param);
                }
            }
            */
            if($form_state->get('table') == 'ek_sales_invoice'){
                $form_state->setRedirect('ek_sales.invoices.list');
            } else {
                $form_state->setRedirect('ek_sales.purchases.list');
            }
            
        }
 
    }

}
