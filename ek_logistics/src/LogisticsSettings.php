<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Settings.
 * 
 */

namespace Drupal\ek_logistics;


use Drupal\Core\Database\Database;
/**
 * Set and retrieve settings parameters used in sales
 */
  class LogisticsSettings  {


  /**
   * company id. 
   */
  protected $coid;



  public function __construct($coid = NULL) {
    
    if($coid == NULL) $coid = 1;
    $this->coid = $coid;
    $query = "SELECT * from {ek_logi_settings} WHERE coid=:coid";
    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':coid' => $this->coid))->fetchObject();
    
    $this->settings = unserialize($data->settings);
  }
 
  /**
   * Get setting values by name
   *
   * 
   * @param key key of array
   */    
  public function get($key) {
  
    return $this->settings[$key];

  }

  /**
   * Set setting values by name
   *
   * @param key = key of array (setting name)
   * @param value = value of key
   */  
  public function set($key, $value) {
    return  $this->settings[$key] = $value;
  }

  /**
   * Save settings values by key
   *
   */   
  public function save() {
  
    $save = Database::getConnection('external_db', 'external_db')->update('ek_logi_settings')
      ->condition('coid' , $this->coid)
      ->fields(array(
        'settings' => serialize($this->settings ) ,
      ))
      ->execute();    
  
  if($save) return TRUE;
  
  }  


}

