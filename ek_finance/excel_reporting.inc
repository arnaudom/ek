<?php


use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
if($coid != 'all') {
$query = "SELECT name from {ek_company} WHERE id=:id";
$company_name = Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => $coid))
        ->fetchField();
} else {
    $company_name = (string)t('All');
    $company_name_short = (string)t('All');
}

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$objPHPExcel->getActiveSheet()->setBreak( 'O1' , \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN );
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$objPHPExcel->getActiveSheet()->getPageSetup()->setFitToWidth(1);

$objPHPExcel->getProperties()->setCreator("Ek")
        ->setLastModifiedBy('')
        ->setTitle(t("Reporting @c @y", array('@c' => $company_name, '@y' => $year)))
        ->setSubject("computer generated")
        ->setDescription((string) t("management report"))
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle(substr($company_name, 0, 25));

$columns = Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
$l = 2;
$bluefont = array(
    'font' => array(
        'color' => array('rgb' => '013ADF'),
    ),
);

$greyfont = array(
    'font' => array(
        'color' => array('rgb' => '6E6E6E'),
    ),
);

if($view['S'] == 'allocation') {
    $text = (string)t('Allocated values');
} else {
    $text = (string)t('Actual values');
}
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(45);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(20);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A1", $text);
$objPHPExcel->getActiveSheet()->mergeCells('A1:N1');
$objPHPExcel->getActiveSheet()->getStyle('A1:N1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
//
// Purchases
//
$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue("A$l", (string) t("Purchases @c @y @m", array('@c' => $company_name, '@y' => $year, '@m' => $baseCurrency)))
        ->setCellValue("B$l", 'JAN')
        ->setCellValue("C$l", 'FEB')
        ->setCellValue("D$l", 'MAR')
        ->setCellValue("E$l", 'APR')
        ->setCellValue("F$l", 'MAY')
        ->setCellValue("G$l", 'JUN')
        ->setCellValue("H$l", 'JUL')
        ->setCellValue("I$l", 'AUG')
        ->setCellValue("J$l", 'SEP')
        ->setCellValue("K$l", 'OCT')
        ->setCellValue("L$l", 'NOV')
        ->setCellValue("M$l", 'DEC')
        ->setCellValue("N$l", 'TOTAL');

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setRGB('ff9933');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                )
            )
        )
);

foreach ($purchases[0]['classes'] as $key => $values){
        $l++;
        $c = 0;
        $row = "A" . $l . ":M" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ffcc99');
        $cell = $columns[$c] . $l;
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $values['id'] . " - " . $values['name']);
                    
            foreach ($values['rows'] as $aid => $v) {
                if ($v['sumRow'] <> 0) {
                        $l++;
                        $cell = $columns[0] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $aid . " " . $v['desc']);

                        for ($m = 1; $m <= 12; $m++) {
                            $cell = $columns[$m] . $l;
                            if ($v[$m] == "") {
                                $v[$m] = 0;
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
                            } else {
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
                            }
                            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $v[$m]);
                            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
                            
                        }

                        $cell = $columns[13] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
                    
                }
            }    
        
        $l++;
        $cell = $columns[0] . $l;
        $row = "A" . $l . ":N" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('ffcc99');
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));

        for ($m = 1; $m <= 12; $m++) {
            //$total_class = $subtotal_column_purchases[$m];
            $cell = $columns[$m] . $l;
            if ($values['subTotal'][$m] == "") {
                $values['subTotal'][$m] = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $values['subTotal'][$m]);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        } // for loop months
        // total subtotal line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
        

}

//
// Grand total purchases
//
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total'). ' ' . (string) t('purchases'));

for ($m = 1; $m <= 12; $m++) {
    
    $cell = $columns[$m] . $l;
    if ($purchases[0]['total'][$m] == "0") {
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $purchases[0]['total'][$m]);

   
    //$grand_total_purchases[$m] = $amount; 
}  // for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ff9933');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;

$l++;

//
//  Expenses
//  journal data linked to expense table. The reporting consider purchase value from allocation of expense 
//  NOT from the actual spending entity. This is to compare revenue & expenses from analytical point of view
//  Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
// 

