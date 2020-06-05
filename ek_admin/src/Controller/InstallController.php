<?php
/**
* @file
* Contains \Drupal\ek-admin\Controller\InstallController
*/

namespace Drupal\ek_admin\Controller;

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
class InstallController extends ControllerBase
{

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
    public static function create(ContainerInterface $container)
    {
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
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler)
    {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * data update
     *
     *
    */

    public function update()
    {
        include_once drupal_get_path('module', 'ek_admin') . '/' . 'update.php';
        return  array('#markup' => $markup) ;
    }

    /**
     * data merge
     * combine data from other database tables with current data tables
     * @return Form
    */

    public function merge()
    {
        $form_builder = $this->formBuilder();
        $form = $form_builder->getForm('Drupal\ek_admin\Form\Merge');
        
        return array(
            $form,
            '#title' => $this->t('Merge data'),
        );
    }
 
 
    /**
     * install required tables in a separate database
     *
    */

    public function install()
    {
        $query = "CREATE TABLE IF NOT EXISTS `ek_admin_settings` (
            `coid` INT NULL COMMENT 'company id, 0 = global',
            `settings` BLOB NULL COMMENT 'settings serialized array',
            UNIQUE INDEX `Index 1` (`coid`)
    )
    COMMENT='global and per company settings references'
    COLLATE='utf8_general_ci'
    ENGINE=InnoDB";
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Settings table installed <br/>';
        }
    
        $query = "INSERT INTO `ek_admin_settings` (`coid`) VALUES (0)";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Settings table updated <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_company` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `access` BLOB NULL DEFAULT NULL COMMENT 'serialized uid list access',
            `settings` BLOB NULL COMMENT 'holds accounts settings',
            `name` VARCHAR(45) NULL DEFAULT NULL COMMENT 'company name',
            `reg_number` VARCHAR(45) NULL DEFAULT NULL COMMENT 'company registration number',
            `address1` TINYTEXT NULL COMMENT 'address line 1',
            `address2` TINYTEXT NULL COMMENT 'address line 2',
            `address3` TINYTEXT NULL COMMENT 'alternate address line 1',
            `address4` TINYTEXT NULL COMMENT 'alternate address line 2',
            `city` VARCHAR(250) NULL DEFAULT NULL COMMENT 'city name',
            `city2` VARCHAR(250) NULL DEFAULT NULL COMMENT 'alternate city name',
            `postcode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'postcode',
            `postcode2` VARCHAR(45) NULL DEFAULT NULL COMMENT 'alternate postcode',
            `country` VARCHAR(45) NULL DEFAULT NULL COMMENT 'country name',
            `country2` VARCHAR(250) NULL DEFAULT NULL COMMENT 'alternate country name',
            `telephone` VARCHAR(45) NULL DEFAULT NULL COMMENT 'telephone',
            `telephone2` VARCHAR(45) NULL DEFAULT NULL COMMENT 'altenate telephone',
            `fax` VARCHAR(45) NULL DEFAULT NULL COMMENT 'fax',
            `fax2` VARCHAR(45) NULL DEFAULT NULL COMMENT 'alternate fax',
            `email` VARCHAR(45) NULL DEFAULT NULL COMMENT 'email contact',
            `contact` VARCHAR(45) NULL DEFAULT NULL COMMENT 'contact person',
            `mobile` VARCHAR(45) NULL DEFAULT NULL COMMENT 'mobile contact',
            `logo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'image',
            `favicon` VARCHAR(50) NULL DEFAULT NULL COMMENT 'image' COLLATE 'utf8_general_ci',
            `sign` VARCHAR(255) NULL DEFAULT NULL COMMENT 'image' COLLATE 'utf8_general_ci',
            `short` VARCHAR(10) NULL DEFAULT NULL COMMENT 'short name',
            `accounts_year` VARCHAR(10) NULL DEFAULT NULL COMMENT 'financial year',
            `accounts_month` VARCHAR(10) NULL DEFAULT NULL COMMENT 'financial month',
            `active` VARCHAR(1) NOT NULL COMMENT 'active or inactive setting',
            `itax_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'income tax number',
            `pension_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'fund number',
            `social_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'fund number',
            `vat_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'vat tax number',
                PRIMARY KEY (`id`)
              )
              COMMENT='List of companies / entities'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Company / entity table installed <br/>';
        }
   
        $query = "CREATE TABLE IF NOT EXISTS `ek_company_documents` (
              `id` INT(5) NOT NULL AUTO_INCREMENT,
              `coid` INT(5) NULL DEFAULT NULL COMMENT 'company id',
              `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id',
              `filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.' COLLATE 'utf8mb4_general_ci',
              `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' COLLATE 'utf8mb4_general_ci',
              `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' COLLATE 'utf8mb4_general_ci',
              `date` INT(10) NULL DEFAULT '0' COMMENT 'document date',
              `size` INT(10) NULL DEFAULT '0' COMMENT 'document size',
              `share` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared uid',
              `deny` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of denied uid',
              PRIMARY KEY (`id`)
            )
            COMMENT='holds data about uploaded company document'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB
            AUTO_INCREMENT=1";
            
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Company documents table installed <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_country` (
                `id` SMALLINT(5) NOT NULL AUTO_INCREMENT,
                `access` BLOB NULL DEFAULT NULL COMMENT 'serialized list uid',
                `name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'country name',
                `code` VARCHAR(5) NULL DEFAULT NULL COMMENT 'country code',
                `entity` VARCHAR(255) NULL DEFAULT NULL COMMENT 'organization entity',
                `status` VARCHAR(5) NULL DEFAULT '1' COMMENT 'status 1, 0',
                PRIMARY KEY (`id`)
              )
              COMMENT='Countries table'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
              
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Countries table installed <br/>';
        }
    
    
    
        $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . $this->t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
       
        return  array(
      '#title'=> $this->t('Installation of Ek_admin module'),
      '#markup' => $markup
      ) ;
    }
} //class
