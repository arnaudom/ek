<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';


try {
  
  $query = "RENAME TABLE `items` TO `ek_items`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Items table renamed';

  $query = "UPDATE {ek_items} set stop=0 WHERE stop='stop'";
  Database::getConnection('external_db', 'external_db')->query($query);
  $query = "UPDATE {ek_items} set stop=1 WHERE stop=''";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Items stop table updated';

   
  $query = "
  ALTER TABLE `ek_items`
	CHANGE COLUMN `type` `type` VARCHAR(45) NULL DEFAULT '' AFTER `id`,
	CHANGE COLUMN `itemcode` `itemcode` VARCHAR(100) NULL DEFAULT '' AFTER `type`,
	CHANGE COLUMN `description1` `description1` TEXT NULL AFTER `itemcode`,
	CHANGE COLUMN `description2` `description2` TEXT NULL AFTER `description1`,
	CHANGE COLUMN `suppliercode` `supplier_code` VARCHAR(100) NULL DEFAULT '' AFTER `description2`,
	CHANGE COLUMN `stop` `active` INT(1) NULL DEFAULT '1' COMMENT '1 = yes 0 = no' AFTER `supplier_code`,
	ADD COLUMN `coid` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'company id' AFTER `id`,
	ADD COLUMN `collection` VARCHAR(100) NULL DEFAULT '' AFTER `active`,
	ADD COLUMN `department` VARCHAR(100) NULL DEFAULT '' AFTER `collection`,
	ADD COLUMN `family` VARCHAR(100) NULL DEFAULT '' AFTER `department`,
	ADD COLUMN `size` VARCHAR(100) NULL DEFAULT '' AFTER `family`,
	ADD COLUMN `color` VARCHAR(100) NULL DEFAULT '' AFTER `size`,
	ADD COLUMN `supplier` VARCHAR(100) NULL DEFAULT '' AFTER `color`,
	ADD COLUMN `stamp` VARCHAR(20) NULL DEFAULT '' AFTER `supplier`,
	ADD INDEX `Index 1` (`itemcode`)
  ";

  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Items table altered';

  $query = "UPDATE {ek_items} SET coid=1";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Items table updateded';  
    /* 
    * move data from item_info to items
    */

    $query = "SELECT * from item_info";

    $data = Database::getConnection('external_db', 'external_db')->query($query);

    While ($r = $data->fetchObject() ) {

    $fields= array (
                    'family' => $r->family  ,
                    'size' =>  $r->size  ,
                    'color' =>  $r->color  ,
                    'supplier' =>  $r->supplier ,
                    'supplier_code' =>  $r->scoderef 
    );
    Database::getConnection('external_db', 'external_db')
      ->update('ek_items')
      ->fields($fields)
      ->condition('itemcode', $r->itemcode)
      ->execute();
    /*
    $query = "UPDATE ek_items
                set `collection` = " . $r->collection .",
                    `family` = " . $r->family . " ,
                    `size` = " . $r->size . " ,
                    `color` = " . $r->color . " ,
                    `supplier` = " . $r->supplier . ",
                    `supplier_code` = " . $r->scoderef . "
                    Where `itemcode` = " . $r->itemcode ;
      
      Database::getConnection('external_db', 'external_db')->query($query);
    */
    }
    $markup .= '<br/>Items table updated. <b>Table item_info can be dropped.</b>';
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  
  $query = "RENAME TABLE `item_stock` TO `ek_item_packing`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Item_stock table renamed';
  
  
  $query ="
  ALTER TABLE `ek_item_packing`
	ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
	CHANGE COLUMN `itemcode` `itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'code in main table' AFTER `id`,
	CHANGE COLUMN `units` `units` VARCHAR(45) NULL DEFAULT '' COMMENT 'stock units' AFTER `itemcode`,
	CHANGE COLUMN `unitmeasure` `unit_measure` VARCHAR(45) NULL DEFAULT '' COMMENT 'unit measure' AFTER `units`,
	CHANGE COLUMN `itemsize` `item_size` VARCHAR(45) NULL DEFAULT NULL COMMENT 'size' AFTER `unit_measure`,
	CHANGE COLUMN `packsize` `pack_size` VARCHAR(45) NULL DEFAULT NULL COMMENT 'packing' AFTER `item_size`,
	CHANGE COLUMN `qtypack` `qty_pack` VARCHAR(45) NULL DEFAULT NULL COMMENT 'quantity per pack' AFTER `pack_size`,
	CHANGE COLUMN `c20` `c20` VARCHAR(30) NULL DEFAULT NULL COMMENT 'container 20 capacity' AFTER `qty_pack`,
	CHANGE COLUMN `c40` `c40` VARCHAR(30) NULL DEFAULT NULL COMMENT 'container 40 capacity' AFTER `c20`,
	CHANGE COLUMN `minorder` `min_order` VARCHAR(30) NULL DEFAULT NULL COMMENT 'order minimum' AFTER `c40`,
	DROP COLUMN `logistic_cost`,
	DROP PRIMARY KEY,
	ADD INDEX `Index 1` (`itemcode`),
	ADD PRIMARY KEY (`id`)
  ";


  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Item_packing table altered';
  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
} 


try {
    
  $query = "RENAME TABLE `item_prices` TO `ek_item_prices`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Item_prices table renamed';
  
  $query = "
  ALTER TABLE `ek_item_prices`
	ADD COLUMN `id` INT(10) NOT NULL AUTO_INCREMENT FIRST,
	CHANGE COLUMN `itemcode` `itemcode` VARCHAR(100) NOT NULL DEFAULT '' AFTER `id`,
	CHANGE COLUMN `purchaseprice` `purchase_price` DOUBLE NULL DEFAULT '0' AFTER `itemcode`,
	CHANGE COLUMN `datepurchase` `date_purchase` VARCHAR(45) NULL DEFAULT NULL AFTER `currency`,
	CHANGE COLUMN `sellingprice` `selling_price` DOUBLE NULL DEFAULT '0' AFTER `date_purchase`,
	CHANGE COLUMN `promoprice` `promo_price` DOUBLE NULL DEFAULT '0' AFTER `selling_price`,
	CHANGE COLUMN `discountprice` `discount_price` DOUBLE NULL DEFAULT '0' AFTER `promo_price`,
	CHANGE COLUMN `esellingprice` `exp_selling_price` DOUBLE NULL DEFAULT '0' AFTER `discount_price`,
	CHANGE COLUMN `epromoprice` `exp_promo_price` DOUBLE NULL DEFAULT '0' AFTER `exp_selling_price`,
	CHANGE COLUMN `ediscountprice` `exp_discount_price` DOUBLE NULL DEFAULT '0' AFTER `exp_promo_price`,
	CHANGE COLUMN `lcurrency` `loc_currency` VARCHAR(45) NULL DEFAULT NULL AFTER `exp_discount_price`,
	CHANGE COLUMN `ecurrency` `exp_currency` VARCHAR(45) NULL DEFAULT NULL AFTER `loc_currency`,
	DROP PRIMARY KEY,
	ADD PRIMARY KEY (`id`),
	ADD INDEX `Index 2` (`itemcode`)
  ";

  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Item_prices table altered';
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
} 

try {
  
  $query = "
  CREATE TABLE `ek_item_images` (
    `id` INT(10) NOT NULL AUTO_INCREMENT,
    `itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'code in main table',
    `uri` VARCHAR(255) NOT NULL COMMENT 'file uri',
    PRIMARY KEY (`id`),
    INDEX `Index 2` (`itemcode`)
  )
  COMMENT='List images for items'
  COLLATE='utf8_general_ci'
  ENGINE=InnoDB
  AUTO_INCREMENT=1";
  
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Item_images table created';
  
  /*
  * move private://products/images/1/image.jpg
  */
  $query = "SELECT * from item_images";
    $data = Database::getConnection('external_db', 'external_db')->query($query);

    While ($r = $data->fetchObject() ) {
    
    $query = 'SELECT id FROM {ek_items} WHERE itemcode=:i';
    $id = Database::getConnection('external_db', 'external_db')->query($query, array(':i' => $r->itemcode))->fetchField();

    if ($r->image1 <> '' && $r->image1 <> 'blank.png'){
      $uri = "private://products/images/" . $id . "/" . $r->image1;
      $fields = array(
       'itemcode' => $r->itemcode,
       'uri' => $uri
      );
      
       Database::getConnection('external_db', 'external_db')
        ->insert('ek_item_images')
        ->fields($fields)->execute();
    }
    
    if ($r->image2 <> '' && $r->image2 <> 'blank.png'){
      $uri = "private://products/images/" . $id . "/" . $r->image2;
      $fields = array(
       'itemcode' => $r->itemcode,
       'uri' => $uri
      );
      
       Database::getConnection('external_db', 'external_db')
        ->insert('ek_item_images')
        ->fields($fields)->execute();
    }
    
    if ($r->image3 <> '' && $r->image3 <> 'blank.png'){
      $uri = "private://products/images/" . $id . "/" . $r->image3;
      $fields = array(
       'itemcode' => $r->itemcode,
       'uri' => $uri
      );
      
       Database::getConnection('external_db', 'external_db')
        ->insert('ek_item_images')
        ->fields($fields)->execute();
    }
  }  
  $markup .= '<br/>Item_images table updated. <b>Table can be dropped.</b>';  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
} 


