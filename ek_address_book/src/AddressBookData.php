<?php

namespace Drupal\ek_address_book;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Interface for address book data data
 * @addresslist : list by id  or name
 * @getname : get entry name from id
 * @getid : get entry id from name
 * @geturl : return an html format url
 */
 class AddressBookData {
 
 
   /**
   * Constructs a AddressBookData
   *
   * 
   */
  public function __construct() {
    
  }  
 
   /**
   * return an array of addresses by id, name  
   * @param type 1 = client, 2 = supplier, 3 = other
   * @param status 1 or 0
   * @param category (variable) ie 'head office'
   */ 
  public static function addresslist($type = NULL, $status = NULL, $category = NULL) {
  
  if($type  == NULL) $type = '%';
  if($status  == NULL) $status = '%';
  if($category  == NULL) $category = '%';
  
    
    $query = "SELECT id,name from {ek_address_book} WHERE type like :t and category like :c and status like :s order by name";
    $a = array(':t'=> $type, ':c' => $category, ':s' => $status);   

    $options = Database::getConnection('external_db', 'external_db')->query($query,$a)->fetchAllKeyed();
    
    return $options;
  
  }
 
 
   /**
   * @return 
   *    name from id
   * 
   */ 
  public static function getname($id = NULL) {
  
    if($id != NULL) {
      $query = "SELECT name from {ek_address_book} WHERE id=:id";
      return Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => $id))->fetchField();
    } else {
      return NULL;
    }
  
  }
 
    /**
   * @return 
   *    id from name
   *
   * 
   */ 
  public static function getid($name = NULL) {
  
    if($name != NULL) {
      $query = "SELECT id from {ek_address_book} WHERE name=:n";
      return Database::getConnection('external_db', 'external_db')
        ->query($query, array(':n' => trim($name)))->fetchField();
    } else {
      return NULL;
    }
  
  }
 
   /**
   * @param int id
   *  address book id
   * @param array option
   *  formatting options
   * i.e. ['short' => 8] for 8 characters display
   * @return html string
   *    linked name from id
   *    \Drupal\ek_address_book\AddressBookData::geturl($id, $option);
   * 
   */ 
  public static function geturl($id = NULL, $option = NULL) { 
 
    $query = "SELECT name from {ek_address_book} where id=:id";
    
    $name = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $id))
            ->fetchField();
    $fullname = $name;
    if(isset($option['short'])) {
         $name = substr($name , 0, $option['short']);
    }
     
    $link =  Url::fromRoute('ek_address_book.view', array('abid' => $id))->toString();
    
    return "<a title='" . $fullname . "' href='". $link ."'>" . $name . "</a>";
 
 }
 
 
 } // class