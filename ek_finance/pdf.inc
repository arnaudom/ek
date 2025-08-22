<?php
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
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

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e')
                    ->fields('e')
                    ->condition('id', $id)
                    ->execute();
        $data[$id]['line'] = $query->fetchObject();
        if(!null == $data[$id]['line']->attachment) {
            $data[$id]['line']->attachment_realpath = \Drupal::service('file_system')->realpath($data[$id]['line']->attachment);
            $data[$id]['line']->attachment_absolute = \Drupal::service('file_url_generator')->generateAbsoluteString($data[$id]['line']->attachment);
            $data[$id]['line']->attachment_image = false;
            
            if ($data[$id]['line']->attachment_realpath && file_exists($data[$id]['line']->attachment_realpath)) {
                // @TODO getimagesize always return false (s3//)
                $image_info = @getimagesize($data[$id]['line']->attachment_realpath);
                if ($image_info !== false) {
                    $data[$id]['line']->attachment_image = $image_info;
                }
            }
        }

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j')
                    ->fields('j')
                    ->condition('reference', $id)                    
                    ->condition('source', 'expense')
                    ->condition('type', 'debit')
                    ->execute();
        $data[$id]['journal'] = $query->fetchObject();


        if($data[$id]['line']->clientname != 'n/a'){
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'b')
                    ->fields('b',['name'])
                    ->condition('id',  $data[$id]['line']->clientname)
                    ->execute();
            $data[$id]['clientname'] = $query->fetchField();
        }

        if($data[$id]['line']->suppliername != 'n/a'){
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'b')
                    ->fields('b',['name'])
                    ->condition('id',  $data[$id]['line']->suppliername)
                    ->execute();
            $data[$id]['suppliername'] = $query->fetchField();
        }

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a')
                    ->fields('a',['aname'])
                    ->condition('aid',  $data[$id]['line']->type)
                    ->condition('coid',  $data[$id]['line']->company)
                    ->execute();
        $data[$id]['type'] = $query->fetchField();

        if($data[$id]['line']->cash <> 'Y') {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank_accounts', 'k')
                    ->fields('k',['account_ref'])
                    ->condition('id',  $data[$id]['line']->cash)
                    ->execute();
            $data[$id]['bank_account'] = $query->fetchField();

        } else {
          $data[$id]['bank_account'] = t('cash');
        }

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c')
                    ->fields('c')
                    ->condition('id',  $data[$id]['line']->company)
                    ->execute();
        $data[$id]['company'] = $query->fetchObject();     
        
        
    }
    
}

//cash voucher
if ($type == 2) {

    $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_cash', 'c')
                ->fields('c')
                ->condition('id', $id)
                ->execute();
    $line = $query->fetchObject(); 

if ($line->uid == 0) { 
    $employee = t("company cash account");
} else {
    $u = Drupal\user\Entity\User::load($line->uid);
    $employee = '';
    if($u) {
        $employee = $u->getAccountName();
    }
    
}
  
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c')
                    ->fields('c')
                    ->condition('id', $line->coid)
                    ->execute();
    $company = $query->fetchObject();     

    $settings = new FinanceSettings(); 
    $baseCurrency = $settings->get('baseCurrency'); 

    $template = 'default_cash_voucher_pdf';
    $fileName = t('cash_voucher') . '_' . $line->id;
    $userName =  \Drupal::currentUser()->getAccountName();

}


// reconciliation report
if ($type == 3) {

    $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal_reco_history', 'j')
                ->fields('j')
                ->condition('id', $id)
                ->execute();
    $line = $query->fetchObject(); 

    $aid = $line->aid;
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a')
                    ->fields('a',['aname'])
                    ->condition('aid',  $line->aid)
                    ->condition('coid', $line->coid)
                    ->execute();
    $aname = $query->fetchField();

    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c')
                    ->fields('c')
                    ->condition('id', $line->coid)
                    ->execute();
    $company = $query->fetchObject();   

    $settings = new FinanceSettings(); 
    $baseCurrency = $settings->get('baseCurrency'); 

    // insert account details in filename
    $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_accounts','a')
                        ->fields('a', ['aname'])
                        ->condition('aid', $line->aid)
                        ->condition('coid', $line->coid)
                        ->execute();
    $acc = $query->fetchField();
    $data = unserialize($line->data);
    $template = 'default_reco_report_pdf';
    $fileName = t('Reconciliation_report') . '_' . $line->id. '_' . $acc. '_' . $line->date;
    $userName =  \Drupal::currentUser()->getAccountName();

}

// profit & loss
if ($type == 4) {
    
    $params = unserialize($param);
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c')
                    ->fields('c')
                    ->condition('id', $params['coid'])
                    ->execute();
    $company = $query->fetchObject();   
    
    $template = 'profit_and_loss_pdf';
    $fileName = t('Profit_and_loss') . '_' . $params['year'] . '_' . $params['month'];
    $userName =  \Drupal::currentUser()->getAccountName();   
}

//balance sheet
if ($type == 5) {
    
    $params = unserialize($param);
    $a = array(':id' => $params['coid']);
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c')
                    ->fields('c')
                    ->condition('id', $params['coid'])
                    ->execute();
    $company = $query->fetchObject();  
    
    $template = 'balance_sheet_pdf';
    $fileName = t('Balance_sheet') . '_' . $params['year'] . '_' . $params['month'];
    $userName =  \Drupal::currentUser()->getAccountName();
}


// bank label
if ($type == 6) {

    $params = unserialize($param);
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank', 'b')
                    ->fields('b')
                    ->condition('id', $params['id'])
                    ->execute();
    $bank = $query->fetchObject();  
    
    $template = 'bank_label_pdf';
    $fileName = $bank->name;
    $userName =  \Drupal::currentUser()->getAccountName();

}

// bank acc. label
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

