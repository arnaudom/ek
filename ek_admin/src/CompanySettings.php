<?php

/**
 * @file
 * Contains \Drupal\ek_admin\CompanySettings.
 * 
 */

namespace Drupal\ek_admin;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
/**
 * Set and retrieve settings parameters used in accounts
 */
  class CompanySettings  {


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


  public function __construct($coid = NULL) {
     $this->coid = $coid;
     $query = "SELECT settings from {ek_company} WHERE id=:id";
     $data = Database::getConnection('external_db', 'external_db')
             ->query($query, array(':id' => $this->coid))->fetchObject(); 
     $this->settings = unserialize($data->settings);

  }
 
/**
 * Get a setting by setting name and optional currency reference if needed
 */  
  public function get($name , $currency = NULL) {
  
    if(!empty($this->settings)) {
      if(!$currency == '') {
        return  $this->settings[$currency][$name];
      } elseif(isset($this->settings[$name])) {
        return  $this->settings[$name];
      } else {
          return NULL;
      }
    }
  }

/**
 * Set a setting value by setting name and optional currency reference if needed
 */ 
  public function set($name , $value, $currency = NULL) {
  
    if(!$currency == '') {
    
    if(empty($this->settings[$currency])) {
    $this->settings[$currency] = array();
    }
     $this->settings[$currency][$name] = $value;
    
    
    } else {
    
    $this->settings[$name] = $value; 

      
    }
    
  
  }

/**
 * save settings 
 */    
  public function save() {
  
    $data = serialize($this->settings ) ;
    Database::getConnection('external_db', 'external_db')->update('ek_company')
      ->condition('id' , $this->coid)
      ->fields(array(
        'settings' => $data,
      ))
      ->execute();    
  
  }  


}

