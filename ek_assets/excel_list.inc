<?php
use Drupal\Core\Database\Database;

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

$objPHPExcel->getProperties()->setCreator("Ek")
							 ->setLastModifiedBy('')
							 ->setTitle("Assets list")
							 ->setSubject("computer generated")
							 ->setDescription("Assets list filtered")
							 ->setKeywords("office 2007 openxml php")
							 ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('List');


$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'ID') 
            ->setCellValue('B3', 'name')    
            ->setCellValue('C3', 'brand')                     
            ->setCellValue('D3', 'reference')           
            ->setCellValue('E3', 'company')           
            ->setCellValue('F3', 'quantity') 
            ->setCellValue('G3', 'category') 
            ->setCellValue('H3', 'comment') 
            ->setCellValue('I3', 'currency') 
            ->setCellValue('J3', "value") 
            ->setCellValue('K3', 'purchase date') 
            ->setCellValue('L3', 'amortization terms') 
            ->setCellValue('M3', 'amortization status') 
            ->setCellValue('N3', 'Allocation ID') ;                                                                                     
            
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(50);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
$objPHPExcel->getActiveSheet()->getStyle('A3:P3')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:P3')
        ->getFill()->getStartColor()->setARGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle('A3:P3')
        ->applyFromArray(
		array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
			),
			'borders' => array(
				'top'     => array(
 					'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
 				)
			)
		)
);             
   
   
$l=3; 
$t = (STRING)t('Assets list');
$term = ['' => '-', 'Y' => $this->t('Years'), 'M' => $this->t('Months')];
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $t ) ;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', $company_name) ;


WHILE ($data = $result->fetchAssoc()) {
     
    $aname = $chartList[$data['coid']][$data['aid']];
    $l=$l+1; // line index

    if ($l & 1) {
        $row="A".$l.":N".$l;
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)
                ->getFill()->getStartColor()->setARGB('e6e6fa');
        } 

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $data['id']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $data['asset_name']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $data['asset_brand']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $data['asset_ref']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $company_name);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $data['unit']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $data['aid'] . ' ' . $aname);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $data['asset_comment']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $data['currency']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $data['asset_value']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $data['date_purchase']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $data['term'] . " " . $term[$data['term_unit']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $status[$data['amort_status']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $data['eid'] . ' ' . $data['name']);


}

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'assets_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;






    
            
            
            
            
            
            
            
            
            
            