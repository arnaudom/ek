<?php

  /* Manage Pdf output
  * get data from modules and generate a pdf output base on tcpdf library and output templates
  * variables : source = ie. invoice, purchase, quotation; id = source id, signature = bolean; stamps = 
  * 1,2,3 ; mode = print = 0 or save = 1
  */
use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;

  
$param = unserialize($param);
    if($param[0] != 'memo_range') {
        $id = $param[0];
        $source = $param[1];
        $signature = $param[2];
        $stamp = $param[3];
        $template = $param[4];
        $mode = isset($param[5]) ? $param[5] : NULL; 
    } else {
        $source = $param[0];
        $category = $_SESSION['memrgfilter']['category'];
        $coid = $_SESSION['memrgfilter']['coid'];
        $coid2 = $_SESSION['memrgfilter']['coid2'];
        $from = $_SESSION['memrgfilter']['from'];
        $to = $_SESSION['memrgfilter']['to'];
        $status = $_SESSION['memrgfilter']['status'];
        $template = $_SESSION['memrgfilter']['template'];
        $signature = $_SESSION['memrgfilter']['signature'];
        $stamp = $_SESSION['memrgfilter']['stamp'];
        $_SESSION['memrgfilter'] = NULL;
        $mode = NULL;
    }
 

/*
* data
*/


Switch ($source) {

    case 'expenses_memo':

        $head = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_expenses_memo} where id=:id", array(':id' => $id))
                ->fetchObject();  
        $query =  "SELECT m.aid,description,amount,value_base,receipt,aname "
                . "FROM {ek_expenses_memo_list} m "
                . "INNER JOIN {ek_accounts} a ON m.aid=a.aid "
                . "WHERE serial=:s and a.coid=:c" ;

        if($head->category < 5 ) {
          $coid = $head->entity; 
          $title = t('Internal Memo');
        } else {
            //cat 5 is for personal claim
            $coid = $head->entity_to;
            $title = t('Personal Claim');
          }

        $lines = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':s' => $head->serial, ':c' => $coid));

        $documents = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_expenses_memo_documents} "
                        . "WHERE serial=:id", array(':id' => $head->serial));
            
        
        if(isset($head->pcode) && $head->pcode != 'n/a') { 
            $head->pcode_raw = $head->pcode;
            $head->pcode = \Drupal\ek_projects\ProjectData::geturl($head->pcode);
            
        } else {
            $head->pcode = "";
            $head->pcode_raw = "";
        }
        
        if($head->category < 5) {     
            $query = "SELECT * from {ek_company} where id=:id";
            $company = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $head->entity))
                    ->fetchObject();
        } else {
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u');
            $query->condition('uid', $head->entity);
            $company = $query->execute()->fetchObject();
            //$query = "SELECT * from {users_field_data} where uid=:id";
            //$company = db_query($query, array(':id' => $head->entity))->fetchObject();

        }   
        
        $auth =  explode('|',$head->auth);

        $query = "SELECT * from {ek_company} where id=:id";
        $company_to = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $head->entity_to))->fetchObject();

        $client = (object)[];
        $client_card = (object)[];
        if($head->client != '0'){
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab');
            $query->condition('id', $head->client, '=');
            $client = $query->execute()->fetchObject();
            $query = "SELECT * from {ek_address_book} where id=:id";
           
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc');
            $query->condition('abid', $client->id, '=');
            $query->condition('main', 1, '=');
            $client_card = $query->execute()->fetchObject();
            
        } 
        
        break;
    
    case 'memo_range':
        
        $data = array();
        
        if($category == 'personal') {
            $query = "select * FROM ek_expenses_memo
                        WHERE date >= :d1
                        AND date <= :d2
                        AND entity like :e1
                        AND entity_to like :e2
                        AND status like :s
                        AND category = :c";
            
            $a = [
               ':d1' => $from,
               ':d2' => $to, 
               ':e1' => $coid,
               ':e2' => $coid2,
               ':s' => $status,
               ':c' => 5,
            ];
            $list = Database::getConnection('external_db', 'external_db')
                ->query($query, $a);
            
               WHILE($l = $list->fetchObject()) {
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u');
                    $query->condition('uid', $l->entity);
                    $entity = $query->execute()->fetchAssoc();
                    //$query = "SELECT * from {users_field_data} where uid=:id";
                    //$entity = db_query($query, array(':id' => $l->entity))
                    //        ->fetchAssoc();
                    
                    $query = "SELECT * from {ek_company} where id=:id";
                    $entity_to = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $l->entity_to))
                            ->fetchAssoc();
                    
                    $query = "SELECT * from {ek_address_book} where id=:id";
                    $client = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $l->client))
                            ->fetchAssoc();

                    $query = "SELECT * from {ek_address_book_contacts} where abid=:id and main=:m";
                    $client_card = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $client->id, ':m' => 1))
                            ->fetchAssoc();
                    
                    $data[$l->id]['main'] = [
                        'serial' => $l->serial,
                        'category' => $l->category,
                        'entity' => $entity,
                        'entity_to' => $entity_to,
                        'client' => $client,
                        'client_card' => $client_card,
                        'pcode' => $l->pcode,
                        'mission' => $l->mission,
                        'budget' => $l->budget,
                        'refund' => $l->refund,
                        'invoice' => $l->invoice,
                        'date' => $l->date,
                        'status' => $l->status,
                        'value' => $l->total,
                        'currency' => $l->currency,
                        'value_base' => $l->value_base,
                        'amount_paid' => $l->amount_paid,
                        'amount_paid_base' => $l->amount_paid_base,
                        'comment' => $l->comment,
                        'auth' => $l->auth,
                   ];
                   
                   $query =  "SELECT m.aid,description,amount,value_base,receipt,aname "
                            . "FROM {ek_expenses_memo_list} m "
                            . "INNER JOIN {ek_accounts} a ON m.aid=a.aid "
                            . "WHERE serial=:s and a.coid=:c" ;
                   
                    $lines = Database::getConnection('external_db', 'external_db')
                                ->query($query, [':s' => $l->serial, ':c' => $l->entity]);
                    
                    WHILE($ls = $lines->fetchObject()) {
                        
                       $data[$l->id]['lines'][] = [
                           'aid' => $ls->aid,
                           'aname' => $ls->aname,
                           'description' => $ls->description,
                           'amount' => $ls->amount,
                           'value_base' => $ls->value_base,
                           'receipt' => $ls->receipt,
                       ];
                           
                    
                        
                    }
                    
               }
            
        } else {
            $query = "select * FROM ek_expenses_memo
                        WHERE date >= :d1
                        AND date <= :d2
                        AND entity like :e1
                        AND entity_to like :e2
                        AND status like :s
                        AND category < :c";
            
            $a = [
               ':d1' => $from,
               ':d2' => $to, 
               ':e1' => $coid,
               ':e2' => $coid2,
               ':s' => $status,
               ':c' => 5,
            ];
           
            $list = Database::getConnection('external_db', 'external_db')
                ->query($query, $a);
            
               WHILE($l = $list->fetchObject()) {
              
                   $query = "SELECT * from {ek_company} where id=:id";
                    $entity = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $l->entity))
                            ->fetchAssoc();

                    $entity_to = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $l->entity_to))
                            ->fetchAssoc();
                    
                    $query = "SELECT * from {ek_address_book} where id=:id";
                    $client = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $l->client))
                            ->fetchAssoc();

                    $query = "SELECT * from {ek_address_book_contacts} where abid=:id and main=:m";
                    $client_card = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $client->id, ':m' => 1))
                            ->fetchAssoc();
                   
                   $data[$l->id]['main'] = [
                        'serial' => $l->serial,
                        'category' => $l->category,
                        'entity' => $entity,
                        'entity_to' => $entity_to,
                        'client' => $client,
                        'client_card' => $client_card,
                        'pcode' => $l->pcode,
                        'mission' => $l->mission,
                        'budget' => $l->budget,
                        'refund' => $l->refund,
                        'invoice' => $l->invoice,
                        'date' => $l->date,
                        'status' => $l->status,
                        'value' => $l->total,
                        'currency' => $l->currency,
                        'value_base' => $l->value_base,
                        'amount_paid' => $l->amount_paid,
                        'amount_paid_base' => $l->amount_paid_base,
                        'comment' => $l->comment,
                        'auth' => $l->auth,
                   ];
                   
                   $query =  "SELECT m.aid,description,amount,value_base,receipt,aname "
                            . "FROM {ek_expenses_memo_list} m "
                            . "INNER JOIN {ek_accounts} a ON m.aid=a.aid "
                            . "WHERE serial=:s and a.coid=:c" ;
                   
                    $lines = Database::getConnection('external_db', 'external_db')
                                ->query($query, [':s' => $l->serial, ':c' => $l->entity]);
                    
                    WHILE($ls = $lines->fetchObject()) {
                        
                       $data[$l->id]['lines'][] = [
                           'aid' => $ls->aid,
                           'aname' => $ls->aname,
                           'description' => $ls->description,
                           'amount' => $ls->amount,
                           'value_base' => $ls->value_base,
                           'receipt' => $ls->receipt,
                       ];
                           
                    
                        
                    }
                   
               }    
            
        }
        
       
        break;
    


}

  /* 
  * get template data from settings based on source
  */
