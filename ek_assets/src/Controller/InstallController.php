<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_assets\Controller;

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
    */

    public function update()
    {
        include_once drupal_get_path('module', 'ek_assets') . '/' . 'update.php';
        return  array('#markup' => $markup) ;
    }
    /**
       * install required tables in a separate database
       *
    */

    public function install()
    {
        try {
            $query = "
    CREATE TABLE `ek_assets` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`asset_name` VARCHAR(250) NULL DEFAULT NULL COMMENT 'name if asset' COLLATE 'utf8_unicode_ci',
	`asset_brand` VARCHAR(250) NULL DEFAULT NULL COMMENT 'asset tag' COLLATE 'utf8_unicode_ci',
	`asset_ref` VARCHAR(250) NULL DEFAULT NULL COMMENT 'reference' COLLATE 'utf8_unicode_ci',
	`coid` INT(5) NOT NULL DEFAULT '1' COMMENT 'company id',
	`unit` INT(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'quantity',
	`aid` VARCHAR(15) NULL DEFAULT NULL COMMENT 'accounts classification' COLLATE 'utf8_unicode_ci',
	`asset_comment` MEDIUMTEXT NULL COMMENT 'comment' COLLATE 'utf8_unicode_ci',
	`asset_doc` VARCHAR(250) NULL DEFAULT NULL COMMENT 'uri of document reference' COLLATE 'utf8_unicode_ci',
	`asset_pic` VARCHAR(250) NULL DEFAULT NULL COMMENT 'uri of picture file' COLLATE 'utf8_unicode_ci',
	`asset_value` DOUBLE NULL DEFAULT NULL COMMENT 'value of asset',
	`currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'currency of value' COLLATE 'utf8_unicode_ci',
	`date_purchase` VARCHAR(15) NOT NULL DEFAULT '0000-00-00' COMMENT 'date of purchase' COLLATE 'utf8_unicode_ci',
        `eid` VARCHAR(15) NULL DEFAULT NULL COMMENT 'HR kink' COLLATE 'utf8_unicode_ci',
	PRIMARY KEY (`id`)
        )
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
         AUTO_INCREMENT=1";

    
            $db = Database::getConnection('external_db', 'external_db')->query($query);
            if ($db) {
                $markup .= 'Assets table installed <br/>';
            }
        } catch (Exception $e) {
            $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
        }
        try {
            $query = "
    CREATE TABLE `ek_assets_amortization` (
	`asid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id from main table',
	`term_unit` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Y = year M = month' COLLATE 'utf8_unicode_ci',
	`method` VARCHAR(10) NULL DEFAULT NULL COMMENT 'depreciation method: 1 = straight line' COLLATE 'utf8_unicode_ci',
	`term` DOUBLE NULL DEFAULT '0' COMMENT 'number of years or months',
	`amort_rate` DOUBLE NULL DEFAULT '0' COMMENT 'rate of amortisation %',
	`amort_value` DOUBLE NULL DEFAULT '0' COMMENT 'value of amortisation cumulated',
	`amort_salvage` DOUBLE NULL DEFAULT '0' COMMENT 'estimated salvage value after depreciation',
	`amort_record` TEXT NULL COMMENT 'array record of amortization schedule' COLLATE 'utf8_unicode_ci',
	`amort_status` VARCHAR(5) NULL DEFAULT '0' COMMENT '1=amortized 0 not amortized' COLLATE 'utf8_unicode_ci',
	`alert` VARCHAR(255) NULL DEFAULT '0' COMMENT 'alert uids' COLLATE 'utf8_unicode_ci',
	PRIMARY KEY (`asid`)
        )
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB
        ROW_FORMAT=DYNAMIC
        AUTO_INCREMENT=1";

    
            $db = Database::getConnection('external_db', 'external_db')->query($query);
            if ($db) {
                $markup .= 'Assets amortization table installed <br/>';
            }
        } catch (Exception $e) {
            $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
        }
    
        if (!$this->moduleHandler->moduleExists('ek_admin')) {
            $markup .= '<br/><b class="messages messages--warning">Main administration module is not installed. Please install this module in order to use Ek_assets module.</b> <br/>';
        } else {
            $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
            $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
        }
        

        return  array(
      '#title'=> t('Installation of Ek_assets module'),
      '#markup' => $markup
      ) ;
    }
} //class
