<?php
use Drupal\Core\Database\Database;
use Drupal\ek_finance\Journal;
use Drupal\ek_finance\FinanceSettings;

        $settings = new FinanceSettings(); 
        $baseCurrency = $settings->get('baseCurrency'); 
        $folders = array('general', 'expense', 'receipt', 'payroll', 'invoice', 'purchase');
        
        $journal = new Journal();
        $sheet = 0;
        
     
     
     
// Create new PHPExcel object
$objPHPExcel = new PHPExcel(); 
$objPHPExcel->setActiveSheetIndex(0);
$objPHPExcel->getProperties()->setCreator("Ek")
							 ->setLastModifiedBy('')
							 ->setTitle("Journal")
							 ->setSubject("computer generated")
							 ->setDescription("Journal extraction by period")
							 ->setKeywords("office 2007 openxml php")
							 ->setCategory("file");

$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

$TopBorderThin = array(
	'borders' => array(
		'top' => array(
			'style' => PHPExcel_Style_Border::BORDER_THIN,
			'color' => array('argb' => 'FF000000'),
		),
	),
);

$FontBoldCenter = array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
			),
		);

foreach($folders as $folder) {

// title
$objPHPExcel->setActiveSheetIndex($sheet)->setCellValue('A1', "Journal from " . $param['date1'] . " to " . $param['date2'] );
          
// subtitle
$company = Database::getConnection('external_db', 'external_db')
        ->query('SELECT name from {ek_company} WHERE id=:id', array(':id' => $param['company']))
        ->fetchField();
$objPHPExcel->setActiveSheetIndex($sheet)->setCellValue('A2', $company);
$objPHPExcel->setActiveSheetIndex($sheet)->setCellValue('D2', ucfirst($folder) );
            
// format titles
$objPHPExcel->getActiveSheet($sheet)->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet($sheet)->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet($sheet)->getStyle('A1')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet($sheet)->getStyle('A2:D2')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet($sheet)->getStyle('A2:D2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet($sheet)->getStyle('A2:D2')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet($sheet)->getStyle('D2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT); 

// Add rows
$objPHPExcel->setActiveSheetIndex($sheet)
            ->setCellValue('A3', 'ENTRY')           
            ->setCellValue('B3', 'ACCOUNT')           
            ->setCellValue('C3', 'DEBIT') 
            ->setCellValue('D3', 'CREDIT') ;
$objPHPExcel->getActiveSheet($sheet)->getColumnDimension('A')->setWidth(15);
$objPHPExcel->getActiveSheet($sheet)->getColumnDimension('B')->setWidth(50);
$objPHPExcel->getActiveSheet($sheet)->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet($sheet)->getColumnDimension('D')->setWidth(20);
$objPHPExcel->getActiveSheet($sheet)->setBreak( 'D1' , PHPExcel_Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet($sheet)->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet($sheet)->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT); 
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->getFill()->getStartColor()->setARGB('FFFFFF');
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->applyFromArray($TopBorderThin);     
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->applyFromArray($FontBoldCenter); 
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet($sheet)->getStyle('A3:D3')->getFill()->getStartColor()->setARGB('e6e6fa');

     
        
  $data = array();

  $data = $journal->display( array (
    'date1' => $param['date1'],
    'date2' => $param['date2'],
    'company' => $param['company'],
    'edit'=> 0,
    'source'=> $folder,
    'format' => 'excel'
    )
  );
        


  $objPHPExcel->getActiveSheet($sheet)->setTitle($folder);


$l = 5; // line index


    // insert journal entries
    foreach($data as $key => $row) 
    {

$c = 0;
      $cell=$columns[$c].$l;
      $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, $row['reference'] . ' - ' . $row['reference_detail']);
      $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getFont()->setBold(true);                
      $l+=1;
      $cell=$columns[$c].$l;
      $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, $row['date'] );
      $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getFont()->setBold(true); 

                       
      $l+=1;      
          foreach($row['transactions'] as $t => $line)                 
          {
$c = 0;                                  
                    $cell=$columns[$c].$l;
                    $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, $line['id'] . " - " . $line['count']);                    
                    $c+=1;
                    $cell=$columns[$c].$l;
                    if ($line['exchange'] == 1) { 
                        $ex= (string) t('exchange') ;} else { 
                          $ex = '';} 
                                       
                    $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, $line['aname'] . ' ' . $ex);

                    if ($line['type'] == 'debit') {
                    $c+=1;
                    $cell=$columns[$c].$l;              
                      $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, round($line['value'],2) );
                      $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1); 
                    
                    } else  {
                    $c+=2;
                    $cell=$columns[$c].$l;               
                      $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, round($line['value'],2));
                      $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    } 
                    $l+=1;
          } //for each transactions
               
               //total               
              if($baseCurrency <> $row['currency']) {
                  
                  $c=0;
                  $cell=$columns[$c].$l;            
                  $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, (string) t('Total') . ' ' . $row['currency']);
                  $c+=2;
                  $cell=$columns[$c].$l;
                  $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, round($row['total_debit'],2)); 
                  $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                  $c+=1;
                  $cell=$columns[$c].$l; 
                  $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, round($row['total_credit'],2) ); 
                  $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                  $r ="A".$l.":D".$l;
                  $objPHPExcel->getActiveSheet($sheet)->getStyle($r)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
                  $objPHPExcel->getActiveSheet($sheet)->getStyle($r)->getFill()->getStartColor()->setARGB('e6e6fa');   
                  $objPHPExcel->getActiveSheet()->getStyle($r)->applyFromArray($TopBorderThin);  
                  $objPHPExcel->getActiveSheet($sheet)->getStyle($r)->getFont()->setBold(true);
                  $l+=1;      
                } 
               $c=0;
               
               $cell=$columns[$c].$l;
               $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, (string) t('Total') . ' ' . $baseCurrency);
               $c+=2;
               $cell=$columns[$c].$l;
               $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, round($row['total_debit']+$row['total_debit_base'],2) );
               $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1); 
               $c+=1;
               $cell=$columns[$c].$l; 
               $objPHPExcel->setActiveSheetIndex($sheet)->setCellValue($cell, round($row['total_credit']+$row['total_credit_base'],2) ); 
               $objPHPExcel->getActiveSheet($sheet)->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
              
               $r="A".$l.":D".$l;
               $objPHPExcel->getActiveSheet($sheet)->getStyle($r)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
               $objPHPExcel->getActiveSheet($sheet)->getStyle($r)->getFill()->getStartColor()->setARGB('e6e6fa');
               $objPHPExcel->getActiveSheet()->getStyle($r)->applyFromArray($TopBorderThin);
               $objPHPExcel->getActiveSheet($sheet)->getStyle($r)->getFont()->setBold(true);
                

$l+=2; 
    } //foreach data

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex($sheet)->setCellValue("A$l", $date); 

$sheet++; 
   if($sheet > 0) {
  // Create a new worksheet per folder
  $objPHPExcel->createSheet();
  }

  $objPHPExcel->setActiveSheetIndex($sheet);

} //folders
   

$objPHPExcel->setActiveSheetIndex(0);

$fileName = 'journal_' . $param['date1'] . '_' . $param['date2'] . '.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        //header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');    
        exit;

  
