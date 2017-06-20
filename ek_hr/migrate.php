<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;


/*
 * documents table update
*/
try {
  $query = "RENAME TABLE `hr_documents` TO `ek_hr_documents`";
  Database::getConnection('external_db','external_db')->query($query);

  $markup = 'Documents table renamed';

  $query = "
  ALTER TABLE `ek_hr_documents`
    COMMENT='holds data about uploaded HR documents',
    ADD COLUMN `fid` INT(5) NULL DEFAULT NULL COMMENT 'file managed id' AFTER `employee_id`,
    CHANGE COLUMN `doc_name` `filename` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Name of the file with no path components.' AFTER `fid`,
    ADD COLUMN `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the URI of the file' AFTER `filename`,
    ADD COLUMN `filemime` VARCHAR(255) NULL DEFAULT NULL COMMENT 'The files MIME type.' AFTER `uri`,
    CHANGE COLUMN `doc_type` `type` VARCHAR(200) NULL DEFAULT NULL COMMENT 'tag or type' AFTER `filemime`,
    CHANGE COLUMN `doc_comment` `comment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comment' AFTER `type`,
    CHANGE COLUMN `date` `date` VARCHAR(100) NULL DEFAULT NULL COMMENT 'time stamp' AFTER `comment`,
    ADD COLUMN `size` VARCHAR(50) NULL DEFAULT '0' AFTER `date`";

  Database::getConnection('external_db','external_db')->query($query);

  $query = "ALTER TABLE `ek_hr_documents` ADD UNIQUE INDEX `Index 2` (`uri`)";
  Database::getConnection('external_db','external_db')->query($query);

  $markup .= '<br/> Documents table fields altered';

  $query = "select * from {ek_hr_documents}";
  $data = Database::getConnection('external_db', 'external_db')->query($query);
  $output = '';
  $finfo = new finfo(FILEINFO_MIME_TYPE);

  while ($d = $data->fetchObject()) {

      $stamp = strtotime($d->date);
      //$uuid = Drupal\Component\Uuid\Php::generate();
      $uri = 'private://hr/documents/'. $d->employee_id . '/'. $d->filename;
      $mime = $finfo->file($uri);
      $size = filesize($uri);
      if ($size == '') $size = 0;

      $fields = array(
        'uri' => $uri ,
        'size' => $size,
        'date' => $stamp,
        'filemime' => $mime,
      );

      Database::getConnection('external_db', 'external_db')
        ->update('ek_hr_documents')->fields($fields)->condition('id', $d->id)->execute();

      //$markup .= '<br/> update ' . $uri;
      

  }
  $markup .= '<br/>Document table updated';
  $query = "
  ALTER TABLE `ek_hr_documents` 	CHANGE COLUMN `date` `date` INT(10) NULL DEFAULT '0' AFTER `comment`";
  Database::getConnection('external_db', 'external_db')->query($query);

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

/*
* group hr settings in 1 table
*/

 //$query ="RENAME TABLE `workforce_ad` TO `ek_hr_workforce_ad`";
 //$db = Database::getConnection('external_db', 'external_db')->query($query);
 //$markup .= '<br/> Table workforce_ad renamed';
 
 //$query ="RENAME TABLE `workforce_cat` TO `ek_hr_workforce_cat`";
 //$db = Database::getConnection('external_db', 'external_db')->query($query);
 //$markup .= '<br/> Table workforce_cat renamed'; 

 //$query ="RENAME TABLE `workforce_param` TO `ek_hr_workforce_param`";
 //$db = Database::getConnection('external_db', 'external_db')->query($query);
 //$markup .= '<br/> Table workforce_param renamed'; 
 try {
    $query = "CREATE TABLE  `ek_hr_workforce_settings` (
              `coid` SMALLINT(6) NOT NULL DEFAULT '0' COMMENT 'company ID',
              `ad` TEXT NULL COMMENT 'allowances deductions',
              `cat` TEXT NULL COMMENT 'categories',
              `param` TEXT NULL COMMENT 'parameters',
              `accounts` TEXT NULL COMMENT 'accounts links / finance',
              `roster` TEXT NULL COMMENT 'roster settings',
              UNIQUE INDEX `Index 1` (`coid`)
            )
            COMMENT='store HR settings'
            COLLATE='utf8_general_ci'
            ENGINE=MyISAM;
            ";
	
$create = Database::getConnection('external_db','external_db')->query($query);

$query = "SELECT count(coid) from {ek_hr_workforce_settings}";
$count= Database::getConnection('external_db','external_db')->query($query)->fetchField();

if($count == 0 ) {


    // allowance and deductions

    // structure

    /*array[coid] = (
      
      array[code+origin] = (
      'value'=>
      'type' =>
      'description' =>
      'formula' =>
      'tax' =>
      
      )
    )
    */
    $query = "SELECT distinct cid from {workforce_ad} ";

    $data = Database::getConnection('external_db','external_db')->query($query);
    
    while ($coid = $data->fetchField()) {
    $company = array();
      $query = "SELECT * from {workforce_ad} WHERE cid=:c and code<>:d";
      $a = array(':c' => $coid, ':d' => 'free');
       
        $rows = Database::getConnection('external_db','external_db')->query($query, $a);
        
        $param = array();
        
        while ($r = $rows->fetchObject()) {
        
          $param[$r->code.'-'.$r->origin] = array(
            'value'=> $r->value,
            'type' => $r->type,
            'description' => $r->description,
            'formula' => $r->formula,
            'tax' => $r->tax,
          );
        
        }
        
      $company[$coid] = $param;
      $company = serialize($company);
      //dvm($company);
      $fields = array(
      'coid' => $coid,
      'ad' => $company 
      ); 
      
      Database::getConnection('external_db','external_db')->insert('ek_hr_workforce_settings')->fields($fields)->execute();
    }
    
  $markup .= '<br/> Moved AD parameters.'; 
  
  // categories

  // structure

  /*
  description is linked to ad 'origin' column
  array[coid] = (
    
    array[description] = (
    'data'=> // human name
    )
  )
  */  
    $query = "SELECT distinct coid from {workforce_cat} ";

    $data = Database::getConnection('external_db','external_db')->query($query);
    
    while ($coid = $data->fetchField()) {
    $company = array();
      $query = "SELECT * from {workforce_cat} WHERE coid=:c";
      $a = array(':c' => $coid);
       
        $rows = Database::getConnection('external_db','external_db')->query($query, $a);
        
        $param = array();
        
        while ($r = $rows->fetchObject()) {
        $desc = explode('_', $r->description);
          $param[$desc[1]] = array(
            'value'=> $r->data,
          );
        
        }
        
      $company[$coid] = $param;
      //dvm($company);
      $company = serialize($company);
      
      $fields = array(
      'cat' => $company,
      ); 
      
      Database::getConnection('external_db','external_db')->update('ek_hr_workforce_settings')->fields($fields)->condition('coid', $coid)->execute();
    }
    
    $markup .= '<br/> Moved CAT parameters.'; 

    // param
    // structure

    /*
    Gloabal parameters by coid
    code a, b, c etc
    array[coid] = (
      
      array[code] = (
      'description'=> // human name
      'value' =>
      )
    )
    */  

    $query = "SELECT distinct coid from {workforce_param} ";

    $data = Database::getConnection('external_db','external_db')->query($query);
    
    while ($coid = $data->fetchField()) {
    $company = array();
      $query = "SELECT * from {workforce_param} WHERE coid=:c";
      $a = array(':c' => $coid);
       
        $rows = Database::getConnection('external_db','external_db')->query($query, $a);
        
        $param = array();
        
        while ($r = $rows->fetchObject()) {
        if($r->description <> 'empty') {
          $param[$r->code] = array(
            'description'=> $r->description,
            'value'=> $r->data,
          );
          }
        }
        
      $company[$coid] = $param;
      //dvm($company);
      $company = serialize($company);
      
      $fields = array(
      'param' => $company,
      ); 
      
      Database::getConnection('external_db','external_db')->update('ek_hr_workforce_settings')->fields($fields)->condition('coid', $coid)->execute();
    }
    
    $markup .= '<br/> Moved PARAM parameters.'; 
    

/*
* add finance accounts settings
*/  

  $query = "SELECT * from {hr_accounts} ORDER BY coid";
    $data = Database::getConnection('external_db','external_db')->query($query);
    
    while ($r = $data->fetchObject()) {
    
      $list = array(
        $r->coid => array( 
            'pay_account' => $r->pay_account, 
            'fund1_account' => $r->fund1_account, 
            'fund2_account' => $r->fund2_account,  
            'fund3_account' => $r->fund3_account, 
            'fund4_account' => $r->fund4_account, 
            'fund5_account' => $r->fund5_account, 
            'tax1_account' => $r->tax1_account, 
            'tax2_account' => $r->tax2_account,     
            )
        );
        
           $list = serialize($list);
      
      $fields = array(
      'accounts' => $list,
      ); 
      
      Database::getConnection('external_db','external_db')
      ->update('ek_hr_workforce_settings')->fields($fields)
      ->condition('coid', $r->coid)->execute();
    
    }
  
  $markup .= '<br/>Moved ACCOUNTS parameters.'; 
  $markup .= '<br/>Tables workforce_param, workforce_cat, workforce_ad, hr_accounts can be deleted.'; 

  }//if count

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

/*
* rename
*/
try {
 $query ="RENAME TABLE `workforce` TO `ek_hr_workforce`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/>Table workforce renamed';

 $query ="ALTER TABLE `ek_hr_workforce`
          ADD COLUMN `given_name` VARCHAR(100) NULL DEFAULT NULL AFTER `name`,
          CHANGE COLUMN `administrator` `administrator` VARCHAR(255) NULL DEFAULT '0' AFTER `picture`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table workforce altered';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="RENAME TABLE `workforce_pay` TO `ek_hr_workforce_pay`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table workforce_pay renamed';
 
 $query ="ALTER TABLE `ek_hr_workforce_pay`
          CHANGE COLUMN `lead_aw` `custom_aw1` DOUBLE NULL DEFAULT '0' AFTER `tleave`,
          CHANGE COLUMN `perf_aw` `custom_aw2` DOUBLE NULL DEFAULT '0' AFTER `custom_aw1`,
          CHANGE COLUMN `other_aw` `custom_aw3` DOUBLE NULL DEFAULT '0' AFTER `custom_aw2`,
          CHANGE COLUMN `claim` `custom_aw4` DOUBLE NULL DEFAULT '0' AFTER `custom_aw3`,
          CHANGE COLUMN `travel` `custom_aw5` DOUBLE UNSIGNED NULL DEFAULT '0' AFTER `custom_aw4`,
          CHANGE COLUMN `custom_d2` `custom_d1` DOUBLE NULL DEFAULT '0' AFTER `advance`,
          CHANGE COLUMN `custom_d3` `custom_d2` DOUBLE NULL DEFAULT '0' AFTER `custom_d1`,
          CHANGE COLUMN `custom_d4` `custom_d3` DOUBLE NULL DEFAULT '0' AFTER `custom_d2`,
          CHANGE COLUMN `custom_d5` `custom_d4` DOUBLE NULL DEFAULT '0' AFTER `custom_d3`,
          CHANGE COLUMN `custom_d6` `custom_d5` DOUBLE NULL DEFAULT '0' AFTER `custom_d4`,
          CHANGE COLUMN `custom_d7` `custom_d6` DOUBLE NULL DEFAULT '0' AFTER `custom_d5`,
          ADD COLUMN `comment` VARCHAR(200) NULL DEFAULT '0' COMMENT 'optional comment' AFTER `with_yee`,
          ADD COLUMN `deduction` DOUBLE NULL DEFAULT '0' COMMENT 'total deductions' AFTER `socso_yee`,
          ADD COLUMN `custom_d7` DOUBLE NULL DEFAULT '0' AFTER `custom_d6`";
          
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/>Table workforce_pay altered'; 

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="RENAME TABLE `post_data` TO `ek_hr_post_data`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table post_data renamed';
 
 $query ="ALTER TABLE `ek_hr_post_data`
          CHANGE COLUMN `lead_aw` `custom_aw1` DOUBLE NULL DEFAULT '0' AFTER `tleave`,
          CHANGE COLUMN `perf_aw` `custom_aw2` DOUBLE NULL DEFAULT '0' AFTER `custom_aw1`,
          CHANGE COLUMN `other_aw` `custom_aw3` DOUBLE NULL DEFAULT '0' AFTER `custom_aw2`,
          CHANGE COLUMN `claim` `custom_aw4` DOUBLE NULL DEFAULT '0' AFTER `custom_aw3`,
          CHANGE COLUMN `travel` `custom_aw5` DOUBLE UNSIGNED NULL DEFAULT '0' AFTER `custom_aw4`,
          CHANGE COLUMN `custom_d2` `custom_d1` DOUBLE NULL DEFAULT '0' AFTER `advance`,
          CHANGE COLUMN `custom_d3` `custom_d2` DOUBLE NULL DEFAULT '0' AFTER `custom_d1`,
          CHANGE COLUMN `custom_d4` `custom_d3` DOUBLE NULL DEFAULT '0' AFTER `custom_d2`,
          CHANGE COLUMN `custom_d5` `custom_d4` DOUBLE NULL DEFAULT '0' AFTER `custom_d3`,
          CHANGE COLUMN `custom_d6` `custom_d5` DOUBLE NULL DEFAULT '0' AFTER `custom_d4`,
          CHANGE COLUMN `custom_d7` `custom_d6` DOUBLE NULL DEFAULT '0' AFTER `custom_d5`,
          ADD COLUMN `comment` VARCHAR(200) NULL DEFAULT '0' COMMENT 'optional comment' AFTER `with_yee`,
          ADD COLUMN `deduction` DOUBLE NULL DEFAULT '0' COMMENT 'total deductions' AFTER `socso_yee`,
          CHANGE COLUMN `no_payday` `no_payday` TINYINT(3) NULL DEFAULT '0' AFTER `gross`,
          CHANGE COLUMN `less_hours` `less_hours` TINYINT(3) NULL DEFAULT '0' AFTER `no_payday`,
          ADD COLUMN `custom_d7` DOUBLE NULL DEFAULT '0' AFTER `custom_d6`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table post_data altered';        

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="RENAME TABLE `workforce_roster` TO `ek_hr_workforce_roster`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table workforce_roster renamed';


} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="RENAME TABLE `workforce_ph` TO `ek_hr_workforce_ph`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table workforce_ph renamed';


} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {

 $query ="RENAME TABLE `location` TO `ek_hr_location`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table location renamed';

 $query ="ALTER TABLE `ek_hr_location` ADD COLUMN `coid` MEDIUMINT(3) NOT NULL DEFAULT '1' AFTER `id`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table location altered'; 
 
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}



