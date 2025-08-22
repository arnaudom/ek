<?php

namespace Drupal\ek_sales;

use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\File\FileExists;
use Drupal\user\Entity\User;
use Drupal\ek_products\ItemData;
use Drupal\ek_sales\SalesSettings;


class PrintManager {

    protected $extdb;

    /**
    * Constructs a PromptService object.
    */
    public function __construct() {
        $this->extdb = Database::getConnection('external_db', 'external_db');
    }

    /**
    * Print document in Pdf format
    *  @param serialize array
    *   id: database id
    *   source: string, invoice, purchase, quotation
    *   signature: array [sales, pos]
    *   stamp: bolean
    *   template: sting
    *   contact: int, address book contact id
    *   mode: mode 1 = save file for attachment (pdf) or NULL
    */
    public function makePdf($param) {

        $param = unserialize($param);
        $id = $param[0];
        $source = $param[1];
        $signature = $param[2][0];
        $s_pos = $param[2][1];
        $stamp = $param[3];
        $template = $param[4];
        $contact = $param[5];
        $mode = (isset($param[6])) ? $param[6] : NULL; 
        $sales = new SalesSettings();
        $customSettings = $sales->get('custom_form');
        $items = [];

        $head = $this->getHeaderData($source, $id);
        $items = $this->getItemData($source, $head);
        $company = $this->getCompanyData($head);
        $client = $this->getClientData($head);
        $client_card = $this->getClientCardData($client,$contact); 
        if($source == 'invoice' && $head->bank != 0) {
            // bank ref.
            $bank = $this->getBankData($head);
        } else {
            $bank = null;
        }
        if ($head->pcode && $head->pcode != 'n/a') {
            $project_link = \Drupal::service('project.service')->geturl($head->pcode);
        }
        if($source == 'quotation') {
            // change print status
            $this->printStatus($head);
        }

        // select template
        if( $template == '0' ) {
            $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/templates/default_" . $source . "_pdf";
            if(!empty($customSettings["default_" . $source . "_pdf"])){
                $custom = $customSettings["default_" . $source . "_pdf"];
            }
        } elseif($template == 'default_receipt_invoice_pdf') {
            
            $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . '/templates/default_receipt_invoice_pdf';
            if(!empty($customSettings["default_receipt_invoice_pdf"])){
                $custom = $customSettings["default_receipt_invoice_pdf"];
            }
            
        } else {
            // if print template is a custom file, copy from private to public before include
            // use this feature when storage of data is remote.
            $filesystem = \Drupal::service('file_system');
            $path = PublicStream::basePath() . "/" . $template;
            $filesystem->copy("private://sales/templates/". $source . '/' . $template, $path, FileExists::Replace);
            
            if(!empty($customSettings[$template])){
                $custom = $customSettings[$template];
            }
            
            $template = $path;
        }
        
        $fileName = $head->serial;
        include_once $template;  
        
        // output
        if ($mode == 1) {
            // save temp file
            $fileName = \Drupal::service('file_system')->getTempDirectory() ."/" . str_replace("/","_", $head->serial ) . ".pdf";
            $pdf->Output($fileName,"F"); 
            return $fileName;
        
        } else {
            if (headers_sent()) {
                exit('Unable to stream pdf: headers already sent');
            }
        
            header('Cache-Control: private');
            header('Content-Type: application/pdf');
            $f = $fileName . ".pdf";
            echo $pdf->Output($f,"I");
        
            exit ;
        }
    }
    