try {

  $query = "
  CREATE TABLE `ek_item_barcodes` (
      `id` INT(10) NOT NULL AUTO_INCREMENT,
      `itemcode` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'code in main table',
      `barcode` VARCHAR(45) NOT NULL DEFAULT '0' COMMENT 'barcode',
      `encode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'encoding value',
      PRIMARY KEY (`id`),
      INDEX `Index 2` (`itemcode`)
    )
    COMMENT='Itams barcode list'
    COLLATE='latin1_swedish_ci'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
  ";

  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Item_barcodes table created';
  
    
  /*
  * move 
  */
  $query = "SELECT * from item_barcodes";
    $data = Database::getConnection('external_db', 'external_db')->query($query);

    While ($r = $data->fetchObject() ) {
    
      if ($r->barcode1 <> ''){
      $query = "INSERT into `ek_item_barcodes` (`itemcode`, `barcode`, `encode`) 
        VALUES ('" . $r->itemcode . "', '" . $r->barcode1 . "', '" . $r->encode1 . "')";
        
       Database::getConnection('external_db', 'external_db')->query($query);
      }

      if ($r->barcode2 <> ''){
      $query = "INSERT into `ek_item_barcodes` (`itemcode`, `barcode`, `encode`) 
        VALUES ('" . $r->itemcode . "', '" . $r->barcode2 . "', '" . $r->encode2 . "')";
        
       Database::getConnection('external_db', 'external_db')->query($query);
      }
      
      if ($r->barcode3 <> ''){
      $query = "INSERT into `ek_item_barcodes` (`itemcode`, `barcode`, `encode`) 
        VALUES ('" . $r->itemcode . "', '" . $r->barcode3 . "', '" . $r->encode3 . "')";
        
       Database::getConnection('external_db', 'external_db')->query($query);
      }
      
      if ($r->barcode4 <> ''){
      $query = "INSERT into `ek_item_barcodes` (`itemcode`, `barcode`, `encode`) 
        VALUES ('" . $r->itemcode . "', '" . $r->barcode4 . "', '" . $r->encode4 . "')";
        
       Database::getConnection('external_db', 'external_db')->query($query);
      }
      
      if ($r->barcode5 <> ''){
      $query = "INSERT into `ek_item_barcodes` (`itemcode`, `barcode`, `encode`) 
        VALUES ('" . $r->itemcode . "', '" . $r->barcode5 . "', '" . $r->encode5 . "')";
        
       Database::getConnection('external_db', 'external_db')->query($query);
      }
    
    }
    $markup .= '<br/>Item_barcodes table updated. <b>Table can be dropped.</b>';  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
} 


try {
  
  $query = "RENAME TABLE `item_price_history` TO `ek_item_price_history`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>item_price_history table renamed';
  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}   
  

  