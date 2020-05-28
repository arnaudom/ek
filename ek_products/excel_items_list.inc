<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;

// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// Set properties
$objPHPExcel->getProperties()->setCreator("EK")
	    ->setLastModifiedBy("EK")
	    ->setTitle("items list")
	    ->setSubject("items list")
	    ->setDescription("items list extracted for Excel generated using PHP classes.")
	    ->setKeywords("")
	    ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('items_list');



// Data
$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

// title
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', "Items list");
          
// subtitle
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A2', "");
            
// format titles
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(16);
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);

$objPHPExcel->getActiveSheet()->getStyle('A2')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A2')->getFont()->setBold(true);

// Add rows
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'ITEM CODE')
            ->setCellValue('B3', 'COMPANY')
            ->setCellValue('C3', 'DESCRIPTION 1')           
            ->setCellValue('D3', 'DESCRIPTION 2')           
            ->setCellValue('E3', 'SUPPLIER') 
            ->setCellValue('F3', 'ACTIVE') 
            ->setCellValue('G3', 'PURCHASE PRICE') 
            ->setCellValue('H3', 'CURRENCY') 
            ->setCellValue('I3', "DATE PURCHASE") 
            ->setCellValue('J3', strtoupper($labels['selling_price_label'])) 
            ->setCellValue('K3', strtoupper($labels['promo_price_label'])) 
            ->setCellValue('L3', strtoupper($labels['discount_price_label'])) 
            ->setCellValue('M3', strtoupper($labels['exp_selling_price_label'])) 
            ->setCellValue('N3', strtoupper($labels['exp_promo_price_label'])) 
            ->setCellValue('O3', strtoupper($labels['exp_discount_price_label'])) 
            ->setCellValue('P3', 'LOCAL CURRENCY') 
            ->setCellValue('Q3', 'EXPORT CURRENCY') 
            ->setCellValue('R3', 'STOCK') 
            ->setCellValue('S3', 'STOCK UNITS')             
            ->setCellValue('T3', 'SIZE') 
            ->setCellValue('U3', 'PACK SIZE')   
            ->setCellValue('V3', 'QUANTITY PER PACK')
            ->setCellValue('W3','BARCODE')
            ->setCellValue('X3','DEPARTMENT')
            ->setCellValue('Y3','FAMILY')
            ->setCellValue('Z3','COLLECTION') ;  
                                         
$objPHPExcel->getActiveSheet()->getRowDimension('3')->setRowHeight(26.5); 
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
$objPHPExcel->getActiveSheet()->getStyle('A3:Z3')->getAlignment()->setWrapText(true);
$objPHPExcel->getActiveSheet()->getStyle('A3:Z3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:Z3')->getFill()->getStartColor()->setARGB('D8D8D8');
$objPHPExcel->getActiveSheet()->getStyle('A3:Z3')->applyFromArray(
		array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'top'     => array(
 					'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
 				)
			)
		)
);
      
// insert from line 4
$l=3;
while ($d = $data->fetchObject()){
    $c=0; // column index
    $l++; // line index

    if ($l & 1) {
        $row="A".$l.":Z".$l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('e6e6fa');
    }

    if($d->active == '0') {
       $active = (string) t('No');
    } else {
       $active = (string) t('Yes');
    }
    $dp = '-';
    if($d->date_purchase != '') {
        $dp = date('Y-m-d', $d->date_purchase);
    }
    $b=array();
        foreach($barcode as $v) {

            if($v['itemcode'] == $d->itemcode) {
                array_push($b,$v['barcode']);
            }

        }

    $bc = implode(',', $b);


    // insert lines rows 

    $cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->itemcode);

    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->name);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->description1);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->description2);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->a_name);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $active);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->purchase_price);
    $c++;$cell=$columns[$c].$l;        
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->currency);
    $c++;$cell=$columns[$c].$l;  
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $dp);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->selling_price);
    $c++;$cell=$columns[$c].$l;  
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->promo_price);
    $c++;$cell=$columns[$c].$l;  
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->discount_price);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->exp_selling_price);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->exp_promo_price);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->exp_discount_price);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->loc_currency);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->exp_currency);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->units);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->unit_measure);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->item_size);
    $c++;$cell=$columns[$c].$l;  
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->pack_size);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->qty_pack);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $bc);
    $c++;$cell=$columns[$c].$l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->department);
    $c++;$cell=$columns[$c].$l;  
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->family);
    $c++;$cell=$columns[$c].$l; 
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->collection);
    $c++;$cell=$columns[$c].$l; 
}


// footer
$date = date('Y-m-d h:i:s') ;
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'items_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;



