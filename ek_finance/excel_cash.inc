<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

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

// Set properties
$objPHPExcel->getProperties()->setCreator("ek")
                                ->setLastModifiedBy("")
                                ->setTitle("Cash balance")
                                ->setSubject("Cash balance")
                                ->setDescription("Cash balance for Excel generated using PHP classes.")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");


$objPHPExcel->getActiveSheet()->setTitle('Cash balance');
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(70);
$objPHPExcel->getActiveSheet()->setBreak( 'G1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// title
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
$text = (string)t("Cash transactions") . ' ' 
            . $data['filter']['username'] . ' ' 
            . (string)t('from'). ' ' .  $data['from']. ' ' 
            . (string)t('to'). ' ' .  $data['to'];

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $text );
$objPHPExcel->getActiveSheet()->getStyle('A3:F4')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A3:F4')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A3:F4')->getFont()->setBold(true);

$objPHPExcel->getActiveSheet()->getStyle("A3:F4")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A3:F4")->getFill()->getStartColor()->setRGB('acacac');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A3', (string)t('Account') . ' ' . $parameters['aid']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A4', (string)t('Date'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B4', (string)t('Ref.'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C3', (string)t('Local currency'));
$objPHPExcel->getActiveSheet()->mergeCells('C3:D3');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E3', $parameters['baseCurrency']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('F3', (string)t('Comment'));

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C4', (string)t('Debit'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D4', (string)t('Credit'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E4', '');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('F4', '');
// Data
$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A5', (string)t('sum values before'). ' ' .  $data['from']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B5', '');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C5', $data['total']['debit_open']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D5', $data['total']['credit_open']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E5', '');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('F5', '');
$objPHPExcel->getActiveSheet()->getStyle("C5:D5")->getNumberFormat()
             ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$i=5;
$total_debit_amount = 0;
$total_debit_base = 0;
$total_credit_amount = 0;
$total_credit_base = 0;


 foreach ($data['data'] as $line) {
 $i++;
 if ($i%2) { $class="e6e6fa";} else {$class='';}
 if ($line['op']=='debit') 
  { 
    $amount = round(-$line['amount'],$parameters['rounding']); 
    $baseamount = round(-$line['basecurrency'],$parameters['rounding']);
    $total_debit_amount+=$line['amount'];
    $total_debit_base+=$line['basecurrency'];
    
  } else {
    $amount = round($line['amount'],$parameters['rounding']); 
    $baseamount = round($line['basecurrency'],$parameters['rounding']);
    $total_credit_amount+=$line['amount'];
    $total_credit_base+=$line['basecurrency'];  
  }
  $t1 = round(($total_credit_amount - $total_debit_amount),$parameters['rounding']);
  $t2 = round(($total_credit_base - $total_debit_base),$parameters['rounding']);
  
  //formating
  $objConditional1 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
  $objConditional1->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
  $objConditional1->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHAN);
  $objConditional1->addCondition('0');
  $objConditional1->getStyle()->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
  $objConditional1->getStyle()->getNumberFormat()
             ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

  if ($i%2) { 
      $objConditional1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $objConditional1->getStyle()->getFill()->getEndColor()->setRGB('e6e6fa');
  } 
  
  $objConditional2 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
  $objConditional2->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
  $objConditional2->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHANOREQUAL);
  $objConditional2->addCondition('0');
  $objConditional2->getStyle()->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKGREEN);
  $objConditional2->getStyle()->getNumberFormat()
             ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

  if ($i%2) { 
      $objConditional2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $objConditional2->getStyle()->getFill()->getEndColor()->setRGB('e6e6fa');
      
  }

  $conditionalStyles = $objPHPExcel->getActiveSheet()->getStyle("C$i:E$i")->getConditionalStyles();
  array_push($conditionalStyles, $objConditional1);
  array_push($conditionalStyles, $objConditional2);
  $objPHPExcel->getActiveSheet()->getStyle("C$i:E$i")->setConditionalStyles($conditionalStyles);

  
$objPHPExcel->getActiveSheet()->getStyle("A$i:F$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:F$i")->getFont()->setSize(10);
if(!$class=='') {
    $objPHPExcel->getActiveSheet()->getStyle("A$i:F$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle("A$i:F$i")->getFill()->getStartColor()->setRGB($class);
}
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $line['date']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $line['id'] . ' ' . $line['type']);
if ($line['op']=='debit') {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $amount);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i",'');
} else {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", '');
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i",$amount);    
}
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $baseamount);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$i", "$line[comment] ");
$objPHPExcel->getActiveSheet()->getStyle("F$i")->applyFromArray($Fontgrey);


}
 $i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:E$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:E$i")->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle("A$i:E$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (string)t('Period total'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $data['total']['debit']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", $data['total']['credit']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $data['total']['base']);
$objPHPExcel->getActiveSheet()->getStyle("C$i:E$i")->getNumberFormat()
             ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);


 $i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:E$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:E$i")->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle("A$i:E$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (string)t('Total') . ' ' . $data['total']['year']);

if ($data['total']['balance'] >= 0) {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", $data['total']['balance']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $data['total']['balance_base']);
    
} else {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $data['total']['balance']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $data['total']['balance_base']);    
    
}

$objPHPExcel->getActiveSheet()->getStyle("C$i:E$i")->getNumberFormat()
             ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

// Set header and footer. When no different headers for odd/even are used, odd header is assumed.;
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddHeader('&L&Bcash balance&RPrinted on &D');
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddFooter('&L&B' . $objPHPExcel->getProperties()->getTitle() . '&RPage &P of &N');


// Set page orientation and size
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$fileName = "cash_balance_" . $data['filter']['username'] . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;



