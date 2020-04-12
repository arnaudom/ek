<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_address_book\Controller;


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

    $query = "CREATE TABLE IF NOT EXISTS `ek_address_book` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(250) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
                `shortname` VARCHAR(10) NOT NULL DEFAULT '',
                `address` TINYTEXT NULL  COLLATE 'utf8mb4_unicode_ci',
                `address2` TINYTEXT NOT NULL  COLLATE 'utf8mb4_unicode_ci',
                `postcode` VARCHAR(50) NULL DEFAULT NULL,
                `state` VARCHAR(50) NULL DEFAULT NULL COMMENT 'address state',
                `city` VARCHAR(100) NULL DEFAULT NULL,
                `country` VARCHAR(45) NULL DEFAULT NULL,
                `telephone` VARCHAR(45) NULL DEFAULT NULL,
                `fax` VARCHAR(45) NOT NULL DEFAULT '',
                `website` VARCHAR(255) NULL DEFAULT NULL,
                `type` VARCHAR(20) NULL DEFAULT NULL COMMENT '1 client, 2 supplier, 3 other',
                `category` VARCHAR(45) NULL DEFAULT NULL,
                `status` VARCHAR(1) NULL DEFAULT '1' COMMENT 'status, 1=active, 0=inactive',
                `stamp` VARCHAR(50) NULL DEFAULT NULL,
                `activity` VARCHAR(255) NULL DEFAULT NULL,
                `logo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'contact logo',
                `reg` VARCHAR(30) NULL DEFAULT NULL COMMENT 'registration number',
                PRIMARY KEY (`id`)
              )
              COMMENT='list of addresses'
              COLLATE=utf8_general_ci
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
    
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Address book table installed <br/>';
    
    $query = "CREATE TABLE IF NOT EXISTS `ek_address_book_contacts` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `abid` INT(10) UNSIGNED NOT NULL COMMENT 'id in address book',
                `main` VARCHAR(2) NULL DEFAULT '0',
                `contact_name` VARCHAR(255) NOT NULL COMMENT 'full name' COLLATE 'utf8mb4_unicode_ci',
                `salutation` VARCHAR(20) NULL DEFAULT NULL COMMENT 'salutation',
                `title` VARCHAR(100) NULL DEFAULT NULL COMMENT 'function ttitle',
                `telephone` VARCHAR(45) NULL DEFAULT NULL COMMENT 'fix line',
                `mobilephone` VARCHAR(45) NULL DEFAULT NULL COMMENT 'mobile line',
                `email` VARCHAR(100) NULL DEFAULT NULL COMMENT 'email',
                `card` VARCHAR(255) NULL DEFAULT NULL COMMENT 'name card, fid',
                `department` VARCHAR(100) NULL DEFAULT NULL COMMENT 'department',
                `link` VARCHAR(100) NULL DEFAULT NULL COMMENT 'social link',
                `comment` TEXT NULL COMMENT 'comment',
                `stamp` VARCHAR(50) NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `Index 2` (`abid`)
              )
              COMMENT='Address book contacts'
              COLLATE=utf8_general_ci
            ENGINE=InnoDB
            AUTO_INCREMENT=1";
            
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Contacts table installed <br/>';

    $query = "CREATE TABLE IF NOT EXISTS `ek_address_book_comment` (
            `abid` INT(10) UNSIGNED NOT NULL,
            `comment` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
            PRIMARY KEY (`abid`)
            )
            COMMENT='list of comments per address book entry'
            COLLATE=utf8_general_ci
            ENGINE=InnoDB
            ROW_FORMAT=COMPACT";

    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Comments table installed <br/>';
    
    
    if(!$this->moduleHandler->moduleExists('ek_admin')) {
      $markup .= '<br/><b class="messages messages--warning">Main administration module is not installed. Please install this module in order to use Ek_logistics module.</b> <br/>';    
    } else {
        
    $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
    $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
    }
    
    return  array(
      '#title'=> t('Installation of Ek_address_book module'),
      '#markup' => $markup
      ) ;
 
 }


   
} //class