<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';
//update file uri ; 
/*
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`uid` INT(5) NULL DEFAULT NULL,
	`type` VARCHAR(5) NULL DEFAULT NULL,
	`doc_name` VARCHAR(200) NULL DEFAULT NULL,
	`doc_folder` VARCHAR(200) NULL DEFAULT NULL,
	`doc_comment` VARCHAR(255) NULL DEFAULT NULL,
	`doc_date` VARCHAR(50) NULL DEFAULT '0',
	`doc_size` VARCHAR(50) NULL DEFAULT NULL,
	`share` VARCHAR(1) NULL DEFAULT '0',
	`share_who` VARCHAR(100) NULL DEFAULT '0',
*/
try {
  $query = "RENAME TABLE `user_documents` TO `ek_documents`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= 'documents table renamed';

  $query = "ALTER TABLE `ek_documents` 	
  ADD COLUMN `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id' AFTER `uid`,
  CHANGE COLUMN `doc_name` `filename` VARCHAR(255) NULL COMMENT 'Name of the file with no path components.' AFTER `type`,
  ADD COLUMN `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' AFTER `filename`,
  CHANGE COLUMN `doc_folder` `folder` VARCHAR(255) NULL DEFAULT NULL COMMENT 'tag or folder' AFTER `uri`,
  CHANGE COLUMN `doc_comment` `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' AFTER `folder`,
  CHANGE COLUMN `doc_date` `date` VARCHAR(50) NULL DEFAULT 0 COMMENT 'stamp' AFTER `comment`,
  CHANGE COLUMN `doc_size` `size` VARCHAR(50) NULL DEFAULT 0 COMMENT 'file size' AFTER `date`,
  CHANGE COLUMN `share` `share` VARCHAR(1) NULL DEFAULT '0' COMMENT 'bolean 0=not shared, 1=shared,2=visible all' AFTER `size`,
  CHANGE COLUMN `share_who` `share_uid` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared uid' AFTER `share`,
  ADD COLUMN `share_gid` VARCHAR(255) NULL DEFAULT '0' COMMENT 'list of shared groups' AFTER `share_uid`,
  ADD COLUMN `expire` VARCHAR(255) NULL DEFAULT '0' COMMENT 'optional share expiration date' AFTER `share_gid`";

  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>documents table altered';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "select * from {ek_documents}";
  $data = Database::getConnection('external_db', 'external_db')->query($query);

  //$finfo = new finfo(FILEINFO_MIME_TYPE);

  while ($d = $data->fetchObject()) {

  //edit the link to documents
  $uri = 'private://documents/users/'. $d->uid . '/'. $d->filename;

  //$file = $finfo->file($uri);
  //$size = filesize($uri);
  //if ($size == '') $size = 0;


  Database::getConnection('external_db', 'external_db')
  ->update('ek_documents')->fields(array('uri' => $uri))->condition('id', $d->id)->execute();

  $markup .= $uri . '| ' . $d->id .'<br/>';
  $markup .= '<br/>uri altered';

  }


} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}