    /**
    * Render document in html format
    *  @param serialize array
    *   id: database id
    *   source: string, invoice, purchase, quotation
    *   signature: array [sales, pos]
    *   stamp: bolean
    *   template: sting
    *   contact: int, address book contact id
    *   url: pdf, excel, edit
    */
    public function renderHtml($param) {
        $param = unserialize($param);
        $variables = [];
        $id = $param[0];
        $source = $param[1];
        $variables['signature'] = $param[2];
        $stamp = $param[3];
        $template = $param[4];
        $contact = $param[5];
        $variables['url_pdf'] = $param[6];
        $variables['url_excel'] = $param[7];
        $variables['url_edit'] = $param[8];

        $variables['head'] = $this->getHeaderData($source, $id);
        $variables['items'] = $this->getItemData($source, $variables['head']); 
        $variables['company'] = $this->getCompanyData($variables['head']);
        $variables['client'] = $this->getClientData($variables['head']);
        $variables['client_card'] = $this->getClientCardData($variables['client'],$contact); 
        if($source == 'invoice' && $variables['head']->bank != 0) {
            // bank ref.
            $variables['bank'] = $this->getBankData($variables['head']);
        } else {
            $variables['bank'] = null;
        }
        if ($stamp == "2") {
            $copy = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/art/copy.png";
            if (file_exists($copy)) {
                $variables['copy'] = "<IMG src='" . \Drupal::service('file_url_generator')->generateAbsoluteString($copy) . "' alt='copy' class='align-center'/>";
            }
        } elseif ($stamp == "1") {
            $original = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/art/original.png";
            if (file_exists($original)) {
                $variables['original'] = "<IMG src='" . \Drupal::service('file_url_generator')->generateAbsoluteString($original) . "' alt='original' class='align-center'/>";
            }
        }
        if ($variables['head']->pcode && $variables['head']->pcode != 'n/a') {
            $variables['project_link'] = \Drupal::service('project.service')->geturl($variables['head']->pcode);
        }
        $renderer = \Drupal::service('renderer');
        $build = [
            '#theme' => 'default_' . $source,
            '#content' => $variables,
          ];
        $html = $renderer->render($build);
        return $html;

    }
    
    /**
    * Download document in excel format
    *  @param serialize array
    *   id: database id
    *   source: string, invoice, purchase, quotation
    *   signature: array [sales, pos]
    *   stamp: bolean
    *   template: sting
    *   contact: int, address book contact id
    *   format: excel, csv
    */
    public function exportExcel($param) {

        $param = unserialize($param);
        $id = $param[0];
        $source = $param[1];
        $signature = $param[2];
        $stamp = $param[3];
        $template = $param[4];
        $contact = $param[5];
        $mode = (isset($param[6])) ? $param[6] : 1; 
        $items = [];

        $head = $this->getHeaderData($source, $id);
        $items = $this->getItemData($source, $head);
        $company = $this->getCompanyData($head);
        $client = $this->getClientData($head);
        $client_card = $this->getClientCardData($client,$contact); 
        if($source == 'invoice' && $head->bank != 0) {
            // bank ref.
            $bank = $this->getBankData($head);
        } else {
            $bank = null;
        }

        if($mode == '1') {
            if( $template == '0' ) {
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/templates/default_" . $source . "_excel";
            } elseif($template == 'default_receipt_invoice_excel') {
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/templates/default_receipt_invoice_excel";
            } else {
                // if print template is a custom file, copy from private to public before include
                // use this feature when storage of data is remote.
                $filesystem = \Drupal::service('file_system');
                $path = PublicStream::basePath() . "/" . $template;
                $filesystem->copy("private://sales/templates/". $source . '/' . $template, $path, FileExists::Replace);
                $template = $path ;
            }
            
            $fileName = $head->serial;
            include_once $template; 
            
          } elseif($mode == '2') {
          
                // csv file
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
                $f = \Drupal::service('file.repository')->writeDataa($file, 'private://tmp/' . $head->serial . '.csv', NULL);
                  if ($f) {
                      $id = \Drupal::currentUser()->id();
                  if ($id) {
                      $user = User::load($id);
                      $f->setOwner($user->getAccountName());
                    } else {
                      $f->setOwner('admin');
                    }
                        // Change the file status to be temporary.
                        $f->setTemporary();
                        // Save the changes.
                        $f->save();
                  }
           
                  $build['csv']=[
                      '#markup' => "<a href='". \Drupal::service('file_url_generator')->generateAbsoluteString($f->getFileUri()) 
                          . "'>" . t('download') . "</a>"
                  ];
        }  
    }

    private function getCompanyData($header) {
        $query = $this->extdb->select('ek_company', 't');
        $query->fields('t');
        $query->condition('id', $header->head);
        return $query->execute()->fetchObject();
    }

    private function getClientData($header) {
        $query = $this->extdb->select('ek_address_book', 't');
        $query->fields('t');
        $query->condition('id', $header->client);
        return $query->execute()->fetchObject();
    }

    private function getClientCardData($client, $contact) {
        $query = $this->extdb->select('ek_address_book_contacts', 't');
        $query->fields('t');
        if($contact != '') {
            // selected contact from list
            $query->condition('id', $contact);
        } else {
            // default contact
            $query->condition('abid', $client->id);
            $query->condition('main', 1);
        }
        return $query->execute()->fetchObject();
    }

