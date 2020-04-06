<?php

  /* Manage print output based on format
  * get data from modules and generate an html output
  * variables : source = ie. invoice, purchase, quotation; id = source id, signature = bolean; stamps = 
  * 1,2,3 ; mode = print = 0 or save = 1
  */
use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\File\FileSystemInterface;
use Drupal\ek_products\ItemData;

 
$param = unserialize($param);

$id = $param[0];
$source = $param[1];
$signature = $param[2][0];
$s_pos = $param[2][1];
$stamp = $param[3];
$template = $param[4];
$contact = $param[5];
$mode = (isset($param[6])) ? $param[6] : NULL; 

/*
* data
*/

$head = Database::getConnection('external_db', 'external_db')
        ->query("SELECT * FROM {ek_sales_". $source. "} WHERE id=:id", array(':id' => $id))
        ->fetchObject();

if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {      
    if ($head->pcode && $head->pcode != 'n/a') {
            $project_link = \Drupal\ek_projects\ProjectData::geturl($head->pcode);
    } else {
        $project_link = '';
    }
}

Switch ($source) {

    case 'invoice':
    case 'purchase':
    
    $lines = Database::getConnection('external_db', 'external_db')
            ->query("SELECT * FROM {ek_sales_". $source. "_details} WHERE serial=:s ORDER BY id", array(':s' => $head->serial));

    $items = array();                 
    $taxline = 0;

      While ($line = $lines->fetchObject()) {
        $data['description2'] = '';
        $data['itemid'] = '';
        $data['itemcode'] = '';
        $data['type'] = '';
        $data['supplier_code'] = '';
        $data['collection'] = '';
        $data['department'] = '';
        $data['family'] = '';
        $data['size'] = '';
        $data['color'] = ''; 
        $data['unit_measure'] = ''; 
        $data['item_size'] = ''; 
        $data['pack_size'] = ''; 
        $data['qty_pack'] = ''; 
        
        if ($line->itemdetail != '') {
        
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_items', 'i');
            $query->fields('i');
            $query->leftJoin('ek_item_packing', 'p', 'i.itemcode = p.itemcode');
            $query->fields('p', ['unit_measure','item_size','pack_size','qty_pack']);
            $query->condition('i.id', $line->itemdetail);
            $thisitem = $query->execute()->fetchObject();
                
            if($thisitem->description1 != '') {
                //add this verification for situation of db migrating from old format
                $data['item'] = $thisitem->description1;
                $data['description2'] = $thisitem->description2;
                $data['itemid'] = $thisitem->id;
                $data['itemcode'] = $thisitem->itemcode;
                $data['type'] = $thisitem->type;
                $data['supplier_code'] = $thisitem->supplier_code;
                $data['collection'] = $thisitem->collection;
                $data['department'] = $thisitem->department;
                $data['family'] = $thisitem->family;
                $data['size'] = $thisitem->size;
                $data['color'] = $thisitem->color;
                $data['unit_measure'] = $thisitem->unit_measure; 
                $data['item_size'] = $thisitem->item_size; 
                $data['pack_size'] = $thisitem->pack_size; 
                $data['qty_pack'] = $thisitem->qty_pack;

              } else {
                $data['item']= $line->item;
      
              }
          
        } else {
            $data['item']= $line->item;
        }

        $data['quantity'] = $line->quantity;
        $data['value'] = $line->value;
        $data['total'] = round( ($line->quantity*$line->value) , 2);
        $data['opt'] = $line->opt;

        if($line->opt == 1){
          $taxline+=$data['total'];
        }
        $data['aid'] = $line->aid;

        $items[] = $data;

      }
     
    $items['taxable'] = $taxline;
    $items['taxamount'] = $taxline * $head->taxvalue / 100;

    $query = "SELECT * FROM {ek_company} WHERE id=:id";
    $company = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->head))
            ->fetchObject();

    $query = "SELECT * FROM {ek_address_book} WHERE id=:id";
    $client = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->client))
            ->fetchObject();
    $client_card = new stdClass();
    
    if($contact != '') {
    //selected contact from list
    $query = "SELECT * FROM {ek_address_book_contacts} WHERE id=:id ";
    $client_card = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $contact))
            ->fetchObject();    
    } else {
    //default contact
    $query = "SELECT * FROM {ek_address_book_contacts} WHERE abid=:id and main=:m";
    $client_card = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $client->id, ':m' => 1))
            ->fetchObject();
    }

    if($source == 'invoice') {
        //collect bank data
        if($head->bank != 0) {
            $query = "SELECT * FROM {ek_bank_accounts} ba "
                    . "LEFT JOIN {ek_bank} b "
                    . "ON ba.bid=b.id "
                    . "WHERE ba.id=:id ";
            $bank = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $head->bank))
                    ->fetchObject();
            $company->bank = $bank;//used in footer
        }
    }
       
    break;
    
    case 'quotation':
    
    //use last revision
    $query = "SELECT DISTINCT revision FROM {ek_sales_quotation_details} WHERE serial=:s order by revision DESC";
    $revision = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':s' => $head->serial))
            ->fetchField();  
    $lines = Database::getConnection('external_db', 'external_db')
            ->query("SELECT * from {ek_sales_". $source. "_details} WHERE serial=:s and revision=:r ORDER BY weight,id", 
                    array(':s' => $head->serial, ':r' => $revision));

    $items = array();
    $taxline = 0;

      While ($line = $lines->fetchObject()) {
        $data['description2'] = '';
        $data['itemid'] = '';
        $data['itemcode'] = '';
        $data['type'] = '';
        $data['supplier_code'] = '';
        $data['collection'] = '';
        $data['department'] = '';
        $data['family'] = '';
        $data['size'] = '';
        $data['color'] = ''; 
        $data['unit_measure'] = ''; 
        $data['item_size'] = ''; 
        $data['pack_size'] = ''; 
        $data['qty_pack'] = ''; 
        
        if ($line->itemid != NULL && ItemData::item_bycode($line->itemid) ) {
        //item exist

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_items', 'i');
            $query->fields('i');
            $query->leftJoin('ek_item_packing', 'p', 'i.itemcode = p.itemcode');
            $query->fields('p', ['unit_measure','item_size','pack_size','qty_pack']);
            $query->condition('i.itemcode', $line->itemid);
            $thisitem = $query->execute()->fetchObject();
            
            if($thisitem->description1 != '') {
                //add this verification for situation of db migrating from old format
                $data['item'] = $thisitem->description1;
                $data['description2'] = $thisitem->description2;
                $data['itemid'] = $thisitem->id;
                $data['itemcode'] = $thisitem->itemcode;
                $data['type'] = $thisitem->type;
                $data['supplier_code'] = $thisitem->supplier_code;
                $data['collection'] = $thisitem->collection;
                $data['department'] = $thisitem->department;
                $data['family'] = $thisitem->family;
                $data['size'] = $thisitem->size;
                $data['color'] = $thisitem->color;
                $data['unit_measure'] = $thisitem->unit_measure; 
                $data['item_size'] = $thisitem->item_size; 
                $data['pack_size'] = $thisitem->pack_size; 
                $data['qty_pack'] = $thisitem->qty_pack;

              } else {
                $data['item']= $line->itemdetails;
       
              }
          
          } else {
            $data['item']= $line->itemdetails;
          }

        $data['unit'] = $line->unit;
        $data['value'] = $line->value;
        $data['total'] = round( ($line->unit*$line->value) , 2);
        $data['opt'] = $line->opt;
        $data['column_2'] = $line->column_2;
        $data['column_3'] = $line->column_3;

        $taxline+=$data['total'];

        $items['lines'][] = $data;

      }

     
    $items['taxable'] = $taxline;
    $items['revision'] = $revision;
    if ($items['revision'] > 0) { $items['reference'] = $head->serial  . ' '  . t('revision') . $items['revision']; 
      } else {
      $items['reference'] = $head->serial;    
      }
    $incoterm = explode('|', $head->incoterm);
    $items['incoterm_name'] = $incoterm[0];
    $items['incoterm_rate'] = $incoterm[1];
    if($head->tax == '') {
        $items['tax_name'] = '';
        $items['tax_rate'] = '';  
    } else {
        $tax = explode('|', $head->tax);
        $items['tax_name'] = $tax[0];
        $items['tax_rate'] = $tax[1];  
    }
    
    $salesSettings = new \Drupal\ek_sales\SalesSettings();
    $quotationSettings = $salesSettings->get('quotation');
    foreach($quotationSettings as $key => $data) {
        $items['column_name'. $key] = $data['name'];
        $items['column_active'. $key] = $data['active'];
    }
    
    $query = "SELECT * from {ek_company} where id=:id";
    $company = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->head))
            ->fetchObject();

    $query = "SELECT * from {ek_address_book} where id=:id";
    $client = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $head->client))
            ->fetchObject();

    if($contact != '') {
    //selected contact from list
        $query = "SELECT * from {ek_address_book_contacts} where id=:id ";
        $client_card = Database::getConnection('external_db', 'external_db')
            ->query($query, array(':id' => $contact))
            ->fetchObject();    
    } else {
    //default contact
        $query = "SELECT * from {ek_address_book_contacts} where abid=:id and main=:m";
        $client_card = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $client->id, ':m' => 1))
                ->fetchObject();
    }
    
    //change quotation status
    if($format != 'html') {
        Database::getConnection('external_db', 'external_db')
            ->update('ek_sales_quotation')
            ->fields(array('status' => 1))
            ->condition('id',$id)
            ->execute();
    }
    
    break;


}

  
  /* 
  * get template data from settings based on source
  * output document based on format or mode 
   * mode 1 = save file for emailing (pdf)
  */

