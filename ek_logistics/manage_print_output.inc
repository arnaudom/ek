<?php

/* Manage Pdf output
 * get data from modules and generate a pdf output base on pdf library and output templates
 * variables : source = ie. delivery, reveiving; id = source id, signature = bolean; stamps = 
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
    if($source == 'logi_returning') {
        //revert to default record table
        $source = 'logi_receiving';
    }
$signature = $param[2];
$stamp = $param[3];
$template = $param[4];
$contact = $param[5];
$mode = $param[6];


/*
 * data
 */

$head = Database::getConnection('external_db', 'external_db')
        ->query("SELECT * from {ek_" . $source . "} where id=:id", array(':id' => $id))
        ->fetchObject();

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
            $project_link = \Drupal\ek_projects\ProjectData::geturl($head->pcode);
    }
}
Switch ($source) {

    case 'logi_delivery':
    case 'logi_receiving':
    case 'logi_returning':

        $lines = Database::getConnection('external_db', 'external_db')
                ->query("SELECT * from {ek_" . $source . "_details} where serial=:s", array(':s' => $head->serial));

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

        if ($source == 'logi_delivery') {
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

        if ($contact != '') {
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



        break;
}


/*
 * get template data from settings based on source
 */
if ($format == 'pdf' | $mode == 1) {

    //change  status
    Database::getConnection('external_db', 'external_db')->update(" ek_" . $source)
            ->fields(array('status' => 1))
            ->condition('id', $id)
            ->condition('status', '0')
            ->execute();
        
    if ($template == '0') {
        $template = drupal_get_path('module', 'ek_logistics') . "/default_" . $tpl . "_pdf";
    } else {
        //if print template is a custom file, copy from private to public before include
        //use this feature when storage of data is remote.
        $filesystem = \Drupal::service('file_system');
        $path = PublicStream::basePath() . "/" . $tpl;
        $filesystem->copy("private://logistics/templates/" . $source . '/' . $tpl, $path, FileSystemInterface::EXISTS_REPLACE);
        $template = $path;
    }

    $fileName = str_replace('/', '-', $head->serial);

    include_once $template;

    if ($mode == 1) {
        //save temp file
        $path = \Drupal::config('system.file')->get('path.temporary');
        $fileName = $path . "/" . str_replace("/", "_", $head->serial) . ".pdf";
        $pdf->Output($fileName, "F");
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
} else {
    //html format
    if ($template == '0') {

        $template = drupal_get_path('module', 'ek_logistics') . "/default_" . $tpl . "_html";
    }  else {
        $filesystem = \Drupal::service('file_system');
        $path = PublicStream::basePath() . "/" . $tpl;
        $filesystem->copy("private://logistics/templates/" . $source . '/' . $tpl, $path, FileSystemInterface::EXISTS_REPLACE);
        $template = $path;
    }

    $fileName = $head->serial;
    include_once $template;
    
}