<?php
use Drupal\Core\Database\Database;


// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Purchases list")
                                ->setSubject("computer generated")
                                ->setDescription("Purchases list filtered")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle((string)t('List'));


$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'ID') 
            ->setCellValue('B3', (string) t('Serial'))    
            ->setCellValue('C3', (string) t('Due'))            
            ->setCellValue('D3', (string) t('Client'))                     
            ->setCellValue('E3', (string) t('Header'))
            ->setCellValue('F3', (string) t('Allocated'))
            ->setCellValue('G3', (string) t('Status'))
            ->setCellValue('H3', (string) t('Amount'))
            ->setCellValue('I3', (string) t('Currency'))
            ->setCellValue('J3', (string) t('Ex. rate'))
            ->setCellValue('K3', (string) t('Tax value'))
            ->setCellValue('L3', (string) t("Amount in base currency") . " " . $baseCurrency)
            ->setCellValue('M3', (string) t('Amount paid'))
            ->setCellValue('N3', (string) t('Payment ex. rate'))
            ->setCellValue('O3', (string) t('Pay date'))
            ->setCellValue('P3', (string) t('Project'))
            ->setCellValue('Q3', (string) t('Comment'));                                                                                     
            
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);  
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(30);  
$objPHPExcel->getActiveSheet()->getStyle('A3:Q3')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:Q3')
        ->getFill()->getStartColor()->setARGB('FFC866');
$objPHPExcel->getActiveSheet()->getStyle('A3:Q3')
        ->applyFromArray(
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
   
   
$l=3; 

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string)t('Purchases list')) ;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', (string)t('Fom') . ':' . $options['from'] . ' ' . (string)t('to') . ':' . $options['to']) ;

$total = 0;

//store company data
$companies = Database::getConnection('external_db', 'external_db')
            ->query("SELECT id,name from {ek_company}")
            ->fetchAllKeyed();
//store a. book data
$abook = Database::getConnection('external_db', 'external_db')
            ->query("SELECT id,name from {ek_address_book}")
            ->fetchAllKeyed();

WHILE ($data = $result->fetchAssoc()) {
     
  
    $client_name = $abook[$data['client']];
    $due = date('Y-m-d', strtotime(date("Y-m-d", strtotime($data['date'])) . "+" . $data['due'] . "days"));

    $l=$l+1; // line index

    if ($l & 1) {
        $row="A".$l.":O".$l;
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->getStartColor()->setARGB('e6e6fa');
        } 

    if( $data['amountbase']) {
        $exrate = round($data['amount'] / $data['amountbase'] , 4);
    } else {
        $exrate = "";
    }            
    $tax = round($data['taxvalue']*$data['amount']/100, 2);
    $total += $data['amountbase'];

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $data['id']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $data['serial']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $due);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $data['name']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $companies[$data['head']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $companies[$data['allocation']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $status[$data['status']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $data['amount']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $data['currency']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $exrate);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $tax);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $data['amountbase']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $data['amountpaid']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $data['pay_rate']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $data['pdate']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $data['pcode']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("Q$l", $data['comment']);



}
$l+=1;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", (string)t('TOTAL') );
    
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $total);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $baseCurrency);

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'purchases_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
       exit;

  
     // end generate report




    
            
            
            
            
            
            
            
            
            
            