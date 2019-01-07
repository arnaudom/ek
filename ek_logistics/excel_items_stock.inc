<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;

// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// Set properties
$objPHPExcel->getProperties()->setCreator("EK")
	    ->setLastModifiedBy("EK")
	    ->setTitle("items stock list")
	    ->setSubject("items stock list")
	    ->setDescription("items list extracted for Excel generated using PHP classes.")
	    ->setKeywords("")
	    ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('items_list');



// Data
$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

// title
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', (string) t("Items stock list") );
          
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
$objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(16.5);
// Add rows
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', (string) t('ITEM CODE'))
            ->setCellValue('B3', (string) t('COMPANY') )
            ->setCellValue('C3', (string) t('DESCRIPTION 1') )                      
            ->setCellValue('D3', (string) t('SUPPLIER') ) 
            ->setCellValue('E3', (string) t('ACTIVE') ) 
            ->setCellValue('F3', (string) t('STOCK') ) 
            ->setCellValue('G3', (string) t('STOCK UNITS') )             
            ->setCellValue('H3', (string) t('SIZE') ) 
            ->setCellValue('I3', (string) t('PACK SIZE') )   
            ->setCellValue('J3', (string) t('QUANTITY PER PACK') )
            ->setCellValue('K3', (string) t('BARCODES'))
            ->setCellValue('L3', (string) t('SELLING PRICE') );  
                                         
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(40);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(25);

$objPHPExcel->getActiveSheet()->getStyle('A3:L3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:L3')->getFill()->getStartColor()->setARGB('FFA0A0A0');
$objPHPExcel->getActiveSheet()->getStyle('A3:L3')->applyFromArray(
		array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			),
			'borders' => array(
				'top'     => array(
 					'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
 				)
			)
		)
);
$HalignCenter = array(
    'alignment' => array(
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
); 
$HalignLeft = array(
    'alignment' => array(
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
    ),
);
$HalignRight = array(
    'alignment' => array(
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
    ),
);
// insert from line 4
$l=3;
while ($d = $data->fetchObject()){
$c=0; // column index
$l++; // line index

if ($l & 1) {
$row="A".$l.":L".$l;
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('e6e6fa');
}

if($d->active == '0') {
    $active = (string) t('No');
} else {
    $active = (string) t('Yes');
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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->a_name);
$c++;$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $active);
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
$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($HalignLeft);
$c++;$cell=$columns[$c].$l; 
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d->selling_price . ' ' . $d->exp_currency);
}


// footer
$date = date('Y-m-d h:i:s') ;
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'items_stock_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;



