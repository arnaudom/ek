<?php
use Drupal\Core\Database\Database;
use Drupal\ek_finance\FinanceSettings;

//expense payment voucher
if ($type == 1) { 
    
    if(is_numeric($id)) {
        $selection = [$id];
        $fileName = t('voucher') . '_' . $id;
    } else {
        $selection = unserialize($id);
        $fileName = t('voucher') . '_' . t('range');

    }
    $data = [];    
    $settings = new FinanceSettings(); 
    $baseCurrency = $settings->get('baseCurrency'); 
    $template = 'default_voucher_pdf';
    $userName =  \Drupal::currentUser()->getAccountName();
  
    foreach ($selection as $key => $id) {
     
        $data[$id]['suppliername'] = t('n/a');
        $data[$id]['clientname'] = t('n/a');

        $query = "SELECT * from {ek_expenses} where id=:id";
        $a = array(':id' => $id);
        $data[$id]['line'] = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

        $query = "SELECT * FROM {ek_journal} WHERE reference=:id AND source=:s AND type=:t";
        $a = array(':id' => $id, ':s' => 'expense', ':t' => 'debit');
        $data[$id]['journal'] = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

        if($data[$id]['line']->clientname != 'n/a'){
            $data[$id]['clientname'] = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $data[$id]['line']->clientname))
                    ->fetchField();
        }
        if($data[$id]['line']->suppliername != 'n/a'){
            $data[$id]['suppliername'] = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $data[$id]['line']->suppliername))
                    ->fetchField();
        }
        $data[$id]['type'] = $data[$id]['line']->type . "-" . Database::getConnection('external_db', 'external_db')
                ->query("SELECT aname from {ek_accounts} WHERE aid=:aid and coid=:c", 
                        array(':aid' => $data[$id]['line']->type, ':c' => $data[$id]['line']->company ) )
                ->fetchField();

        if($data[$id]['line']->cash <> 'Y') {
          $data[$id]['bank_account'] = Database::getConnection('external_db', 'external_db')
                  ->query("SELECT account_ref from {ek_bank_accounts} WHERE id=:id", array(':id' => $data[$id]['line']->cash))
                  ->fetchField();
        } else {
          $data[$id]['bank_account'] = t('cash');
        }

        $data[$id]['company'] = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_company} where id=:id", array(':id' => $data[$id]['line']->company))
                ->fetchObject();        
        
        
    }
    
}

//cash voucher
if ($type == 2) {

$query = "SELECT * from {ek_cash} where id=:id";
$a = array(':id' => $id);
$line = Database::getConnection('external_db', 'external_db')
        ->query($query, $a)->fetchObject();

if ($line->uid == 0) { 
    $employee = t("company cash account");
} else {
    //$employee = db_query('SELECT name from {users_field_data} WHERE uid=:u', array(':u' => $line->uid))->fetchField();
    $u = Drupal\user\Entity\User::load($line->uid);
    $employee = '';
    if($u) {
        $employee = $u->getAccountName();
    }
    
}
  
  $company = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_company} where id=:id", array(':id' => $line->coid))
          ->fetchObject();

  $settings = new FinanceSettings(); 
  $baseCurrency = $settings->get('baseCurrency'); 

  $template = 'default_cash_voucher_pdf';
  $fileName = t('cash_voucher') . '_' . $line->id;
  $userName =  \Drupal::currentUser()->getAccountName();

}


//reconciliation report
if ($type == 3) {

$query = "SELECT * from {ek_journal_reco_history} where id=:id";
$a = array(':id' => $id);
$line = Database::getConnection('external_db', 'external_db')
        ->query($query, $a)
        ->fetchObject();

  $aid = $line->aid;
  $query = "SELECT aname from {ek_accounts} WHERE aid=:a AND coid=:c";
  $aname = Database::getConnection('external_db', 'external_db')
          ->query($query, array(':a' => $line->aid, ':c' => $line->coid))
          ->fetchField();  
  $company = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_company} where id=:id", array(':id' => $line->coid))
          ->fetchObject();

  $settings = new FinanceSettings(); 
  $baseCurrency = $settings->get('baseCurrency'); 
 
  $data = unserialize($line->data);
  $template = 'default_reco_report_pdf';
  $fileName = t('Reconciliation_report') . '_' . $line->id;
  $userName =  \Drupal::currentUser()->getAccountName();

}

//profit & loss
if ($type == 4) {
    
    $params = unserialize($param);
    $a = array(':id' => $params['coid']);
    $company = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_company} where id=:id",$a)
          ->fetchObject();
    
    $template = 'profit_and_loss_pdf';
    $fileName = t('Profit_and_loss') . '_' . $params['year'] . '_' . $params['month'];
    $userName =  \Drupal::currentUser()->getAccountName();   
}

//balance sheet
if ($type == 5) {
    
    $params = unserialize($param);
    $a = array(':id' => $params['coid']);
    $company = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_company} where id=:id",$a)
          ->fetchObject();
    
    $template = 'balance_sheet_pdf';
    $fileName = t('Balance_sheet') . '_' . $params['year'] . '_' . $params['month'];
    $userName =  \Drupal::currentUser()->getAccountName();
}


//bank label
if ($type == 6) {

    $params = unserialize($param);
    $a = array(':id' => $params['id']);
    $bank = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_bank} where id=:id",$a)
          ->fetchObject();
    
    $template = 'bank_label_pdf';
    $fileName = $bank->name;
    $userName =  \Drupal::currentUser()->getAccountName();

}

//bank acc. label
if ($type == 7) {

    $params = unserialize($param);
    $a = array(':id' => $params['id']);
    $query = "SELECT a.account_ref, a.currency,b.name,b.address1,b.address2,b.postcode,b.country,"
            . "b.swift,bank_code, c.name as company, beneficiary "
            . "from {ek_bank_accounts} a LEFT JOIN {ek_bank} b "
            . "ON b.id=a.bid "
            . "LEFT JOIN {ek_company} c "
            . "ON c.id=b.coid "
            . "where a.id=:id";
    
    $bank = Database::getConnection('external_db', 'external_db')
          ->query($query,$a)
          ->fetchObject();
    
    $template = 'bank_account_label_pdf';
    $fileName = $bank->name;
    $userName =  \Drupal::currentUser()->getAccountName();

}

include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . '/' .$template;

  header('Cache-Control: private');
  header('Content-Type: application/pdf');
  //header("Content-Disposition: 'attachment'; filename='$fileName' ");
  $f = str_replace(' ', '_', $fileName) . '.pdf';
  echo $pdf->Output($f, 'I');
  

  exit ;

