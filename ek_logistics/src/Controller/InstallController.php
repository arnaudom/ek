<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_logistics\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\user\UserInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
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
   * install required tables in a separate database
   *
*/

 public function install() {

    $query = "CREATE TABLE IF NOT EXISTS `ek_logi_settings` (
                `coid` INT(11) NULL DEFAULT NULL COMMENT 'company ID',
                `settings` VARCHAR(255) NULL DEFAULT NULL COMMENT 'serialized settings',
                UNIQUE INDEX `Index 1` (`coid`)
              )
              COMMENT='holds settings by company'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              ROW_FORMAT=COMPACT";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Logistics settings table installed <br/>';
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_logi_delivery` (
              `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'a unique constructed serial reference',
              `head` VARCHAR(5) NOT NULL DEFAULT '1' COMMENT 'entity id issuing the order (company)',
              `allocation` VARCHAR(5) NOT NULL DEFAULT '1' COMMENT 'entity id for which the order is issued in multi entities config',
              `date` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'creation date',
              `ddate` VARCHAR(50) NULL DEFAULT NULL COMMENT 'delivery date',
              `title` VARCHAR(255) NULL DEFAULT NULL COMMENT 'optional comment / title',
              `po` VARCHAR(45) NULL DEFAULT NULL COMMENT 'a purchase reference from client',
              `pcode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'optional project code ref',
              `client` VARCHAR(5) NOT NULL COMMENT 'a client id',
              `status` VARCHAR(2) NULL DEFAULT NULL COMMENT '0 open, 1 printed, 2 invoiced, 3 posted',
              `amount` DOUBLE NULL DEFAULT NULL COMMENT 'a DO total value',
              `ordered_quantity` DOUBLE NULL DEFAULT NULL COMMENT 'client total ordered quantities',
              `post` TINYINT(4) NULL DEFAULT '0' COMMENT 'post quantities to stock status',
              PRIMARY KEY (`id`)
            )
            COMMENT='record deliveries orders information'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB
            AUTO_INCREMENT=1";
            
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Delivery table installed <br/>';

    $query = "CREATE TABLE IF NOT EXISTS  `ek_logi_delivery_details` (
              `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'serial ref. from main delivery',
              `itemcode` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'item code reference',
              `quantity` DOUBLE NOT NULL DEFAULT 0 COMMENT 'quantities delivered',
              `date` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'date of delivery',
              `amount` DOUBLE NULL DEFAULT NULL COMMENT 'quantities x value',
              `currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'optional currency',
              `value` DOUBLE NULL DEFAULT NULL COMMENT 'unit price value',
              PRIMARY KEY (`id`)
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB
            AUTO_INCREMENT=1";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Delivery items table installed <br/>';            

    $query = "CREATE TABLE IF NOT EXISTS `ek_logi_receiving` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'a unique constructed serial reference',
                `head` VARCHAR(5) NOT NULL DEFAULT '1' COMMENT 'entity id issuing the order (company)',
                `allocation` VARCHAR(5) NOT NULL DEFAULT '1' COMMENT 'entity id for which the order is issued in multi entities config',
                `date` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'creation date',
                `ddate` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'delivery date',
                `title` VARCHAR(255) NULL DEFAULT '' COMMENT 'optional comment / title',
                `do` VARCHAR(45) NULL DEFAULT '' COMMENT 'a delivery reference from client',
                `pcode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'optional project code ref',
                `supplier` VARCHAR(45) NULL DEFAULT NULL COMMENT 'supplier ID',
                `status` VARCHAR(45) NULL DEFAULT NULL COMMENT '0 open, 1 printed, 2 posted',
                `amount` DOUBLE NULL DEFAULT '0' COMMENT 'order total value',
                `type` CHAR(2) NULL DEFAULT NULL COMMENT 'RR receiving RT returning',
                `logistic_cost` DOUBLE NULL DEFAULT NULL COMMENT 'optional cost computation value',
                `post` VARCHAR(1) NULL DEFAULT '0' COMMENT 'post quantities to stock status',
                PRIMARY KEY (`id`)
              )
              COMMENT='record receiving reports'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
              
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Receiving table installed <br/>';            


    $query = "CREATE TABLE IF NOT EXISTS  `ek_logi_receiving_details` (
              `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'serial ref. from main table',
              `itemcode` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'item code reference',
              `quantity` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'quantities delivered',
              `date` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'date of delivery',
              `amount` DOUBLE NULL DEFAULT NULL COMMENT 'value per item',
              `currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'optional currency',
              PRIMARY KEY (`id`)
            )
            COMMENT='record details per receiving report'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB
            AUTO_INCREMENT=1";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Receiving items table installed <br/>';                 

    if(!$this->moduleHandler->moduleExists('ek_admin')) {
      $markup .= '<br/><b class="messages messages--warning">Main administration module is not installed. Please install this module in order to use Ek_logistics module.</b> <br/>';    
    } 
    
    if(!$this->moduleHandler->moduleExists('ek_address_book')) {
      $markup .= '<br/><b class="messages messages--warning">Address book module is not installed. Please install this module in order to use Ek_logistics module.</b> <br/>';    
    } 
    
    if(!$this->moduleHandler->moduleExists('ek_products')) {
      $markup .= '<br/><b class="messages messages--warning">Products and services module is not installed. Please install this module in order to use Ek_logistics module.</b> <br/>';    
    }      

    //$url = Url::fromRoute('ek_logistics_majour', array())->toString();   
    //$markup .= "<br/>If you are migrating from a previous installation, you may need to update current tables and settings. <a title='" . t('link') . "' href='". $url ."'> You can run the migration script here.</a>";

    $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
    $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));

    return  array(
      '#title'=> t('Installation of Ek_logistics module'),
      '#markup' => $markup
      ) ;
 
 }


   
} //class