$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue("A$l", (string) t("Expenses @c @y @m", array('@c' => $company_name, '@y' => $year, '@m' => $baseCurrency)))
        ->setCellValue("B$l", 'JAN')
        ->setCellValue("C$l", 'FEB')
        ->setCellValue("D$l", 'MAR')
        ->setCellValue("E$l", 'APR')
        ->setCellValue("F$l", 'MAY')
        ->setCellValue("G$l", 'JUN')
        ->setCellValue("H$l", 'JUL')
        ->setCellValue("I$l", 'AUG')
        ->setCellValue("J$l", 'SEP')
        ->setCellValue("K$l", 'OCT')
        ->setCellValue("L$l", 'NOV')
        ->setCellValue("M$l", 'DEC')
        ->setCellValue("N$l", 'TOTAL');

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setARGB('FF9C9C');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                )
            )
        )
);
foreach ($expenses[0]['classes'] as $key => $values){
        $l++;
        $c = 0;
        $row = "A" . $l . ":M" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('FFDBE6');
        $cell = $columns[$c] . $l;
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $values['id'] . " - " . $values['name']);
                    
            foreach ($values['rows'] as $aid => $v) {
                if ($v['sumRow'] <> 0) {
                        $l++;
                        $cell = $columns[0] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $aid . " " . $v['desc']);

                        for ($m = 1; $m <= 12; $m++) {
                            $cell = $columns[$m] . $l;
                            if ($v[$m] == "") {
                                $v[$m] = 0;
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
                            } else {
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
                            }
                            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $v[$m]);
                            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
                            
                        }

                        $cell = $columns[13] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
                    
                }
            }    
        
        $l++;
        $cell = $columns[0] . $l;
        $row = "A" . $l . ":N" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('FFDBE6');
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));

        for ($m = 1; $m <= 12; $m++) {

            //$total_class = $subtotal_column_expenses[$m];
            $cell = $columns[$m] . $l;
            if ($values['subTotal'][$m] == "0") {
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }
            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $values['subTotal'][$m]);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        } // for loop months
        // total subtotal line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
        //$sum_row_amount = 0;

}

//
// Grand total exp
//

$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total'). ' ' . (string) t('expenses'));


for ($m = 1; $m <= 12; $m++) {
    
    $cell = $columns[$m] . $l;
    if ($expenses[0]['total'][$m] == "0") {
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }
    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $expenses[0]['total'][$m]);

  
}  // for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ed95b0');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;

$l++;

//
// Income
// journal data linked to sales table. The reporting consider sales value from allocation of sales 
// NOT from the actual invoicing entity. This is to compare revenue & expenses from analytical point of view
// Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
//
$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue("A$l", (string) t("Income @c @y @m", array('@c' => $company_name, '@y' => $year, '@m' => $baseCurrency)))
        ->setCellValue("B$l", 'JAN')
        ->setCellValue("C$l", 'FEB')
        ->setCellValue("D$l", 'MAR')
        ->setCellValue("E$l", 'APR')
        ->setCellValue("F$l", 'MAY')
        ->setCellValue("G$l", 'JUN')
        ->setCellValue("H$l", 'JUL')
        ->setCellValue("I$l", 'AUG')
        ->setCellValue("J$l", 'SEP')
        ->setCellValue("K$l", 'OCT')
        ->setCellValue("L$l", 'NOV')
        ->setCellValue("M$l", 'DEC')
        ->setCellValue("N$l", 'TOTAL');

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                )
            )
        )
);

foreach ($income[0]['classes'] as $key => $values){
        $l++;
        $c = 0;
        $row = "A" . $l . ":M" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('d1d5f8');
        $cell = $columns[$c] . $l;
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $values['id'] . " - " . $values['name']);
                    
            foreach ($values['rows'] as $aid => $v) {
                if ($v['sumRow'] <> 0) {
                        $l++;
                        $cell = $columns[0] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $aid . " " . $v['desc']);

                        for ($m = 1; $m <= 12; $m++) {
                            $cell = $columns[$m] . $l;
                            if ($v[$m] == "") {
                                $v[$m] = 0;
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
                            } else {
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
                            }
                            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $v[$m]);
                            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
                            
                        }

                        $cell = $columns[13] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
                    
                }
            }    
        
        $l++;
        $cell = $columns[0] . $l;
        $row = "A" . $l . ":N" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('d1d5f8');
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));

        for ($m = 1; $m <= 12; $m++) {
;
            $cell = $columns[$m] . $l;
            if ($values['subTotal'][$m] == "0") {
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }
            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $values['subTotal'][$m]);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        } // for loop months
        // total subtotal line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
        //$sum_row_amount = 0;

}


