<?php

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

//formatting
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
$Fontgrey = array(
    'font' => array(
        'color' => array('rgb' => 'A0A0A0'),
        ));

$Fontblue = array(
    'font' => array(
        'color' => array('rgb' => '0B05B5'),
    ),
    'fill' => array(
        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'color' => array('rgb' => 'A0A0A0')
    )
);
$Fontlightblue = array(
    'font' => array(
        'color' => array('rgb' => '0B05B5'),
    ),
    'fill' => array(
        'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startcolor' => array('rgb' => 'E0E0E0')
    )
);

// Set properties
$objPHPExcel->getProperties()->setCreator("ek")
        ->setLastModifiedBy("")
        ->setTitle("Chart of accounts")
        ->setSubject("Chart of accounts")
        ->setDescription("Chart of accounts for Excel generated using PHP classes.")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");

$columns = Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

$objPHPExcel->getActiveSheet()->setTitle('Chart of accounts');
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(8);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(12);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);

$objPHPExcel->getActiveSheet()->setBreak('H1', \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN);
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// title
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);


$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $company);

$objPHPExcel->getActiveSheet()->getStyle('A2:G2')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A2:G2')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A2:G2')->getFont()->setBold(true);

$objPHPExcel->getActiveSheet()->getStyle("A2:G2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A2:G2")->getFill()->getStartColor()->setRGB('acacac');

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', (string) t('Account'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B2', (string) t('Name'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C2', (string) t('Type'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D2', (string) t('Balance'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('E2', (string) t('Balance') . " " . $baseCurrency);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('F2', (string) t('Date'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('G2', (string) t('Status'));

// Data

$i = 3;

While ($d = $data->fetchObject()) {

    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $d->aid);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $d->aname);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $d->atype);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", $d->balance);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$i", $d->balance_base);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$i", $d->balance_date);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$i", $d->astatus);

    if ($d->astatus == '0') {
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->applyFromArray($Fontgrey);
    }
    if ($d->atype == 'header') {
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->getFont()->setSize(12);
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->applyFromArray($Fontblue);
    }
    if ($d->atype == 'class') {
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->getFont()->setSize(10);
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->applyFromArray($Fontlightblue);
    }
    if ($d->atype == 'detail') {
        $objPHPExcel->getActiveSheet()->getStyle("A$i:G$i")->getFont()->setSize(10);
    }

    $i++;
}

// Set header and footer. When no different headers for odd/even are used, odd header is assumed.;
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddHeader('&L&Bchart of accounts&RPrinted on &D');
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddFooter('&L&B' . $objPHPExcel->getProperties()->getTitle() . '&RPage &P of &N');


// Set page orientation and size
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$fileName = "chart_of_accounts_" . str_replace(" ", "_", $company) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;
?>
