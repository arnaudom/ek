<?php
use Drupal\Core\Database\Database;


// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Quotations list")
                                ->setSubject("computer generated")
                                ->setDescription("Quotations list filtered")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle((string)t('List'));


$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'ID') 
            ->setCellValue('B3', (string) t('Serial'))         
            ->setCellValue('C3', (string) t('Client'))                     
            ->setCellValue('D3', (string) t('Header'))
            ->setCellValue('E3', (string) t('Allocated'))
            ->setCellValue('F3', (string) t('Status'))
            ->setCellValue('G3', (string) t('Amount'))
            ->setCellValue('H3', (string) t('Currency'))
            ->setCellValue('I3', (string) t('Tax'))
            ->setCellValue('J3', (string) t("Value"))
            ->setCellValue('K3', (string) t("Incoterm"))
            ->setCellValue('L3', (string) t("Value"))
            ->setCellValue('M3', (string) t('Project'))
            ->setCellValue('N3', (string) t('Comment'));                                                                                     
            
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);  
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(30);
$objPHPExcel->getActiveSheet()->getStyle('A3:N3')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:N3')
        ->getFill()->getStartColor()->setARGB('FFC866');
$objPHPExcel->getActiveSheet()->getStyle('A3:N3')
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

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string)t('Quotations list')) ;
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
        $row="A".$l.":N".$l;
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle("$row")
                ->getFill()->getStartColor()->setARGB('e6e6fa');
        } 

    $total += $data['amount'];

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $data['id']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $data['serial']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $data['name']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $companies[$data['head']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $companies[$data['allocation']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $status[$data['status']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $data['amount']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $data['currency']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", explode('|', $data['tax'])[0]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", round(explode('|', $data['tax'])[1]*$data['amount']/100,2) );
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", explode('|', $data['incoterm'])[0]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", round(explode('|', $data['incoterm'])[1]*$data['amount']/100,2));
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $data['pcode']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $data['comment']);



}
$l+=1;
if($options['currency'] != '%'){
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", (string)t('TOTAL') );
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $total);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $options['currency']);
}
$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'quotations_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
       exit;




    
            
            
            
            
            
            
            
            
            
            