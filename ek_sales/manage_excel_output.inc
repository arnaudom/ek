<?php

  /* Manage excel output
  * get data from modules and generate an excel or csv output
  * variables : source = ie. invoice, purchase, quotation; id = source id, signature = bolean; stamps = 
  * 1,2,3 ; output_format = excel = 1 or csv = 2
  */
use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\user\Entity\User;
use Drupal\ek_products\ItemData;


$param = unserialize($param);

$id = $param[0];
$source = $param[1];
$signature = $param[2];
$stamp = $param[3];
$template = $param[4];
$contact = $param[5];
$output_format = $param[6]; 

/*
* data
*/

$head = Database::getConnection('external_db', 'external_db')
        ->query("SELECT * FROM {ek_sales_". $source. "} WHERE id=:id", array(':id' => $id))->fetchObject();


Switch ($source) {

    case 'invoice':
    case 'purchase':
    
    $lines = Database::getConnection('external_db', 'external_db')
            ->query("SELECT * FROM {ek_sales_". $source. "_details} WHERE serial=:s ORDER BY id", array(':s' => $head->serial));

    $items = array();
    $taxline = 0;

      While ($line = $lines->fetchObject()) {
        $data = array();
        if ($line->itemdetail != '') {
        
        $query = "SELECT * FROM {ek_items} WHERE id=:id";
        $thisitem = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $line->itemdetail))
                ->fetchObject();
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

              } else {
                $data['item']= $line->item;
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
        $query = "SELECT * FROM {ek_bank_accounts} ba "
                . "LEFT JOIN {ek_bank} b "
                . "ON ba.bid=b.id "
                . "WHERE ba.id=:id ";
        $bank = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $head->bank))->fetchObject();
        $company->bank = $bank;//used in footer
    }
    
    break;
    
    case 'quotation':
    
    //use last revision
    $query = "SELECT DISTINCT revision FROM {ek_sales_quotation_details} WHERE serial=:s order by revision DESC";
    $revision = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => $head->serial))->fetchField();  
    $lines = Database::getConnection('external_db', 'external_db')
            ->query("SELECT * FROM {ek_sales_". $source. "_details} WHERE serial=:s and revision=:r ORDER BY id", array(':s' => $head->serial, ':r' => $revision));

    $items = array();
    $taxline = 0;

      While ($line = $lines->fetchObject()) {
        $data = array();
        if ($line->itemid != NULL && ItemData::item_bycode($line->itemid) ) {
        //item exist
        
        $query = "SELECT * from {ek_items} where itemcode=:id";
        $thisitem = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $line->itemid))
                ->fetchObject();
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

              } else {
                $data['item']= $line->item;
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
    $tax =  explode('|', $head->tax);
    $items['incoterm_name'] = $incoterm[0];
    $items['incoterm_rate'] = $incoterm[1];
    $items['tax_name'] = $tax[0];
    $items['tax_rate'] = $tax[1];
    
    $salesSettings = new \Drupal\ek_sales\SalesSettings();
    $quotationSettings = $salesSettings->get('quotation');
    foreach($quotationSettings as $key => $data) {
        $items['column_name'. $key] = $data['name'];
        $items['column_active'. $key] = $data['active'];
    }
    
    $query = "SELECT * from {ek_company} where id=:id";
    $company = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $head->header))->fetchObject();

    $query = "SELECT * from {ek_address_book} where id=:id";
    $client = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $head->client))->fetchObject();

    if($contact != '') {
    //selected contact from list
    $query = "SELECT * from {ek_address_book_contacts} where id=:id ";
    $client_card = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $contact))->fetchObject();    
    } else {
    //default contact
    $query = "SELECT * from {ek_address_book_contacts} where abid=:id and main=:m";
    $client_card = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $client->id, ':m' => 1))->fetchObject();
    }
    
    //change quotation status
    Database::getConnection('external_db', 'external_db')->update('ek_sales_quotation')
    ->fields(array('status' => 1))
    ->condition('id',$id)
    ->execute();
    
    break;


}

  
  /* 
  * get template data from settings based on source
  * output document based on format
  */

if($output_format == '1') {
  if( $template == '0' ) {
      
      $template = drupal_get_path('module', 'ek_sales') . "/default_" . $source . "_excel";
  
  } elseif($template == 'default_receipt_invoice_excel') {
     
      $template = 'default_receipt_invoice_excel';
     
  } else {
      
      $template =  PrivateStream::basePath() . '/sales/templates/' . $source . '/' . $template;
    
  }
  
  $fileName = $head->serial;
  include_once $template; 
  

  

  
} elseif($output_format == '2') {

  //csv file
    $header_i = "id,serial,do_no,head,allocation,status,amount,currency,date,title,pcode,comment,client,amountreceived,pay_date,class,amountbase,balancebase,terms,due,bank,tax,taxvalue,reconcile,balance_post,alert,alert_who";
    $header_v = "''," 
            .  $head->serial . ','
            .  $head->do_no . ','
            .  $head->head
            .  $head->allocation . ',' 
            .  $head->status . ',' 
            .  $head->amount . ',' 
            .  $head->currency . ',' 
            .  $head->date . ',' 
            .  $head->title . ',' 
            .  $head->pcode . ',' 
            .  $head->comment . ',' 
            .  $head->client . ',' 
            .  $head->amountreceived . ',' 
            .  $head->pay_date . ',' 
            .  $head->class . ',' 
            .  $head->amountbase . ',' 
            .  $head->balancebase . ',' 
            .  $head->terms . ',' 
            .  $head->due . ',' 
            .  $head->bank . ',' 
            .  $head->tax . ',' 
            .  $head->taxvalue . ',' 
            .  $head->reconcile . ',' 
            .  $head->balance_post . ',' 
            .  $head->alert . ',' 
            .  $head->alert_who;
    
    $items_i = "id,serial,item,itemdetail,value,margin,quantity,total,totalbase,opt,aid";
    $items_l = '';
    foreach ($items as $detail) {
        $items_l .= "''," . $detail['serial'] . ',';
        $items_l .=  $detail['item'] . ',';
        $items_l .=  $detail['itemdetail'] . ',';
        $items_l .=  $detail['value'] . ',';
        $items_l .=  $detail['margin'] . ',';
        $items_l .=  $detail['quantity'] . ',';
        $items_l .=  $detail['total'] . ',';
        $items_l .=  $detail['totalbase'] . ',';
        $items_l .=  $detail['opt'] . ',';
        $items_l .=  $detail['aid'] . '\r\n';
        
    }
    
    $file = $header_i . '\r\n' . $header_v . '\r\n' . $items_i . '\r\n' . $items_l;
    $f = file_save_data($file, 'private://tmp/' . $head->serial . '.csv', NULL);
        
        if ($f) {
   
            $id = \Drupal::currentUser()->id();
        if ($id) {
            $user = User::load($id);
            $f->setOwner($user);
            
          }
          else {
            $f->setOwner($this->adminUser);
          }
          // Change the file status to be temporary.
          $f->setTemporary();
          // Save the changes.
          $f->save();
        }
 
        $build['csv']=[
            '#markup' => "<a href='". file_create_url($f->getFileUri()) 
                . "'>" . t('download') . "</a>"
        ];
  
}


  
  