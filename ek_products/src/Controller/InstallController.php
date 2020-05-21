<?php

/**
 * @file
 * Contains \Drupal\ek_products\Controller\
 */

namespace Drupal\ek_products\Controller;

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
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
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
     * Update
     *
     */
    public function update() {

        //update and conversion of DB
        include_once drupal_get_path('module', 'ek_products') . '/' . 'update.php';
        return array('#markup' => $markup);
    }

    /**
     * install required tables in a separate database
     *
     */
    public function install() {
        /**/
        $query = "CREATE TABLE IF NOT EXISTS `ek_items` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'main ref code',
	`coid` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'company id',
	`type` VARCHAR(45) NULL DEFAULT '' COMMENT 'tag for type - taxonomy, ex service, tool',
	`description1` TEXT NULL COMMENT 'main description',
	`description2` TEXT NULL COMMENT 'other description',
	`supplier_code` VARCHAR(100) NULL DEFAULT '' COMMENT 'supplier ref code',
	`active` INT(1) NULL DEFAULT '1' COMMENT '1 = yes 0 = no',
	`collection` VARCHAR(100) NULL DEFAULT '' COMMENT 'tag - taxonomy',
	`department` VARCHAR(100) NULL DEFAULT '' COMMENT 'tag - taxonomy',
	`family` VARCHAR(100) NULL DEFAULT '' COMMENT 'tag - taxonomy',
	`size` VARCHAR(100) NULL DEFAULT '' COMMENT 'a size description',
	`color` VARCHAR(100) NULL DEFAULT '' COMMENT 'a color ref.',
	`supplier` VARCHAR(100) NULL DEFAULT '' COMMENT 'a supplier ref.',
	`stamp` VARCHAR(100) NULL DEFAULT '' COMMENT 'edit stamp',
        `format` VARCHAR(20) NULL DEFAULT '' COMMENT 'description format',
	UNIQUE INDEX `Index 1` (`id`, `itemcode`)
        )
        COMMENT='items list'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB
        ";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'items table created <br/>';
        }

        $query = "CREATE TABLE `ek_item_settings` (
        `id` INT(10) UNSIGNED NOT NULL COMMENT 'Primary Key: Unique ID.',
        `settings` LONGBLOB NULL COMMENT 'A serialized array containing the settings.',
        PRIMARY KEY (`id`)
        )
        COMMENT='Stores items settings.'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";

        $db = Database::getConnection('external_db', 'external_db')->query($query);

        Database::getConnection('external_db', 'external_db')
                ->insert('ek_item_settings')
                ->fields(array(
                    'id' => 0,
                    'settings' => '',
                ))
                ->execute();

        if ($db) {
            $markup .= 'items settings table created <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_item_barcodes` (
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'code in main table',
	`barcode` VARCHAR(45) NOT NULL DEFAULT '0' COMMENT 'barcode',
	`encode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'encoding value',
	PRIMARY KEY (`id`),
	INDEX `Index 2` (`itemcode`)
        )
        COMMENT='Itams barcode list'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'items barcodes table created <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_item_images` (
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'code in main table',
	`uri` VARCHAR(255) NOT NULL COMMENT 'file uri',
	PRIMARY KEY (`id`),
	INDEX `Index 2` (`itemcode`)
        )
        COMMENT='List images for items'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'items images table created <br/>';
        }


        $query = "CREATE TABLE IF NOT EXISTS `ek_item_packing` (
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`itemcode` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'code in main table',
	`units` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'stock units',
	`unit_measure` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'unit measure',
	`item_size` VARCHAR(45) NULL DEFAULT NULL COMMENT 'size',
	`pack_size` VARCHAR(45) NULL DEFAULT NULL COMMENT 'packing',
	`qty_pack` VARCHAR(45) NULL DEFAULT NULL COMMENT 'quantity per pack',
	`logistic_cost` DOUBLE NULL DEFAULT NULL COMMENT 'a unit cost in value',
	`c20` VARCHAR(30) NULL DEFAULT NULL COMMENT 'container 20 capacity',
	`c40` VARCHAR(30) NULL DEFAULT NULL COMMENT 'container 40 capacity',
	`min_order` VARCHAR(30) NULL DEFAULT NULL COMMENT 'order minimum',
	PRIMARY KEY (`id`),
	INDEX `Index 2` (`itemcode`)
        )
        COMMENT='packing data and stock value'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'items packings table created <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_item_prices` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'code in main table',
	`purchase_price` DOUBLE NOT NULL DEFAULT '0',
	`currency` VARCHAR(10) NOT NULL DEFAULT '',
	`date_purchase` VARCHAR(45) NOT NULL DEFAULT '',
	`selling_price` DOUBLE NOT NULL DEFAULT '0' COMMENT 'local',
	`promo_price` DOUBLE NOT NULL DEFAULT '0' COMMENT 'local',
	`discount_price` DOUBLE NOT NULL DEFAULT '0' COMMENT 'local',
	`exp_selling_price` DOUBLE NOT NULL DEFAULT '0' COMMENT 'export',
	`exp_promo_price` DOUBLE NOT NULL DEFAULT '0' COMMENT 'export',
	`exp_discount_price` DOUBLE NOT NULL DEFAULT '0' COMMENT 'export',
	`loc_currency` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'local',
	`exp_currency` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'export',
	PRIMARY KEY (`id`)
        )
        COMMENT='Prices data'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'items prices table created <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_item_price_history` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`itemcode` VARCHAR(100) NOT NULL DEFAULT '',
	`date` VARCHAR(50) NOT NULL DEFAULT '0000-00-00',
	`price` DOUBLE NOT NULL DEFAULT '0',
	`currency` VARCHAR(10) NULL DEFAULT NULL,
	`type` VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'type pp sp ep',
	PRIMARY KEY (`id`)
        )
        COMMENT='Record of prices history per item'
        COLLATE='utf8mb4_general_ci'
        ENGINE=InnoDB";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'items prices history created <br/>';
        }

        $link = Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . $this->t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));

        return array(
            '#title' => $this->t('Installation of Ek_products module'),
            '#markup' => $markup
        );
    }

}

//class
