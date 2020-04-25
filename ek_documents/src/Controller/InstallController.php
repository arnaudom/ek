<?php
/**
* @file
* Contains \Drupal\ek\Controller\
*/

namespace Drupal\ek_documents\Controller;

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
        include_once drupal_get_path('module', 'ek_documents') . '/' . 'update.php';
        return  array('#markup' => $markup) ;
    }

    /**
       * install required tables in a separate database
       *
    */

    public function install()
    {
        /**/
        $query = "CREATE TABLE IF NOT EXISTS `ek_documents` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `uid` INT(10) NULL DEFAULT NULL COMMENT 'user id',
                `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id',
                `type` VARCHAR(5) NULL DEFAULT NULL COMMENT 'doc or folder',
                `filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.'  COLLATE 'utf8mb4_unicode_ci',
                `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' COLLATE 'utf8mb4_unicode_ci',
                `folder` VARCHAR(200) NULL DEFAULT NULL COMMENT 'tag or folder',
                `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' COLLATE 'utf8mb4_unicode_ci',
                `date` VARCHAR(50) NULL DEFAULT '0',
                `size` VARCHAR(50) NULL DEFAULT '0',
                `share` VARCHAR(1) NULL DEFAULT '0' COMMENT 'bolean 0=not shared, 1=shared,2=visible all',
                `share_uid` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared users id',
                `share_gid` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared groups',
                `expire` VARCHAR(255) NULL DEFAULT '0' COMMENT 'optional share expiration date',
                PRIMARY KEY (`id`),
                UNIQUE INDEX `Index 2` (`uri`(150))
              )
              COMMENT='holds data about uploaded and shared document'
              COLLATE='utf8mb4_general_ci'
              ENGINE=InnoDB
              AUTO_INCREMENT=1";
    
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Users documents table installed <br/>';
        }
    
        $query = "CREATE TABLE `ek_document_settings` (
                `id` INT(11) NOT NULL,
                `settings` BLOB NULL,
                INDEX `Index 1` (`id`)
            )
            COMMENT='Keep admin settings for documents'
            ENGINE=InnoDB
            ;";
        $db = Database::getConnection('external_db', 'external_db')->query($query);
        if ($db) {
            $markup = 'Documents settings table installed <br/>';
    
            $db = Database::getConnection('external_db', 'external_db')
                ->insert('ek_document_settings')
                ->fields(array(
                  'id' => 0,
                  'settings' => '',
                ))
                ->execute();
            if ($db) {
                $markup = 'Documents settings table updated <br/>';
            }
        }
   
        $link =  Url::fromRoute('ek_admin.main', array(), array())->toString();
        $markup .= '<br/>' . t('You can proceed to further <a href="@c">settings</a>.', array('@c' => $link));
    
        return  array(
      '#title'=> t('Installation of Ek_documents module'),
      '#markup' => $markup
      ) ;
    }
} //class
