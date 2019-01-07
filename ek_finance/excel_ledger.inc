<?php
use Drupal\Core\Database\Database;
use Drupal\ek_finance\Journal;

     $items['data'] = array();
     $journal = new Journal();
     $param = unserialize($param);
     $items['data'] = $journal->ledger( $param );
     
     
     
// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Ledger")
                                ->setSubject("computer generated")
                                ->setDescription("Ledger extraction by period and accounts")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('Ledger local currency');



$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

// title
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', "Ledger from " . $param['date1'] . " to " . $param['date2'] );
          
// subtitle
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A2', $company);
            
// format titles
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle('A2')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A2')->getFont()->setBold(true);

// Add rows
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A3', 'DATE')
            ->setCellValue('B3', 'ENTRY')           
            ->setCellValue('C3', 'COMMENTS')           
            ->setCellValue('D3', '') 
            ->setCellValue('E3', 'DEBIT') 
            ->setCellValue('F3', 'CREDIT') ;
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(50);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
$objPHPExcel->getActiveSheet()->setBreak( 'G1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT); 

$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->getFill()->getStartColor()->setARGB('FFFFFF');

$TopBorderThin = array(
	'borders' => array(
		'top' => array(
			'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
			'color' => array('argb' => 'FF000000'),
		),
	),
);

$FontBoldCenter = array(
			'font'    => array(
				'bold'      => true
			),
			'alignment' => array(
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
			),
		);


$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->applyFromArray($TopBorderThin);     
$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->applyFromArray($FontBoldCenter); 
$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:F3')->getFill()->getStartColor()->setARGB('e6e6fa');


     
// insert from line 4
$l=4;


      foreach ($items['data']['ledger']['accounts'] as $keys => $lines) {

        $c=0; // column index
        $l=$l+1; // line index
          
          //table header
          $cell=$columns[$c].$l;
          $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $lines['line']['total']['aid'] . '-' . $lines['line']['total']['aname'] );
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setName('Arial');
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);

          $l++;
          $cell=$columns[$c].$l;
          $text = (string) t('opening') . ': ' . round($lines['line']['total']['balance_open'],2) 
                  . " , (". round($lines['line']['total']['balance_open_base'],2) . " " 
                  . $items['data']['baseCurrency'] . ")";
          $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell,$text);
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setSize(10);
      
          foreach($lines['line'] as $key => $row ) {
          
            if($key <> 'total') {
              if (is_array($row['comment'])) {
                    $row['comment'] = $row['comment']['#markup'];
                    //remove the hyperlink tag for excel
                    preg_match("'>(.*?)</a>'si", $row['comment'], $match);
                    $row['comment'] = $match[1];
                } 
              $c=0; 
              $l++;
              $cell=$columns[$c].$l;                
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['date']);
              $c++;
              $cell=$columns[$c].$l; 
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['id'] . " - " . $row['count']);               
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['reference'] . ' ' . $row['comment']);                 
              $c++;
              $cell=$columns[$c].$l;
                          
                  if ($row['exchange']==1) {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('exchange') );              
                    $objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->getFont()->setSize(9);               
                  } else { 
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['currency']);                
                              }
              $c++;
              $cell=$columns[$c].$l;  
                           
                   //input left column
                  if ($row['type'] == 'debit') {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['value'],2) ); 
                    $c++;
                    $cell=$columns[$c].$l; 
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, '');
                                  
                      if($row['exchange']==0) {
                      } else {
                        $exchange_view=1;
                      }
                  }
                         
                   //input right column 
                   if ($row['type'] == 'credit') {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell , '');
                    $c++;
                    $cell=$columns[$c].$l;  
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['value'],2) );             

                      if($row['exchange']==0) {
                      } else 
                      {
                        $exchange_view=1;
                      }
                    } 
          
            } //IF
              else {
              //Total
                $l++;
                $c=0;
                $cell=$columns[$c].$l;
                $objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->applyFromArray($TopBorderThin);
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Total (local currency)') );
                $c=4;
                $cell=$columns[$c].$l;            
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['sum_debit'],2));              
                $c=5;
                $cell=$columns[$c].$l;                               
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['sum_credit'],2));              
                                
                if ($exchange_view==1) {
                  $l++ ;
                  $c=0;
                  $cell=$columns[$c].$l;
                  
                  $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, t('Total') . ' ' . $items['data']['baseCurrency']);
                  $c=4;
                  $cell=$columns[$c].$l;            
                  $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['sum_debit']+$row['sum_debit_exchange'],2));              
                  $c=5;
                  $cell=$columns[$c].$l;                               
                  $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell,round($row['sum_credit']+$row['sum_credit_exchange'],2));
                }

                $l++;
                $c=0;


                    if ($row['account'] == '-') {
                    $cell=$columns[$c].$l;
                    $objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->applyFromArray($TopBorderThin);
                    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                              
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Closing (local currency)') );
                    $c=4;
                    $cell=$columns[$c].$l;            
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['closing'],2));              
                             
                      if ($exchange_view==1) {
                             
                        $l++;
                        $c=0;
                        $cell=$columns[$c].$l; 
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);           
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Closing') . ' ' . $items['data']['baseCurrency']);
                        $c=4;
                        $cell=$columns[$c].$l;            
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['closing_exchange'],2) );              
     
                        }              
                    } else {
                    $cell=$columns[$c].$l;
                    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                                 
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell,  (string) t('Closing (local currency)') );
                    $c=5;
                    $cell=$columns[$c].$l;            
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($row['closing'],2) );              
                             
                      if ($exchange_view==1) {
                        $l++;
                        $c=0;
                        $cell=$columns[$c].$l; 
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);        
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Closing') . ' ' . $items['data']['baseCurrency'] );
                        $c=5;
                        $cell=$columns[$c].$l;            
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell,round($row['closing_exchange'],2) );              
                             
                      }

                   }
                
                $l+=2;
              
              
              }

          }

      
      }



$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'ledger_' . $param['date1'] . '_' . $param['date2'] . '.xlsx' ;      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;

  
     // end generate report





        
            
            
            
            
            
            
            
            
            
            