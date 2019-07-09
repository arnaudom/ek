<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_projects\Controller;


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
   * data update upon migration
   *
*/
  public function migrate() {
  
  //update and conversion of DB
  include_once drupal_get_path('module', 'ek_projects') . '/' . 'migrate3.php';
  return  array('#markup' => $markup) ;
  }


 
/**
   * install required tables in a separate database
   *
*/

 public function install() {

     
    $query = "CREATE TABLE IF NOT EXISTS `ek_project` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`pname` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Given name by owner',
	`client_id` VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'Address book id',
	`cid` INT(3) NULL DEFAULT NULL COMMENT 'country id',
	`date` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Creation date',
	`category` VARCHAR(3) NOT NULL DEFAULT '0',
	`pcode` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'Generated serial code',
	`status` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'open awarded completed closed',
	`level` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'Main or Sub project',
        `main` INT(10) NULL DEFAULT NULL COMMENT 'Group by main project id',
	`subcount` INT(11) NOT NULL DEFAULT '0' COMMENT 'Number of sub projects attached',
	`priority` INT(11) NOT NULL DEFAULT '0' COMMENT '0 to 3',
	`editor` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'User id',
	`owner` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT 'User id',
	`last_modified` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Unix timestamp',
	`share` VARCHAR(255) NULL DEFAULT '0'  COMMENT 'List user id',
	`deny` VARCHAR(255) NULL DEFAULT '0'  COMMENT 'List user id',
	`notify` VARCHAR(255) NULL DEFAULT '0'  COMMENT 'List user id',
        `archive` TINYINT(1) NULL DEFAULT '0' COMMENT 'archive status 0 = no 1 = yes',
	PRIMARY KEY (`id`, `pcode`)
        )
        COMMENT='main projects reference'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup = 'Projects table installed <br/>';
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_actionplan` (
	`pcode` VARCHAR(45) NOT NULL DEFAULT '',
	`ap_doc` TEXT NULL,
	`what_1` TEXT NULL,
	`who_1` VARCHAR(100) NULL DEFAULT NULL,
	`date_1` VARCHAR(45) NULL DEFAULT NULL,
	`status_1` VARCHAR(20) NULL DEFAULT NULL,
	`what_2` TEXT NULL,
	`who_2` VARCHAR(100) NULL DEFAULT NULL,
	`date_2` VARCHAR(45) NULL DEFAULT NULL,
	`status_2` VARCHAR(20) NULL DEFAULT NULL,
	`what_3` TEXT NULL,
	`who_3` VARCHAR(100) NULL DEFAULT NULL,
	`date_3` VARCHAR(45) NULL DEFAULT NULL,
	`status_3` VARCHAR(20) NULL DEFAULT NULL,
	PRIMARY KEY (`pcode`)
        )
        COMMENT='action plan data'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects AP table installed <br/>';
    
    $query ="CREATE TABLE IF NOT EXISTS `ek_project_description` (
	`pcode` VARCHAR(45) NOT NULL DEFAULT '',
	`submission` VARCHAR(20) NULL DEFAULT NULL COMMENT 'date of proposal',
	`deadline` VARCHAR(20) NULL DEFAULT NULL COMMENT 'deadline',
	`start_date` VARCHAR(20) NULL DEFAULT NULL COMMENT 'start date',
	`validation` VARCHAR(20) NULL DEFAULT NULL COMMENT 'date validated',
	`completion` VARCHAR(20) NULL DEFAULT NULL COMMENT 'date completed',
	`project_description` TEXT NULL COLLATE 'utf8_general_ci',
	`project_comment` TEXT NULL,
	`product` VARCHAR(45) NULL DEFAULT NULL,
	`supplier_offer` TINYTEXT NULL,
	`current_offer` TINYTEXT NULL,
	`country` VARCHAR(50) NULL DEFAULT NULL,
	`perso_1` VARCHAR(150) NULL DEFAULT NULL COMMENT 'responsible',
	`perso_2` VARCHAR(150) NULL DEFAULT NULL COMMENT 'responsible',
	`perso_3` VARCHAR(150) NULL DEFAULT NULL COMMENT 'responsible',
	`repo_1` VARCHAR(150) NULL DEFAULT NULL COMMENT 'responsibility',
	`repo_2` VARCHAR(150) NULL DEFAULT NULL COMMENT 'responsibility',
	`repo_3` VARCHAR(150) NULL DEFAULT NULL COMMENT 'responsibility',
	`task_1` VARCHAR(250) NULL DEFAULT NULL COMMENT 'task',
	`task_2` VARCHAR(250) NULL DEFAULT NULL COMMENT 'task',
	`task_3` VARCHAR(250) NULL DEFAULT NULL COMMENT 'task',
	PRIMARY KEY (`pcode`)
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects description table installed <br/>';  
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_documents` (
	`id` INT(5) NOT NULL AUTO_INCREMENT,
	`pcode` VARCHAR(45) NULL DEFAULT NULL,
	`fid` INT(10) NULL DEFAULT NULL COMMENT 'file managed ID',
	`filename` VARCHAR(200) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`folder` VARCHAR(20) NULL DEFAULT NULL,
	`comment` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the stream uri of file' COLLATE 'utf8mb4_unicode_ci',
	`date` VARCHAR(30) NULL DEFAULT NULL COMMENT 'date stamp',
	`size` INT(10) NULL DEFAULT NULL COMMENT 'size',
	`share` VARCHAR(250) NULL DEFAULT '0',
	`deny` VARCHAR(250) NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	UNIQUE INDEX `Index 2` (`uri`(191))
        )
        COMMENT='documents attached to projects'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects documents table installed <br/>';    
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_finance` (
	`pcode` VARCHAR(45) NOT NULL DEFAULT '',
	`payment_terms` VARCHAR(45) NULL DEFAULT '',
	`purchase_value` DOUBLE NULL DEFAULT '0',
	`discount_offer` DOUBLE NULL DEFAULT '0',
	`paymentdate_d` VARCHAR(20) NULL DEFAULT '0000-00-00',
	`paymentdate_i` VARCHAR(20) NULL DEFAULT '0000-00-00',
	`project_amount` DOUBLE NULL DEFAULT '0',
	`lc_status` VARCHAR(45) NULL DEFAULT NULL,
	`lc_revision` VARCHAR(50) NULL DEFAULT NULL,
	`lc_expiry` VARCHAR(20) NULL DEFAULT '0000-00-00',
	`tender_offer` VARCHAR(45) NULL DEFAULT '',
	`down_payment` DOUBLE NOT NULL DEFAULT '0',
	`offer_delivery` VARCHAR(45) NOT NULL DEFAULT '0',
	`offer_validity` VARCHAR(45) NOT NULL DEFAULT '0',
	`invoice` DOUBLE NULL DEFAULT NULL,
	`incoterm` VARCHAR(45) NOT NULL DEFAULT '',
	`currency` VARCHAR(45) NULL DEFAULT '',
	`payment` VARCHAR(45) NULL DEFAULT '',
	`comment` TEXT NULL,
	PRIMARY KEY (`pcode`)
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects finance table installed <br/>';   
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_shipment` (
	`pcode` VARCHAR(45) NOT NULL DEFAULT '',
	`first_ship` VARCHAR(20) NOT NULL DEFAULT '0000-00-00',
	`second_ship` VARCHAR(20) NOT NULL DEFAULT '0000-00-00',
	`third_ship` VARCHAR(20) NOT NULL DEFAULT '0000-00-00',
	`four_ship` VARCHAR(20) NOT NULL DEFAULT '0000-00-00',
	`ship_status` VARCHAR(45) NOT NULL DEFAULT '',
	`last_delivery` VARCHAR(20) NOT NULL DEFAULT '0000-00-00',
	PRIMARY KEY (`pcode`)
        )
        COMMENT='logistic data'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects logistics table installed <br/>';    
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_tasks` (
	`id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
	`pcode` VARCHAR(50) NULL DEFAULT NULL COMMENT 'project code reference',
	`event` VARCHAR(50) NULL DEFAULT NULL COMMENT 'event group name',
	`task` TEXT NULL COMMENT 'task description',
	`weight` DOUBLE NULL DEFAULT NULL COMMENT 'option',
	`start` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'start date',
	`end` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'end date',
	`uid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL COMMENT 'uid assignment',
	`gid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL COMMENT 'group id assignment',
	`completion_rate` VARCHAR(3) NULL DEFAULT NULL COMMENT 'rate of completion 0-100',
	`notify` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'notification period : 0=no,1=week,2=5days,3=3days,4=1day,5=daily,6=monthly',
	`notify_who` VARCHAR(100) NULL DEFAULT NULL COMMENT 'list notification uid ex 1,2,3',
	`notify_when` VARCHAR(50) NULL DEFAULT NULL COMMENT 'notification time',
	`note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'option note',
	`color` VARCHAR(10) NULL DEFAULT NULL COMMENT 'option color',
	PRIMARY KEY (`id`)
        )
        COMMENT='tasks per project'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects tasks table installed <br/>';   
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_tracker` (
	`pcode` VARCHAR(50) NULL DEFAULT NULL,
	`uid` INT(10) NULL DEFAULT NULL,
	`stamp` INT(10) NULL DEFAULT NULL,
	`action` VARCHAR(100) NULL DEFAULT NULL,
	INDEX `Index 1` (`pcode`)
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects trackers table installed <br/>';      
    
    $query ="CREATE TABLE IF NOT EXISTS `ek_project_type` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`short` VARCHAR(5) NULL DEFAULT NULL,
	`gp` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'group',
	`type` VARCHAR(45) NOT NULL DEFAULT '',
	`comment` TINYTEXT NULL,
	PRIMARY KEY (`id`)
        )
        COMMENT='categories of projects'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects types table installed <br/>'; 
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_project_users` (
	`uid` INT(11) NOT NULL COMMENT 'user id',
	`section_1` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = no access 1 = access',
	`section_2` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = no access 1 = access',
	`section_3` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = no access 1 = access',
	`section_4` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = no access 1 = access',
	`section_5` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = no access 1 = access'
        )
        COMMENT='sections access data per user'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects users access table installed <br/>';     
    
    $query = "CREATE TABLE `ek_project_settings` (
	`coid` INT(11) NULL DEFAULT NULL COMMENT 'value 0 for global',
	`settings` BLOB NULL,
	UNIQUE INDEX `Index 1` (`coid`)
        )
        COMMENT='settings variables for projects module'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    $query = "INSERT into {ek_project_settings} (`coid`) VALUES (0) ";
    
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Projects settings installed <br/>';
    
    $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
    $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
        
    return  array(
      '#title'=> t('Installation of Ek_projects module'),
      '#markup' => $markup
      ) ;
 
 }


   
} //class