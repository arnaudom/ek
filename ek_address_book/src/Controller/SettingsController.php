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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Controller routines for ek module routes.
*/
class SettingsController extends ControllerBase
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
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler){
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }


    /**
       * data update
       *
    */

    public function update(){
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_address_book') . '/' . 'update.php';
        return  array('#markup' => $markup) ;
    }

    /**
       * Return settings management form
       *
    */

    public function settings(Request $request){
    }
} 
