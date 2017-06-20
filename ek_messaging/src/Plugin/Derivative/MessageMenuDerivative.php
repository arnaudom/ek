<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Plugin\Derivative\MessageMenuDerivative.
 */

namespace Drupal\ek_messaging\Plugin\Derivative;

use Drupal\Core\Database\Database;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MessageMenuDerivative extends DeriverBase implements ContainerDeriverInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {dpm('test');
    $links = array();
$links['ek_messaging_inbox_c' ] = [
                                'title' => t('Messages') . ' (1)',
                                'menu_name' => 'tools',
                                'route_name' => 'ek_messaging_inbox',
                                //'route_parameters' => [ ],
                              ] + $base_plugin_definition;
           
    
   

    return $links;
  }
}