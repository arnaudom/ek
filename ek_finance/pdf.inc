<?php
use Drupal\Core\Database\Database;
use Drupal\ek_finance\FinanceSettings;

//expense payment voucher
if ($type == 1) {

    $suppliername = t('n/a');
    $clientname = t('n/a');

    $query = "SELECT * from {ek_expenses} where id=:id";
    $a = array(':id' => $id);
    $line = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();

    $query = "SELECT * FROM {ek_journal} WHERE reference=:id AND source=:s AND type=:t";
    $a = array(':id' => $id, ':s' => 'expense', ':t' => 'debit');
    $journal = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
    
    if($line->clientname != 'n/a'){
        $clientname = Database::getConnection('external_db', 'external_db')
                ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $line->clientname))
                ->fetchField();
    }
    if($line->suppliername != 'n/a'){
        $suppliername = Database::getConnection('external_db', 'external_db')
                ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $line->suppliername))
                ->fetchField();
    }
    $type = $line->type . "-" . Database::getConnection('external_db', 'external_db')
            ->query("SELECT aname from {ek_accounts} WHERE aid=:aid and coid=:c", array(':aid' => $line->type, ':c' => $line->company ) )
            ->fetchField();

    if($line->cash <> 'Y') {
      $bank_account = Database::getConnection('external_db', 'external_db')
              ->query("SELECT account_ref from {ek_bank_accounts} WHERE id=:id", array(':id' => $line->cash))
              ->fetchField();
    } else {
      $bank_account = t('cash');
    }

    $company = Database::getConnection('external_db', 'external_db')
            ->query("SELECT * from {ek_company} where id=:id", array(':id' => $line->company))
            ->fetchObject();

    $settings = new FinanceSettings(); 
    $baseCurrency = $settings->get('baseCurrency'); 

    $template = 'default_voucher_pdf';
    $fileName = t('voucher') . '_' . $line->id;
    $userName =  \Drupal::currentUser()->getUsername();

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
  $employee = db_query('SELECT name from {users_field_data} WHERE uid=:u', array(':u' => $line->uid))->fetchField();
}
  
  $company = Database::getConnection('external_db', 'external_db')
          ->query("SELECT * from {ek_company} where id=:id", array(':id' => $line->coid))
          ->fetchObject();

  $settings = new FinanceSettings(); 
  $baseCurrency = $settings->get('baseCurrency'); 

  $template = 'default_cash_voucher_pdf';
  $fileName = t('cash_voucher') . '_' . $line->id;
  $userName =  \Drupal::currentUser()->getUsername();

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
 
  $data = unserialize(utf8_decode($line->data));
  $template = 'default_reco_report_pdf';
  $fileName = t('Reconciliation_report') . '_' . $line->id;
  $userName =  \Drupal::currentUser()->getUsername();

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
    $userName =  \Drupal::currentUser()->getUsername();   
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
    $userName =  \Drupal::currentUser()->getUsername();
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
    $userName =  \Drupal::currentUser()->getUsername();

}

//bank acc. label
if ($type == 7) {

    $params = unserialize($param);
    $a = array(':id' => $params['id']);
    $query = "SELECT a.account_ref, a.currency,b.name,b.address1,b.address2,b.postcode,b.country,"
            . "b.swift, c.name as company "
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
    $userName =  \Drupal::currentUser()->getUsername();

}
include_once drupal_get_path('module', 'ek_finance') . '/' .$template;

  header('Cache-Control: private');
  header('Content-Type: application/pdf');
  //header("Content-Disposition: 'attachment'; filename='$fileName' ");
  $f = str_replace(' ', '_', $fileName) . '.pdf';
  echo $pdf->Output($f, 'I');
  

  exit ;

