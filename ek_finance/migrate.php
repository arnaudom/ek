<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';


$query = "SELECT id,value FROM {ek_journal} order by id";
$data =Database::getConnection('external_db', 'external_db')->query($query);
while ($d = $data->fetchObject()) {
    $newvalue = round($d->value,2);
    $query = "update {ek_journal} set value=:n  WHERE id=:id";
            Database::getConnection('external_db', 'external_db')->query($query, [':n' => $newvalue, ':id' => $d->id]);
}
/*
$query = "SELECT id FROM {ek_company} order by id";
$data =Database::getConnection('external_db', 'external_db')->query($query);
while ($d = $data->fetchObject()) {
        $n =0;
        $query = "SELECT id FROM {ek_journal} WHERE coid = :c order by id";
        $journal =Database::getConnection('external_db', 'external_db')->query($query, [':c' => $d->id]);
        
        while ($j = $journal->fetchObject()) {
            $n++;
            $query = "update {ek_journal} set count=:n  WHERE id=:id";
            Database::getConnection('external_db', 'external_db')->query($query, [':n' => $n, ':id' => $j->id]);
            
        }
    
}


try {
  $query = "RENAME TABLE `accounts` TO `ek_accounts`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>accounts table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "RENAME TABLE `expenses` TO `ek_expenses`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses table renamed';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


  //update formats
     
  $query = "select id,pcode from {ek_expenses}";

  $data = Database::getConnection('external_db', 'external_db')->query($query);

    while ($d = $data->fetchAssoc()) {

     if($d['pcode'] != 'n/a' || $d['pcode'] != 'not project related' ) {
     $pcode = str_replace('-', '_' , $d['pcode']);
     $pcode = str_replace('/', '-' , $pcode);
     $query = "update {ek_expenses} set pcode='" . $pcode ."'  where id=" . $d['id'] ."";
      Database::getConnection('external_db', 'external_db')->query($query);
      //$markup .= $pcode . "<br/>"; 
      } else {
      $query = "update {ek_expenses} set pcode='n/a'  where id=" . $d['id'] ."";
      Database::getConnection('external_db', 'external_db')->query($query);
      }
      
    }
$markup .= "<br/>pcode format and serial format updated";
      $query = "update {ek_expenses} set clientname='n/a'  where clientname='Not client related'";
      Database::getConnection('external_db', 'external_db')->query($query);
      $query = "update {ek_expenses} set suppliername='n/a'  where suppliername='Not supplier related'";
      Database::getConnection('external_db', 'external_db')->query($query);  
$markup .= "<br/>client and supplier format  updated"; 


try {	
//ALTER `comment` DROP DEFAULT,
//ALTER `clientname` DROP DEFAULT,
  $query = "
  ALTER TABLE `ek_expenses`
	CHANGE COLUMN `year` `year` INT(10) UNSIGNED NULL DEFAULT '0' AFTER `amount_paid`,
	CHANGE COLUMN `month` `month` INT(10) UNSIGNED NULL DEFAULT '0' AFTER `year`,
	CHANGE COLUMN `comment` `comment` VARCHAR(255) NULL AFTER `month`,
	CHANGE COLUMN `pcode` `pcode` VARCHAR(45) NULL DEFAULT '' AFTER `comment`,
	CHANGE COLUMN `clientname` `clientname` VARCHAR(100) NULL AFTER `pcode`,
	CHANGE COLUMN `receipt` `receipt` VARCHAR(45) NULL DEFAULT '' AFTER `suppliername`,
	CHANGE COLUMN `employee` `employee` VARCHAR(45) NULL DEFAULT '' AFTER `receipt`,
	CHANGE COLUMN `reconcile` `reconcile` VARCHAR(5) NOT NULL DEFAULT '0' AFTER `pdate`,
	CHANGE COLUMN `cash` `cash` CHAR(15) NOT NULL AFTER `status`,
	ADD COLUMN `attachment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'file attached uri' AFTER `reconcile` ,
	ADD COLUMN `tax` DOUBLE NULL DEFAULT NULL COMMENT 'tax value if any' AFTER `amount_paid`
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses table altered';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
 
  // update the documents uri in expenses table
  // location private://finance/receipt
  
  
  $query = "select id,company, doc_name from {ek_expenses} e INNER JOIN {expenses_documents} d on e.id=d.eid";
  $data = Database::getConnection('external_db', 'external_db')->query($query);

  while ($d = $data->fetchObject()) {

    $uri = 'private://finance/receipt/'. $d->company . '/' . addslashes($d->doc_name) ;
    //$query = 'update {ek_expenses} set attachment="' . $uri . '" where id=' .$d->id ;
    //$markup .= $query . '<br/>';
    $query = 'update {ek_expenses} set attachment = :a WHERE id = :id';
    $a = [':a' => $uri, ':id' => $d->id];
    
    Database::getConnection('external_db', 'external_db')->query($query, $a);
  }

  $markup .= "<br/>Expenses attachments updated (<b>expenses_documents to be dropped</b>).";
  
} catch (Exception $e) {
    $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `currency` TO `ek_currency`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>currency table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "RENAME TABLE `settings` TO `ek_finance_settings`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>settings table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "RENAME TABLE `journal` TO `ek_journal`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>journal table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}







try {
  $query = "RENAME TABLE `bank` TO `ek_bank`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>bank table renamed';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "
  ALTER TABLE `ek_bank`
	COMMENT='Bank entities',
	CHANGE COLUMN `address1` `address1` VARCHAR(45) NULL DEFAULT '' AFTER `name`,
	CHANGE COLUMN `address2` `address2` VARCHAR(45) NULL DEFAULT '' AFTER `address1`,
	CHANGE COLUMN `postcode` `postcode` VARCHAR(45) NULL DEFAULT '' AFTER `address2`,
	CHANGE COLUMN `country` `country` VARCHAR(45) NULL DEFAULT '' AFTER `postcode`,
	CHANGE COLUMN `contact` `contact` VARCHAR(45) NULL DEFAULT '' AFTER `country`,
	CHANGE COLUMN `telephone` `telephone` VARCHAR(45) NULL DEFAULT '' AFTER `contact`,
	CHANGE COLUMN `fax` `fax` VARCHAR(45) NULL DEFAULT ''  AFTER `telephone`,
	CHANGE COLUMN `email` `email` VARCHAR(45) NULL DEFAULT ''  AFTER `fax`,
	CHANGE COLUMN `account1` `account1` VARCHAR(45) NULL DEFAULT ''  AFTER `email`,
	CHANGE COLUMN `account2` `account2` VARCHAR(45) NULL DEFAULT ''  AFTER `account1`,
	CHANGE COLUMN `swift` `swift` VARCHAR(45) NULL DEFAULT ''  AFTER `account2`,
	DROP COLUMN `cid`,
	COLLATE='utf8_unicode_ci'
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>bank table updated';

} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}



try {
  $query = "RENAME TABLE `cash` TO `ek_cash`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>cash table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "
  ALTER TABLE `ek_cash`
	COMMENT='Use to compile cash allocations',
	CHANGE COLUMN `date` `date` VARCHAR(15) NULL DEFAULT '0000-00-00' COMMENT 'transaction record' AFTER `id`,
	CHANGE COLUMN `pay_date` `pay_date` VARCHAR(15) NULL DEFAULT '0000-00-00' COMMENT 'transaction date' AFTER `date`,
	CHANGE COLUMN `type` `type` VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'debit, credit' COLLATE 'utf8_unicode_ci' AFTER `pay_date`,
	CHANGE COLUMN `currency` `currency` VARCHAR(5) NULL DEFAULT NULL COMMENT 'transaction currency' COLLATE 'utf8_unicode_ci' AFTER `cashamount`,
	CHANGE COLUMN `baid` `baid` VARCHAR(10) NULL DEFAULT NULL COMMENT 'bank account id' AFTER `coid`,
	CHANGE COLUMN `uid` `uid` VARCHAR(5) NULL DEFAULT NULL COMMENT 'user id, employee id' COLLATE 'utf8_unicode_ci' AFTER `baid` 
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>cash table altered';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `bank_accounts` TO `ek_bank_accounts`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>bank_accounts table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `bank_transactions` TO `ek_bank_transactions`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>bank_transactions table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `journal_reco_history` TO `ek_journal_reco_history`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>journal_reco_history table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `expenses_memo` TO `ek_expenses_memo`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "RENAME TABLE `expenses_memo_list` TO `ek_expenses_memo_list`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo_list table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}



try {
  $query = "
  ALTER TABLE `ek_expenses_memo_list`
	CHANGE COLUMN `em_serial` `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'main table serial ref' AFTER `id`,
	CHANGE COLUMN `exp_type` `aid` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'aid reference from ek_accounts' AFTER `serial`,
	CHANGE COLUMN `description` `description` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'user added description' AFTER `aid`,
	CHANGE COLUMN `amount` `amount` DOUBLE NOT NULL DEFAULT '0' COMMENT 'line value' AFTER `description`,
	CHANGE COLUMN `valueusd` `value_base` DOUBLE NOT NULL COMMENT 'line value in base currency' AFTER `amount`,
	CHANGE COLUMN `receipt` `receipt` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'recipt reference' AFTER `value_base`  
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo_list table altered';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "RENAME TABLE `expenses_memo_documents` TO `ek_expenses_memo_documents`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo_documents table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}

try {
  $query = "
  ALTER TABLE `ek_expenses_memo_documents`
  COMMENT='references for receipts uploaded',
	CHANGE COLUMN `emid` `serial` VARCHAR(100) NOT NULL DEFAULT '0' AFTER `id`,
	CHANGE COLUMN `doc_name` `uri` VARCHAR(255) NULL DEFAULT NULL AFTER `serial`,
	CHANGE COLUMN `doc_date` `doc_date` VARCHAR(100) NULL DEFAULT NULL COMMENT 'date uploaded' AFTER `uri`
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo_documents table altered';
  
 
  //
  //change the date format for expenses_memo_documents
  //

  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


  $query = "select * from {ek_expenses_memo_documents}";
  $data = Database::getConnection('external_db', 'external_db')->query($query);

    while ($d = $data->fetchObject()) {

      $stamp = strtotime($d->doc_date);
      Database::getConnection('external_db', 'external_db')
        ->update('ek_expenses_memo_documents')->fields(array('doc_date' => $stamp))
        ->condition('id', $d->id)->execute();
      
    }
  
  $query = "ALTER TABLE `ek_expenses_memo_documents` CHANGE COLUMN `doc_date` `doc_date` INT(11) NULL COMMENT 'date uploaded' AFTER `uri` ";
  Database::getConnection('external_db', 'external_db')->query($query); 
  $markup .= "<br/>date format in memo documents updated";


  
try {
  $query = "ALTER TABLE `ek_expenses_memo`
	ADD COLUMN `auth` INT(2) NULL DEFAULT '0' COMMENT 'authorization setting 0=not required 1=pending 2=auth 3=reject | authorizer.' AFTER `post`;";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo table field added';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>";
}
 

try {
//	ALTER `localcurrency` DROP DEFAULT,
//	ALTER `value_usd` DROP DEFAULT,
  $query = "
  ALTER TABLE `ek_expenses_memo`
	CHANGE COLUMN `em_serial` `serial` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'memo serial No' AFTER `id`,
	CHANGE COLUMN `category` `category` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '1=internal 2 = purchase, 3=claim,4=advance,5=perso' AFTER `serial`,
	CHANGE COLUMN `entity` `entity` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'entity claiming' AFTER `category`,
	CHANGE COLUMN `entity_to` `entity_to` VARCHAR(5) NULL DEFAULT NULL COMMENT 'entity paying' AFTER `entity`,
	CHANGE COLUMN `client` `client` VARCHAR(45) NULL DEFAULT '' COMMENT 'if client related' AFTER `entity_to`,
	CHANGE COLUMN `project` `pcode` VARCHAR(45) NULL DEFAULT '' COMMENT 'pcode if proejct related' AFTER `client`,
	CHANGE COLUMN `mission` `mission` TINYTEXT NULL COMMENT 'a title/description' AFTER `pcode`,
	CHANGE COLUMN `budget` `budget` VARCHAR(45) NULL DEFAULT '' COMMENT 'Y=budgeted,N=not budgeted' AFTER `mission`,
	CHANGE COLUMN `refund` `refund` VARCHAR(45) NULL DEFAULT '' COMMENT 'action refund' AFTER `budget`,
	CHANGE COLUMN `invoice` `invoice` VARCHAR(45) NULL DEFAULT '' COMMENT 'action invoice' AFTER `refund`,
	CHANGE COLUMN `date` `date` VARCHAR(15) NOT NULL DEFAULT '0000-00-00' COMMENT 'date' AFTER `invoice`,
	CHANGE COLUMN `pdate` `pdate` VARCHAR(15) NULL DEFAULT '0000-00-00' COMMENT 'payment date' AFTER `date`,
	CHANGE COLUMN `status` `status` VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'status  0=not paid,1=partial,2=paid' AFTER `pdate`,
	CHANGE COLUMN `localcurrency` `value` DOUBLE UNSIGNED NOT NULL COMMENT 'amount in local currency' AFTER `status`,
	CHANGE COLUMN `currency` `currency` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'selected currency' AFTER `value`,
	CHANGE COLUMN `value_usd` `value_base` DOUBLE UNSIGNED NOT NULL COMMENT 'amount in base currency' AFTER `currency`,
	CHANGE COLUMN `amount_paid` `amount_paid` DOUBLE NULL DEFAULT '0' COMMENT 'amount paid local currency' AFTER `value_base`,
	CHANGE COLUMN `amount_paid_usd` `amount_paid_base` DOUBLE NULL DEFAULT '0' COMMENT 'amount paid base currency' AFTER `amount_paid`,
	CHANGE COLUMN `comment` `comment` TEXT NULL COMMENT 'comment.' AFTER `amount_paid_base`,
	CHANGE COLUMN `reconcile` `reconcile` TINYINT(4) NOT NULL DEFAULT '0' COMMENT 'reconcile status, 0 not reconciled.' AFTER `comment`,
	CHANGE COLUMN `post` `post` TINYINT(2) NOT NULL DEFAULT '0' COMMENT 'post status, 0 =not recorded in expenses, 1 not received.' AFTER `reconcile`,
	CHANGE COLUMN `auth` `auth` VARCHAR(15) NULL DEFAULT '0' COMMENT 'authorization setting 0=not required 1=pending 2=auth 3=reject | authorizer.' AFTER `post`  
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>expenses_memo table altered';

 } catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}



try { 
  
  //
  // standardize pcode ref in table
  //

  $query = "UPDATE {ek_expenses_memo} set pcode='n/a' WHERE pcode='not project related' OR pcode=''";
  Database::getConnection('external_db', 'external_db')->query($query); 

  //
  // change format
  //
  $query = "select id, serial, pcode,budget,refund,invoice from {ek_expenses_memo}";
  $data = Database::getConnection('external_db', 'external_db')->query($query);

      while ($d = $data->fetchAssoc()) {

       if($d['pcode'] != 'n/a' || $d['pcode'] != 'not project related' ) {
       $pcode = str_replace('-', '_' , $d['pcode']);
       $pcode = str_replace('/', '-' , $pcode);
       } else {
       $pcode = $d['pcode'];
       }
       
       $serial = str_replace('-', '_' , $d['serial']);
       $serial = str_replace('/', '-' , $serial);
       
       if($d['budget'] == 'Y') {
        $budget = 1;
       } else {
        $budget = 0;
       }

       if($d['refund'] == 'refund') {
        $refund = 1;
       } else {
        $refund = 0;
       }

       if($d['invoice'] == 'invoice') {
        $invoice = 1;
       } else {
        $invoice = 0;
       } 
        
        $query = "update {ek_expenses_memo} set pcode='" . $pcode ."'  , serial='" . $serial ."' , budget='" . $budget ."' , refund='" . $refund ."' , invoice='" . $invoice ."' WHERE id=" . $d['id'] ."";
        Database::getConnection('external_db', 'external_db')->query($query);
        $query = "update {ek_expenses_memo_list} set serial='" . $serial ."' where serial='" . $d['serial'] . "'";
        Database::getConnection('external_db', 'external_db')->query($query);  

        $query = "update {ek_expenses_memo_documents} set serial='" . $serial ."' where serial='" . $d['serial'] . "'";
        Database::getConnection('external_db', 'external_db')->query($query);   
        
        //$markup .= $serial . ' ' . $pcode . "<br/>"; 
      }

  $markup .= '<br/>formats changed';


} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}



try {
  $query = "RENAME TABLE `yearly_budget` TO `ek_yearly_budget`";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>yearly_budget table renamed';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "
  ALTER TABLE `ek_yearly_budget`
	COMMENT='storage of budget data',
	CHANGE COLUMN `value_usd` `value_base` DOUBLE UNSIGNED NULL DEFAULT NULL COMMENT 'budget value in base currency' AFTER `reference`,
	DROP COLUMN `field3`,
	DROP COLUMN `field4`,
	CHANGE COLUMN `reference` `reference` VARCHAR(25) NOT NULL COMMENT 'account-country-year-month' ,
	DROP COLUMN `id`,
	DROP PRIMARY KEY,
	ADD UNIQUE INDEX `Index 1` (`reference`)
  ";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>yearly_budget table altered';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


try {
  $query = "CREATE TABLE `ek_finance` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`settings` BLOB NULL DEFAULT NULL COMMENT 'settings object',
	PRIMARY KEY (`id`)
    )
    COMMENT='settings for finance and accounts'
    COLLATE='utf8_general_ci'
    ENGINE=InnoDB";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>ek_finance table created';
  
  $query = "INSERT INTO `ek_finance` (`id`) VALUES (1)";
  Database::getConnection('external_db', 'external_db')->query($query);
  $markup .= '<br/>ek_finance table updated';  
  
  
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}


// change memo stauts type 0 not paid 1 partial 2 paid

try {
$query = "UPDATE {ek_expenses_memo} set status='0' WHERE status='no' ";
Database::getConnection('external_db', 'external_db')->query($query); 
$query = "UPDATE {ek_expenses_memo} set status='2' WHERE status='yes' ";
Database::getConnection('external_db', 'external_db')->query($query); 
$query = "UPDATE {ek_expenses_memo} set status='1' WHERE status='part' ";
Database::getConnection('external_db', 'external_db')->query($query); 
$markup .= "<br/>status in memo updated";
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}



//update currency in journal table from exp. table

try {
  $query = "select id, company, currency from {ek_expenses} WHERE company = :c";
  $data = Database::getConnection('external_db', 'external_db')->query($query, [':c' => 20]);
$markup .= '<br/>copy and execute script below for update:<br>';
  while ($d = $data->fetchObject()) {

      $markup .= "update ek_journal set currency ='" . $d->currency ."' where reference ='" .$d->id . "' "
      . "and source='expense' and coid='".$d->company."';<br>";
    //$query = "update {ek_journal} set currency ='" . $d->currency ."' where reference ='" .$data->id . "' "
      //     . "and source='expense' and coid='". $d->company."'";
    //Database::getConnection('external_db', 'external_db')->query($query);
  }
  $markup .= '<br/>currency in journal table update';
} catch (Exception $e) {
  $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}
 */
