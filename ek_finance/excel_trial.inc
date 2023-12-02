<?php

use Drupal\Core\Database\Database;
use Drupal\ek_finance\Journal;

     $items['data'] = array();
     $journal = new Journal();
     $param = unserialize($param);
     $items['data'] = $journal->trial( $param, 0 );
     
     
     
// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Trial balance")
                                ->setSubject("computer generated")
                                ->setDescription("Trial balance report")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle("Trial balance");



$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

// title
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', "Trial balance up to: " . $param['year'] . "-" . $param['month'] );
if($param['active'] == 1) { $active = (string) t('no');} else {$active = (string) t('yes');}
if($param['null'] == 1) { $null = (string) t('no');} else {$null = (string) t('yes');}
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', "Show non active accounts: " . $active);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('c2', "Show accounts with no transaction: " . $null);
// subtitle
$company = Database::getConnection('external_db', 'external_db')
        ->query('SELECT name from {ek_company} WHERE id=:id', array(':id' => $param['coid']))
        ->fetchField();
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A3', $company);
            
// format titles
$objPHPExcel->getActiveSheet()->getStyle('A1:H5')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1:H1')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle('A2:H2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A2:H2')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle('A3')->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle('A3:H3')->getFont()->setBold(true);

// Add rows
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A5', 'ACCOUNT')
            ->setCellValue('B5', 'OPEN')           
            ->setCellValue('C5', 'DEBIT')           
            ->setCellValue('D5', 'CREDIT') 
            ->setCellValue('E5', 'NET') 
            ->setCellValue('F5', 'YTD DEBIT')
            ->setCellValue('G5', 'YTD CREDIT') 
            ->setCellValue('H5', 'CLOSE') ;
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(40);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
$objPHPExcel->getActiveSheet()->setBreak( 'I1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$objPHPExcel->getActiveSheet()->getStyle('A4:H5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT); 
$objPHPExcel->getActiveSheet()->getStyle('A4:H5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A4:H5')->getFill()->getStartColor()->setARGB('FFFFFF');

$TopBorderThin = array(
	'borders' => array(
		'top' => array(
			'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
			'color' => array('argb' => 'FF000000'),
		),
	),
);

$FontBoldCenter = array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
		);
$FontRed = array(
			'font'    => array(
			        'color' => array(
                                        'rgb' => 'FF6666'
                                ),
			),

		);
$NumberFormat = array(
                'numberformat' => [
                    'code' => '#,##0.00'
                ]
                );
$objPHPExcel->getActiveSheet()->getStyle('A4:H4')->applyFromArray($TopBorderThin);     
$objPHPExcel->getActiveSheet()->getStyle('A4:H5')->applyFromArray($FontBoldCenter); 
$objPHPExcel->getActiveSheet()->getStyle('A4:H5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A4:H5')->getFill()->getStartColor()->setARGB('e6e6fa');


$l=5;

        $c=0; // column index
        $l=$l+1; // line index
     
          foreach($items['data']['transactions'] as $key => $row) {
          
              $c=0; 
              $l++;
              $cell=$columns[$c].$l;                
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['aid'] . '-' . $row['aname']);
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['open_base']);
              $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($NumberFormat);
              $c++;
              $cell=$columns[$c].$l; 
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_debit'][0]);               
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_credit'][0]);                 
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, ($row['transaction_credit'][0]-$row['transaction_debit'][0]) );                 
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_ytd_debit'][0]);                 
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_ytd_credit'][0]);   
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['closing_base']);
              $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($NumberFormat);
              
              if($row['active'] == 0) {
                $objPHPExcel->getActiveSheet(0)->getStyle("A$l:H$l")->applyFromArray($FontRed);
              }              
          
              $c=0; 
              $l++;
              $cell=$columns[$c].$l;                
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('base currency') . ' ' . $items['data']['baseCurrency'] );
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['open']);
              $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($NumberFormat);
              $c++;
              $cell=$columns[$c].$l; 
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_debit'][1]);               
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_credit'][1]);                 
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, ($row['transaction_credit'][1]-$row['transaction_debit'][1]) );                 
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_ytd_debit'][1]);                 
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['transaction_ytd_credit'][1]);  
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['closing']); 
              $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($NumberFormat);
              $objPHPExcel->getActiveSheet()->getStyle("A$l:H$l")->getFont()->setSize(9);
              if($row['active'] == 0) {
                $objPHPExcel->getActiveSheet(0)->getStyle("A$l:H$l")->applyFromArray($FontRed);
              }               
          }

//TOTAL
$l++;
$c=0;
$objPHPExcel->getActiveSheet()->getStyle("A$l:H$l")->applyFromArray($TopBorderThin);
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Total') ); 
$objPHPExcel->getActiveSheet()->getStyle("A$l:H$l")->getFont()->setBold(true); 

$c=2;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_debit']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);     

$c=3;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_credit']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);  

$c=4;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['total_net']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

if ($items['data']['total']['error1'] == 1) {
$objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getFill()->getStartColor()->setRGB('FF6666');}

$c=5;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_ytd_debit']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT); 

$c=6;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_ytd_credit']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);


if ($items['data']['total']['error2'] == 1) {
$objPHPExcel->getActiveSheet()->getStyle("F$l:H$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("F$l:H$l")->getFill()->getStartColor()->setRGB('FF6666');

}

$l++;
$c=0;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Total'). ' ' . $items['data']['baseCurrency'] ); 
$objPHPExcel->getActiveSheet()->getStyle("A$l:H$l")->getFont()->setBold(true); 

$c=2;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_debit_base']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);     

$c=3;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_credit_base']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);  

$c=4;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['total_net_base']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

if ($items['data']['total']['error1'] == 1) {
$objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getFill()->getStartColor()->setRGB('FF6666');}

$c=5;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_ytd_debit_base']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT); 

$c=6;
$cell=$columns[$c].$l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $items['data']['total']['transaction_ytd_credit_base']);  
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);


if ($items['data']['total']['error2'] == 1) {
$objPHPExcel->getActiveSheet()->getStyle("F$l:H$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("F$l:H$l")->getFill()->getStartColor()->setRGB('FF6666');

}


$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'Trial_balance_' . $param['year'] . '_' . $param['month'] . '.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;

  
     // end generate report





        
            
            
            
            
            
            
            
            
            
            