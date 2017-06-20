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
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_documents\DocumentsData;

/**
* Controller routines for ek module routes.
*/
class DocumentsController extends ControllerBase {

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
   * Return my documents
   *
*/

 public function documents(Request $request) {
 
 $items = array();
 $items['bar'] = "
 <div class='bar'>
    <div class='float-right'>
    <div id='expand' class='open-ico'></div>
   </div>
   <div class='float-right' id='gridview'>
    <div class='grid-ico'></div>
   </div>
   <div class='float-right' id='listview'>
    <div class='list-ico'></div>
   </div>
 </div>";
 
     return array(
      '#theme' => 'ek_documents_my',
      '#items' => $items,
      '#attached' => array(
        'drupalSettings' => array('ek_documents' => 'myDocs' ),        
        'library' => array('ek_documents/ek_documents_display'),
        
      ),
    );
 
 }


/**
   * Return shared documents
   *
*/

 public function documentsshared(Request $request) {

 $items = array();
 $items['bar'] = "
 <div class='bar'>
   <div class='float-right'>
    <div id='expand' class='open-ico'></div>
   </div>
   <div class='float-right' id='gridview'>
    <div class='grid-ico'></div>
   </div>
   <div class='float-right' id='listview'>
    <div class='list-ico'></div>
   </div>
 </div>"; 
     return array(
      '#theme' => 'ek_documents_shared',
      '#items' => $items,
      '#attached' => array(
        'drupalSettings' => array('ek_documents' => 'sharedDocs'),
        'library' => array('ek_documents/ek_documents_display'),
        
      ),
    ); 
 
 
 }
 
/**
   * Return common shared documents
   *
*/

 public function documentscommon(Request $request) {

 $items = array();
 $items['bar'] = "
 <div class='bar'>
   <div class='float-right'>
    <div id='expand' class='open-ico'></div>
   </div>
   <div class='float-right' id='gridview'>
    <div class='grid-ico'></div>
   </div>
   <div class='float-right' id='listview'>
    <div class='list-ico'></div>
   </div>
 </div>"; 
     return array(
      '#theme' => 'ek_documents_common',
      '#items' => $items,
      '#attached' => array(
        'drupalSettings' => array('ek_documents' => 'commonDocs'),
        'library' => array('ek_documents/ek_documents_display'),
        
      ),
    ); 
 
 
 }
   
} //class