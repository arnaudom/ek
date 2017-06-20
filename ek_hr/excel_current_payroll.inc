<?php
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;


// Create new PHPExcel object
$objPHPExcel = new PHPExcel(); 

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Current payroll list")
                                ->setSubject("computer generated")
                                ->setDescription("Current payroll list")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('List');

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(5);

$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('I5', (string)t('Employee'))
            ->setCellValue('O5', (string)t('Employer')) ;
$objPHPExcel->getActiveSheet()->mergeCells('I5:N5');
$objPHPExcel->getActiveSheet()->mergeCells('O5:Q5');
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A6', (string)t('ID')) 
            ->setCellValue('B6', (string)t('Name')) 
            ->setCellValue('C6', (string)t('Month'))  
            ->setCellValue('D6', (string)t('Status'))                    
            ->setCellValue('E6', (string)t('Joined'))          
            ->setCellValue('F6', (string)t('Bank'))           
            ->setCellValue('G6', (string)t('Gross'))
            ->setCellValue('H6', (string)t('Deductions')) 
            ->setCellValue('I6', $fund['fund1'])
            ->setCellValue('J6', $fund['fund2'])
            ->setCellValue('K6', $fund['fund3'])
            ->setCellValue('L6', (string)t("Tax"))            
            ->setCellValue('M6', (string)t("Net")) 
            ->setCellValue('N6', (string)t('Currency')) 
            ->setCellValue('O6', $fund['fund1'])
            ->setCellValue('P6', $fund['fund2'])
            ->setCellValue('Q6', $fund['fund3'])
        ;
            
            
$objPHPExcel->getActiveSheet()->getStyle('A5:Q5')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A5:H5')->getFill()->getStartColor()->setARGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle('I5:N5')->getFill()->getStartColor()->setARGB('22b2ff');
$objPHPExcel->getActiveSheet()->getStyle('O5:Q5')->getFill()->getStartColor()->setARGB('88b5ee');
$objPHPExcel->getActiveSheet()->getStyle('A6:Q6')->getFill()->getStartColor()->setARGB('6a6a6a');
$objPHPExcel->getActiveSheet()->getStyle('A5:Q6')->applyFromArray(
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

/*
* DATA
*/

  $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string) t('filter')) ;
  $objPHPExcel->setActiveSheetIndex(0)->setCellValue('C1', (string) t('Company') . ":" );
  $query = "SELECT name from {ek_company} WHERE id=:id";
  $company_name = Database::getConnection('external_db', 'external_db')
          ->query($query, array( ':id' => $param['coid']) )
          ->fetchField();
  $objPHPExcel->setActiveSheetIndex(0)->setCellValue('D1', $company_name) ;

   
$l=6; 
 WHILE ($r = $data->fetchObject()) {

    $l=$l+1; // line index
    if ($l & 1) {
    $row="A".$l.":Q".$l;
    $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
    } 

    if (!$r->bank_account_status == 'own') {

    $bank = $r->bank . "(". $r->thirdp . ") - " . $r->bank_account;

    } else {
    $bank = $r->bank . " - " . $r->bank_account;
    }


    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $r->id);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $r->name);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $r->month);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $r->active);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $r->start);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $bank );
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("G$l",$r->gross,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("H$l",$r->deduction,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("I$l",$r->epf_yee,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("J$l",$r->socso_yee,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("K$l",$r->with_yee,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("L$l",$r->incometax,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("M$l",$r->nett,PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $r->currency);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", '');

    
    
}
$l++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $total);
   
$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'current_payroll.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');   
           
        exit();

  
     // end generate report




     
            
            
            
            
            
            
            
            
            
            