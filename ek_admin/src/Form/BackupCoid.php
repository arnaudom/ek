<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\BackupCoid
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form.
 */
class BackupCoid extends FormBase {

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
        return 'ek_admin_backup_by_coid';
    }

    /**
     * 
     * id structure : pcode|query|type
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $coid = NULL) {


        $company = AccessCheck::CompanyListByUid();


        if (!$coid == NULL && !$company[$coid] == '') {

            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $coid,
            );
            
            $query = "SELECT name FROM {ek_company} WHERE id=:id";
            $c = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $coid))
                ->fetchObject();
            $form['company'] = array(
                '#type' => 'item',
                '#markup' => '<h2>' . t('Company') . ': ' . $c->name . '</h2>',
            );
            $form['eof'] = array(
                '#type' => 'select',
                '#options' => ['0' => t('none'), 'chr' => 'chr', 'PHP_EOL' => 'PHP_EOL', '\n' => '\n', '\r' => '\r', '\r\n' => '\r\n'],
                '#required' => TRUE,
                '#title' => t('End of file mark'),
            );
            $form['actions'] = array('#type' => 'actions');
            $form['actions']['upload'] = array(
                '#id' => 'upbuttonid1',
                '#type' => 'submit',
                '#value' => t('Backup @c data', ['@c' => $company[$coid]]),
                '#ajax' => array(
                    'callback' => array($this, 'backup'),
                    'wrapper' => 'message',
                    'method' => 'replace'
                ),
            );

            $form['section']['div'] = array(
                '#type' => 'item',
                '#prefix' => '<div id="message" class="" >',
            );

            $form['section']['sql'] = array(
                '#type' => 'item',
                
                '#markup' => NULL,
            );

            $form['section']['/div'] = array(
                '#type' => 'item',
                '#suffix' => '</div>',
            );
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
    public function backup(array &$form, FormStateInterface $form_state) {

        $file = '';
        $coid = $form_state->getValue('coid');
        $lineEnd = ($form_state->getValue('eof') == '0') ? '' : $form_state->getValue('eof');
        $lineEnd = 'chr' ? chr(13) . chr(10) : $lineEnd;
  
            
            
            ////////////////
            //company
            ////////////////
            $table = 'ek_company';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`access`,`settings`,`name`,`reg_number`,`address1`,`address2`,`address3`,`address4`,`city`,"
                    . "`city2`,`postcode`,`postcode2`,`country`,`country2`,`telephone`,"
                    . "`telephone2`,`fax`,`fax2`,`email`,`contact`,`mobile`,`logo`,"
                    . "`favicon`,`sign`,`short`,`accounts_year`,`accounts_month`,"
                    . "`active`,`itax_no`,`pension_no`,`social_no`,`vat_no`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE id=:c';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
          
            ////////////////
            //company docs
            ////////////////
            $table = 'ek_company_documents';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`coid`,`fid`,`filename`,`uri`,`comment`,`date`,`size`,`share`,`deny`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
        if ($this->moduleHandler->moduleExists('ek_finance')) {

            ////////////////
            //accounts chart
            ////////////////
            $table = 'ek_accounts';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`aid`,`aname`,`atype`,`astatus`,`coid`,`link`,`balance`,`balance_base`,`balance_date`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by aid';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
 
            ////////
            //assets
            ////////

            $table = 'ek_assets';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`asset_name`,`asset_brand`,`asset_ref`,`coid`,`unit`,`aid`,`asset_comment`,`asset_doc`,`asset_pic`,"
                    . "`asset_value`, `currency`, `date_purchase`, `eid`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            $table = 'ek_assets_amortization';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`asid`,`term_unit`,`method`,`term`,`amort_rate`,`amort_value`,`amort_salvage`,`amort_record`,`amort_status`,"
                    . "`alert`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_assets} b ON '
                    . ''. $table .'.asid = b.id WHERE coid=:c ORDER by ' . $table . '.asid';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            ////////
            //bank
            ////////

            $table = 'ek_bank';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`name`,`address1`,`address2`,`postcode`,`country`,`contact`,`telephone`,`fax`,`email`,"
                    . "`account1`, `account2`, `swift`, `bank_code`, `coid`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';
             
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
       

            ///////////////
            //bank accounts
            ///////////////

            $table = 'ek_bank_accounts';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = $table .".`id`,`account_ref`,`currency`,`bid`,`aid`, `active`, `beneficiary`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_bank} b ON '
                    . ''. $table .'.bid = b.id WHERE coid=:c ORDER by ' . $table . '.id';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);

            ///////////////
            //bank accounts transactions
            ///////////////

            $table = 'ek_bank_transactions';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = $table .".`id`," . $table .".`account_ref`,`date_transaction`,`year_transaction`,`type`,"
                    . $table .".`currency`,`amount`,`description`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_bank_accounts} ba ON '
                    . ''. $table .'.account_ref = ba.account_ref '
                    . 'LEFT JOIN {ek_bank} b ON '
                    . 'ba.bid = b.id '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
            
            ///////////////
            //cash
            ///////////////

            $table = 'ek_cash';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`date`,`pay_date`,`type`,`amount`,`cashamount`,`currency`,`coid`,`baid`,`uid`,`comment`,`reconcile`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
          
            ///////////////
            //currency
            ///////////////

            $table = 'ek_currency';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`currency`,`name`,`rate`,`active`,`date`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' ORDER by id';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
        
            ///////////////
            //expenses
            ///////////////

            $table = 'ek_expenses';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`class`,`type`,`allocation`,`company`,`localcurrency`,`rate`,`amount`,`currency`,`amount_paid`,`tax`,`year`,"
                    . "`month`,`comment`,`pcode`,`clientname`,`suppliername`,`receipt`,`employee`,`status`,`cash`,"
                    . "`pdate`,`reconcile`,`attachment`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE company=:c ORDER by id';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
          
            ///////////////
            //expenses memo
            ///////////////

            $table = 'ek_expenses_memo';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`serial`,`category`,`entity`,`entity_to`,`client`,`pcode`,`mission`,`budget`,`refund`,`invoice`,`date`,"
                    . "`pdate`,`status`,`value`,`currency`,`value_base`,`amount_paid`,`amount_paid_base`,`comment`,`reconcile`,"
                    . "`post`,`auth`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE entity=:c ORDER by id';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            ////////////////////
            //expenses memo list
            ////////////////////

            $table = 'ek_expenses_memo_list';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table . ".`serial`,`aid`,`description`,`amount`," . $table . ".`value_base`,`receipt`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' INNER JOIN {ek_expenses_memo} b ON '
                    . ''. $table .'.serial = b.serial WHERE entity=:c ORDER by ' . $table . '.id';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
                 

            ////////////////////
            //expenses memo doc
            ////////////////////

            $table = 'ek_expenses_memo_documents';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table . ".`serial`,`uri`,`doc_date`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' INNER JOIN {ek_expenses_memo} b ON '
                    . ''. $table .'.serial = b.serial WHERE entity=:c ORDER by ' . $table . '.id';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
           
            ////////////////////
            //finance settings
            ////////////////////

            $table = 'ek_finance';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "`id`,`settings`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
            
           
            ////////////////////
            //items
            ////////////////////

            $table = 'ek_items';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`coid`,`type`,`itemcode`,`description1`,`description2`,`supplier_code`,`active`, "
                    . "`collection`, `department`, `family`, `size`, `color`,`supplier`,`stamp`,`format`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by ' . $table . '.id';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);              
           
            ////////////////////////
            //item barcodes
            ////////////////////////

            $table = 'ek_item_barcodes';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "" . $table . ".id," . $table . ".itemcode,`barcode`,`encode`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_items} ON ek_items.itemcode = ' . $table . '.itemcode '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
           
            ////////////////////////
            //item barcodes
            ////////////////////////

            $table = 'ek_item_barcodes';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "" . $table . ".id," . $table . ".itemcode,`barcode`,`encode`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_items} ON ek_items.itemcode = ' . $table . '.itemcode '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
                                      
            ////////////////////
            //item images
            ////////////////////

            $table = 'ek_item_images';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
           $fields = "" . $table . ".id," . $table . ".itemcode,`uri`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_items} ON ek_items.itemcode = ' . $table . '.itemcode '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
                                      
            ////////////////////
            //item packing
            ////////////////////

            $table = 'ek_item_packing';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
           $fields = "" . $table . ".id," . $table . ".itemcode,`units`,`unit_measure`,"
                   . "`item_size`,`pack_size`,`qty_pack`,`c20`,`c40`,`min_order`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_items} ON ek_items.itemcode = ' . $table . '.itemcode '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
           
                                      
            ////////////////////
            //item prices
            ////////////////////

            $table = 'ek_item_prices';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
           $fields = "" . $table . ".id," . $table . ".itemcode,`purchase_price`,`currency`,`date_purchase`,"
                   . "`selling_price`,`promo_price`,`discount_price`,`exp_selling_price`,`exp_promo_price`,`exp_discount_price`,"
                   . "`loc_currency`,`exp_currency`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_items} ON ek_items.itemcode = ' . $table . '.itemcode '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
           
                                      
            ////////////////////
            //item prices history
            ////////////////////

            $table = 'ek_item_price_history';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
           $fields = "" . $table . ".id," . $table . ".itemcode,`date`,`price`,`currency`,"
                   . "" . $table . ".`type`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_items} ON ek_items.itemcode = ' . $table . '.itemcode '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
                       
            ////////////////////////
            //journal reconciliation
            ////////////////////////

            $table = 'ek_journal_reco_history';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "`id`,`type`,`date`,`aid`,`coid`,`data`,`uri`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);              
           
            ////////////////////////
            //journal trail
            ////////////////////////

            $table = 'ek_journal_trail';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "" . $table . ".id,`jid`,`username`,`action`,`timestamp`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' '
                    . 'LEFT join {ek_journal} ON ek_journal.id = ' . $table . '.jid '
                    . 'WHERE coid=:c ORDER by ' . $table . '.id ';
            
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
            
            /* check if there are archive tables 
             * to do this we arbitrary browse 20 years back
             */
            $year = date('Y');
            for($past = 1; $past <= 20; $past++) {
                $y = $year - $past;
                $archive = "ek_journal_" . $y . "_" . $coid;
                $query = "SHOW TABLES LIKE '" . $archive . "'";
                $table = Database::getConnection('external_db', 'external_db')
                            ->query($query)->fetchField();
                
                if($table == $archive) {
                    $file .= " #--------------------------------------------------------" . $lineEnd;
                    $file .= " # Table  " . $table . $lineEnd;
                    $file .= " #--------------------------------------------------------" . $lineEnd;

                    $fields = $table .".`id`,`aid`,`count`,`exchange`,`coid`,`type`,`source`,`reference`, "
                            . "`date`, `value`, `reconcile`, `currency`, `comment`";
                    $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by ' . $table . '.id';

                    $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
                }
            }
            
           
            ////////////////////
            //budget
            ////////////////////

            $table = 'ek_yearly_budget';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "`reference`,`value_base`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE reference like :c ';
            $condition = '%-' . $coid . '-%';
            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd, $condition);  
          
            
       } //finance 

        if ($this->moduleHandler->moduleExists('ek_sales')) {  
            ////////////////////
            //invoice
            ////////////////////

            $table = 'ek_sales_invoice';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`serial`,`do_no`,`po_no`,`head`,`allocation`,`status`,`amount`, "
                    . "`currency`, `date`, `title`, `type`, `pcode`, `comment`,`client`,"
                    . "`amountreceived`,`pay_date`,`class`,`amountbase`,`balancebase`,`terms`,"
                    . "`due`,`bank`,`tax`,`taxvalue`,`reconcile`,`alert`,`alert_who`,`balance_post`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE head=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
        
           
            ////////////////////
            //invoice details
            ////////////////////

            $table = 'ek_sales_invoice_details';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table .".`serial`,`item`,`itemdetail`,`value`,`quantity`,`total`, "
                    . "`totalbase`, `opt`, `aid`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_sales_invoice} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            

                       
            ////////////////////
            //invoice tasks
            ////////////////////

            $table = 'ek_sales_invoice_tasks';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table .".`serial`,`event`,`uid`,`task`,`weight`,`start`, "
                    . "`end`, `completion_rate`,`notify`,`notify_who`,`notify_when`,`note`,`color`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_sales_invoice} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            
            ////////////////////
            //purchase
            ////////////////////

            $table = 'ek_sales_purchase';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`serial`,`head`,`allocation`,`status`,`amount`, "
                    . "`currency`, `date`, `title`, `type`, `pcode`, `comment`,`client`,"
                    . "`amountpaid`,`amountbc`,`balancebc`,`bank`,`tax`,`taxvalue`,"
                    . "`terms`,`due`,`pdate`,`pay_ref`,`reconcile`,`alert`,`alert_who`,`uri`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE head=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
        
           
            ////////////////////
            //purchase details
            ////////////////////

            $table = 'ek_sales_purchase_details';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table .".`serial`,`item`,`itemdetail`,`value`,`quantity`,`total`, "
                    . "`opt`, `aid`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_sales_purchase} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
           
            ////////////////////
            //purchase tasks
            ////////////////////

            $table = 'ek_sales_purchase_tasks';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table .".`serial`,`event`,`uid`,`task`,`weight`,`start`, "
                    . "`end`, `completion_rate`,`notify`,`notify_who`,`notify_when`,`note`,`color`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_sales_purchase} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);


            ////////////////////
            //quotation
            ////////////////////
  
            $table = 'ek_sales_quotation';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`serial`,`head`,`allocation`,`status`,`amount`, "
                    . "`currency`, `date`, `title`, `pcode`, `comment`,`client`,"
                    . "`incoterm`,`tax`,`bank`,`principal`,`type`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE head=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
        
       
            ////////////////////
            //quotation details
            ////////////////////

            $table = 'ek_sales_quotation_details';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table .".`serial`,`itemid`,`itemdetails`,`weight`,`unit`,`value`, "
                    . "`total`, `revision`,`opt`,`column_2`,`column_3`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_sales_quotation} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);     
            
            ////////////////////
            //quotation settings
            ////////////////////
 
            $table = 'ek_sales_quotation_settings';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`field`,`name`,`active`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table ;

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);  
            
            ////////////////
            //sales docs
            ////////////////
           
            $query = "SELECT distinct client FROM {ek_sales_invoice} WHERE head=:c";
            $abid1 = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $coid])
                    ->fetchCol();
        
            $query = "SELECT distinct client FROM {ek_sales_purchase} WHERE head=:c";
            $abid2 = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $coid])
                    ->fetchCol();

            $query = "SELECT distinct client FROM {ek_sales_quotation} WHERE head=:c";
            $abid3 = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $coid])
                    ->fetchCol();
         
            $abid_sales = array_merge((array)$abid1, (array)$abid2, (array)$abid3);
            
            $condition = implode(',', $abid_sales);  
        
            $table = 'ek_sales_documents';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;

            $fields = "`id`,`abid`,`fid`,`filename`,`uri`,`comment`,`date`,`size`,`share`,`deny`,`folder`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE  FIND_IN_SET (abid, :c ) ORDER by id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd, $condition);            
       
            ////////////////////
            //sales settings
            ////////////////////
 
            $table = 'ek_sales_settings';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "`coid`,`settings`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);              
        }//sales     
       
        if ($this->moduleHandler->moduleExists('ek_hr')) {
       
            ////////////////////
            //hr workforce
            ////////////////////

            $table = 'ek_hr_workforce';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`custom_id`,`company_id`,`origin`,`name`,`given_name`,`surname`, "
                    . "`email`, `address`, `telephone`, `sex`, `rank`,`ic_no`,"
                    . "`ic_type`,`birth`,`epf_no`,`socso_no`,`itax_no`,`itax_c`,"
                    . "`e_status`,`location`,`service`,`bank`,`bank_account`,`bank_account_status`,`thirdp`,`active`,"
                    . "`start`,`resign`,`contract_expiration`,`currency`,`salary`,`th_salary`,`aleave`,"
                    . "`mcleave`,`archive`,`picture`,`administrator`,`default_ps`,`note`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE company_id=:c ORDER by id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
                    
            ////////////////////
            //hr workforce pay
            ////////////////////

            $table = 'ek_hr_workforce_pay';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`month`,`d_pay`,`n_days`,`basic`,`n_ot_days`, "
                    . "`n_ot_val`, `r_day`, `r_day_val`, `ph_day`, `ph_day_val`,`mc_day`,"
                    . "`mc_day_val`,`xr_hours`,`xr_hours_val`,`tleave`,`custom_aw1`,`custom_aw2`,"
                    . "`custom_aw3`,`custom_aw4`,`custom_aw5`,`custom_aw6`,`custom_aw7`,`custom_aw8`,"
                    . "`custom_aw9`,`custom_aw10`,"
                    . "`custom_aw11`,`custom_aw12`,`custom_aw13`,`commission`,`turnover`,`gross`,"
                    . "`no_payday`,`less_hours`,`less_hours_val`,`advance`,`custom_d1`,"
                    . "`custom_d2`,`custom_d3`,`custom_d4`,`custom_d5`,`custom_d6`,`custom_d7`,"
                    . "`epf_yee`,`socso_yee`,`deduction`,`nett`,`epf_er`,`socso_er`,`incometax`,"
                    . "`with_yer`,`with_yee`,`comment`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_hr_workforce} b ON '
                    . ''. $table .'.id = b.id WHERE company_id=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);              
            
            
            ////////////////////
            //hr workforce post
            ////////////////////

            $table = 'ek_hr_post_data';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`emp_id`,`month`,`d_pay`,`n_days`,`basic`,`n_ot_days`, "
                    . "`n_ot_val`, `r_day`, `r_day_val`, `ph_day`, `ph_day_val`,`mc_day`,"
                    . "`mc_day_val`,`xr_hours`,`xr_hours_val`,`tleave`,`custom_aw1`,`custom_aw2`,"
                    . "`custom_aw3`,`custom_aw4`,`custom_aw5`,`custom_aw6`,`custom_aw7`,`custom_aw8`,"
                    . "`custom_aw9`,`custom_aw10`,"
                    . "`custom_aw11`,`custom_aw12`,`custom_aw13`,`commission`,`turnover`,`gross`,"
                    . "`no_payday`,`less_hours`,`less_hours_val`,`advance`,`custom_d1`,"
                    . "`custom_d2`,`custom_d3`,`custom_d4`,`custom_d5`,`custom_d6`,`custom_d7`,"
                    . "`epf_yee`,`socso_yee`,`deduction`,`nett`,`epf_er`,`socso_er`,`incometax`,"
                    . "`with_yer`,`with_yee`,`comment`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_hr_workforce} b ON '
                    . ''. $table .'.emp_id = b.id WHERE company_id=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);              
            
            
            ///////////////////////
            //hr workforce location
            ///////////////////////

            $table = 'ek_hr_location';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`coid`,`location`,`description`,`turnover`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
            
            ///////////////////////
            //hr document
            ///////////////////////

            $table = 'ek_hr_documents';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`employee_id`,`fid`,`filename`,`uri`,`filemime`,`type`,`comment`,`date`,`size`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_hr_workforce} b ON '
                    . ''. $table .'.employee_id = b.id WHERE company_id=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
            
            
            ///////////////////////
            //hr payroll cycle
            ///////////////////////

            $table = 'ek_hr_payroll_cycle';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "`coid`,`current`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
            
            ///////////////////////
            //hr service
            ///////////////////////

            $table = 'ek_hr_service';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`sid`,`service_name`,`lib_service`,`eid`,`coid`,"
                    . "`id_service`,`color_service`,`bgcolor_service`,`opened_service`,"
                    . "`display_vertical_service`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by sid';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
                      
            ///////////////////////
            //hr workforce ph
            ///////////////////////

            $table = 'ek_hr_workforce_ph';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`coid`,`date`,`description`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ORDER by id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);  
            
            ///////////////////////
            //hr workforce roster
            ///////////////////////

            $table = 'ek_hr_workforce_roster';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`period`,`emp_id`,`roster`,`status`,". $table .".`note`,`audit`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_hr_workforce} b ON '
                    . ''. $table .'.emp_id = b.id WHERE company_id=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
            
            ///////////////////////
            //hr settings
            ///////////////////////

            $table = 'ek_hr_workforce_settings';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`coid`,`ad`,`cat`,`param`,`accounts`,`roster`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
            
            

        }//hr
      
        if ($this->moduleHandler->moduleExists('ek_products')) {
            
            ///////////////////////
            //items settings
            ///////////////////////

            $table = 'ek_item_settings';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`settings`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            ///////////////////////
            //items
            ///////////////////////

            $table = 'ek_items';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`coid`,`type`,`itemcode`,`description1`,`description2`,"
                    . "`supplier_code`,`active`,`collection`,`department`,`family`,`size`,`color`,`supplier`,"
                    . "`stamp`,`format`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
            
            ///////////////////////
            //items barcode
            ///////////////////////

            $table = 'ek_item_barcodes';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," .  $table .".`itemcode`,`barcode`,`encode`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_items} b ON '
                    . ''. $table .'.itemcode = b.itemcode WHERE coid=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
            
            ///////////////////////
            //items images
            ///////////////////////

            $table = 'ek_item_images';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," .  $table .".`itemcode`,`uri`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_items} b ON '
                    . ''. $table .'.itemcode = b.itemcode WHERE coid=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
            
            ///////////////////////
            //items packing
            ///////////////////////

            $table = 'ek_item_packing';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," .  $table .".`itemcode`,`units`,`unit_measure`,`item_size`,`pack_size`,"
                    . "`qty_pack`,`c20`,`c40`,`min_order`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_items} b ON '
                    . ''. $table .'.itemcode = b.itemcode WHERE coid=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd); 
            
            ///////////////////////
            //items prices
            ///////////////////////

            $table = 'ek_item_prices';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," .  $table .".`itemcode`,`purchase_price`,`currency`,`date_purchase`,"
                    . "`selling_price`,`promo_price`,`discount_price`,`exp_selling_price`,`exp_promo_price`,"
                    . "`exp_discount_price`,`loc_currency`,`exp_currency`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_items} b ON '
                    . ''. $table .'.itemcode = b.itemcode WHERE coid=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            ///////////////////////
            //items prices history
            ///////////////////////

            $table = 'ek_item_price_history';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," .  $table .".`itemcode`,`date`,`price`,`currency`," .  $table .".`type`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_items} b ON '
                    . ''. $table .'.itemcode = b.itemcode WHERE coid=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);
            
            
            //keep references to extract address book data
            $query = "SELECT distinct supplier FROM {ek_items} WHERE coid=:c";
            $abid_items = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $coid])
                    ->fetchCol();
         
            
        } //products    
        
        if ($this->moduleHandler->moduleExists('ek_projects')) { 
            
            //keep references to extract address book data
            $query = "SELECT distinct client_id FROM {ek_project}";
            $abid_projects = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':c' => $coid])
                    ->fetchCol();
            
            
        }       
        
        
        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            
            $abid = array_merge((array)$abid_sales, (array)$abid_items, (array)$abid_projects); 
            $condition = implode(',', $abid);
            
            ///////////////////////
            //address book
            ///////////////////////

            $table = 'ek_address_book';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`name`,`shortname`,`address`,`address2`,`postcode`,"
                    . "`city`,`country`,`telephone`,`fax`,`website`,`type`,`category`,"
                    . "`status`,`stamp`,`activity`,`logo`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE  FIND_IN_SET (id, :c ) ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd, $condition);            

                        
            ///////////////////////
            //address book contacts
            ///////////////////////

            $table = 'ek_address_book_contacts';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`abid`,`main`,`contact_name`,`salutation`,`title`,"
                    . "" . $table .".`telephone`,`mobilephone`,`email`,`card`,`department`,`link`,`comment`,"
                    . "" . $table .".`stamp`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_address_book} b ON '
                    . ''. $table .'.abid = b.id WHERE  FIND_IN_SET (b.id, :c ) ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd, $condition);
            
            ///////////////////////
            //address book comments
            ///////////////////////

            $table = 'ek_address_book_comment';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`abid`,`comment`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_address_book} b ON '
                    . ''. $table .'.abid = b.id WHERE  FIND_IN_SET (b.id, :c ) ORDER by ' . $table . '.abid';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd, $condition); 
            
            
            }     
        
        if ($this->moduleHandler->moduleExists('ek_logistics')) {
            ////////////////////
            //Logistics delivery
            ////////////////////

            $table = 'ek_logi_delivery';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`serial`,`head`,`allocation`,`date`,`ddate`, "
                    . "`title`, `po`, `pcode`,`client`,"
                    . "`status`,`amount`,`ordered_quantity`,`post`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE head=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
        
           
            ////////////////////////////
            //Logistics delivery details
            ////////////////////////////

            $table = 'ek_logi_delivery_details';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table . " .`id`," . $table .".`serial`,`itemcode`,`quantity`," . $table .".`date`, "
                    . $table . ".`amount`, `currency`,`value`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_logi_delivery} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);    
        
            ////////////////////
            //Logistics receive
            ////////////////////

            $table = 'ek_logi_receiving';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`,`serial`,`head`,`allocation`,`date`,`ddate`, "
                    . "`title`, `do`, `pcode`,`supplier`,"
                    . "`status`,`amount`,`type`,`logistic_cost`,`post`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE head=:c ';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);             
        
           
            ////////////////////////////
            //Logistics receive details
            ////////////////////////////

            $table = 'ek_logi_receiving_details';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = $table .".`id`," . $table .".`serial`,`itemcode`,`quantity`," . $table .".`date`, "
                    . $table . ".`amount`, `currency`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' LEFT JOIN {ek_logi_receiving} b ON '
                    . ''. $table .'.serial = b.serial WHERE head=:c ORDER by ' . $table . '.id';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
            
            ////////////////////
            //logistics settings
            ////////////////////
 
            $table = 'ek_logi_settings';
            
            $file .= " #--------------------------------------------------------" . $lineEnd;
            $file .= " # Table  " . $table . $lineEnd;
            $file .= " #--------------------------------------------------------" . $lineEnd;
            
            $fields = "`coid`,`settings`";
            $query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE coid=:c';

            $file .= self::querydb($coid, $table, $fields, $query, $lineEnd);            
            
            
        }
         
        $name = md5(date('U')) . '.sql';
        $sql = file_save_data($file, 'private://tmp/' . $name, NULL);
        
        if ($sql) {
   
            $id = \Drupal::currentUser()->id();
        if ($id) {
            $user = User::load($id);
            $sql->setOwner($user);
            
          }
          else {
            $sql->setOwner($this->adminUser);
          }
          // Change the file status to be temporary.
          $sql->setTemporary();
          // Save the changes.
          $sql->save();
        }
        
        $form['section']['sql']['#markup'] = "<a href='". file_create_url($sql->getFileUri()) 
                . "'>" . t('download') . "</a>"; 
        return $form['section'];
    }
    
  /**
   * {@inheritdoc}
   */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }
    
    public static function querydb($coid = NULL, $table = NULL, $fields = NULL, $query = NULL, $lineEnd = NULL, $condition = NULL) {
        
            $file = '';
            $create = 'SHOW CREATE TABLE ' . $table;

            $data = Database::getConnection('external_db', 'external_db')
                    ->query($create)
                    ->fetchAssoc();

            $file .= str_replace('"', '`', $data['Create Table']) . ';' . $lineEnd;
            
            if($condition) {
                $a = [':c' => $condition];
            } else {
                $a = [':c' => $coid];
            }

            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a);
        
            $rows = '';
            $i = 0;
                    While ($r = $data->fetchAssoc()) {

                        $i++;
                        if ($i > 500) {
                            $rows = substr($rows, 0, -3) . ';';
                            $i = 1;
                            $rows .= "INSERT INTO `" . $table . "` (" . $fields . ") VALUES ";
                        }
                        $values = '';
                        foreach($r as $key => $val) {
                            $values .= '"' . addslashes($val) . '",';
                        }
                        $values = rtrim($values, ',');
                        //$rows .= '("' . @implode('","', $r) . '"),' . $lineEnd;
                        $rows .= '(' . $values . '),' . $lineEnd;
                    }
                    
                    if($i > 0) {
                        $file .= "INSERT INTO `" . $table . "` (" . $fields . ") VALUES ";
                        $file .= $rows;
                        $file = substr($file, 0, -3) . ';' . $lineEnd ; 
                    } else {
                        $file .= '# No data ------------------------------------' . $lineEnd;
                    }
                    
                    
            
            
            return $file;
    }


}
