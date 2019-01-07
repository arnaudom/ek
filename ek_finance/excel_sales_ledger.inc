<?php
use Drupal\Core\Database\Database;
use Drupal\ek_finance\Journal;

    $items['data'] = array();
    $journal = new Journal();
    
  
            if ($p['source1'] == 'purchase') {
                $entity = 'purchase';
                $source2 = 'payment';
                $source3 = 'purchase dn';
                $book = 2;
                if ($p['client'] == '%') {
                    $clients = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT DISTINCT ab.id FROM {ek_address_book} ab "
                                    . "INNER JOIN {ek_sales_purchase} p ON p.client = ab.id order by name")
                            ->fetchCol();
                } else {
                    $clients = [0 => $p['client']];
                }
            } else {
                $entity = 'invoice';
                $source2 = 'receipt';
                $source3 = 'invoice cn';
                $book = 1;
                if ($p['client'] == '%') {
                    $clients = Database::getConnection('external_db', 'external_db')
                            ->query("SELECT DISTINCT ab.id FROM {ek_address_book} ab "
                                    . "INNER JOIN {ek_sales_invoice} i ON i.client = ab.id order by name")
                            ->fetchCol();
                } else {
                    $clients = [0 => $p['client']];
                }
            }
            $items['book'] = \Drupal\ek_address_book\AddressBookData::addresslist($book);
            $items['data'] = [];
            
            foreach ($clients as $clientId) {
                //select the documents ids related to the selected client
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_' .$entity, 's');

                $result = $query->fields('s', array('id'))
                        ->condition('s.client', $clientId, '=')
                        ->condition('head', $p['coid'], '=')
                        ->execute();

                $ids = $result->fetchCol();
                $row = [];
               
                $row['client_id'] = $clientId;
                $row['client_name'] = $items['book'][$clientId];

                if (!empty($ids)) {

                    $param = [
                        'coid' => $p['coid'],
                        'references' => $ids,
                        'source1' => $entity,
                        'source2' => $source2,
                        'source3' => $source3,
                        'date1' => $p['date1'],
                        'date2' => $p['date2'],
                    ];


                    $row['journal'] = $journal->salesledger($param);
                 
                    $items['data'][] = $row;
                
                }
                
                
            }


// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Sales ledger")
                                ->setSubject("computer generated")
                                ->setDescription("Ledger extraction by period and sales account")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('Ledger');
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$objPHPExcel->getActiveSheet()->setBreak( 'G1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );

$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
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
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(45);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);

// title
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', "Sales ledger from " . $param['date1'] . " to " . $param['date2'] );
          
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
$l=2;     
foreach($items['data'] as $rows) {
   
$lines = $rows['journal']['ledger'][0]['line']; 
   
$l+=2;

$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue("A$l", (string)t('DATE') )
            ->setCellValue("B$l", (string)t('ENTRY') )           
            ->setCellValue("C$l", (string)t('COMMENTS') )           
            ->setCellValue("D$l", '') 
            ->setCellValue("E$l", (string)t('DEBIT') ) 
            ->setCellValue("F$l", (string)t('CREDIT') ) ;



$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT); 
$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->getFill()->getStartColor()->setARGB('FFFFFF');
$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->applyFromArray($TopBorderThin);     
$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->applyFromArray($FontBoldCenter); 
$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:F$l")->getFill()->getStartColor()->setARGB('e6e6fa');

$c = 0; // column index
$l++;       
          
          $cell = $columns[$c].$l;
          $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $rows['client_name'] );
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setName('Arial');
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setSize(13);
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);

          $l+=2;
          $cell = $columns[$c].$l;
          $text = (string) t('opening') . ': ' . round($lines['total']['balance_open'],2) 
                  . " , (". round($lines['total']['balance_open_base'],2) . " " 
                  . $rows['journal']['baseCurrency'] . ")";
          $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell,$text);
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
          $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setSize(10);
          $l++;
          foreach($lines as  $key => $row ) {
     
            if($key != 'total') {
              $c=0; 
              $l++;
              $cell=$columns[$c].$l;                
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['date']);
              $c++;
              $cell=$columns[$c].$l; 
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['id'] . " - " . $row['count']);               
              $c++;
              $cell=$columns[$c].$l;
              $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $row['aid'] . ' ' . $row['aname']);                 
              $c++;
              $cell=$columns[$c].$l;
                          
                  if ($row['exchange'] == 1) {
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
                                  
                      if($row['exchange'] == 0) {
                      } else {
                        $exchange_view = 1;
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
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Total') );
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
                              
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Closing') );
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
                                 
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell,  (string) t('Closing') );
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

          }//ledger rows

      
}//rows in data      



$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);
$name = substr($param['client'], 0, 8);
$name = str_replace('', '_', $name);

$fileName = 'sales_ledger_' . $name . '_' . $param['date1'] . '_' . $param['date2'] . '.xlsx' ;      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;

  
     // end generate report





        
            
            
            
            
            
            
            
            
            
            