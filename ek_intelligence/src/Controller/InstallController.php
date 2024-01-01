<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\
 */

namespace Drupal\ek_intelligence\Controller;

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
     * data update
     *
     */
    public function update() {
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_intelligence') . '/' . 'update.php';
        return array('#markup' => $markup);
    }

    /**
     * install required tables in a separate database
     *
     */
    public function install() {
        try {
            $query = "
     CREATE TABLE `ek_ireports` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
	`serial` VARCHAR(100) NULL DEFAULT NULL COMMENT 'main reference' COLLATE 'utf8_unicode_ci',
	`owner` INT(10) NULL DEFAULT NULL COMMENT 'user id',
	`assign` INT(10) NULL DEFAULT NULL COMMENT 'user id',
	`edit` INT(10) NULL DEFAULT NULL COMMENT 'edition stamp',
	`description` VARCHAR(250) NULL DEFAULT NULL COMMENT 'description or tag' COLLATE 'utf8_unicode_ci',
	`status` VARCHAR(2) NULL DEFAULT '1' COMMENT '0:closed 1:active',
        `pcode` VARCHAR(50) NULL DEFAULT NULL COMMENT 'project reference',
	`month` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
	`year` VARCHAR(5) NULL DEFAULT NULL,
	`gid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL,
	`coid` VARCHAR(5) NULL DEFAULT NULL COMMENT 'company id',
        `abid` VARCHAR(5) NULL DEFAULT NULL COMMENT 'address book id',
	`type` VARCHAR(5) NULL DEFAULT NULL COMMENT '1:briefing 2:report 3:training',
        `format` VARCHAR(20) NULL DEFAULT NULL COMMENT 'format input of report',
	`report` LONGBLOB NULL,
	PRIMARY KEY (`id`),
	INDEX `id` (`id`)
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=1;
    ";


            $db = Database::getConnection('external_db', 'external_db')->query($query);
            if ($db) {
                $markup .= 'Intelligence reports table installed <br/>';
            }
        } catch (Exception $e) {
            $markup .= '<br/><b>Caught exception: ' . $e->getMessage() . "</b>\n";
        }

        if (!$this->moduleHandler->moduleExists('ek_admin')) {
            $markup .= '<br/><b class="messages messages--warning">Main administration module is not installed. Please install this module in order to use Ek_assets module.</b> <br/>';
        } else {
            $link = Url::fromRoute('ek_admin.main', array(), array())->toString();
            $markup .= '<br/>' . $this->t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
        }


        return array(
            '#title' => $this->t('Installation of Ek_intelligence module'),
            '#markup' => $markup
        );
    }

}

//class
