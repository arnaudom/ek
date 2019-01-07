<?php
use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;


// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// Set properties
$objPHPExcel->getProperties()->setCreator("ek")
                                ->setLastModifiedBy("")
                                ->setTitle("Reconciliation list")
                                ->setSubject("Reconciliation list")
                                ->setDescription("Reconciliation list for Excel generated using PHP classes.")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");

//formatting
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
$Fontgrey = array(
    'font'  => array(
        'color' => array('rgb' => '6a6a6a'),
        'size'  => 10,
));

$objPHPExcel->getActiveSheet()->setTitle('Reconciliation list');
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(80);
$objPHPExcel->getActiveSheet()->setBreak( 'F1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// title
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1:B2')->getFont()->setBold(true);

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $data['company'] );
$text = (string)t("Reconciliation data") . ' '  
            . (string)t('as of'). ' ' .  $data['date']. ' ' 
            . (string)t('for'). ' ' .  $data['aid'] . '-' . $data['aname'];

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', $text );

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A3', (string)t('Debits') );
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B3', $data['opendebit'] );
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A4', (string)t('Credits') );
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B4', $data['opencredit'] );
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A5', (string)t('Balance') );
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B5', $data['openbalance'] );
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A6', (string)t('Current total') );
$objPHPExcel->getActiveSheet()->getStyle('C6:D6')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

//table header
$objPHPExcel->getActiveSheet()->getStyle('A7:E7')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A7:E7')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A7:E7')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("A7")->getBorders()->getRight()->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN); 

$objPHPExcel->getActiveSheet()->getStyle("B7")->getBorders()->getRight()->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN); 
$objPHPExcel->getActiveSheet()->getStyle("C7")->getBorders()->getRight()->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN); 
$objPHPExcel->getActiveSheet()->getStyle("D7")->getBorders()->getRight()->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN); 
$objPHPExcel->getActiveSheet()->getStyle("E7")->getBorders()->getRight()->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN); 
$objPHPExcel->getActiveSheet()->getStyle("A7:E7")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A7:E7")->getFill()->getStartColor()->setRGB('C0C0C0');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A7', (string)t('Id'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B7', (string)t('Date'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C7', (string)t('Debit'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D7', (string)t('Credit'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E7', (string)t('Object'));



// Data
$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

//$sdata = subval_sort($data,'date');

$i=8;

 foreach ($data['rows'] as $line) {

    if ($i & 1) {
        $row="A".$i.":E".$i;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('e6e6fa');
    } 
     $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $line['id']);
     $objPHPExcel->getActiveSheet()->getStyle("A$i")->applyFromArray($HalignLeft);
     $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $line['date']);
     $objPHPExcel->getActiveSheet()->getStyle("B$i")->applyFromArray($HalignCenter);
     
     if($line['type'] == 'debit'){
         $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $line['value']);
         $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", '');
     } else {
         $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", $line['value']);
         $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", '');
     }
     $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
             ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

     $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $line['comment']);
     $objPHPExcel->getActiveSheet()->getStyle("E$i")->applyFromArray($Fontgrey);
     
     $i++;
 }
 
  $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C6",  "=sum(C8:C".$i.")");
  $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D6",  "=sum(D8:D".$i.")");
 
  $i++;
  $date = date('Y-m-d h:i:s');
  $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $date);
  
 $fileName = "Reconciliation_" . $data['aid'] . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;