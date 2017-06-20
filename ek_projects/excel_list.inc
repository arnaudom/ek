<?php
use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;


// Create new PHPExcel object
$objPHPExcel = new PHPExcel();

//formatting
$HalignCenter = array(
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
			),
);

$HalignLeft = array(
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
			),
);
$HalignRight = array(
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
			),
);
$ValignTop = array(
			'alignment' => array(
				'vertical' => PHPExcel_Style_Alignment::VERTICAL_TOP,
			),
);
$Fontgrey = array(
    'font'  => array(
        'color' => array('rgb' => '6a6a6a'),
        'size'  => 10,
));

// Set properties
$objPHPExcel->getProperties()->setCreator("ek")
                                ->setLastModifiedBy("")
                                ->setTitle("Projects list")
                                ->setSubject("Projects list")
                                ->setDescription("Projects list for Excel generated using PHP classes.")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");


$objPHPExcel->getActiveSheet()->setTitle('Projects list');
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(50);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(50);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(50);
$objPHPExcel->getActiveSheet()->setBreak( 'L1' , PHPExcel_Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
// title
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(16);
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
$text = (string)t("Projects");

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $text );

// Data
$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A5', (string)t('No'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B5', (string)t('Ref.'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C5', (string)t('Name'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D5', (string)t('Status'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E5', (string)t('Offer date'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('F5', (string)t('Deadline'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('G5', (string)t('Start'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('H5', (string)t('Completion'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('I5', (string)t('Value'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('J5', (string)t('Description'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('K5', (string)t('Comments'));

$objPHPExcel->getActiveSheet()->getStyle('A5:K5')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A5:K5')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle('A5:K5')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A5:K5')->getFill()->getStartColor()->setRGB('A0A0A0');
$i=5;

 WHILE ($r = $data->fetchObject()) {
 $i++;
 if ($i%2) { $class="e6e6fa";} else {$class='';}
 
  
$objPHPExcel->getActiveSheet()->getStyle("A$i:L$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:L$i")->getFont()->setSize(10);
if(!$class=='') {
    $objPHPExcel->getActiveSheet()->getStyle("A$i:L$i")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle("A$i:L$i")->getFill()->getStartColor()->setRGB($class);
}
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(10);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $i);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $r->pcode);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $r->pname);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", $r->status);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $r->submission);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$i", $r->deadline);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$i", $r->start_date);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$i", $r->completion);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$i", $r->project_amount . ' ' . $r->currency);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$i", $r->project_description);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$i", $r->project_comment);
$objPHPExcel->getActiveSheet()->getStyle("J$i:K$i")->getAlignment()->setWrapText(true); 
$objPHPExcel->getActiveSheet()->getStyle("A$i:K$i")->applyFromArray($ValignTop);
$objPHPExcel->getActiveSheet()->getRowDimension($i)->setRowHeight(50);
}
 $i++;


// Set header and footer. When no different headers for odd/even are used, odd header is assumed.;
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddHeader('&L&BProjects &RPrinted on &D');
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddFooter('&L&B' . $objPHPExcel->getProperties()->getTitle() . '&RPage &P of &N');


// Set page orientation and size
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);

$fileName = "Projects_" . date('Y-m-d');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');    
        exit;


?>
