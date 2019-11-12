<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

$objPHPExcel->getProperties()->setCreator("Ek")
        ->setLastModifiedBy('')
        ->setTitle("Expenses list")
        ->setSubject("computer generated")
        ->setDescription("Expenses list")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle('List');

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(16);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('n')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(32);
$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A5', 'ID')
        ->setCellValue('B5', (string) t('Class'))
        ->setCellValue('C5', (string) t('Description'))
        ->setCellValue('D5', (string) t('Supplier'))
        ->setCellValue('E5', (string) t('Client'))
        ->setCellValue('F5', (string) t('Case'))
        ->setCellValue('G5', (string) t('Debit date'))
        ->setCellValue('H5', (string) t('Debit Value'))
        ->setCellValue('I5', "")
        ->setCellValue('J5', (string) t('Debit base currency'))
        ->setCellValue('K5', "")
        ->setCellValue('L5', (string) t('Credit account'))
        ->setCellValue('M5', (string) t('Company'))
        ->setCellValue('N5', (string) t('Allocation'))
        ->setCellValue('O5', (string) t('Reconciled'))
        ->setCellValue('P5', (string) t('Comment'));


$objPHPExcel->getActiveSheet()->getStyle('A5:P5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A5:P5')->getFill()->getStartColor()->setARGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle('A5:P5')->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                )
            )
        )
);
$HalignCenter = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
);

$HalignLeft = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
    ),
);
$HalignRight = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
    ),
);


$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string) t('Company') . ":");
if (isset($param['coid']) || $param['coid'] != '') {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B1', $company_array[$param['coid']]);
} else {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B1', (string) t('Any'));
}
if (isset($param['from'])) {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', (string) t('from') . ":");
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B2', $param['from']);  
}
if (isset($param['to'])) {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('C2', (string) t('to') . ":");
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('D2', $param['to']);
}
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A3', (string) t('supplier') . ":");
if (!isset($param['supplier']) || $param['supplier'] == '%') {
    $supplier = (string) t('Any');
} else {
    $supplier = Database::getConnection('external_db', 'external_db')
            ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $param['supplier']))
            ->fetchField();
}
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B3', $supplier);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C3', (string) t('client') . ":");
if (!isset($param['client']) || $param['client'] == '%') {
    $client = (string) t('Any');
} else {
    $client = Database::getConnection('external_db', 'external_db')
            ->query("SELECT name from {ek_address_book} WHERE id=:id", array(':id' => $param['client']))
            ->fetchField();
}
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D3', $client);
if (!isset($param['pcode']) || $param['pcode'] == '%') {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A4', (string) t('project') . ":");
    $pcode = (string) t('Any');
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B4', $pcode);
} else {
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A4', (string) t('project') . ":");
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B4', $param['pcode']);
}



$l = 5;
$total = 0;

