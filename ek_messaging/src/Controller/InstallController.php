<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\
 */

namespace Drupal\ek_messaging\Controller;

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
     * install required tables in a separate database
     *
     */
    public function install() {
        $query = "CREATE TABLE IF NOT EXISTS `ek_messaging` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `stamp` INT(11) NOT NULL DEFAULT '0',
                `from_uid` INT(11) NOT NULL DEFAULT '0' COMMENT 'user sending message',
                `to` LONGBLOB NOT NULL COMMENT 'user id, multiple or group',
                `to_group` INT(11) NULL DEFAULT '0' COMMENT 'record the goup id the message is sent to',
                `type` INT(3) NOT NULL DEFAULT '0' COMMENT '1=private,2=multiple,3=group',
                `status` LONGBLOB NULL COMMENT '0,NULL = unread, array of readers id',
                `reply` LONGBLOB NULL COMMENT 'list of users reply',
                `inbox` LONGBLOB NULL COMMENT 'list of users in inbox',
                `archive` LONGBLOB NULL COMMENT 'list of user in archive',
                `subject` VARCHAR(255) NULL DEFAULT NULL COMMENT 'subject',
                `priority` VARCHAR(5) NOT NULL DEFAULT '2' COMMENT '1=high, 2 meduim, 3 low',
                INDEX `Index 1` (`id`)
                )
                COMMENT='record and management of messages between users'
                COLLATE='utf8mb4_general_ci'
                ENGINE=InnoDB
                AUTO_INCREMENT=1";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Main message table installed <br/>';
        }

        $query = "CREATE TABLE IF NOT EXISTS `ek_messaging_text` (
                 `id` INT(11) NOT NULL,
                `text` LONGBLOB NULL COMMENT 'text of the message',
                `format` VARCHAR(20) NULL DEFAULT NULL COMMENT 'text format',
                  INDEX `Index 1` (`id`)
                )
                COMMENT='record messaging body content'
                COLLATE='utf8mb4_general_ci'
                ENGINE=InnoDB
                ROW_FORMAT=COMPACT";

        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup .= 'Secondary message table installed<br/>';
        }

        $link = Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . $this->t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));

        return array(
            '#title' => $this->t('Installation of Ek_messaging module'),
            '#markup' => $markup
        );
    }

}

//class
