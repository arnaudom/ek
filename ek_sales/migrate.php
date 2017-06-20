<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';
/**/

try {
$query = "RENAME TABLE `purchase` TO `ek_purchase`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>purchase table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}

try {
$query = "RENAME TABLE `purchase_details` TO `ek_purchase_details`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>purchase_details table renamed';

} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
	//ALTER `bank` DROP DEFAULT,
	//ALTER `tax` DROP DEFAULT,
	//ALTER `taxvalue` DROP DEFAULT,
	//ALTER `pdate` DROP DEFAULT,
$query = "
ALTER TABLE `ek_purchase`

	CHANGE COLUMN `amountpaid` `amountpaid` DOUBLE NULL DEFAULT '0' AFTER `client`,
	CHANGE COLUMN `amountusd` `amountusd` DOUBLE NULL DEFAULT '0' AFTER `amountpaid`,
	CHANGE COLUMN `bank` `bank` VARCHAR(5) NULL AFTER `balanceusd`,
	CHANGE COLUMN `tax` `tax` VARCHAR(100) NULL AFTER `bank`,
	CHANGE COLUMN `taxvalue` `taxvalue` DOUBLE NULL AFTER `tax`,
	CHANGE COLUMN `pdate` `pdate` VARCHAR(15) NULL AFTER `due`,
	CHANGE COLUMN `reconcile` `reconcile` VARCHAR(5) NULL DEFAULT '0' AFTER `pay_ref`
";

Database::getConnection('external_db', 'external_db')->query($query);

$query = "ALTER TABLE `ek_purchase`
	CHANGE COLUMN `amountusd` `amountbc` DOUBLE NOT NULL DEFAULT '0' COMMENT 'amount base currency' AFTER `amountpaid`,
	CHANGE COLUMN `balanceusd` `balancebc` DOUBLE NULL DEFAULT NULL COMMENT 'amount base currency' AFTER `amountbc`,
	ADD COLUMN `uri` VARCHAR(250) NULL DEFAULT NULL COMMENT 'uri of file attached' AFTER `alert_who`
";
Database::getConnection('external_db', 'external_db')->query($query);

$markup .= '<br/>purchase table altered';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {

$query  = "
  CREATE TABLE `ek_purchase_tasks` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `serial` VARCHAR(50) NULL DEFAULT NULL COMMENT 'serial reference of purchase',
    `event` VARCHAR(50) NOT NULL COMMENT 'event name',
    `uid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL COMMENT 'uid assignment',
    `task` TEXT NULL COMMENT 'task description',
    `weight` DOUBLE NULL DEFAULT NULL COMMENT 'option',
    `start` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'start date',
    `end` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'end date',
    `completion_rate` VARCHAR(3) NULL DEFAULT NULL COMMENT 'rate of completion',
    `notify` SMALLINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'notification period : 0=no,1=week,2=5days,3=3days,4=1day,5=daily,6=monthly',
    `notify_who` VARCHAR(100) NULL DEFAULT NULL COMMENT 'list notification uid ex 1,2,3',
    `notify_when` VARCHAR(50) NULL DEFAULT NULL COMMENT 'notification time',
    `note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'option note',
    `color` VARCHAR(10) NULL DEFAULT NULL COMMENT 'option color',
    PRIMARY KEY (`id`)
  )
  COMMENT='tasks, alerts per purchase'
  COLLATE='utf8_unicode_ci'
  ENGINE=InnoDB
  ROW_FORMAT=COMPACT
  AUTO_INCREMENT=1
";

Database::getConnection('external_db', 'external_db')->query($query);

$markup .= '<br/>purchase tasks table created';

} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {

///////////////////////////////////////////////////////////////////////////////
$query = "RENAME TABLE `invoice` TO `ek_invoice`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<hr><br/>invoice table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "RENAME TABLE `invoice_details` TO `ek_invoice_details`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>invoice_details table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "
ALTER TABLE `ek_invoice`
	COMMENT='Main invoice ref table',
	CHANGE COLUMN `serial` `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'unique serial number' AFTER `id`,
	CHANGE COLUMN `do_no` `do_no` VARCHAR(50) NULL DEFAULT NULL COMMENT 'deliveri order ref' AFTER `serial`,
	CHANGE COLUMN `head` `head` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'company id' AFTER `do_no`,
	CHANGE COLUMN `country` `allocation` TINYINT(4) NOT NULL COMMENT 'company id allocation' AFTER `head`,
	CHANGE COLUMN `status` `status` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '0 unpaid' AFTER `allocation`,
	CHANGE COLUMN `amount` `amount` DOUBLE NOT NULL DEFAULT '0' COMMENT 'total amount' AFTER `status`,
	CHANGE COLUMN `currency` `currency` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'currency' AFTER `amount`,
	CHANGE COLUMN `date` `date` VARCHAR(15) NOT NULL DEFAULT '0000-00-00' COMMENT 'invoice date' AFTER `currency`,
	CHANGE COLUMN `title` `title` VARCHAR(150) NOT NULL COMMENT 'title on the printed doc' AFTER `date`,
	CHANGE COLUMN `pcode` `pcode` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'project reference' AFTER `title`,
	CHANGE COLUMN `comment` `comment` TEXT NOT NULL COMMENT 'comment on printed doc' AFTER `pcode`,
	CHANGE COLUMN `client` `client` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'client id' AFTER `comment`,
	CHANGE COLUMN `pay_date` `pay_date` VARCHAR(15) NULL COMMENT 'date payment' AFTER `amountreceived`,
	CHANGE COLUMN `class` `class` VARCHAR(45) NULL DEFAULT '' AFTER `pay_date`,
	CHANGE COLUMN `amountusd` `amountbase` DOUBLE NOT NULL DEFAULT '0' COMMENT 'base corrency with tax' AFTER `class`,
	CHANGE COLUMN `balanceusd` `balancebase` DOUBLE NULL DEFAULT NULL COMMENT 'amount not paid base currency with tax' AFTER `amountbase`,
	CHANGE COLUMN `terms` `terms` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'payment terms definition' ,
	ADD COLUMN `alert` TINYINT NULL DEFAULT '0' AFTER `reconcile`,
	ADD COLUMN `alert_who` VARCHAR(255) NULL AFTER `alert`
";

Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>invoice table altered';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "Update ek_invoice set status = 0 where status = 'nyp'";
Database::getConnection('external_db', 'external_db')->query($query);
$query = "Update ek_invoice set status = 1 where status = 'paid'";
Database::getConnection('external_db', 'external_db')->query($query);
$query = "Update ek_invoice set terms = 0 where terms = 'due on receipt'";
Database::getConnection('external_db', 'external_db')->query($query);
$query = "Update ek_invoice set terms = 1 where terms <> 'due on receipt'";
Database::getConnection('external_db', 'external_db')->query($query);

$markup .= '<br/>invoice table data updated';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {

$query = "
ALTER TABLE `ek_invoice_details`
	CHANGE COLUMN `item` `item` TEXT NULL COMMENT 'item info' COLLATE 'utf8_unicode_ci' AFTER `serial`,
	CHANGE COLUMN `itemdetail` `itemdetail` TEXT NULL COMMENT 'extended item description' COLLATE 'utf8_unicode_ci' AFTER `item`,
	CHANGE COLUMN `unitprice` `value` DOUBLE NULL DEFAULT '0' COMMENT 'unit price value of item' AFTER `itemdetail`,
	CHANGE COLUMN `quantity` `quantity` DOUBLE NULL DEFAULT NULL COMMENT 'quantity of items' AFTER `value`,
	CHANGE COLUMN `value` `total` DOUBLE NULL DEFAULT NULL COMMENT 'line total value local currency' AFTER `quantity`,
	CHANGE COLUMN `valueusd` `totalbase` DOUBLE NULL DEFAULT NULL COMMENT 'value converted base currency' AFTER `total`,
	CHANGE COLUMN `opt` `opt` VARCHAR(2) NULL DEFAULT NULL COMMENT 'option tax' AFTER `totalbase`,
	CHANGE COLUMN `aid` `aid` VARCHAR(15) NULL DEFAULT NULL COMMENT 'account id' AFTER `opt`,
	DROP COLUMN `margin`	
";

Database::getConnection('external_db', 'external_db')->query($query);

$markup .= '<br/>invoice_details table data altered';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "RENAME TABLE `invoice_template_tasks` TO `ek_invoice_tasks`";
Database::getConnection('external_db', 'external_db')->query($query);

$markup .= '<br/>ek_invoice_tasks table data renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "
ALTER TABLE `ek_invoice_tasks`
	CHANGE COLUMN `serial` `serial` VARCHAR(50) NULL DEFAULT NULL COMMENT 'serial reference of invoice' AFTER `id`,
	CHANGE COLUMN `event` `event` VARCHAR(50) NOT NULL COMMENT 'event name' AFTER `serial`,
	CHANGE COLUMN `uid` `uid` MEDIUMINT(8) UNSIGNED NULL DEFAULT NULL COMMENT 'uid assignment' AFTER `event`,
	CHANGE COLUMN `task` `task` TEXT NULL COMMENT 'task description' AFTER `uid`,
	CHANGE COLUMN `weight` `weight` DOUBLE NULL DEFAULT NULL COMMENT 'option' AFTER `task`,
	CHANGE COLUMN `start` `start` VARCHAR(50) NOT NULL DEFAULT '0000-00-00' COMMENT 'start date' AFTER `weight`,
	CHANGE COLUMN `end` `end` VARCHAR(50) NULL DEFAULT '0000-00-00' COMMENT 'end date' AFTER `start`,
	CHANGE COLUMN `completion_rate` `completion_rate` VARCHAR(3) NULL DEFAULT NULL COMMENT 'rate of completion' AFTER `end`,
	CHANGE COLUMN `notify` `notify` SMALLINT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'notification period : 0=no,1=week,2=5days,3=3days,4=1day,5=daily,6=monthly' AFTER `completion_rate`,
	CHANGE COLUMN `notify_who` `notify_who` VARCHAR(100) NULL DEFAULT NULL COMMENT 'list notification uid ex 1,2,3' AFTER `notify`,
	CHANGE COLUMN `notify_when` `notify_when` VARCHAR(50) NULL DEFAULT NULL COMMENT 'notification time' AFTER `notify_who`,
	CHANGE COLUMN `note` `note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'option note' AFTER `notify_when`,
	CHANGE COLUMN `color` `color` VARCHAR(10) NULL DEFAULT NULL COMMENT 'option color' AFTER `note`
";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<hr><br/>invoice_tasks table altered';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
///////////////////////////////////////////////////////////////////////////////
$query = "RENAME TABLE `quotation` TO `ek_quotation`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<hr><br/>quotation table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "RENAME TABLE `quotation_details` TO `ek_quotation_details`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>quotation_details table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "RENAME TABLE `quotation_settings` TO `ek_quotation_settings`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>quotation_settings table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "
ALTER TABLE `ek_quotation`
	CHANGE COLUMN `incoterm` `incoterm` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'add incoterm format term-%' AFTER `client`,
	ADD COLUMN `tax` VARCHAR(45) NULL DEFAULT '' COMMENT 'add a tax value format: name|%' AFTER `incoterm`
";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>quotation table altered';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
//CHANGE COLUMN `column_2` VARCHAR(255) NULL COMMENT 'optional data' AFTER `opt`,
//CHANGE COLUMN `column_3` VARCHAR(255) NULL COMMENT 'optional data' AFTER `column_2`
$query = "
ALTER TABLE `ek_quotation_details`
	CHANGE COLUMN `itemid` `itemid` TEXT NULL AFTER `serial`,
	CHANGE COLUMN `itemdetails` `itemdetails` TEXT NULL AFTER `itemid`,
	CHANGE COLUMN `margin` `margin` DOUBLE NULL DEFAULT '0' AFTER `itemdetails`,
	CHANGE COLUMN `opt` `opt` VARCHAR(10) NULL AFTER `revision`
	
";

Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>quotation_details table altered';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
///////////////////////////////////////////////////////////////////////////////
$query = "
CREATE TABLE `ek_sales_settings` (
	`coid` INT NULL COMMENT 'company ID',
	`settings` BLOB NULL COMMENT 'serialized settings',
	UNIQUE INDEX `Index 1` (`coid`)
)
COMMENT='holds settings by company'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
";

Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<hr><br/>settings table created';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
///////////////////////////////////////////////////////////////////////////////
$query = "RENAME TABLE `client_documents` TO `ek_sales_documents`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<hr><br/>document table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "
ALTER TABLE `ek_sales_documents`
	COMMENT='holds data about uploaded prospects documents',
	CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
	CHANGE COLUMN `client_id` `abid` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'address book id' AFTER `id`,
	ADD COLUMN `fid` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'file managed id, option' AFTER `abid`,
	CHANGE COLUMN `doc_name` `filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.' AFTER `fid`,
	ADD COLUMN `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' AFTER `filename`,
	CHANGE COLUMN `doc_comment` `comment` VARCHAR(255) NULL COMMENT 'comment' AFTER `uri`,
	CHANGE COLUMN `date` `date` VARCHAR(50) NULL DEFAULT '0' AFTER `comment`,
	ADD COLUMN `size` INT(10) NULL DEFAULT '0' AFTER `date`,
	ADD COLUMN `share` VARCHAR(255) NULL DEFAULT '0' AFTER `size`,
	ADD COLUMN `deny` VARCHAR(255) NULL DEFAULT '0' AFTER `share`,
	DROP COLUMN `doc_type`,
	ADD INDEX `Index 2` (`abid`)
";

Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>document table altered';


$query = "select * from {ek_sales_documents}";
$data = Database::getConnection('external_db', 'external_db')->query($query);
$i = 0;
while ($d = $data->fetchObject()) {
$i++;
$uri = 'private://sales/documents' . $d->abid . '/' . $d->filename;
$size = filesize($uri);
if ($size == '') $size = 0;
$stamp = strtotime($d->date);

$fields = array(
	'uri' => $uri ,
	'size' => $size,
	'date' => $stamp,
);

Database::getConnection('external_db', 'external_db')
  ->update('ek_sales_documents')->fields($fields)->condition('id', $d->id)->execute();

}

$markup .= '<br/>' . $i . ' files fields updated in documents';

} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