WHILE ($r = $result->fetchObject()) {
    $l++; // line index
    if ($l & 1) {
        $row = "A" . $l . ":P" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('e6e6fa');
    }
    $CompanySettings = new CompanySettings($r->coid);
    $stax_deduct_aid = $CompanySettings->get('stax_deduct_aid');
    $aid = $r->aid;
    $aname = $chartList[$r->coid][$aid];
    $company_name = $company_array[$r->coid];
    $allocation = $company_array[$r->allocation];
    $date = $r->date;
    $value = $r->value;
    $currency = $r->currency;
    if (strpos($r->source, 'xpense')) {
        $supplier = $r->suppliername;
    } else {
        $supplier = $r->client;
    }
    $clientname = Database::getConnection('external_db', 'external_db')
            ->query("SELECT name FROM {ek_address_book} WHERE id=:id", [':id' => $r->clientname])
            ->fetchField();
    if ($clientname == '')
        $clientname = '-';
    $suppliername = Database::getConnection('external_db', 'external_db')
            ->query("SELECT name FROM {ek_address_book} WHERE id=:id", [':id' => $supplier])
            ->fetchField();
    if ($suppliername == '')
        $suppliername = '-';

    if (strpos($r->source, 'xpense')) {
            $id = $r->e_id;
            $comment = $r->comment;
            if ($r->tax > 0) {
                //if tax is collected, retrieve the tax collection account per company
                //get the total tax record from journal
                
                $query = "SELECT id,value,exchange FROM {ek_journal} WHERE reference=:r AND coid=:c AND aid=:a AND type=:t order by id";
                $a = [':r' => $r->e_id, ':c' => $r->coid, ':a' => $stax_deduct_aid, ':t' => 'debit'];

                $stax = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a);

                $stax_deduct_aid_value = array();
                While ($st = $stax->fetchObject()) {
                    $stax_deduct_aid_value[$st->exchange] = $st->value;
                }
            } else {
                $stax_deduct_aid_value = 0;
            }

            if ($r->currency <> $baseCurrency) {
                $r = $result->fetchObject();
                $ecurrency = $r->currency;
                $evalue = ($value + $r->value);
            } else {
                $ecurrency = $currency;
                $evalue = $value;
            }

            $total = $total + $evalue;
            if ($r->reconcile == '1')
                $reco = (string) t('yes');
            if ($r->reconcile == '0')
                $reco = (string) t('no');

            if ($r->cash == "Y") {
                $credit = (string) t('cash');
            } else {
                // bank account
                $query = "SELECT account_ref from {ek_bank_accounts} where id=:id";
                $credit = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $r->cash))->fetchField();
            }

    } elseif ($r->source == 'purchase') {          
                $id = $r->p_id;
                $comment = $r->title;
                $credit  = '';
                
                if ($r->currency <> $baseCurrency) {
                    $r = $result->fetchObject();
                    $ecurrency = $r->currency;
                    $evalue = ($value + $r->value);
                } else {
                    $ecurrency = $currency;
                    $evalue = $value;
                }

                $total = $total + $evalue;
                
                if ($r->taxvalue > 0 ) {
                    $stax_deduct_aid_value_0 = round($value*$r->taxvalue/100, $rounding);
                    $stax_deduct_aid_value_1 = round($evalue*$r->taxvalue/100, $rounding);
                } else {
                    $stax_deduct_aid_value_0 = round($value, $rounding);
                    $stax_deduct_aid_value_1= round($evalue, $rounding);
                }
                
                
            }

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $id);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $aid);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $aname);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $suppliername);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $clientname);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $r->pcode);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $r->date);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $value);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $currency);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $evalue);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $ecurrency);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $credit);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $company_name);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $allocation);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $reco);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $comment);
    
    $objPHPExcel->getActiveSheet()->getStyle("H$l")->applyFromArray($HalignRight);
    $objPHPExcel->getActiveSheet()->getStyle("J$l")->applyFromArray($HalignRight);
    $objPHPExcel->getActiveSheet()->getStyle("L$l")->applyFromArray($HalignCenter);
    $objPHPExcel->getActiveSheet()->getStyle("H$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
    $objPHPExcel->getActiveSheet()->getStyle("J$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);    
    
    //add one line to display tax paid/deductible
    if (isset($r->tax) && $r->tax > 0) {
        
        $l++;
        $row = "A" . $l . ":O" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('FAFAE6');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $id);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $stax_deduct_aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", (string) t('tax'));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $r->date);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", round($stax_deduct_aid_value[0], $rounding));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $currency);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", round($stax_deduct_aid_value[0] + $stax_deduct_aid_value[1], $rounding));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $ecurrency);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $credit);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $company_name);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", '');

        $objPHPExcel->getActiveSheet()->getStyle("H$l")->applyFromArray($HalignRight);
        $objPHPExcel->getActiveSheet()->getStyle("J$l")->applyFromArray($HalignRight);
        $objPHPExcel->getActiveSheet()->getStyle("L$l")->applyFromArray($HalignCenter);
        $objPHPExcel->getActiveSheet()->getStyle("H$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        $objPHPExcel->getActiveSheet()->getStyle("J$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        
    } elseif(isset($r->taxvalue) && $r->taxvalue > 0){
        
        $l++;
        $row = "A" . $l . ":O" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('FAFAE6');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $id);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $stax_deduct_aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", (string) t('tax'));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $r->date);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $stax_deduct_aid_value_0);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $currency);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $stax_deduct_aid_value_1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $ecurrency);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $credit);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $company_name);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", '');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", '');
        
        $objPHPExcel->getActiveSheet()->getStyle("H$l")->applyFromArray($HalignRight);
        $objPHPExcel->getActiveSheet()->getStyle("J$l")->applyFromArray($HalignRight);
        $objPHPExcel->getActiveSheet()->getStyle("L$l")->applyFromArray($HalignCenter);
        $objPHPExcel->getActiveSheet()->getStyle("H$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        $objPHPExcel->getActiveSheet()->getStyle("J$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
    }
}
$p = $l;
$l++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", "=SUM(J6:J$p)");
$objPHPExcel->getActiveSheet()->getStyle("J$l")->applyFromArray($HalignRight);
$objPHPExcel->getActiveSheet()->getStyle("J$l")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'expenses_list.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;


// end generate report




     
            
            
            
            
            
            
            
            
            
            