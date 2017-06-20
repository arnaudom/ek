<?php
use Drupal\Core\Database\Database;


// Create new PHPExcel object
$objPHPExcel = new PHPExcel(); 

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Invoices list")
                                ->setSubject("computer generated")
                                ->setDescription("Invoices list filtered")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle((string)t('List'));


$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'ID') 
            ->setCellValue('B3', (string) t('Serial'))    
            ->setCellValue('C3', (string) t('Delivery order'))                     
            ->setCellValue('D3', (string) t('Header'))
            ->setCellValue('E3', (string) t('Allocated'))
            ->setCellValue('F3', (string) t('Status'))
            ->setCellValue('G3', (string) t('Amount'))
            ->setCellValue('H3', (string) t('Currency'))
            ->setCellValue('I3', (string) t('Tax value'))
            ->setCellValue('J3', (string) t("Amount in base currency") . " " . $baseCurrency)
            ->setCellValue('K3', (string) t('Amount paid'))
            ->setCellValue('L3', (string) t('Pay date'))
            ->setCellValue('M3', (string) t('Project'))
            ->setCellValue('N3', (string) t('Client'))
            ->setCellValue('O3', (string) t('Comment'));                                                                                     
            
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(18);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(20);   
$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(15);
$objPHPExcel->getActiveSheet()->getStyle('A3:O3')->getFill()
        ->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:O3')
        ->getFill()->getStartColor()->setARGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle('A3:O3')
        ->applyFromArray(
		array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
			),
			'borders' => array(
				'top'     => array(
 					'style' => PHPExcel_Style_Border::BORDER_THIN
 				)
			)
		)
);             
   
   
$l=3; 

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string)t('Invoices list')) ;
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

    $l=$l+1; // line index

    if ($l & 1) {
        $row="A".$l.":O".$l;
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->getStartColor()->setARGB('e6e6fa');
        } 

            
    $tax = round($data['taxvalue']*$data['amount']/100, 2);
    $total += $data['amountbase'];

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $data['id']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $data['serial']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $data['do_no']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $data['name']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $companies[$data['allocation']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $status[$data['status']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $data['amount']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $data['currency']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $tax);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $data['amountbase']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $data['amountreceived']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $data['pay_date']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $data['pcode']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $data['name']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $data['comment']);



}
$l+=1;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", (string)t('TOTAL') );
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $total);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $baseCurrency);

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'invoices_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        //header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');    
       exit;

  
     // end generate report




    
            
            
            
            
            
            
            
            
            
            