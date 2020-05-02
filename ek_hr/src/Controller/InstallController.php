<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_hr\Controller;

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
       * update version
       *
    */
    public function update()
    {
  
  //update and conversion of DB
        include_once drupal_get_path('module', 'ek_hr') . '/' . 'update.php';
        return  array('#markup' => $markup) ;
    }
 
    /**
       * install required tables in a separate database
       *
    */

    public function install()
    {
        /**/
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_workforce_settings` (
              `coid` SMALLINT(6) NOT NULL DEFAULT '0' COMMENT 'company ID',
              `ad` TEXT NULL COMMENT 'allowances deductions',
              `cat` TEXT NULL COMMENT 'categories',
              `param` TEXT NULL COMMENT 'parameters',
              `accounts` TEXT NULL COMMENT 'accounts links / finance',
              `roster` TEXT NULL COMMENT 'roster settings',
              UNIQUE INDEX `Index 1` (`coid`)
            )
            COMMENT='store HR settings'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;
            ";
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Settings table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_documents` (
                `id` INT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
                `employee_id` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL,
                `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id',
                `filename` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.' COLLATE 'utf8mb4_unicode_ci',
                `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' COLLATE 'utf8mb4_unicode_ci',
                `filemime` VARCHAR(255) NULL DEFAULT NULL COMMENT 'The file\'s MIME type.',
                `type` VARCHAR(200) NULL DEFAULT NULL COMMENT 'tag or type',
                `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' COLLATE 'utf8mb4_unicode_ci',
                `date` INT(10) NULL DEFAULT '0',
                `size` VARCHAR(50) NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE INDEX `Index 2` (`uri`(191))
              )
              COMMENT='holds data about uploaded HR documents'
              COLLATE='utf8mb4_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
   
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Documents table created <br/>';
        }


        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_location` (
                `id` MEDIUMINT(3) NOT NULL AUTO_INCREMENT,
                `coid` MEDIUMINT(3) NOT NULL DEFAULT '1',
                `location` VARCHAR(50) NULL DEFAULT '0',
                `description` VARCHAR(200) NULL DEFAULT NULL,
                `turnover` DOUBLE UNSIGNED NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
              )
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1
                ";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Locations table created <br/>';
        }
    
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_payroll_cycle` (
                `coid` INT(11) NOT NULL,
                `current` VARCHAR(255) NULL DEFAULT NULL,
                UNIQUE INDEX `Index 1` (`coid`)
              )
              COMMENT='data about payroll cycles per company'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB;";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Payroll cycles table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_post_data` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `emp_id` INT(10) NULL DEFAULT '0',
                `month` VARCHAR(25) NULL DEFAULT NULL,
                `d_pay` TINYINT(3) NULL DEFAULT NULL,
                `n_days` TINYINT(3) NULL DEFAULT NULL,
                `basic` DOUBLE NULL DEFAULT NULL,
                `n_ot_days` TINYINT(3) NULL DEFAULT NULL,
                `n_ot_val` DOUBLE NULL DEFAULT NULL,
                `r_day` TINYINT(3) NULL DEFAULT NULL,
                `r_day_val` DOUBLE NULL DEFAULT NULL,
                `ph_day` TINYINT(3) NULL DEFAULT NULL,
                `ph_day_val` DOUBLE NULL DEFAULT NULL,
                `mc_day` TINYINT(3) NULL DEFAULT NULL,
                `mc_day_val` DOUBLE NULL DEFAULT NULL,
                `xr_hours` TINYINT(3) NULL DEFAULT NULL,
                `xr_hours_val` DOUBLE NULL DEFAULT NULL,
                `tleave` TINYINT(5) NULL DEFAULT NULL,
                `custom_aw1` DOUBLE NULL DEFAULT NULL,
                `custom_aw2` DOUBLE NULL DEFAULT NULL,
                `custom_aw3` DOUBLE NULL DEFAULT NULL,
                `custom_aw4` DOUBLE NULL DEFAULT NULL,
                `custom_aw5` DOUBLE NULL DEFAULT NULL,
                `custom_aw6` DOUBLE NULL DEFAULT NULL,
                `custom_aw7` DOUBLE NULL DEFAULT NULL,
                `custom_aw8` DOUBLE NULL DEFAULT NULL,
                `custom_aw9` DOUBLE NULL DEFAULT NULL,
                `custom_aw10` DOUBLE NULL DEFAULT NULL,
                `custom_aw11` DOUBLE NULL DEFAULT NULL,
                `custom_aw12` DOUBLE NULL DEFAULT NULL,
                `custom_aw13` DOUBLE NULL DEFAULT NULL,
                `commission` DOUBLE NULL DEFAULT NULL,
                `turnover` DOUBLE NULL DEFAULT NULL,
                `gross` DOUBLE NULL DEFAULT NULL,
                `no_payday` TINYINT(3) NULL DEFAULT NULL,
                `less_hours` TINYINT(3) NULL DEFAULT NULL,
                `less_hours_val` DOUBLE NULL DEFAULT NULL,
                `advance` DOUBLE NULL DEFAULT NULL,
                `custom_d1` DOUBLE NULL DEFAULT '0',
                `custom_d2` DOUBLE NULL DEFAULT '0',
                `custom_d3` DOUBLE NULL DEFAULT '0',
                `custom_d4` DOUBLE NULL DEFAULT '0',
                `custom_d5` DOUBLE NULL DEFAULT '0',
                `custom_d6` DOUBLE NULL DEFAULT '0',
                `custom_d7` DOUBLE NULL DEFAULT '0',
                `epf_yee` DOUBLE NULL DEFAULT NULL,
                `socso_yee` DOUBLE NULL DEFAULT NULL,
                `deduction` DOUBLE NULL DEFAULT '0' COMMENT 'total deductions',
                `nett` DOUBLE NULL DEFAULT NULL,
                `epf_er` DOUBLE NULL DEFAULT NULL,
                `socso_er` DOUBLE NULL DEFAULT NULL,
                `incometax` DOUBLE NULL DEFAULT NULL,
                `with_yer` DOUBLE NOT NULL,
                `with_yee` DOUBLE NULL DEFAULT NULL,
                `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'optional comments',
                PRIMARY KEY (`id`)
              )
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1
              ";
    
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Posted data table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_service` (
                `sid` INT(10) NOT NULL AUTO_INCREMENT,
                `service_name` VARCHAR(250) NOT NULL COLLATE 'latin1_general_cs',
                `lib_service` LONGTEXT NULL COLLATE 'latin1_general_cs',
                `eid` INT(10) NOT NULL DEFAULT '-1' COMMENT 'employee ID',
                `coid` INT(10) NOT NULL DEFAULT '1' COMMENT 'Company ID',
                `id_service` INT(10) NULL DEFAULT '-1',
                `color_service` VARCHAR(7) NULL DEFAULT '#000000' COLLATE 'latin1_general_cs',
                `bgcolor_service` VARCHAR(7) NULL DEFAULT '#9D9DCE' COLLATE 'latin1_general_cs',
                `opened_service` TINYINT(1) NULL DEFAULT '1',
                `display_vertical_service` TINYINT(1) NULL DEFAULT '0',
                PRIMARY KEY (`sid`),
                INDEX `id_responsable` (`eid`),
                INDEX `id_organigramme` (`coid`),
                INDEX `id_service_parent` (`id_service`)
              )
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
    
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Services table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_workforce` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `custom_id` VARCHAR(15) NOT NULL DEFAULT '0' COMMENT 'Custom ID' COLLATE 'utf8mb4_unicode_ci',
                `company_id` INT(3) NULL DEFAULT NULL COMMENT 'Id of company employee is attached to',
                `origin` VARCHAR(30) NULL DEFAULT NULL,
                `name` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
                `given_name` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
                `surname` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
                `email` VARCHAR(100) NULL DEFAULT NULL,
                `address` TEXT NULL COLLATE 'utf8mb4_unicode_ci',
                `telephone` VARCHAR(50) NULL DEFAULT NULL,
                `sex` VARCHAR(5) NULL DEFAULT NULL,
                `rank` VARCHAR(45) NULL DEFAULT NULL,
                `ic_no` VARCHAR(40) NULL DEFAULT NULL COMMENT 'identification number',
                `ic_type` VARCHAR(10) NULL DEFAULT NULL COMMENT 'identification type',
                `birth` VARCHAR(50) NULL DEFAULT NULL,
                `epf_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'pension fund',
                `socso_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'social security number',
                `itax_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'income tax number',
                `itax_c` VARCHAR(3) NULL DEFAULT NULL COMMENT 'income tax category',
                `e_status` VARCHAR(30) NULL DEFAULT NULL COMMENT 'employee work status',
                `location` VARCHAR(45) NULL DEFAULT NULL,
                `service` INT(5) UNSIGNED NULL DEFAULT NULL,
                `bank` VARCHAR(35) NULL DEFAULT NULL,
                `bank_account` VARCHAR(45) NULL DEFAULT NULL,
                `bank_account_status` VARCHAR(20) NULL DEFAULT NULL,
                `thirdp` VARCHAR(100) NULL DEFAULT NULL COMMENT 'third party credit',
                `active` VARCHAR(30) NULL DEFAULT NULL,
                `start` VARCHAR(50) NULL DEFAULT NULL COMMENT 'date',
                `resign` VARCHAR(50) NULL DEFAULT NULL COMMENT 'date',
                `contract_expiration` VARCHAR(50) NULL DEFAULT NULL COMMENT 'date',
                `currency` VARCHAR(5) NULL DEFAULT NULL,
                `salary` DOUBLE NULL DEFAULT NULL,
                `th_salary` DOUBLE NULL DEFAULT NULL COMMENT 'threshold',
                `aleave` VARCHAR(3) NULL DEFAULT NULL COMMENT 'days annual leave',
                `mcleave` VARCHAR(3) NULL DEFAULT NULL COMMENT 'days medical allowance',
                `archive` VARCHAR(5) NULL DEFAULT NULL,
                `picture` VARCHAR(100) NULL DEFAULT NULL,
                `administrator` VARCHAR(255) NULL DEFAULT NULL COMMENT 'access validation',
                `default_ps` VARCHAR(100) NULL DEFAULT NULL COMMENT 'default payslip form',
                `note` TEXT NULL COMMENT 'Information note',
                PRIMARY KEY (`id`)
              )
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1
              ";
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Workforce table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_workforce_pay` (
                `id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `month` VARCHAR(25) NULL DEFAULT '0',
                `d_pay` MEDIUMINT(5) NULL DEFAULT '0',
                `n_days` MEDIUMINT(5) NULL DEFAULT '0',
                `basic` DOUBLE NULL DEFAULT '0',
                `n_ot_days` TINYINT(3) NULL DEFAULT '0',
                `n_ot_val` DOUBLE NULL DEFAULT '0',
                `r_day` TINYINT(3)  NULL DEFAULT '0',
                `r_day_val` DOUBLE NULL DEFAULT '0',
                `ph_day` TINYINT(3)  NULL DEFAULT '0',
                `ph_day_val` DOUBLE NULL DEFAULT '0',
                `mc_day` TINYINT(3)  NULL DEFAULT '0',
                `mc_day_val` DOUBLE NULL DEFAULT '0',
                `xr_hours` TINYINT(3)  NULL DEFAULT '0',
                `xr_hours_val` DOUBLE NULL DEFAULT '0',
                `tleave` TINYINT(5) NULL DEFAULT '0',
                `custom_aw1` DOUBLE NULL DEFAULT '0',
                `custom_aw2` DOUBLE NULL DEFAULT '0',
                `custom_aw3` DOUBLE NULL DEFAULT '0',
                `custom_aw4` DOUBLE NULL DEFAULT '0',
                `custom_aw5` DOUBLE NULL DEFAULT '0',
                `custom_aw6` DOUBLE NULL DEFAULT '0',
                `custom_aw7` DOUBLE NULL DEFAULT '0',
                `custom_aw8` DOUBLE NULL DEFAULT '0',
                `custom_aw9` DOUBLE NULL DEFAULT '0',
                `custom_aw10` DOUBLE NULL DEFAULT '0',
                `custom_aw11` DOUBLE NULL DEFAULT '0',
                `custom_aw12` DOUBLE NULL DEFAULT '0',
                `custom_aw13` DOUBLE NULL DEFAULT '0',
                `commission` DOUBLE NULL DEFAULT '0',
                `turnover` DOUBLE NULL DEFAULT '0',
                `gross` DOUBLE NULL DEFAULT '0',
                `no_payday` TINYINT(3) NULL DEFAULT '0',
                `less_hours` TINYINT(3) NULL DEFAULT '0',
                `less_hours_val` DOUBLE NULL DEFAULT '0',
                `advance` DOUBLE NULL DEFAULT '0',
                `custom_d1` DOUBLE NULL DEFAULT '0',
                `custom_d2` DOUBLE NULL DEFAULT '0',
                `custom_d3` DOUBLE NULL DEFAULT '0',
                `custom_d4` DOUBLE NULL DEFAULT '0',
                `custom_d5` DOUBLE NULL DEFAULT '0',
                `custom_d6` DOUBLE NULL DEFAULT '0',
                `custom_d7` DOUBLE NULL DEFAULT '0',
                `epf_yee` DOUBLE NULL DEFAULT '0',
                `socso_yee` DOUBLE NULL DEFAULT '0',
                `deduction` DOUBLE NULL DEFAULT '0' COMMENT 'total deductions',
                `nett` DOUBLE NULL DEFAULT '0',
                `epf_er` DOUBLE NULL DEFAULT '0',
                `socso_er` DOUBLE NULL DEFAULT '0',
                `incometax` DOUBLE NULL DEFAULT '0',
                `with_yer` DOUBLE NULL DEFAULT '0',
                `with_yee` DOUBLE NULL DEFAULT '0',
                `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'optional comment',
                PRIMARY KEY (`id`)
              )
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB;
              ";
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Workforce pay table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_workforce_ph` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `coid` INT(2) NULL DEFAULT '0' COMMENT 'company id',
                `date` VARCHAR(50) NULL DEFAULT '0' COMMENT 'holidays date',
                `description` VARCHAR(255) NULL DEFAULT '0' COMMENT 'holidays name',
                PRIMARY KEY (`id`)
              )
              COMMENT='compilation of public holidays'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1;";
    
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Public Holidays table created <br/>';
        }
    
        $query = "CREATE TABLE IF NOT EXISTS `ek_hr_workforce_roster` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `period` VARCHAR(50) NULL DEFAULT '0' COMMENT 'month - year reference',
                `emp_id` VARCHAR(50) NULL DEFAULT '0',
                `roster` VARCHAR(50) NULL DEFAULT '0' COMMENT 'array of timing',
                `status` VARCHAR(3) NULL DEFAULT NULL,
                `note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'note per roster entry' COLLATE 'utf8mb4_unicode_ci',
                `audit` VARCHAR(25) NULL DEFAULT NULL COMMENT 'tracking change',
                PRIMARY KEY (`id`)
              )
              COMMENT='roster per employee and date'
              COLLATE='utf8_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
    
   
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Roster table created <br/>';
        }
    
    
        $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . $this->t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
   
        return  array(
      '#title'=> $this->t('Installation of Ek_hr module'),
      '#markup' => $markup
      ) ;
    }
} //class
