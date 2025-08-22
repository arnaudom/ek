<?php
use Drupal\Core\Database\Database;
use Drupal\ek_finance\Journal;


//Style

$TopBorderThin = array(
	'borders' => array(
		'top' => array(
			'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
			'color' => array('argb' => 'FF000000'),
		),
	),
);

$BottomBorderThin = array(
	'borders' => array(
		'bottom' => array(
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
$FontRed = array(
			'font'    => array(
				'color' => array(
                      'rgb' => 'FF6666'
                    ),
			),

		);

$columns=Array ('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

//$items['data'] = array();
$journal = new Journal();
$param = unserialize($param);
 
// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$objPHPExcel->getProperties()->setCreator("Ek")
                                ->setLastModifiedBy('')
                                ->setTitle("Tax report")
                                ->setSubject("computer generated")
                                ->setDescription("Tax report")
                                ->setKeywords("office 2007 openxml php")
                                ->setCategory("file");
/*
 * Tax deductibe
 * receivable account, tax paid in expenses, purchases
 */

// sheet name
$objPHPExcel->getActiveSheet()->setTitle( (string)t('Deductible'));
// title
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string)t("Tax report for period") . ": " . $param['from'] . "  -  " . $param['to'] );
// subtitle
$company = Database::getConnection('external_db', 'external_db')
        ->query('SELECT name from {ek_company} WHERE id=:id', array(':id' => $param['coid']))
        ->fetchField();
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', $company);    
// format titles
$objPHPExcel->getActiveSheet()->getStyle('A1:E5')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getFont()->setBold(true);


//data
$total_coll = $journal->transactions(
                   array( 
                    'aid'=> $param['staxd'],
                    'type'=> 'debit',
                    'coid'=> $param['coid'],
                    'from'=> $param['from'],
                    'to'=> $param['to']
                     )
                    );
$total_pay = $journal->transactions(
                   array( 
                    'aid'=> $param['staxd'],
                    'type'=> 'credit',
                    'coid'=> $param['coid'],
                    'from'=> $param['from'],
                    'to'=> $param['to']
                     )
                    );
// Add rows header
$objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A5', (STRING)t('TAX DEDUCTIBLE') . ' ' . $param['staxd'] )
            ->setCellValue('A6', (STRING)t('total paid') )
            ->setCellValue('C6', (STRING)t('total credited') )
            ->setCellValue('A7', (STRING)t('multi currency') )
            ->setCellValue('B7', $param['baseCurrency'] )
            ->setCellValue('C7', (STRING)t('multi currency') )
            ->setCellValue('D7', $param['baseCurrency'] )
            ->setCellValue('A8', $total_coll[0] )
            ->setCellValue('B8', $total_coll[1] )
            ->setCellValue('C8', $total_pay[0] )
            ->setCellValue('D8', $total_pay[1] ) 
            ->setCellValue('A9', (string)t('date') )
            ->setCellValue('B9', (string)t('reference') )
            ->setCellValue('C9', (string)t('amount') )
            ->setCellValue('D9', '' )
            ->setCellValue('C10', (string)t('collect (debit)') )
            ->setCellValue('D10', (string)t('credited') ) 
        ;


// Format rows
$objPHPExcel->getActiveSheet()->mergeCells('A5:D5');
$objPHPExcel->getActiveSheet()->mergeCells('A6:B6');
$objPHPExcel->getActiveSheet()->mergeCells('C6:D6');

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);

$objPHPExcel->getActiveSheet()->setBreak( 'F1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$objPHPExcel->getActiveSheet()->getStyle('A4:D5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT); 
$objPHPExcel->getActiveSheet()->getStyle('A4:D5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A4:D5')->getFill()->getStartColor()->setARGB('FFFFFF');


$objPHPExcel->getActiveSheet()->getStyle('A4:D4')->applyFromArray($TopBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A6:D6')->applyFromArray($TopBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A8:D8')->applyFromArray($BottomBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A10:D10')->applyFromArray($BottomBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A4:D5')->applyFromArray($FontBoldCenter); 
$objPHPExcel->getActiveSheet()->getStyle('A4:D5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A4:D5')->getFill()->getStartColor()->setARGB('e6e6fa');
$objPHPExcel->getActiveSheet()->getStyle('A8:D8')->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->mergeCells('C9:D9');

$query = "SELECT  * FROM {ek_journal} 
            WHERE aid = :aid
            AND coid = :coid
            AND date >= :from
            AND date <= :to";
$a = array(':aid' => $param['staxd'], ':coid' => $param['coid'], ':from' => $param['from'], ':to' => $param['to'] );

$data = Database::getConnection('external_db', 'external_db')->query($query, $a);
$total_value_cr = 0;
$total_base_cr = 0;
$total_value_dt = 0;
$total_base_dt = 0;

$l = 10;

    while ($d = $data->fetchObject() ) {
       
       $l++;
       $ref = $journal->reference($d->source, $d->reference);
       
       if($d->exchange == '1') {
           
           $objPHPExcel->getActiveSheet()->getStyle("A$l:D$l")->getFont()->setSize(10);
           $objPHPExcel->getActiveSheet()->getStyle("A$l:E$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
           $objPHPExcel->getActiveSheet()->getStyle("A$l:E$l")->getFill()->getStartColor()->setARGB('cbcbcb');
           if($d->type == 'credit') {
                $total_base_cr = $total_base_cr + $d->value;
           } else {
               $total_base_dt = $total_base_dt + $d->value;
           }
       } else {
           $objPHPExcel->getActiveSheet()->getStyle("A$l:D$l")->getFont()->setSize(12);
           if($d->type == 'credit') {
               $total_value_cr = $total_value_cr + $d->value;
           } else {
               $total_value_dt = $total_value_dt + $d->value;
           }
       }
       
       if(is_array($ref[0])){
            //remove the hyperlink tag for excel
            preg_match("'>(.*?)</a>'si", $ref[0]['#markup'], $match);
            $comment = $match[1];
        } else {
            $comment = $ref[0];
        }

       $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $d->date );
       $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $d->source . " " . $d->reference . " | " . $comment);

        
        if($d->type == 'debit') {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $d->value );
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", '' );
            $objPHPExcel->getActiveSheet()->getStyle("C$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        } else {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", '' );
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $d->value );
            $objPHPExcel->getActiveSheet()->getStyle("D$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            
        } 
        
    }
    $l++;
    $objPHPExcel->getActiveSheet()->getStyle("A$l:D$l")->applyFromArray($TopBorderThin);
    
    //total
    $v = $total_value_dt - $total_value_cr;
    
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", (string)t('balance multi currency') );
    
    
        if($v >= 0) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $v );
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", '' );

        } else {
            $v = $total_value_cr - $total_value_dt;
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", '' );
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $v );
           
            
        }
    $objPHPExcel->getActiveSheet()->getStyle("C$l:D$l")->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle("C$l:D$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
    $l++;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", (string)t('balance base currency') );

    $v = ($total_value_dt+$total_base_dt) - ($total_value_cr + $total_base_cr);
    
        if($v >= 0) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $v );
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", '' );

        } else {
            $v = ($total_value_cr + $total_base_cr) - ($total_value_dt+$total_base_dt);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", '' );
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $v );

        }

    $objPHPExcel->getActiveSheet()->getStyle("C$l:D$l")->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle("C$l:D$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
    
$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

/*
 * Tax collected
 * debit account, tax included in sales
 */
    
$objPHPExcel->createSheet();
$objPHPExcel->setActiveSheetIndex(1);

// sheet name
$objPHPExcel->getActiveSheet(1)->setTitle( (string)t('Collectible'));
// title
$objPHPExcel->setActiveSheetIndex(1)->setCellValue('A1', (string)t("Tax report for period") . ": " . $param['from'] . "  -  " . $param['to'] );
// subtitle
$objPHPExcel->setActiveSheetIndex(1)->setCellValue('A2', $company);    
// format titles
$objPHPExcel->getActiveSheet()->getStyle('A1:E5')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getFont()->setBold(true);

// data
$total_coll = $journal->transactions(
                   array( 
                    'aid'=> $param['staxc'],
                    'type'=> 'credit',
                    'coid'=> $param['coid'],
                    'from'=> $param['from'],
                    'to'=> $param['to']
                     )
                    );
$total_pay = $journal->transactions(
                   array( 
                    'aid'=> $param['staxc'],
                    'type'=> 'debit',
                    'coid'=> $param['coid'],
                    'from'=> $param['from'],
                    'to'=> $param['to']
                     )
                    );

// Add rows header
$objPHPExcel->setActiveSheetIndex(1)
            ->setCellValue('A5', (STRING)t('TAX COLLECTED') . ' ' . $param['staxc'] )
            ->setCellValue('A6', (STRING)t('total collected') )
            ->setCellValue('C6', (STRING)t('total paid') )
            ->setCellValue('A7', (STRING)t('multi currency') )
            ->setCellValue('B7', $param['baseCurrency'] )
            ->setCellValue('C7', (STRING)t('multi currency') )
            ->setCellValue('D7', $param['baseCurrency'] )
            ->setCellValue('A8', $total_coll[0] )
            ->setCellValue('B8', $total_coll[1] )
            ->setCellValue('C8', $total_pay[0] )
            ->setCellValue('D8', $total_pay[1] ) 
            ->setCellValue('A9', (string)t('date') )
            ->setCellValue('B9', (string)t('reference') )
            ->setCellValue('C9', (string)t('amount') )
            ->setCellValue('D9', '' )
            ->setCellValue('E9', '' )
            ->setCellValue('C10', (string)t('collect') )
            ->setCellValue('D10', (string)t('status as of @date', ['@date' => $param['to']]) )
            ->setCellValue('E10', (string)t('paid (debit)') )
        ;

// Format rows
$objPHPExcel->getActiveSheet()->mergeCells('A5:E5');
$objPHPExcel->getActiveSheet()->mergeCells('A6:B6');
$objPHPExcel->getActiveSheet()->mergeCells('C6:E6');

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);

