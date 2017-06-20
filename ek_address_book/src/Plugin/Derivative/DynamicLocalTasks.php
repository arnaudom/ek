<?php
/**
 * @file
 * Contains \Drupal\ek_address_book\Plugin\Derivative\DynamicLocalTasks.
 * add a taks link to sales data if available
 */

namespace Drupal\ek_address_book\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Defines dynamic local tasks.
 */
class DynamicLocalTasks extends DeriverBase implements ContainerDeriverInterface{
   /* The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;
  
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('module_handler')
    );
  }  
  
      /**
   * Constructs a  object.
   *
   *   The moduleHandler service.
   */
  public function __construct( ModuleHandler $module_handler) {
    $this->moduleHandler = $module_handler;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition ) {
    if($this->moduleHandler->moduleExists('ek_sales')) {
      //$p = explode('/', Drupal::getBaseUrl());
      //$p = array_reverse($p);
      
      $this->derivatives['ek_address_book.sales-data'] = $base_plugin_definition;
      $this->derivatives['ek_address_book.sales-data']['title'] = t('Sales data');
      $this->derivatives['ek_address_book.sales-data']['route_name'] = 'ek_sales.data';
      $this->derivatives['ek_address_book.sales-data']['base_route'] = "ek_address_book.view";
      $this->derivatives['ek_address_book.sales-data']['route_parameters'] = array('abid' => 1);
      $this->derivatives['ek_address_book.sales-data']['weight'] = 5;
    }
  
  return $this->derivatives;  
  }

}
?>