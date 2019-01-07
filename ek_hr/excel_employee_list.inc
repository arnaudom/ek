<?php
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;


// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Employee list")
                                ->setSubject("computer generated")
                                ->setDescription("Employee list")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('List');

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(50);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(15);


$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', (string)t('ID')) 
            ->setCellValue('B3', (string)t('Name')) 
            ->setCellValue('C3', (string)t('Email'))  
            ->setCellValue('D3', (string)t('Address'))                    
            ->setCellValue('E3', (string)t('IC'))          
            ->setCellValue('F3', (string)t('Sex'))           
            ->setCellValue('G3', (string)t('Date of birth'))
            ->setCellValue('H3', (string)t('Status')) 
            ->setCellValue('I3', (string)t('Work location')) 
            ->setCellValue('J3', (string)t('Service')) 
            ->setCellValue('K3', (string)t('Start'))
            ->setCellValue('L3', (string)t("Resign"))            
            ->setCellValue('M3', (string)t("Salary")) 
            ->setCellValue('N3', '')
            ->setCellValue('O3', (string)t('Currency')) 
            ->setCellValue('P3', (string)t('Archive')) 
            ->setCellValue('Q3', (string)t('Active')) 
        ;
            
$objPHPExcel->getActiveSheet()->getStyle('A3:Q3')->getFill()->getStartColor()->setARGB('6a6a6a');
$objPHPExcel->getActiveSheet()->getStyle('A3:Q3')->applyFromArray(
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

/*
* DATA
*/


  $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B1', (string) t('Company') . ":" );
  $query = "SELECT name from {ek_company} WHERE id=:id";
  $company_name = Database::getConnection('external_db', 'external_db')
          ->query($query, array( ':id' => $param['coid']) )
          ->fetchField();
  $objPHPExcel->setActiveSheetIndex(0)->setCellValue('C1', $company_name) ;

   
$l=3; 
 WHILE ($r = $data->fetchObject()) {

    $l=$l+1; // line index
    if ($l & 1) {
    $row="A".$l.":Q".$l;
    $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
    } 

    if (!$r->bank_account_status == 'own') {

    $bank = $r->bank . "(". $r->thirdp . ") - " . $r->bank_account;

    } else {
    $bank = $r->bank . " - " . $r->bank_account;
    }


    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $r->id);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $r->name);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $r->email);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $r->address);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $r->ic_no);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $r->sex);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l",$r->birth);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l",$r->e_status);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l",$r->location);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l",$r->service);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l",$r->start);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l",$r->resign);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("M$l",$r->salary,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit("N$l", $r->th_salary,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $r->currency);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $r->archive);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("Q$l", $r->active);

    
    
}
$l++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $total);
   
$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'employees_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');   
           
        exit();


            
            