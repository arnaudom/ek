<?php

namespace Drupal\ek_products;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
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

/**
* generate internal/external link to item page
* @param string $code item code
* @param bolean $ext TRUE to open link in new tab
* @return  formated url from item code input
*  
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
* generate internal/external link to item page
* @param string $id item id
* @param bolean $ext TRUE to open link in new tab
* @return  formated url from item code input
*  
*/

    public static function geturl_byId($id, $ext = NULL) { 
        
        $link = NULL;
        $url =  Url::fromRoute('ek_products.view', array('id' => $id), [])->toString(); 
                if($ext == TRUE) {
                   $link =  "<a target='_blank' href='". $url ."'>" . $id . "</a>";
                } else {
                   $link =  "<a href='". $url ."'>" . $id . "</a>"; 
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
 
     /**ajaxlookupitem
     * Util to return item description callback.
     * @param option
     *  image: to return an image link with reponse
     * @param id
     *  a company id to filter by company, 0 for include all
     * @param term
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxlookupitem(Request $request, $id) {

        $term = $request->query->get('q');
        $option = $request->query->get('option');
        
        if(strlen($term) < 3) {
            return new JsonResponse([]);
        }
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_items', 'i');
        $query->fields('i', ['id', 'itemcode', 'description1', 'description2', 'supplier_code']);
        $query->leftJoin('ek_item_barcodes', 'b', 'i.itemcode = b.itemcode');
        $query->fields('b', ['barcode']);
        $query->leftJoin('ek_item_images', 'g', 'i.itemcode = g.itemcode');
        $query->fields('g', ['uri']);
        $condition = $query->orConditionGroup()
            ->condition('i.id', $term . "%", 'like')
            ->condition('i.itemcode', $term . "%", 'like')
            ->condition('i.description1', $term . "%", 'like')    
            ->condition('b.barcode', $term . "%", 'like')       
            ->condition('i.supplier_code', $term . "%", 'like');
        $query->condition($condition);
        
        
        if ($id != '0') {
            
            $query->condition('i.coid', $id);
                    
        } 
         
        //$query->limit(50);
        
        $data = $query->execute(); 
        
        $name = array();
        while ($r = $data->fetchObject()) {

            if (strlen($r->description1) > 30) {
                $desc = substr($r->description1, 0, 30) . "...";
            } else {
                $desc = $r->description1;
            }
            if (strlen($r->description2) > 60) {
                $desc2 = substr($r->description2, 0, 60) . "...";
            } else {
                $desc2 = $r->description2;
            }
            
            if($option == 'image') {
                $line = [];
                if ($r->uri) {
                         $pic = "<img class='product_thumbnail' src='"
                        . file_create_url($r->uri) . "'>";
                    } else {
                        $default = file_create_url(drupal_get_path('module', 'ek_products') . '/css/images/default.jpg');
                        $pic = "<img class='product_thumbnail' src='"
                        . $default . "'>";
                    }
                    $line['picture'] = isset($pic) ? $pic : '';
                    $line['description'] = $desc;
                    $line['name'] = $r->id . " " . $r->itemcode . " " . $r->barcode . " " . $desc . " " .$r->supplier_code;
                    $line['id'] = $r->id;
                    
                    $name[] = $line;
                
            } else {
                $settings = new \Drupal\ek_products\ItemSettings();
                $str = $r->id . " " . $r->itemcode . " ";
                
                if($settings->get('auto_barcode') == 1) {
                    $str .= $r->barcode . " ";
                }
                
                if($settings->get('auto_main_description') == 1) {
                    $str .= $desc . " ";
                } 
                 
                if($settings->get('auto_supplier_code') == 1) {
                    $str .= $r->supplier_code;
                } 
                
                if($settings->get('auto_other_description') == 1) {
                    $str .= '<br>' . $desc2;
                } 
                
                $name[] = $str;
            }
           
        }
        return new JsonResponse($name);
    }
     
 }
