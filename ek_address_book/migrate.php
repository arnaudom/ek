<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;


/*
* move contacts to new table
*/
$markup = '';


// change name
try {
  $db = Database::getConnection('external_db', 'external_db')
  ->query('RENAME TABLE `clients` TO `ek_address_book`');
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

// move from main table
try {
    $query = "CREATE TABLE  `ek_address_book_contacts` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `abid` INT(10) UNSIGNED NOT NULL COMMENT 'id in address book',
                `main` VARCHAR(2) NULL DEFAULT '0',
                `contact_name` VARCHAR(200) NOT NULL COMMENT 'full name',
                `salutation` VARCHAR(20) NULL DEFAULT NULL COMMENT 'salutation',
                `title` VARCHAR(100) NULL DEFAULT NULL COMMENT 'function ttitle',
                `telephone` VARCHAR(45) NULL DEFAULT NULL COMMENT 'fix line',
                `mobilephone` VARCHAR(45) NULL DEFAULT NULL COMMENT 'mobile ine',
                `email` VARCHAR(100) NULL DEFAULT NULL COMMENT 'email',
                `card` VARCHAR(255) NULL DEFAULT NULL COMMENT 'name card, fid',
                `department` VARCHAR(100) NULL DEFAULT NULL COMMENT 'department',
                `link` VARCHAR(100) NULL DEFAULT NULL COMMENT 'social link',
                `comment` TEXT NULL COMMENT 'comment',
                `stamp` VARCHAR(50) NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `Index 2` (`contact_name`, `abid`)
              )
              COMMENT='Address book contacts'
              COLLATE='latin1_swedish_ci'
            ENGINE=MyISAM
            AUTO_INCREMENT=1";
            
    $db = Database::getConnection('external_db', 'external_db')->query($query);
    if($db) $markup .= 'Contacts table installed <br/>';


$query = "SELECT * from ek_address_book ";
$data = Database::getConnection('external_db', 'external_db')->query($query);
$i = 0;
  while ($d = $data->fetchObject()) {
  
    if( $d->contact != '') {
    $i++;
    if( $d->card <> '' ) { $card = "private://address_book/cards/" . $d->id . '/' . $d->card;} else {
        $card = '';
    }
    
      $fields = array(
        'abid' => $d->id,
        'contact_name' => $d->contact ,
        'title' => $d->title,
        'telephone' => $d->telephone,
        'mobilephone' => $d->mobilephone,
        'email' => $d->email1,
        'card' => $card,
        'main' => 1,
      );

      Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();
    
    }
     if( $d->contact2 != '') {
      $i++;
       $fields = array(
        'abid' => $d->id,
        'contact_name' => $d->contact2 ,
        'title' => $d->title2,
        'telephone' => $d->telephone2,
        'mobilephone' => $d->mobilephone2,
        'email' => $d->email2,
        'card' => $card2,
        'main' => 0,
      );

      Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();     
      
      }
      
      if( $d->contact3 != '') {
      $i++;
       $fields = array(
        'abid' => $d->id,
        'contact_name' => $d->contact3 ,
        'title' => $d->title3,
        'telephone' => $d->telephone3,
        'mobilephone' => $d->mobilephone3,
        'email' => $d->email3,
        'card' => $card3,
        'main' => 0,
      );

      Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();     
      
      }
      if($d->contact4 != '') {
      $i++;
       $fields = array(
        'abid' => $d->id,
        'contact_name' => $d->contact4 ,
        'title' => $d->title4,
        'telephone' => $d->telephone4,
        'mobilephone' => $d->mobilephone4,
        'email' => $d->email4,
        'card' => $card4,
        'main' => 0,
      );

      Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();     
      
      }
      
      if( $d->contact5 != '' ) {
      $i++;
        $fields = array(
        'abid' => $d->id,
        'contact_name' => $d->contact5 ,
        'title' => $d->title5,
        'telephone' => $d->telephone5,
        'mobilephone' => $d->mobilephone5,
        'email' => $d->email5,
        'card' => $card5,
        'main' => 0,
      );

      Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();     
     
      }
      if( $d->contact6 != '' ) {
      $i++;
        $fields = array(
        'abid' => $d->id,
        'contact_name' => $d->contact6 ,
        'title' => $d->title6,
        'telephone' => $d->telephone6,
        'mobilephone' => $d->mobilephone6,
        'email' => $d->email6,
        'card' => $card6,
        'main' => 0,
      );

      Database::getConnection('external_db', 'external_db')->insert('ek_address_book_contacts')->fields($fields)->execute();     
      }
  
  }
  
$markup .= $i . ' contacts moved';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {


  $query = "update {ek_address_book} set type=1 where type='client'";
  Database::getConnection('external_db', 'external_db')->query($query);
  $query = "update  {ek_address_book} set type=2 where type='supplier'";
  Database::getConnection('external_db', 'external_db')->query($query);
  $query = "update  {ek_address_book} set type=3 where type='other'";
  Database::getConnection('external_db', 'external_db')->query($query);
  
  Database::getConnection('external_db', 'external_db')
  ->query("ALTER TABLE `ek_address_book` CHANGE COLUMN `type` `type` VARCHAR(20) NULL DEFAULT NULL COMMENT '1 client, 2 supplier, 3 other' AFTER `website`");
  Database::getConnection('external_db', 'external_db')->query("ALTER TABLE `ek_address_book` CHANGE COLUMN `clientname` `name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `id`, CHANGE COLUMN `clientshortname` `shortname` VARCHAR(5) NOT NULL DEFAULT '' AFTER `name`");

  $markup .= '<br>type value updated';
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
    $query = "CREATE TABLE `ek_address_book_comment` (
        `abid` INT(10) UNSIGNED NOT NULL,
        `comment` TEXT NULL COLLATE 'utf8mb4_bin',
        PRIMARY KEY (`abid`)
        )
        COMMENT='list of clients'
        COLLATE='utf8mb4_bin'
        ENGINE=InnoDB
        ROW_FORMAT=COMPACT";
  $db = Database::getConnection('external_db', 'external_db')
  ->query($query);
  
  $markup .= '<br>comment table created';
  
  $query = "SELECT id,comment FROM {ek_address_book}";
  $data = Database::getConnection('external_db', 'external_db')
  ->query($query);
  
  While ($d = $data->fetchObject()) {
      if($d->id != NULL){
      $fields = array('abid' => $d->id, 'comment' => $d->comment);
      Database::getConnection('external_db', 'external_db')
              ->insert('ek_address_book_comment')->fields($fields)->execute();
      }
  
  }
  
  
  
  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}
 //
