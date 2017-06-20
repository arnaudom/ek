<?php

use Drupal\Core\Database\Database;

$markup = '';
/*
* edit tables
*/
/**/
try {
  $query = "RENAME TABLE `project_report` TO `ek_ireports`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>reports table renamed';
  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "ALTER TABLE `ek_ireports`     
    CHANGE COLUMN `rcode` `serial` varchar(50) NULL COLLATE utf8_unicode_ci DEFAULT NULL  COMMENT 'main reference',
    ADD COLUMN `owner` int(10) NULL DEFAULT NULL COMMENT 'user id' AFTER `serial`,
    ADD COLUMN `assign` int(10) NULL DEFAULT NULL COMMENT 'user id' AFTER `owner`,
    ADD COLUMN `edit` int(10) NULL DEFAULT NULL COMMENT 'edition stamp' AFTER `assign`,
    ADD COLUMN `description` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL  COMMENT 'description or tag' AFTER `edit`,
    ADD COLUMN `status` varchar(2) NULL DEFAULT '1'  COMMENT '0:closed 1:active' AFTER `description`,
    ADD COLUMN `pcode` varchar(100) NULL DEFAULT NULL  COMMENT 'project reference' AFTER `status`,
    CHANGE COLUMN `cid` `coid` varchar(5) NULL DEFAULT NULL  COMMENT 'company id' AFTER `gid`,
    ADD COLUMN `abid` VARCHAR(5) NULL DEFAULT NULL COMMENT 'address book id' AFTER `coid`,
    CHANGE COLUMN `type` `type` varchar(5) NULL DEFAULT NULL  COMMENT '1:briefing 2:report 3:training',
    ADD COLUMN `format` VARCHAR(20) NULL DEFAULT NULL COMMENT 'format input of report' AFTER `type`
    ";

  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>table altered';
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}
