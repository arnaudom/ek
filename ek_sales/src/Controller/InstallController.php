<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_sales\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
* Controller routines for ek module routes.
*/
class InstallController extends ControllerBase {

   /* The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;
  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('module_handler')
    );
  }

  /**
   * Constructs a  object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
    $this->database = $database;
    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;
  }


/**
   * data update upon migration
   *
*/

 public function migrate() {
   include_once drupal_get_path('module', 'ek_sales') . '/' . 'migrate.php';
    return  array('#markup' => $markup) ;
 
 }
 
/**
   * install required tables in a separate database
   *
*/

 public function install() {
/**/ 
    $markup = '';
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_purchase` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(45) NOT NULL DEFAULT '',
	`head` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
	`allocation` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
	`status` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '0 = not paid /fully paid , 1 paid',
	`amount` DOUBLE NOT NULL DEFAULT '0',
	`currency` VARCHAR(45) NOT NULL DEFAULT '',
	`date` VARCHAR(15) NOT NULL DEFAULT '0000-00-00',
	`title` VARCHAR(150) NOT NULL,
        `type` TINYINT(4) NOT NULL DEFAULT '1' COMMENT 'type 1 purchase, 4 debit note',
	`pcode` VARCHAR(45) NULL DEFAULT '',
	`comment` TEXT NULL,
	`client` VARCHAR(45) NOT NULL DEFAULT '',
	`amountpaid` DOUBLE NULL DEFAULT '0',
	`amountbc` DOUBLE NULL DEFAULT '0',
	`balancebc` DOUBLE NULL DEFAULT NULL,
	`bank` VARCHAR(5) NULL DEFAULT NULL,
	`tax` VARCHAR(100) NULL DEFAULT NULL,
	`taxvalue` DOUBLE NULL DEFAULT NULL,
	`terms` VARCHAR(255) NULL DEFAULT NULL,
	`due` VARCHAR(3) NULL DEFAULT '0',
	`pdate` VARCHAR(15) NULL DEFAULT NULL,
	`pay_ref` VARCHAR(100) NULL DEFAULT NULL,
	`reconcile` VARCHAR(5) NULL DEFAULT '0',
	`alert` TINYINT(1) NULL DEFAULT '0',
	`alert_who` VARCHAR(250) NULL DEFAULT NULL,
	`uri` VARCHAR(250) NULL DEFAULT NULL COMMENT 'uri of file attached',
	PRIMARY KEY (`id`)
        )
        COMMENT='Record of purchases'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'purchases table created <br/>'; 
    
    $query = "CREATE TABLE IF NOT EXISTS  `ek_sales_purchase_details` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(45) NOT NULL DEFAULT '',
	`item` TEXT NOT NULL,
	`itemdetail` TEXT NULL,
	`quantity` DOUBLE NULL DEFAULT NULL,
	`value` DOUBLE NULL DEFAULT NULL,
	`total` DOUBLE UNSIGNED NULL DEFAULT NULL,
	`opt` VARCHAR(45) NULL DEFAULT NULL,
	`aid` VARCHAR(50) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
        )
        COMMENT='Record purchases items lines'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB ";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'purchases details table created <br/>';    
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_purchase_tasks` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(50) NULL DEFAULT NULL COMMENT 'serial reference of invoice',
	`event` VARCHAR(50) NOT NULL COMMENT 'event name',
	`uid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL COMMENT 'uid assignment',
	`task` TEXT NULL COMMENT 'task description',
	`weight` DOUBLE NULL DEFAULT NULL COMMENT 'option',
	`start` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'start date',
	`end` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'end date',
	`completion_rate` INT(5) NULL DEFAULT NULL COMMENT 'rate of completion',
	`notify` SMALLINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'notification period : 0=no,1=week,2=5days,3=3days,4=1day,5=daily,6=monthly',
	`notify_who` VARCHAR(100) NULL DEFAULT NULL COMMENT 'list notification uid ex 1,2,3',
	`notify_when` VARCHAR(50) NULL DEFAULT NULL COMMENT 'notification time',
	`note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'option note',
	`color` VARCHAR(10) NULL DEFAULT NULL COMMENT 'option color',
	PRIMARY KEY (`id`)
        )
        COMMENT='tasks, alerts per purchase'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        ROW_FORMAT=COMPACT ";       

    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'purchases tasks table created <br/>'; 
    
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_quotation` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(45) NOT NULL DEFAULT '',
	`head` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
	`allocation` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
	`status` VARCHAR(45) NOT NULL DEFAULT '',
	`amount` DOUBLE NOT NULL DEFAULT '0',
	`currency` VARCHAR(5) NOT NULL DEFAULT '',
	`date` VARCHAR(45) NOT NULL DEFAULT '0000-00-00',
	`title` TEXT NULL,
	`pcode` VARCHAR(45) NULL DEFAULT '',
	`comment` TEXT NULL,
	`client` VARCHAR(5) NOT NULL DEFAULT '',
	`incoterm` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'add incoterm format term-%',
	`tax` VARCHAR(45) NULL DEFAULT '' COMMENT 'add a tax value format: name|%',
	`bank` CHAR(2) NULL DEFAULT NULL,
	`principal` VARCHAR(10) NULL DEFAULT NULL,
	`type` CHAR(2) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
        )
        COMMENT='Record of quotations'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'quotations table created <br/>';  
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_quotation_details` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(45) NOT NULL DEFAULT '',
	`itemid` TEXT NULL,
	`itemdetails` TEXT NULL,
	`weight` INT(5) NULL DEFAULT '0' COMMENT 'row weight',
	`unit` INT(10) UNSIGNED NOT NULL DEFAULT '0',
	`value` DOUBLE NOT NULL DEFAULT '0',
	`total` DOUBLE NULL DEFAULT NULL,
	`revision` CHAR(2) NOT NULL DEFAULT '',
	`opt` TINYTEXT NULL,
	`column_2` VARCHAR(255) NULL DEFAULT NULL,
	`column_3` VARCHAR(255) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
        )
        COMMENT='Record quotations items lines'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'quotations details table created <br/>';      
   
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_invoice` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'unique serial number' COLLATE 'utf8_unicode_ci',
	`do_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'deliveri order ref' COLLATE 'utf8_unicode_ci',
	`head` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'company id',
	`allocation` TINYINT(4) NOT NULL COMMENT 'company id allocation',
	`status` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '0 unpaid' COLLATE 'utf8_unicode_ci',
	`amount` DOUBLE NOT NULL DEFAULT '0' COMMENT 'total amount',
	`currency` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'currency' COLLATE 'utf8_unicode_ci',
	`date` VARCHAR(45) NOT NULL DEFAULT '0000-00-00' COMMENT 'invoice date',
	`title` VARCHAR(150) NOT NULL COMMENT 'title on the printed doc' COLLATE 'utf8_unicode_ci',
        `type` TINYINT(4) NOT NULL DEFAULT '1' COMMENT 'type 1 invoice, 2 commercial, 4 credit note',
	`pcode` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'project reference' COLLATE 'utf8_unicode_ci',
	`comment` TEXT NOT NULL COMMENT 'comment on printed doc' COLLATE 'utf8_unicode_ci',
	`client` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'client id' COLLATE 'utf8_unicode_ci',
	`amountreceived` DOUBLE NOT NULL DEFAULT'0' COMMENT 'amount paid with tax' ,
	`pay_date` VARCHAR(15) NULL DEFAULT NULL COMMENT 'date payment',
	`class` VARCHAR(45) NULL DEFAULT '',
	`amountbase` DOUBLE NOT NULL DEFAULT '0' COMMENT 'base corrency',
	`balancebase` DOUBLE NULL DEFAULT NULL COMMENT 'amount not paid base currency',
	`terms` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'payment terms definition',
	`due` VARCHAR(3) NOT NULL DEFAULT '0',
	`bank` CHAR(2) NOT NULL DEFAULT '',
	`tax` VARCHAR(100) NOT NULL DEFAULT '',
	`taxvalue` DOUBLE NOT NULL DEFAULT '0',
	`reconcile` VARCHAR(5) NULL DEFAULT NULL,
	`balance_post` VARCHAR(1) NULL DEFAULT '0',
	`alert` TINYINT(4) NULL DEFAULT '0',
	`alert_who` VARCHAR(255) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
        )
        COMMENT='Main invoice ref table'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'invoices table created <br/>';      
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_invoice_details` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'main invoice table ref' COLLATE 'utf8_unicode_ci',
	`item` TEXT NULL COMMENT 'item' COLLATE 'utf8_unicode_ci',
	`itemdetail` TEXT NULL COLLATE 'utf8_unicode_ci',
	`value` DOUBLE NULL DEFAULT '0' COMMENT 'unit price value of item',
	`margin` DOUBLE NULL DEFAULT NULL COMMENT 'not used',
	`quantity` DOUBLE NULL DEFAULT NULL COMMENT 'quantity of items',
	`total` DOUBLE NULL DEFAULT NULL COMMENT 'line total value local currency',
	`totalbase` DOUBLE NULL DEFAULT NULL COMMENT 'value converted base currency',
	`opt` VARCHAR(2) NULL DEFAULT '0' COMMENT 'option tax' COLLATE 'utf8_unicode_ci',
	`aid` VARCHAR(15) NULL DEFAULT NULL COMMENT 'account id' COLLATE 'utf8_unicode_ci',
	PRIMARY KEY (`id`)
        ) 
        COMMENT='Record invoices items lines'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";    
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'invoices details table created <br/>'; 
    
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_invoice_tasks` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`serial` VARCHAR(50) NULL DEFAULT NULL COMMENT 'serial reference of invoice',
	`event` VARCHAR(50) NOT NULL COMMENT 'event name',
	`uid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL COMMENT 'uid assignment',
	`task` TEXT NULL COMMENT 'task description',
	`weight` DOUBLE NULL DEFAULT NULL COMMENT 'option',
	`start` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'start date',
	`end` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'end date',
	`completion_rate` INT(5) NULL DEFAULT NULL COMMENT 'rate of completion',
	`notify` SMALLINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'notification period : 0=no,1=week,2=5days,3=3days,4=1day,5=daily,6=monthly',
	`notify_who` VARCHAR(100) NULL DEFAULT NULL COMMENT 'list notification uid ex 1,2,3',
	`notify_when` VARCHAR(50) NULL DEFAULT NULL COMMENT 'notification time',
	`note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'option note',
	`color` VARCHAR(10) NULL DEFAULT NULL COMMENT 'option color',
	PRIMARY KEY (`id`)
        )
        COMMENT='tasks, alerts per invoice'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        ROW_FORMAT=COMPACT ";       

    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'invoices tasks table created <br/>'; 


    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_settings` (
	`coid` INT(11) NULL DEFAULT NULL COMMENT 'company ID',
	`settings` BLOB NULL COMMENT 'serialized settings',
	UNIQUE INDEX `Index 1` (`coid`)
        )
        COMMENT='holds settings by company'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";

    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'sales settings table created <br/>'; 
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_sales_documents` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`abid` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'address book id',
	`fid` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'file managed id, option',
	`filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.',
	`uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file',
	`comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment',
	`date` VARCHAR(50) NULL DEFAULT '0',
	`size` INT(10) NULL DEFAULT '0',
	`share` VARCHAR(255) NULL DEFAULT '0',
	`deny` VARCHAR(255) NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	INDEX `Index 2` (`abid`)
)
        COMMENT='holds data about uploaded prospects documents'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB";

    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'sales documents table created <br/>'; 
    
    $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
    $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));  
    return  array(
      '#title'=> t('Installation of Ek_sales module'),
      '#markup' => $markup
      ) ;
 
 }


   
} //class