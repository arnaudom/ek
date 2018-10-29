<?php

/**
 * @file
 * Contains \Drupal\ek_admin\SalesSettings.
 * 
 */

namespace Drupal\ek_sales;


use Drupal\Core\Database\Database;
/**
 * Set and retrieve settings parameters used in sales
 */
  class SalesSettings  {


  /**
   * company id. 
   */
  protected $coid;



  public function __construct($coid = NULL) {
    
    if($coid == NULL) {
        $coid = 0;
    }
    $this->coid = $coid;
    $query = "SELECT * from {ek_sales_settings} WHERE coid=:coid";
    $data = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':coid' => $this->coid))
            ->fetchObject();
    
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
   * Set setting values by name(key)
   *
   * 
   * @param key key of array
   * @param value = key value
   */    

  public function set($key, $value) {
    return  $this->settings[$key] = $value;
  }

  /**
   * Save settings 
   *
   */     
  public function save() {
  
    $save = Database::getConnection('external_db', 'external_db')->update('ek_sales_settings')
      ->condition('coid' , $this->coid)
      ->fields(array(
        'settings' => serialize($this->settings ) ,
      ))
      ->execute();    
  
    if($save) {
        return TRUE;
    }
  
  }  


}

