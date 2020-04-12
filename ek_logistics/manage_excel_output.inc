<?php

  /* Manage excel output
  * get data from modules and generate a pdf output base on phpexcel library 
  * 
  * mode = print = 0 or save = 1
  */
use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
  
$param = unserialize($param);
$id = $param[0];
$source = $param[1];
$mode = $param[2]; 
$template = $param[3];

/*
* data
*/

$head = Database::getConnection('external_db', 'external_db')
        ->query("SELECT * from {ek_". $source. "} where id=:id", array(':id' => $id))
        ->fetchObject();

if(!isset($head->type)) {
  $head->type = t('DELIVERY ORDER');  
} elseif($head->type == 'RR') {
  $head->type = t('RECEVING REPORT');
} else {
  $head->type = t('RETURNING REPORT');
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
            if ($line->itemcode != '') {
                $data['item'] = $line->itemcode;
                //$data['currency'] = $line->currency;
                $data['quantity'] = $line->quantity;
                if($source == 'logi_delivery') {
                    $data['value'] = $line->value;
                    $data['total'] = round(($line->quantity * $line->value), 2);
                } else {
                    $data['total'] = $line->amount;
                    $data['value'] = round(($line->amount / $line->quantity), 2);
                }
                $data['itemid'] = '';
                $data['itemcode'] = '';
                $data['supplier_code'] = '';
                $data['barcode'] = '';
                $data['unit_measure'] = '';
                
                if(isset($product) && \Drupal\ek_products\ItemData::item_bycode($line->itemcode)){
                    
                    $query = "SELECT * from {ek_items} where itemcode=:id";
                    $thisitem = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $line->itemcode))
                            ->fetchObject();
                    $data['item'] = $thisitem->description1;
                    $data['itemid'] = $thisitem->id;
                    $data['itemcode'] = $thisitem->itemcode;
                    $data['supplier_code'] = $thisitem->supplier_code;

                    $query = "SELECT * from {ek_item_barcodes} where itemcode=:id order by id";
                    $barcodes = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $line->itemcode));
                    $i = 0;
                    while ($b = $barcodes->fetchObject()) {
                        $i++;
                        $data['barcode' . $i] = $b->barcode;
                        $data['encode' . $i] = $b->encode;
                    }

                    $query = "SELECT * FROM {ek_item_packing} where itemcode=:id";
                    $thisitem = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $line->itemcode))
                            ->fetchObject();

                    $data['unit_measure'] = $thisitem->unit_measure;
                    
                }
                $items[] = $data;
            }
        }

    $query = "SELECT * from {ek_company} where id=:id";
    $company = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->head))->fetchObject();

    $query = "SELECT * from {ek_address_book} where id=:id";
    $client = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->client))->fetchObject();


    //default contact
    $query = "SELECT * from {ek_address_book_contacts} where abid=:id and main=:m";
    $client_card = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $client->id, ':m' => 1))->fetchObject();
    
    break;
    



}

  
  /* 
  * get template data from settings based on source
  */

  if( $template == '0' ) {
    $form = str_replace('logi_', '', $source);
    $template = drupal_get_path('module', 'ek_logistics') . "/default_" . $form . "_xls";
  } else {
    $filesystem = \Drupal::service('file_system');
    $path = PublicStream::basePath() . "/" . $template;
    $filesystem->copy("private://logistics/templates/" . $head->head . "/xls/" . $template, $path, FileSystemInterface::EXISTS_REPLACE);
    $template = $path;
    $template = PrivateStream::basePath() . '/logistics/templates/' . $template;
    
  }

  $fileName = str_replace('/', '-', $head->serial);
  $userName = \Drupal::currentUser()->getAccountName();
  
  include_once $template;  
  
  if ($mode == '1') {
  //save temp file

  
  } else {

    if (headers_sent()) {
    exit('Unable to stream pdf: headers already sent');
    }


           
// Redirect output to a clients web browser (Excel5)

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$fileName.'.xlsx"');
header('Cache-Control: max-age=0');

$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;

  }