if($format == 'pdf' || $mode == 1) {
    
  if( $template == '0' ) {
    $template = drupal_get_path('module', 'ek_finance') . "/default_" . $source . "_pdf";
  } else {
    $template = PrivateStream::basePath() . '/finance/templates/' . $source . '/' . $template;
    
  }
  

  include_once $template;
  
  if ($mode == 1) {
  //save temp file
  //$fileName = $head->serial;
    $path = \Drupal::config('system.file')->get('path.temporary');
    $fileName = $path ."/" . str_replace("/","_", $head->serial ) . ".pdf";
    $pdf->Output($fileName,"F"); 
  
  } else {
    if (headers_sent()) {
      exit('Unable to stream pdf: headers already sent');
    }

    header('Cache-Control: private');
    header('Content-Type: application/pdf');
    //header("Content-Disposition: 'attachment'; filename='$fileName' ");
    
    if(isset($head)) {
        $f = str_replace('/', '_', $head->serial) . '.pdf';
    } else {
        $f = 'memos.pdf';
    }
    
    echo $pdf->Output($f, 'I');

    exit ;
  }
  
} else {
    //Html
    
    if( $template == '0' ) {
      
      $template = drupal_get_path('module', 'ek_finance') . "/default_" . $source . "_html";
  
  } else {
      
      $template = PrivateStream::basePath() . '/finance/templates/' . $source . '/' . $template;
    
  }

  $fileName = $head->serial;
  include_once $template;    
    
    
}