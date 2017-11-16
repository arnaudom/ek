<?php

namespace Drupal\ek_products;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

/**
 * 
 *
 * 
 *  Interface to provide data to other modules quering items info
 *  - 
 */
 class ItemData {
 

  
  /**
   * 
   *   
   */
  public function __construct() {
    


  }  

/*
     * @return 
     *  a item view url from item code input
     *  generate internal/external link to the view
     * @param mix $code 
     *  item code
     * @param bolean $ext
     *  flag to open link in new window
     */

    public static function geturl_bycode($code, $ext = NULL) { 
        
        if($code) {
        $query = "SELECT id FROM {ek_items} WHERE itemcode = :c";
        $i = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':c' => $code))
            ->fetchObject();
        $url =  Url::fromRoute('ek_products.view', array('id' => $i->id), [])->toString(); 
                if($ext == TRUE) {
                   $link =  "<a target='_blank' href='". $url ."'>" . $code . "</a>";
                } else {
                   $link =  "<a href='". $url ."'>" . $code . "</a>"; 
                }
        } else {
            $link = NULL;
        }
        return  $link;
    }
    
    
  /**
   * Return item comprehensive name references by id 
   *
   * @param id = item id from table
  */   
 public static function item_byid($id) { 

    $query = "SELECT * from {ek_items} where id like :id";
    $thisitem = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $id))
            ->fetchObject();
    if($thisitem) {
    if (strlen($thisitem->description1)>30) {
      $desc = substr($thisitem->description1,0,30)."...";
        } else {
          $desc = $thisitem->description1;
          }
    $name = $thisitem->id . " " . $thisitem->itemcode . " " . $desc ; 
    
    return $name;
    } else {
        return NULL;
    }
 
 }
 

  /**
   * Return item omprehensive name references by item code
   * @param code = item code from table
  */   
 public static function item_bycode($code) { 

    $query = "SELECT * from {ek_items} where itemcode=:code";
    $thisitem = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':code' => $code))
            ->fetchObject();
    if($thisitem) {
    if (strlen($thisitem->description1)>30) {
      $desc = substr($thisitem->description1,0,30)."...";
        } else {
          $desc = $thisitem->description1;
          }
    $name = $thisitem->id . " " . $thisitem->itemcode . " " . $desc ; 
     
    return $name;
     } else {
        return NULL;
    }
 }

  /**
   * Return item description by id 
   *
   * @param id = item id from table
   * @param key = 1 : description1, 2 : description 2, 3 : description1+description2
  */   
 public static function item_description_byid($id, $key = NULL) { 
 
        $query = "SELECT description1,description2 from {ek_items} where id=:id ";
        $data = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':id' => $id))
          ->fetchObject();
         
      if($data) {
      
          switch($key) {
            case  1:
            $description = $data->description1;
            break;
            case  2:
            $description = $data->description2;
            break; 
            case  3:
            $description = $data->description1 . " " . $data->description2;
            break;                    
          
          }
      

      } else {
        $description = NULL;
      }
  return $description;
 
 }

  /**
   * Return item description by code
   *
   * @param code = item code from table
   * @param key = 1 : description1, 2 : description 2, 3 : description1+description2
  */   
 public static function item_description_bycode($code, $key = NULL) { 
 
        $query = "SELECT description1,description2 from {ek_items} where itemcode=:code ";
        $data = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':code' => $code))
          ->fetchObject();
          
      if($data) {
      
          switch($key) {
            case  1:
            $description = $data->description1;
            break;
            case  2:
            $description = $data->description2;
            break; 
            case  3:
            $description = $data->description1 . " " . $data->description2;
            break;                    
          
          }
      

      } else {
        $description = NULL;
      }
  return $description;
 
 }
 
   /**
   * Return a price by item code and type 
   *
   * @param code = item code
   * @param key = 1 : normal, 2 : promo, 3 : discount,  4 : exp_normal, 5 : exp_promo, 6 : exp_discount
   * @return array of key => values pairs (i.e. $price[0]->selling_price) or value (double)
  */   
 public static function item_sell_price($code, $key = NULL) { 

           if($key == NULL) {
               //retrieve all prices in array
               $query = "SELECT * from {ek_item_prices} where itemcode=:code";
               $price = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':code' => $code))
                ->fetchAll();
               
           } else {
                switch($key) {
                 case  1:
                 //default:
                     $query = "SELECT selling_price from {ek_item_prices} where itemcode=:code";
                 break;   
                 case  2:
                     $query = "SELECT promo_price from {ek_item_prices} where itemcode=:code";
                 break; 
                 case  3:
                     $query = "SELECT discount_price from {ek_item_prices} where itemcode=:code";
                 break;   
                 case  4:
                     $query = "SELECT exp_selling_price from {ek_item_prices} where itemcode=:code";
                 break;
                 case  5:
                     $query = "SELECT exp_promo_price from {ek_item_prices} where itemcode=:code";
                 break;
                 case  6:
                     $query = "SELECT exp_discount_price from {ek_item_prices} where itemcode=:code";
                 break; 
               }
             $price = Database::getConnection('external_db', 'external_db')
               ->query($query, array(':code' => $code))
               ->fetchField();
           }
        return $price;
 
 
 }
 
   /**
   * Return a price type by code and value
   * reverse check price type
   *
   * @param  (string) code = item code
   * @param  (double) value = the value of the item
   * @return int or false
  */   
 public static function item_sell_price_type($code, $value) { 
 
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_item_prices')
            ->fields('ek_item_prices')
            ->condition('itemcode', $code);
     
        $data = $query->execute()->fetchObject();

        $prices = [$data->selling_price => 1 , $data->promo_price => 2, $data->discount_price => 3,
            $data->exp_selling_price => 4, $data->exp_promo_price => 5, $data->exp_discount_price => 6];
        
        if(isset($prices[$value])) {
            return $prices[$value];
        } else {
            return FALSE;
        }
        
 
 }
     
 }//class