try {
Database::getConnection('external_db', 'external_db')->query("
ALTER TABLE `ek_address_book`
	DROP COLUMN `contact`,
        DROP COLUMN `comment`,
        DROP COLUMN `title`,
	DROP COLUMN `mobilephone`,
	DROP COLUMN `email1`,
	DROP COLUMN `contact2`,
	DROP COLUMN `title2`,
	DROP COLUMN `telephone2`,
	DROP COLUMN `mobilephone2`,
	DROP COLUMN `email2`,
	DROP COLUMN `contact3`,
	DROP COLUMN `title3`,
	DROP COLUMN `telephone3`,
	DROP COLUMN `mobilephone3`,
	DROP COLUMN `email3`,
	DROP COLUMN `contact4`,
	DROP COLUMN `title4`,
	DROP COLUMN `telephone4`,
	DROP COLUMN `mobilephone4`,
	DROP COLUMN `email4`,
	DROP COLUMN `contact5`,
	DROP COLUMN `title5`,
	DROP COLUMN `telephone5`,
	DROP COLUMN `mobilephone5`,
	DROP COLUMN `email5`,
	DROP COLUMN `contact6`,
	DROP COLUMN `title6`,
	DROP COLUMN `telephone6`,
	DROP COLUMN `mobilephone6`,
	DROP COLUMN `email6`,
	DROP COLUMN `card`,
	DROP COLUMN `card2`,
	DROP COLUMN `card3`,
	DROP COLUMN `card4`,
	DROP COLUMN `card5`,
	DROP COLUMN `card6`,
	
	ADD COLUMN `status` VARCHAR(1) NULL DEFAULT '1' COMMENT 'status, 1=active, 0=inactive' AFTER `category`,
	ADD COLUMN `stamp` VARCHAR(50) NULL DEFAULT NULL AFTER `status`
	"
	);
	
$markup .= '<br>table fields altered.';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}
/**/