    private function getBankData($header) {
        $query = $this->extdb->select('ek_bank_accounts', 'ba');
        $query->fields('ba');
        $query->leftJoin('ek_bank', 'b', 'ba.bid=b.id');
        $query->fields('b');
        $query->condition('ba.id', $header->bank);
        return $query->execute()->fetchObject();
    }

    private function printStatus($header) {
        $query = $this->extdb->update('ek_sales_quotation')
        ->fields(array('status' => 1))
        ->condition('id',$header->id)
        ->execute();
    }

    private function getHeaderData($source, $id) {
        $table ="ek_sales_". $source;
        $query = $this->extdb->select($table, 't');
        $query->fields('t');
        $query->condition('id', $id);
        return $query->execute()->fetchObject();

    }

    private function getItemData($source, $header) {
        $table ="ek_sales_". $source . "_details";

        Switch ($source) {

            case 'invoice':
            case 'purchase':
                $query = $this->extdb->select($table, 't');
                $query->fields('t');
                $query->condition('serial', $header->serial);
                $query->orderBy('id');
                $result = $query->execute();
                $items = [];                 
                $taxline = 0;

                While ($line = $result->fetchObject()) {
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
                        $query = $this->extdb->select('ek_items', 'i');
                        $query->fields('i');
                        $query->leftJoin('ek_item_packing', 'p', 'i.itemcode = p.itemcode');
                        $query->fields('p', ['unit_measure','item_size','pack_size','qty_pack']);
                        $query->condition('i.id', $line->itemdetail);
                        $thisitem = $query->execute()->fetchObject();
                            
                        if($thisitem->description1 != '') {
                            // add this verification for situation of db migrating from old format
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
                      $taxline += $data['total'];
                    }
                    $data['aid'] = $line->aid;
                    $items[] = $data;

                  }
        // todo: verify template             
                $items['taxable'] = $taxline;
                $items['taxamount'] = $taxline * $header->taxvalue / 100;

                break;
    
            case 'quotation':

                // use last revision
                $query = $this->extdb->select($table, 't');
                $query->fields('t', ['revision']);
                $query->condition('serial', $header->serial);
                $query->orderBy('revision', 'DESC');
                $result = $query->execute();
                $revision = $result->fetchField();

                $query = $this->extdb->select($table, 't');
                $query->fields('t');
                $query->condition('serial', $header->serial);
                $query->condition('revision', $revision);
                $query->orderBy('weight');
                $query->orderBy('id');
                $lines = $query->execute();

                $data = [];
                $items = [];
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
                    // item exist

                        $query = $this->extdb->select('ek_items', 'i');
                        $query->fields('i');
                        $query->leftJoin('ek_item_packing', 'p', 'i.itemcode = p.itemcode');
                        $query->fields('p', ['unit_measure','item_size','pack_size','qty_pack']);
                        $query->condition('i.itemcode', $line->itemid);
                        $thisitem = $query->execute()->fetchObject();
                        
                        if($thisitem->description1 != '') {
                            // add this verification for situation of db migrating from old format
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
                    $taxline += $data['total'];

                    $items[] = $data;

                }
                
                $items['taxable'] = $taxline;
                $items['revision'] = $revision;
                if ($data['revision'] > 0) { 
                    $items['reference'] = $header->serial  . ' '  . t('revision') . $data['revision']; 
                } else {
                    $items['reference'] = $header->serial;    
                }
                $incoterm = explode('|', $header->incoterm);
                $items['incoterm_name'] = $incoterm[0];
                $items['incoterm_rate'] = $incoterm[1];
                if($header->tax == '') {
                    $items['tax_name'] = '';
                    $items['tax_rate'] = '';  
                } else {
                    $tax = explode('|', $header->tax);
                    $items['tax_name'] = $tax[0];
                    $items['tax_rate'] = $tax[1];  
                }
                
                $salesSettings = new \Drupal\ek_sales\SalesSettings();
                $quotationSettings = $salesSettings->get('quotation');
                foreach($quotationSettings as $key => $val) {
                    $items['column_name'. $key] = $val['name'];
                    $items['column_active'. $key] = $val['active'];
                }
                
                break;
        }
        
        return $items;
    }
}