try {

 $query ="RENAME TABLE `hr_service` TO `ek_hr_service`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table hr_service renamed';

 $query ="ALTER TABLE `ek_hr_service`
          CHANGE COLUMN `id_service` `sid` INT(10) NOT NULL AUTO_INCREMENT FIRST,
          CHANGE COLUMN `id_manager` `eid` INT(10) NOT NULL DEFAULT '-1' COMMENT 'employee ID' AFTER `lib_service`,
          CHANGE COLUMN `id_company` `coid` INT(10) NOT NULL DEFAULT '1' COMMENT 'Company ID' AFTER `eid`,
          CHANGE COLUMN `id_service_parent` `id_service_parent` INT(10) NULL DEFAULT '-1' AFTER `coid`,
          CHANGE COLUMN `color_service` `color_service` VARCHAR(7) NULL DEFAULT '#000000' COLLATE 'latin1_general_cs' AFTER `id_service_parent`,
          CHANGE COLUMN `bgcolor_service` `bgcolor_service` VARCHAR(7) NULL DEFAULT '#9D9DCE' COLLATE 'latin1_general_cs' AFTER `color_service`,
          CHANGE COLUMN `opened_service` `opened_service` TINYINT(1) NULL DEFAULT '1' AFTER `bgcolor_service`,
          CHANGE COLUMN `display_vertical_service` `display_vertical_service` TINYINT(1) NULL DEFAULT '0' AFTER `opened_service`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table hr_service altered'; 


} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="RENAME TABLE `malaysia_income_tax` TO `ek_hr_income_tax_my`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table malaysia_income_tax renamed';


} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="ALTER TABLE `ek_hr_income_tax_my` COMMENT='table calculation for income tax'";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table malaysia_income_tax altered'; 
 

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
 $query ="RENAME TABLE `malaysia_socsec` TO `ek_hr_social_sec_my`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table malaysia_socsec renamed';


 $query ="ALTER TABLE `ek_hr_social_sec_my` COMMENT='rate table for social security'";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table malaysia_socsec altered';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
} 
 
 try {
 $query ="RENAME TABLE `malaysia_provision_fund` TO `ek_hr_pension_my`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table malaysia_provision_fund renamed';
 
 $query ="ALTER TABLE `ek_hr_pension_my`
    CHANGE COLUMN `employer` `employer_1` DOUBLE UNSIGNED NULL DEFAULT NULL AFTER `max`,
    CHANGE COLUMN `employee` `employee_1` DOUBLE UNSIGNED NULL DEFAULT NULL AFTER `employer_1`";
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table malaysia_provision_fund altered';
 

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {

 $query ="CREATE TABLE `ek_hr_payroll_cycle` (
            `coid` INT(11) NOT NULL,
            `current` VARCHAR(15) NULL DEFAULT NULL,
            UNIQUE INDEX `Index 1` (`coid`)
          )
          COMMENT='data about payroll cycles per company'
          ENGINE=InnoDB";
 
 $db = Database::getConnection('external_db', 'external_db')->query($query);
 $markup .= '<br/> Table payroll cycle created';

    /*
    * UPDATE DATA
    */
    $query = 'SELECT DISTINCT company_id FROM {payroll_month}';
    $db = Database::getConnection('external_db', 'external_db')
      ->query($query);
      
      while ($m = $db->fetchObject() ) {
        $query = 'SELECT month FROM {payroll_month} WHERE post=:p and company_id=:c';
        $month = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':p' => 0, ':c' => $m->company_id))
          ->fetchField();
          ;
        $query = 'SELECT post FROM {payroll_month} WHERE month=:p and company_id=:c';
        $year = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':p' => 'YEAR', ':c' => $m->company_id))
          ->fetchField();
          ;     
        
        $d = $year . '-' . date('m', strtotime($month));
        
        $query = "INSERT INTO `ek_hr_payroll_cycle` (`coid`, `current`) VALUES ($m->company_id, '$d')";
        Database::getConnection('external_db', 'external_db')->query($query);
      }
$markup .= '<br/> Table payroll cycle updated';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}