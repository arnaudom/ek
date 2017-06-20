<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';
/*
* edit tables
*/
/**/
try {
    $query = "CREATE TABLE IF NOT EXISTS `ek_admin_settings` (
            `coid` INT NULL COMMENT 'company id, 0 = global',
            `settings`  BLOB NULL COMMENT 'settings serialized array',
            UNIQUE INDEX `Index 1` (`coid`)
    )
    COMMENT='global and per company settings references'
    COLLATE='utf8_general_ci'
    ENGINE=MyISAM";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= 'Settings table installed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "INSERT INTO `ek_admin_settings` (`coid`) VALUES (0)";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>Settings table updated';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `country_codes` TO `ek_country`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>country table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "RENAME TABLE `company` TO `ek_company`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>company table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "ALTER TABLE `ek_company` 	
  ADD COLUMN `access` BLOB NULL DEFAULT NULL COMMENT 'serialized uid list access' AFTER `id`,
  ADD COLUMN `settings` BLOB NULL DEFAULT NULL COMMENT 'holds accounts settings' AFTER `access`,
  CHANGE COLUMN `adoc_no` `vat_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'vat number' AFTER `social_no`,
  CHANGE COLUMN `logo` `logo` VARCHAR(255) NULL DEFAULT NULL AFTER `mobile`,
  CHANGE COLUMN `sign` `sign` VARCHAR(255) NULL DEFAULT NULL AFTER `favicon`
    ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>column added to table company';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "ALTER TABLE `ek_country`
	COMMENT='Manage countries',
	CHANGE COLUMN `name` `name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'country name' AFTER `id`,
	CHANGE COLUMN `code` `code` VARCHAR(5) NULL DEFAULT NULL COMMENT 'country code' AFTER `name`,
	ADD COLUMN `entity` VARCHAR(255) NULL DEFAULT NULL COMMENT 'organization entity' AFTER `code`,
	ADD COLUMN `status` VARCHAR(1) NULL DEFAULT '0' COMMENT 'status 1, 0' AFTER `entity`,
	ADD COLUMN `access` BLOB NULL DEFAULT NULL COMMENT 'serialized uid list access' AFTER `id`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>column altered to table country_codes';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "RENAME TABLE `company_documents` TO `ek_company_documents`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>documents table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
$query = "
ALTER TABLE `ek_company_documents`
	COMMENT='holds data about uploaded company document',
	CHANGE COLUMN `coid` `coid` INT(5) NULL DEFAULT NULL COMMENT 'company id' AFTER `id`,
	ADD COLUMN `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id' AFTER `coid`,
	CHANGE COLUMN `doc_name` `filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.' AFTER `fid`,
	ADD COLUMN `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' AFTER `filename`,
	CHANGE COLUMN `doc_comment` `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' AFTER `uri`,
	CHANGE COLUMN `doc_date` `date` VARCHAR(50) NULL DEFAULT '0' AFTER `comment`,
	ADD COLUMN `size` INT(10) NULL DEFAULT '0' AFTER `date`,
	CHANGE COLUMN `share` `share` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared uid' AFTER `size`,
	CHANGE COLUMN `deny` `deny` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of denied uid' AFTER `share`,
	DROP COLUMN `doc_type`";

  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>documents table altered';



    $query = "select * from {ek_company_documents}";
    $data = Database::getConnection('external_db', 'external_db')->query($query);
    $i = 0;
    while ($d = $data->fetchObject()) {
    $i++;
    $uri = 'private://admin/company' . $d->coid . '/documents/' . $d->filename;
    $size = filesize($uri);
    if ($size == '') $size = 0;
    $stamp = strtotime($d->date);

    $fields = array(
      'uri' => $uri ,
      'size' => $size,
      'date' => $stamp,
    );

    Database::getConnection('external_db', 'external_db')
      ->update('ek_company_documents')->fields($fields)
      ->condition('id', $d->id)->execute();

    }

    $markup .= '<br/>' . $i . ' files fields updated';
    
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "
  ALTER TABLE `ek_company_documents` 	CHANGE COLUMN `date` `date` INT(10) NULL DEFAULT '0' AFTER `comment`";
  Database::getConnection('external_db', 'external_db')->query($query);

  $markup .= '<br/>company document fields altered.';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}
$markup .= '<br/>Proceed to access settings for countries and companies.';