// change pcode format
 // ex. AA-RC/VN/1-15/JPP/960-sub1 ==> AA_RC-VN-1_15-JPP-960_sub1
 // ex. 	AA-RA/CA/2-15/A.VN/1047 ==> AA_RA-CA-2_15-A.VN-1047

$query = "select id,pcode from {ek_purchase}";

$data = Database::getConnection('external_db', 'external_db')->query($query);

while ($d = $data->fetchAssoc()) {

 $pcode = str_replace('-', '_' , $d['pcode']);
 $pcode = str_replace('/', '-' , $pcode);
  $query = "update {ek_purchase} set pcode='" . $pcode ."'  where id=" . $d['id'] ."";
  Database::getConnection('external_db', 'external_db')->query($query);
  //$markup .= '|'. $pcode ; 
}
$markup .= '<br/>pcode in purchase altered';


$query = "select id,pcode from {ek_invoice}";

$data = Database::getConnection('external_db', 'external_db')->query($query);

while ($d = $data->fetchAssoc()) {

 $pcode = str_replace('-', '_' , $d['pcode']);
 $pcode = str_replace('/', '-' , $pcode);
  $query = "update {ek_invoice} set pcode='" . $pcode ."'  where id=" . $d['id'] ."";
  Database::getConnection('external_db', 'external_db')->query($query);
  //$markup .= '|'. $pcode ; 
}
$markup .= '<br/>pcode in invoice altered';

$query = "select id,pcode from {ek_quotation}";

$data = Database::getConnection('external_db', 'external_db')->query($query);

while ($d = $data->fetchAssoc()) {

 $pcode = str_replace('-', '_' , $d['pcode']);
 $pcode = str_replace('/', '-' , $pcode);
  $query = "update {ek_quotation} set pcode='" . $pcode ."'  where id=" . $d['id'] ."";
  Database::getConnection('external_db', 'external_db')->query($query);
  //$markup .= '|'. $pcode ; 
}
$markup .= '<br/>pcode in quotation altered';