//
// Grand total
//

$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total'). ' ' . (string) t('income'));

for ($m = 1; $m <= 12; $m++) {
    
    $cell = $columns[$m] . $l;
    if ($grandtotal_column_purchases[$m] == "") {
        $amount = 0;
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($grandtotal_column_purchases[$m], $rounding);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $income[0]['total'][$m]);

} 

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;

$l++;

//
// Balances
// PL
//
$profitloss = [];
$l+=2;

$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue("A$l", (string) t("Balances @c @y @m", array('@c' => $company_name, '@y' => $year, '@m' => $baseCurrency)))
        ->setCellValue("B$l", 'JAN')
        ->setCellValue("C$l", 'FEB')
        ->setCellValue("D$l", 'MAR')
        ->setCellValue("E$l", 'APR')
        ->setCellValue("F$l", 'MAY')
        ->setCellValue("G$l", 'JUN')
        ->setCellValue("H$l", 'JUL')
        ->setCellValue("I$l", 'AUG')
        ->setCellValue("J$l", 'SEP')
        ->setCellValue("K$l", 'OCT')
        ->setCellValue("L$l", 'NOV')
        ->setCellValue("M$l", 'DEC')
        ->setCellValue("N$l", 'TOTAL');

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                )
            )
        )
);

$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Gain/Loss'));

//set variable
$sum_pl = 0;
for ($m = 1; $m <= 12; $m++) {
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    //$pl = $grand_total_revenue[$m] - $grand_total_purchases[$m] - $grand_total_expenses[$m];
    if ($balances['pl'][$m] < 0) {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('81F871');
    }
    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $balances['pl'][$m]);
}
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
if (array_sum($balances['pl']) < 0) {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
} else {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('81F871');
}

$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");

//
// Balances
// INYR
//
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Payments not yet received'));

$sum_nyr = 0;
for ($m = 1; $m <= 12; $m++) {
       
    $cell = $columns[$m] . $l;
    if ($balances['inyr'][$m] == "0") {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    } 

    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $balances['inyr'][$m]);
    
} //for loop months   
// Line total INR
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");


//
// Balances
// short payments
//
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Short payments'));

for ($m = 1; $m <= 12; $m++) {
    $cell = $columns[$m] . $l;
    if ($balances['short'][$m] == "0") {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    //insert line
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $balances['short'][$m]);
} //for loop months   
// Line total SP
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");

//
// Balances
// ENYP
//   
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Expenses not yet paid'));

$sum_np = 0;
for ($m = 1; $m <= 12; $m++) {

    $cell = $columns[$m] . $l;
    if ($balances['enyp'][$m] == "") {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    //insert line

    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $balances['enyp'][$m]);
} //for loop months   
// Line total NYP
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");

//
// Internal Revenue
//
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Internal transfers received'));
for ($m = 1; $m <= 12; $m++) {
    $cell = $columns[$m] . $l;
    if ($internal_received[0][$m] == "0") {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }
    // insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $internal_received[0][$m]);
}

$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
//$thissum_i[$m] = $sum_row_amount;
$sum_row_amount = 0;

$l++;
$cell = $columns[0] . $l;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Internal transfers paid'));
for ($m = 1; $m <= 12; $m++) {
    $cell = $columns[$m] . $l;
    if ($internal_paid[0][$m] == "") {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }
    // insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $internal_paid[0][$m]);
}

$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'reporting_' . str_replace(' ', '_', $company_name) . '-' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');

header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->setPreCalculateFormulas(true);
$objWriter->save('php://output');
exit;