/*
//change record type of client and supplier in expenses table from string to address book id
$query = "SELECT id, clientname, suppliername from {ek_expenses} order by id";

  $data = Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>change expenses client and supplier data type<br>';
$cl = 0;
$sp = 0;
while ($d = $data->fetchObject() ) {
    if($d->clientname != '' || $d->clientname != 'n/a' || $d->clientname != 'not applicable'){
        $query = "SELECT id from {ek_address_book} WHERE name = :n";
        $cl = Database::getConnection('external_db', 'external_db')
                ->query($query, [':n' => $d->clientname])
                ->fetchField();
         if($cl == '') $cl = 0;    
    } else {
        $cl = 0;        
    }
    if($d->suppliername != '' || $d->suppliername != 'n/a' || $d->suppliername != 'not applicable'){
        $query = "SELECT id from {ek_address_book} WHERE name = :n";
        $sp = Database::getConnection('external_db', 'external_db')
                ->query($query, [':n' => $d->suppliername])
                ->fetchField();
        if($sp == '') $sp = 0;  
          
    } else {
        $sp = 0;        
    }    
    
    $query = "update {ek_expenses} set clientname=:c, suppliername=:s WHERE id=:id";
    Database::getConnection('external_db', 'external_db')
                ->query($query, [':c' => $cl, ':s' => $sp, ':id' => $d->id]);
    $markup .=  $d->id . ' ' . $cl . ' ' . $sp .  '<br>';
    
}


$query = "SELECT id, clientname, suppliername from {ek_expenses} order by id";
  $data = Database::getConnection('external_db', 'external_db')->query($query);
  while ($d = $data->fetchObject() ) {
    $query = "update {ek_expenses_x} set clientname=:c, suppliername=:s WHERE id=:id";
    Database::getConnection('external_db', 'external_db')
                ->query($query, [':c' => $cl, ':s' => $sp, ':id' => $d->id]);

  }
 */