$objPHPExcel->getActiveSheet()->setBreak( 'F1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$objPHPExcel->getActiveSheet()->getStyle('A4:E5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT); 
$objPHPExcel->getActiveSheet()->getStyle('A4:E5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A4:E5')->getFill()->getStartColor()->setARGB('FFFFFF');


$objPHPExcel->getActiveSheet()->getStyle('A4:E4')->applyFromArray($TopBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A6:E6')->applyFromArray($TopBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A8:E8')->applyFromArray($BottomBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A10:E10')->applyFromArray($BottomBorderThin);
$objPHPExcel->getActiveSheet()->getStyle('A4:E5')->applyFromArray($FontBoldCenter); 
$objPHPExcel->getActiveSheet()->getStyle('A4:E5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A4:E5')->getFill()->getStartColor()->setARGB('e6e6fa');
$objPHPExcel->getActiveSheet()->getStyle('A8:E8')->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->mergeCells('C9:E9');

$query = "SELECT  * FROM {ek_journal} 
            WHERE aid = :aid
            AND coid = :coid
            AND date >= :from
            AND date <= :to";
$a = array(':aid' => $param['staxc'], ':coid' => $param['coid'], ':from' => $param['from'], ':to' => $param['to'] );
$data = Database::getConnection('external_db', 'external_db')->query($query, $a);
$total_value_cr = 0;
$total_base_cr = 0;
$total_value_dt = 0;
$total_base_dt = 0;
$l = 10;
    while ($d = $data->fetchObject() ) {
       $l++;
       $ref = $journal->reference($d->source, $d->reference);
       
       //check if payment received
       $query = "SELECT status, pay_date FROM {ek_sales_invoice} "
               . "WHERE id=:id AND head=:h";
       $a = array(':id' => $d->reference, ':h' => $param['coid'] );
       $invoice = Database::getConnection('external_db', 'external_db')
               ->query($query, $a)->fetchObject();
       
       if($invoice->status =='1' && $invoice->pay_date <= $to){
           $status = (string)t('paid');
       } elseif( $invoice->status =='2' && $invoice->pay_date <= $to) {
           $status = (string)t('partially paid');
       } else {
           $status = (string)t('unpaid');
       }
       
       if($d->exchange == '1') {    
           $objPHPExcel->getActiveSheet()->getStyle("A$l:E$l")->getFont()->setSize(10);
           $objPHPExcel->getActiveSheet()->getStyle("A$l:E$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
           $objPHPExcel->getActiveSheet()->getStyle("A$l:E$l")->getFill()->getStartColor()->setARGB('cbcbcb');
           if($d->type == 'credit') {
               $total_base_cr = $total_base_cr + $d->value;
           } else {
               $total_base_dt = $total_base_dt + $d->value;
           }
       } else {
           $objPHPExcel->getActiveSheet()->getStyle("A$l:D$l")->getFont()->setSize(12);
           if($d->type == 'credit') {
               $total_value_cr = $total_value_cr + $d->value;
           } else {
               $total_value_dt = $total_value_dt + $d->value;
           }
       }
      
       if(is_array($ref[0])){
            //remove the hyperlink tag for excel
            preg_match("'>(.*?)</a>'si", $ref[0]['#markup'], $match);
            $comment = $match[1];
        } else {
            $comment = $ref[0];
        }
       $objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", $d->date );
       $objPHPExcel->setActiveSheetIndex(1)->setCellValue("B$l", $d->source . " " . $d->reference . " | " . $comment);

        
        if($d->type == 'credit') {
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("C$l", $d->value );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("D$l", $status );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("E$l", '' );
            $objPHPExcel->getActiveSheet()->getStyle("C$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        } else {
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("C$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("D$l", $status );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("E$l", $d->value );
            $objPHPExcel->getActiveSheet()->getStyle("E$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            
        }
    }
    
    $l++;
    $objPHPExcel->getActiveSheet()->getStyle("A$l:E$l")->applyFromArray($TopBorderThin);
    
    //total
    $v = $total_value_dt - $total_value_cr;
    
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", (string)t('balance multi currency') );
    
        if($v >= 0) {
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("C$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("D$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("E$l", $v );
            
        } else {
            $v = $total_value_cr - $total_value_dt;
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("C$l", $v );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("D$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("E$l", '' );
            
        }
    $objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
    $l++;
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", (string)t('balance base currency') );

    $v = ($total_value_dt+$total_base_dt) - ($total_value_cr + $total_base_cr);
    
        if($v >= 0) {
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("C$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("D$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("E$l", $v );
        } else {
            $v = ($total_value_cr + $total_base_cr) - ($total_value_dt+$total_base_dt);
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("C$l", $v );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("D$l", '' );
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue("E$l", '' ); 
        }

    $objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle("C$l:E$l")->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);    

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", $date);

$fileName = 'Tax-report_' . $param['from'] . '_' . $param['to'] . '_' . str_replace(' ','-', $company) . '.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Cache-Control: max-age=0');
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        $objWriter->save('php://output');    
        exit;

  
     // end generate report





        
            
            
            
            
            
            
            
            
            
            