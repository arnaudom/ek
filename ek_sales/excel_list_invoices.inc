<?php

use Drupal\Core\Database\Database;

// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
        ->setLastModifiedBy('')
        ->setTitle("Invoices list")
        ->setSubject("computer generated")
        ->setDescription("Invoices list filtered")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle((string) t('List'));


$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A3', 'ID')
        ->setCellValue('B3', (string) t('Serial'))
        ->setCellValue('C3', (string) t('Type'))
        ->setCellValue('D3', (string) t('Delivery order'))
        ->setCellValue('E3', (string) t('PO No.'))
        ->setCellValue('F3', (string) t('Header'))
        ->setCellValue('G3', (string) t('Allocated'))
        ->setCellValue('H3', (string) t('Status'))
        ->setCellValue('I3', (string) t('Amount'))
        ->setCellValue('J3', (string) t('Currency'))
        ->setCellValue('K3', (string) t('Ex. rate'))
        ->setCellValue('L3', (string) t('Tax value'))
        ->setCellValue('M3', (string) t("Amount in base currency") . " " . $baseCurrency)
        ->setCellValue('N3', (string) t('Amount paid'))
        ->setCellValue('O3', (string) t('Payment ex. rate'))
        ->setCellValue('P3', (string) t('Pay date'))
        ->setCellValue('Q3', (string) t('Project'))
        ->setCellValue('R3', (string) t('Client'))
        ->setCellValue('S3', (string) t('Comment'));

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(5);
$objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(10);
$objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('R')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('S')->setWidth(20);
$objPHPExcel->getActiveSheet()->getStyle('A3:S3')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A3:S3')
        ->getFill()->getStartColor()->setARGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle('A3:S3')
        ->applyFromArray(
                [
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
                ]
);


$l = 3;

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', (string) t('Invoices list'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', (string) t('Fom') . ':' . $options['from'] . ' ' . (string) t('to') . ':' . $options['to']);

$total = 0;

WHILE ($data = $result->fetchAssoc()) {

    $client_name = $abook[$data['client']];
    $l = $l + 1; // line index

    if ($l & 1) {
        $row = "A" . $l . ":S" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)
                ->getFill()->getStartColor()->setARGB('e6e6fa');
    }

    if( $data['amountbase']) {
        $exrate = round($data['amount'] / $data['amountbase'] , 4);
    } else {
        $exrate = "";
    }
    $tax = round($data['taxvalue'] * $data['amount'] / 100, 2);
    $total += $data['amountbase'];

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $data['id']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $data['serial']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $types[$data['type']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $data['do_no']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $data['po_no']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $companies[$data['head']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $companies[$data['allocation']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $status[$data['status']]);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $data['amount']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $data['currency']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $exrate);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $tax);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $data['amountbase']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $data['amountreceived']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $data['pay_rate']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $data['pay_date']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("Q$l", $data['pcode']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("R$l", $data['name']);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("S$l", $data['comment']);
}
$l += 1;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", (string) t('TOTAL'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $total);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $baseCurrency);

$date = date('Y-m-d h:i:s');
$l += 2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'invoices_list.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;    
            
            
            
            
            
            
            
            
            
            