if((isset($format) && $format == 'pdf') || $mode == 1) {
  if( $template == '0' ) {
      
      $template = drupal_get_path('module', 'ek_sales') . "/default_" . $source . "_pdf";
  
  } elseif($template == 'default_receipt_invoice_pdf') {
      
      $template = drupal_get_path('module', 'ek_sales') . '/default_receipt_invoice_pdf';
     
  } else {
      //if print template is a custom file, copy from private to public before include
      //use this feature when storage of data is remote.
      $filesystem = \Drupal::service('file_system');
      $path = PublicStream::basePath() . "/" . $template;
      $filesystem->copy("private://sales/templates/". $source . '/' . $template, $path, FileSystemInterface::EXISTS_REPLACE );
      $template = $path ;
  }
  
  $fileName = $head->serial;
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
    $f = $fileName . ".pdf";
    echo $pdf->Output($f,"I");

    exit ;
  }
  
} else {
    //HTML
  if( $template == '0' ) {
      
      $template = drupal_get_path('module', 'ek_sales') . "/default_" . $source . "_html";
  
  } elseif($template == 'default_receipt_invoice_html') {
     
      $template = 'default_receipt_invoice_html';
     
  } else {
      
      $filesystem = \Drupal::service('file_system');
      $p = PublicStream::basePath() . "/" . $template;
      $filesystem->copy("private://sales/templates/". $source . '/' . $template, $p, FileSystemInterface::EXISTS_REPLACE );
      $template = $p ;
    
  }
  
  $fileName = $head->serial;
  include_once $template;    
  
}


  
  