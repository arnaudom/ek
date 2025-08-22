<?php

namespace Drupal\ek_logistics;

use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;
use Drupal\user\Entity\User;
use Drupal\ek_products\ItemData;
use Drupal\ek_logistics\LogisticsSettings;



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
    public function makePdf($param, $product = False) {

        $param = unserialize($param);
        $id = $param[0];
        $source = $param[1];
        if($source == 'logi_returning') {
            // revert to default record table
            $source = 'logi_receiving';
        }
        $signature = $param[2][0];
        $s_pos = $param[2][1];
        $stamp = $param[3];
        $template = $param[4];
        $contact = $param[5];
        $mode =  (isset($param[6])) ? $param[6] : NULL; 

        $head = $this->getHeaderData($source, $id);

        if (isset($head->type) && $head->type == 'RR') {
            $head->type = t('RECEIVING REPORT');
            $tpl = 'receiving';
        } elseif (isset($head->type) && $head->type == 'RT') {
            $head->type = t('RETURNING REPORT');
            $tpl = 'returning';
        } else {
            $head->type = t('DELIVERY ORDER');
            $tpl = 'delivery';
        }

        if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {      
            if ($head->pcode && $head->pcode != 'n/a') {
                    $project_link = \Drupal::service('project.service')->geturl($head->pcode);
            }
        }

        $items = $this->getItemData($source, $head, $product);        
        $company = $this->getCompanyData($head);
        $client = $this->getClientData($head, $tpl);
        $client_card = $this->getClientCardData($client,$contact); 
        $this->printStatus($source, $head);
            
        if ($template == '0') {
            $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_logistics') . "/templates/default_" . $tpl . "_pdf";
        } else {
            // if print template is a custom file, copy from private to public before include
            // use this feature when storage of data is remote.
            $filesystem = \Drupal::service('file_system');
            $path = PublicStream::basePath() . "/" . $tpl;
            $filesystem->copy("private://logistics/templates/" . $head->head . "/pdf/" . $template, $path, FileExists::Replace);
            $template = $path;
        }

        $fileName = str_replace('/', '-', $head->serial);

        include_once $template;

        if ($mode == 1) {
            // save temp file
            $fileName = \Drupal::service('file_system')->getTempDirectory() . "/" . str_replace("/", "_", $head->serial) . ".pdf";
            $pdf->Output($fileName, "F");
            return $fileName;

        } else {
            if (headers_sent()) {
                exit('Unable to stream pdf: headers already sent');
            }

            header('Cache-Control: private');
            header('Content-Type: application/pdf');

            $f = $fileName . '.pdf';
            echo $pdf->Output($f, 'I');
            exit;
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
    public function renderHtml($param, $product = False) {
        $param = unserialize($param);
        $variables = [];
        $id = $param[0];
        $source = $param[1];
        if($source == 'logi_returning') {
            // revert to default record table
            $source = 'logi_receiving';
        }
        $variables['signature'] = $param[2];
        $stamp = $param[3];
        $template = $param[4];
        $contact = $param[5];
        $variables['url_pdf'] = $param[6];
        $variables['url_excel'] = $param[7];
        $variables['url_edit'] = $param[8];

        $variables['head'] = $this->getHeaderData($source, $id);

        if (isset($variables['head']->type) && $variables['head']->type == 'RR') {
            $variables['head']->type = t('RECEIVING REPORT');
            $tpl = 'receiving';
        } elseif (isset($head->type) && $head->type == 'RT') {
            $variables['head']->type = t('RETURNING REPORT');
            $tpl = 'returning';
        } else {
            $variables['head']->type = t('DELIVERY ORDER');
            $tpl = 'delivery';
        }

        if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {      
            if ($variables['head']->pcode && $variables['head']->pcode != 'n/a') {
                    $variables['project_link'] = \Drupal::service('project.service')->geturl($variables['head']->pcode);
            }
        }

        $variables['items'] = $this->getItemData($source, $variables['head'], $product);        
        $variables['company'] = $this->getCompanyData($variables['head']);
        $variables['client'] = $this->getClientData($variables['head'],$tpl);
        $variables['client_card'] = $this->getClientCardData($variables['client'],$contact); 

        if ($template == '0') {
        $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_logistics') . "/templates/default_" . $tpl . "_html";
        } else {
            $filesystem = \Drupal::service('file_system');
            $path = PublicStream::basePath() . "/" . $tpl;
            $filesystem->copy("private://logistics/templates/" . $variables['head']->head . "/html/" . $tpl, $path, FileExists::Replace);
            $template = $path;
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
    public function exportExcel($param, $product) {

        $param = unserialize($param);
        $id = $param[0];
        $source = $param[1];
        $mode = $param[2]; 
        $template = $param[3];
        $contact = isset($param[4]) ? $param[4]: null;

        $head = $this->getHeaderData($source, $id);

        if (isset($head->type) && $head->type == 'RR') {
            $head->type = t('RECEIVING REPORT');
            $tpl = 'receiving';
        } elseif (isset($head->type) && $head->type == 'RT') {
            $head->type = t('RETURNING REPORT');
            $tpl = 'receiving';
        } else {
            $head->type = t('DELIVERY ORDER');
            $tpl = 'delivery';
        }

        if(\Drupal::moduleHandler()->moduleExists('ek_projects')) {      
            if ($head->pcode && $head->pcode != 'n/a') {
                    $project_link = \Drupal::service('project.service')->geturl($head->pcode);
            }
        }

        $items = $this->getItemData($source, $head, $product);        
        $company = $this->getCompanyData($head);
        $client = $this->getClientData($head, $tpl);
        $client_card = $this->getClientCardData($client,$contact); 
       
        
        if( $template == '0' ) {
            $form = str_replace('logi_', '', $source);
            $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_logistics') . "/templates/default_" . $form . "_xls";
        } else {
            $filesystem = \Drupal::service('file_system');
            $path = PublicStream::basePath() . "/" . $template;
            $filesystem->copy("private://logistics/templates/" . $head->head . "/xls/" . $template, $path, FileExists::Replace);
            $template = $path;            
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

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$fileName.'.xlsx"');
            header('Cache-Control: max-age=0');

            $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
            $objWriter->save('php://output');
            exit;
        }

    }

    private function getCompanyData($header) {
        $query = $this->extdb->select('ek_company', 't');
        $query->fields('t');
        $query->condition('id', $header->head);
        return $query->execute()->fetchObject();
    }

    private function getClientData($header, $tpl) {
        $query = $this->extdb->select('ek_address_book', 't');
        $query->fields('t');
        if($tpl == 'delivery') {
            $query->condition('id',$header->client);
        } else {
            $query->condition('id',  $header->supplier);
        }
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

    private function printStatus($source, $header) {
        $table = "ek_" . $source;
        $query = $this->extdb->update($table)
        ->fields(array('status' => 1))
        ->condition('id',$header->id)
        ->execute();
    }

    private function getHeaderData($source, $id) {
        $table = "ek_" . $source;
        $query = $this->extdb->select($table, 't');
        $query->fields('t');
        $query->condition('id', $id);
        return $query->execute()->fetchObject();

    }

    private function getItemData($source, $header, $product) {
        
        $table ="ek_". $source . "_details";
        $query = $this->extdb->select($table, 't');
                $query->fields('t');
                $query->condition('serial', $header->serial);
                $query->orderBy('id');
                $result = $query->execute();
                $items = [];      

        While ($line = $result->fetchObject()) {
            $data = [];
            if ($line->itemcode != '') {
                $data['item'] = $line->itemcode;
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
                if(isset($product) && ItemData::item_bycode($line->itemcode)){
                    $p = $this->getProduct($line->itemcode);
                    $data['item'] = $p->description1;
                    $data['itemid'] = $p->id;
                    $data['itemcode'] = $p->itemcode;
                    $data['supplier_code'] = $p->supplier_code;

                    $i = 0;
                    $barcodes = $this->getBarcode($line->itemcode);
                    while ($b = $barcodes->fetchObject()) {
                        $i++;
                        $data['barcode' . $i] = $b->barcode;
                        $data['encode' . $i] = $b->encode;
                    }
                    $p = $this->getPack($line->itemcode);
                    $data['unit_measure'] = $p->unit_measure;
                    
                }
                $items[] = $data; 
            }
        }
        
        return $items;
    }

    private function getProduct($itemcode) {
        $query = $this->extdb->select('ek_items', 't');
        $query->fields('t');
        $query->condition('itemcode', $itemcode);
        return $query->execute()->fetchObject();
    }

    private function getBarcode($itemcode) {
        $query = $this->extdb->select('ek_item_barcodes', 't');
        $query->fields('t');
        $query->condition('itemcode', $itemcode);
        return $query->execute();
    }

    private function getPack($itemcode) {
        $query = $this->extdb->select('ek_item_packing', 't');
        $query->fields('t');
        $query->condition('itemcode', $itemcode);
        return $query->execute()->fetchObject();
    }
}