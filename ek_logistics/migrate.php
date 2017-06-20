<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';


try {
  
  $query = "RENAME TABLE `delivery` TO `ek_logi_delivery`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Delivery table renamed';
  
  $query = "
  ALTER TABLE `ek_logi_delivery`
	ADD COLUMN `allocation` TINYINT(3) UNSIGNED NOT NULL AFTER `head`,
	CHANGE COLUMN `date` `date` VARCHAR(15) NULL DEFAULT '0000-00-00' COMMENT 'transaction record' AFTER `allocation`,
	ADD COLUMN `pcode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'project code ref' AFTER `po`,
	CHANGE COLUMN `status` `status` VARCHAR(45) NULL DEFAULT NULL COMMENT '0 not printed 1 printed 2 posted' AFTER `client`
  ";
  
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Delivery table altered';
  
  
  
  
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  
  $query = "RENAME TABLE `delivery_details` TO `ek_logi_delivery_details`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Delivery_details table renamed';
  
  $query = "
  ALTER TABLE `ek_logi_delivery_details`
  CHANGE COLUMN `date` `date` VARCHAR(15) NULL DEFAULT '0000-00-00' COMMENT 'transaction record' AFTER `quantity`,
	CHANGE COLUMN `price` `value` DOUBLE NULL DEFAULT NULL AFTER `currency`
  ";
  
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Delivery_detail table altered';
  
  
  
  
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  
  $query = "RENAME TABLE `logistic_settings` TO `ek_logi_settings`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Settings table renamed';
  
  $query = "
  ALTER TABLE `ek_logi_settings`
	CHANGE COLUMN `id` `coid` SMALLINT(10) NOT NULL AUTO_INCREMENT COMMENT 'company ID' FIRST,
	CHANGE COLUMN `field` `settings` VARCHAR(255) NULL DEFAULT '0' COMMENT 'serialized settings' AFTER `coid`,
	DROP COLUMN `name`,
	DROP COLUMN `active`
  ";
  
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Settings table altered';  
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}
  
try {
  
  $query = "RENAME TABLE `receiving` TO `ek_logi_receiving`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Receiving table renamed';
  
  $query = "
  ALTER TABLE `ek_logi_receiving`
  ADD COLUMN `head` TINYINT(3) UNSIGNED NOT NULL AFTER `serial`,
	ADD COLUMN `allocation` TINYINT(3) UNSIGNED NOT NULL AFTER `head`,
	CHANGE COLUMN `date` `date` VARCHAR(15) NULL DEFAULT '0000-00-00' COMMENT 'transaction record' AFTER `allocation`,
	ADD COLUMN `ddate` VARCHAR(15) NOT NULL DEFAULT '0000-00-00' AFTER `date`,
	ADD COLUMN `pcode` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'optional project ref code' AFTER `do`
  ";
  
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Receiving table altered';    
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}  
  
  
try {
  
  $query = "RENAME TABLE `receiving_details` TO `ek_logi_receiving_details`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Receiving table renamed';
  
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}   
  
  
  
  
  
  
  