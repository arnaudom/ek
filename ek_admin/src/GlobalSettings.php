<?php

/**
 * @file
 * Contains \Drupal\ek_admin\GlobalSettings.
 *
 */

namespace Drupal\ek_admin;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

/**
 * Set and retrieve gloabal settings parameters
 */
  class GlobalSettings
  {


  /**
   * company id.
   *
   *
   */
      protected $coid;
      /**
       * The database connection to use.
       *
       * @var \Drupal\Core\Database\Connection
       */
      protected $connection;


      public function __construct($coid = null)
      {
          $this->coid = $coid;
     
          $external = Database::getConnectionInfo('external_db');
     
          if (!empty($external)) {
              $query = "SHOW TABLES LIKE 'ek_admin_settings'";
              $data = Database::getConnection('external_db', 'external_db')
                         ->query($query)
                         ->fetchField();
              if ($data == 'ek_admin_settings') {
                  /**/
                  $query = "SELECT settings from {ek_admin_settings} WHERE coid=:id";
                  $data = Database::getConnection('external_db', 'external_db')
                     ->query($query, array(':id' => $this->coid))
                     ->fetchObject();
                  $this->settings = unserialize($data->settings);
              } else {
                  //setting table not installed
                  $this->settings = array();
              }
          } else {
              //there is no external db defined
              $this->settings = array();
          }
      }
 
      /**
       * Get a setting by setting name
       */
      public function get($name)
      {
          if (!empty($this->settings)) {
              return  $this->settings[$name];
          }
      }

      /**
       * Set a setting value by setting name
       */
      public function set($name, $value)
      {
          $this->settings[$name] = $value;
      }

      /**
       * save settings
       */
      public function save()
      {
          $data = serialize($this->settings) ;
          Database::getConnection('external_db', 'external_db')
      ->update('ek_admin_settings')
      ->condition('coid', $this->coid)
      ->fields(array(
        'settings' => $data,
      ))
      ->execute();
      }
  }
