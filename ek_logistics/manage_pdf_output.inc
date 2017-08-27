<?php

  /* Manage Pdf output
  * get data from modules and generate a pdf output base on fpdf library and output templates
  * variables : source = ie. invoice, purchase, quotation; id = source id, signature = bolean; stamps = 
  * 1,2,3 ; mode = print = 0 or save = 1
  */
use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\ek_products\ItemData;
  
$param = unserialize($param);

$id = $param[0];
$source = $param[1];
$signature = $param[2];
$stamp = $param[3];
$template = $param[4];
$contact = $param[5];
$mode = $param[6]; 

/*
* data
*/

$head = Database::getConnection('external_db', 'external_db')
        ->query("SELECT * from {ek_". $source. "} where id=:id", array(':id' => $id))
        ->fetchObject();

if($head->type == 'RR') {
  $head->type = t('RECEIVING REPORT');
  $tpl = 'receiving';
} elseif($head->type == 'RT') {
  $head->type = t('RETURNING REPORT');
  $tpl = 'returning';
} else {
  $head->type = t('DELIVERY ORDER');
  $tpl = 'delivery';
}

Switch ($source) {

    case 'logi_delivery':
    case 'logi_receiving':
    case 'logi_returning':
     
    $lines = Database::getConnection('external_db', 'external_db')
            ->query("SELECT * from {ek_". $source. "_details} where serial=:s", array(':s' => $head->serial));

    $items = array();

      While ($line = $lines->fetchObject()) {
        $data = array();
        if ($line->itemcode <> '') {
        
        $query = "SELECT * from {ek_items} where itemcode=:id";
        $thisitem = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $line->itemcode))
                ->fetchObject();
            $data['item'] = $thisitem->description1;
            $data['itemid'] = $thisitem->id;
            $data['itemcode'] = $thisitem->itemcode;
            $data['supplier_code'] = $thisitem->supplier_code;
            
        $query = "SELECT * from {ek_item_barcodes} where itemcode=:id order by id";
        $barcodes = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $line->itemcode));
        $i=0;
        while($b = $barcodes->fetchObject() ) {
        $i++;
            $data['barcode'.$i] = $b->barcode;
            $data['encode'.$i] = $b->encode;
          } 

        $data['quantity'] = $line->quantity;
        $data['value'] = $line->value;
        $data['total'] = round( ($line->quantity*$line->price) , 2);

        $query = "SELECT * FROM {ek_item_packing} where itemcode=:id"; 
        $thisitem = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $line->itemcode))->fetchObject();    
        
        $data['unit_measure'] = $thisitem->unit_measure;
    
    
        $items[] = $data;

      }
    }
    
    if($source == 'logi_delivery') {
        $cl = $head->client;
    } else {
        $cl = $head->supplier;
    }
    $query = "SELECT * from {ek_company} where id=:id";
    $company = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->head))->fetchObject();

    $query = "SELECT * from {ek_address_book} where id=:id";
    $client = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $cl))->fetchObject();

    if($contact != '') {
    //selected contact from list
    $query = "SELECT * from {ek_address_book_contacts} where id=:id ";
    $client_card = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $contact))->fetchObject();    
    } else {
    //default contact
    $query = "SELECT * from {ek_address_book_contacts} where abid=:id and main=:m";
    $client_card = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $client->id, ':m' => 1))->fetchObject();
    }


    //change  status
    Database::getConnection('external_db', 'external_db')->update(" ek_" . $source)
    ->fields(array('status' => 1))
    ->condition('id',$id)
    ->condition('status', '0')
    ->execute();
    
    break;
    



}

  
  /* 
  * get template data from settings based on source
  */

  if( $template == '0' ) {
    $template = drupal_get_path('module', 'ek_logistics') . "/default_" . $tpl . "_pdf";
  } else {
    $template = PrivateStream::basePath() . '/logistics/templates/' . $head->head . '/pdf/' . $template;
  }

  $fileName = str_replace('/', '-', $head->serial);
 
  include_once $template;  
  
  if ($mode == 1) {
  //save temp file
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

  $f = $fileName . '.pdf';
    echo $pdf->Output($f, 'I');

  exit ;
  }