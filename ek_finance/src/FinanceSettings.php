<?php
 
/**
 * @file
 * Contains \Drupal\ek_finance\FinanceSettings.
 * 
 */

namespace Drupal\ek_finance;

use Drupal\Core\Database\Database;

/**
 * Set and retrieve settings parameters used in accounts
 */
  class FinanceSettings  {




  public function __construct() {
    $data = Database::getConnection('external_db', 'external_db')
            ->query("SELECT settings from {ek_finance}")->fetchObject();
    
    $this->settings = unserialize($data->settings);

  }
 

/**
 * Get settings by name
 * @param name = the setting name
 */  
  public function get($name ) {
  
  return  $this->settings[$name];
    

  }

/**
 * Set settings by name
 * @param name = the setting name
 * @param value = the setting value
 */
 
  public function set($name , $value) {
  
    
    $this->settings[$name] = $value;    
  
  }
 
/**
 * Save settings
 * 
 * 
 */ 
   
  public function save() {
  
    $data = serialize($this->settings ) ;
    if (Database::getConnection('external_db', 'external_db')->update('ek_finance')
      ->condition('id' , '1')
      ->fields(array(
        'settings' => $data,
      ))
      ->execute()    
    ) return true;
  
  }
  
}

