<?php
use Drupal\Core\Database\Database;


// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

$objPHPExcel->getProperties()->setCreator("Ek")
                            ->setLastModifiedBy('')
                            ->setTitle("Companies list")
                            ->setSubject("computer generated")
                            ->setDescription("Full data companies list")
                            ->setKeywords("office 2007 openxml php")
                            ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('List');

$query ="SELECT * from {ek_company} order by id";
$result = Database::getConnection('external_db', 'external_db')->query($query);

$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'ID') 
            ->setCellValue('B3', 'active')    
            ->setCellValue('C3', 'name')                     
            ->setCellValue('D3', 'short name')           
            ->setCellValue('E3', 'reg.number')           
            ->setCellValue('F3', 'address line 1') 
            ->setCellValue('G3', 'address line 2') 
            ->setCellValue('H3', 'city') 
            ->setCellValue('I3', 'postcode') 
            ->setCellValue('J3', "country") 
            ->setCellValue('K3', 'telephone') 
            ->setCellValue('L3', 'fax') 
            ->setCellValue('M3', 'email') 
            ->setCellValue('N3', 'contact') 
            ->setCellValue('O3', 'mobile') 
            ->setCellValue('P3', 'other address') 
            ->setCellValue('Q3', 'other address line 2') 
            ->setCellValue('R3', 'other city') 
            ->setCellValue('S3', 'other postcode') 
            ->setCellValue('T3', 'other country')             
            ->setCellValue('U3', 'other telephone')   
            ->setCellValue('V3', 'other fax') 
            ->setCellValue('W3', 'accounts year') 
            ->setCellValue('X3', 'accounts month') 
            ->setCellValue('Y3', 'income tax No') 
            ->setCellValue('Z3', 'pension No') 
            ->setCellValue('AA3', 'social No')
            ->setCellValue('AB3', 'adhoc No');                                                                                     
            
            
$objPHPExcel->getActiveSheet()->getStyle('A3:AB3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:AB3')->getFill()->getStartColor()->setARGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle('A3:AB3')->applyFromArray(
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
 WHILE ($data=$result->fetchAssoc()) {
$l=$l+1; // line index
if ($l & 1) {
$row="A".$l.":AB".$l;
$objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
} 
 
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $data['id']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $data['active']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $data['name']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $data['short']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $data['reg_number']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $data['address1']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $data['address2']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $data['city']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $data['postcode']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $data['country']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $data['telephone']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $data['fax']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $data['email']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $data['contact']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $data['mobile']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $data['address3']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("Q$l", $data['address4']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("R$l", $data['city2']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("S$l", $data['postcode2']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("T$l", $data['country2']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("U$l", $data['telephone2']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("V$l", $data['fax2']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("W$l", $data['accounts_year']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("X$l", $data['accounts_month']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("Y$l", $data['itax_no']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("Z$l", $data['pension_no']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("AA$l", $data['social_no']);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("AB$l", $data['adoc_no']);
;
}   
$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);


$fileName = 'companies_list.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;

  
     // end generate report





     
            
            
            
            
            
            
            
            
            
            