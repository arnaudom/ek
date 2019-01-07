<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;


$param = unserialize($param);
$year = $param['year'];
$baseCurrency = $param['baseCurrency'];
$company = AccessCheck::GetCompanyByUser();
$purchases = [];
$expenses = [];
$income = [];
$balances = [];
$balances['all']['actual'] = 0;
$balances['all']['allocation'] = 0;
$date1 = $year . "-01-01";
$date2 = $year . "-12-31";

$query = "SELECT id,name from {ek_company} ORDER by id";
$items['company'] = Database::getConnection('external_db', 'external_db')
        ->query($query)
        ->fetchAllKeyed();

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

$objPHPExcel->getProperties()->setCreator("Ek")
        ->setLastModifiedBy('')
        ->setTitle(t("Reporting Compilation @y", array('@y' => $year)))
        ->setSubject("computer generated")
        ->setDescription((string) t("management report"))
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle($year);

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


$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('c')->setWidth(25);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A1", (string) t("Compilation") . " " . $year);
$objPHPExcel->getActiveSheet()->mergeCells('A1:C1');
$objPHPExcel->getActiveSheet()->getStyle('A1:C1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
/*
 * Purchases
 */
$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue("A$l", (string) t("Balances @y @m", array('@y' => $year, '@m' => $baseCurrency)))
        ->setCellValue("B$l", (string)t('Actual'))
        ->setCellValue("C$l", (string)t('Allocated'));

$objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->getFill()->getStartColor()->setRGB('66b2ff');
$objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->applyFromArray(
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

foreach($company as $key => $coid) {
    if($coid > 0) {
// Get the class array structure for income
            $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_accounts', 'a');
                $query->fields('a', ['aid']);
                $or = db_or();
                $or->condition('aid', $chart['income'] . '%', 'like');
                $or->condition('aid', $chart['other_income'] . '%', 'like');
                $query->condition($or);
                $query->condition('atype', 'detail');
                $query->condition('astatus', '1');
                $query->condition('coid', $coid);
                $query->distinct();

            $query->orderBy('aid');
            $accounts_income_class = $query->execute()->fetchCol();
            
             // build accounts array structures for expenses
            $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_accounts', 'a');
                $query->fields('a', ['aid']);
                $or = db_or();
                $or->condition('aid', $chart['cos'] . '%', 'like');
                $or->condition('aid', $chart['expenses'] . '%', 'like');
                $or->condition('aid', $chart['other_expenses'] . '%', 'like');

                $query->condition($or);
                $query->condition('atype', 'detail');
                $query->condition('astatus', '1');
                $query->condition('coid', $coid);
                $query->distinct();

                $accounts_exp_class = $query->execute()->fetchCol(); 
                
            // Get the sum of purchases per 'actual'  
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $accounts_exp_class, 'IN');
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                $query->condition('head', $coid);

            $Obj = $query->execute();
            $purchases[$coid]['actual'] = $Obj->fetchObject()->sumValue; 
  
            // Get the sum of purchases per 'allocation'  
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $accounts_exp_class, 'IN');
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $purchases[$coid]['allocation'] = $Obj->fetchObject()->sumValue;        

            // Get the sum of expenses per 'actual' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_exp_class, 'IN');
                    $query->condition('date', $date1, '>=');
                    $query->condition('date', $date2, '<=');
                    $query->condition('source', 'purchase', '!=');
                    $query->condition('j.type', 'debit');
                    $query->condition('company', $coid);

            $Obj = $query->execute();
            $expenses[$coid]['actual'] = $Obj->fetchObject()->sumValue; 


            // Get the sum of expenses per 'allocation' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_exp_class, 'IN');
                    $query->condition('date', $date1, '>=');
                    $query->condition('date', $date2, '<=');
                    $query->condition('source', 'purchase', '!=');
                    $query->condition('j.type', 'debit');
                    $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $expenses[$coid]['allocation'] = $Obj->fetchObject()->sumValue;   

            // Get the sum of income per 'actual' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_income_class, 'IN');
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'invoice', '=');
                    $query->condition('j.type', 'credit');
                    $query->condition('head', $coid);

            $Obj = $query->execute();
            $income[$coid]['actual'] = $Obj->fetchObject()->sumValue;

            // Get the sum of income per 'allocation' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_income_class, 'IN');
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'invoice', '=');
                    $query->condition('j.type', 'credit');
                    $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $income[$coid]['allocation'] = $Obj->fetchObject()->sumValue;        

            $balances[$coid]['actual'] = $income[$coid]['actual'] - $purchases[$coid]['actual'] - $expenses[$coid]['actual'];
            $balances[$coid]['allocation'] = $income[$coid]['allocation'] - $purchases[$coid]['allocation'] - $expenses[$coid]['allocation'];

            $balances['all']['actual'] = $balances['all']['actual'] + $balances[$coid]['actual'];
            $balances['all']['allocation'] = $balances['all']['allocation'] + $balances[$coid]['allocation'];
            
            
            $l++;
            //$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[0] . $l, (string) t('Income'));            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[1] . $l, round($income[$coid]['actual'],2));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[2] . $l, round($income[$coid]['allocation'],2));
            $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $l++;
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[0] . $l, (string) t('Purchases'));            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[1] . $l, round($purchases[$coid]['actual'],2));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[2] . $l, round($purchases[$coid]['allocation'],2));
            $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $l++;
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[0] . $l, (string) t('Expenses'));            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[1] . $l, round($expenses[$coid]['actual'],2));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[2] . $l, round($expenses[$coid]['allocation'],2));
            $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $l++;
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[0] . $l, $items['company'][$coid]);            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[1] . $l, round($balances[$coid]['actual'],2));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[2] . $l, round($balances[$coid]['allocation'],2));
            $objPHPExcel->getActiveSheet()->getStyle($columns[0] . $l)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            $objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->getFill()->getStartColor()->setRGB('E0E0E0');
            $objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->applyFromArray(
                    array(
                        'borders' => array(
                            'top' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                            )
                        )
                    )
            );            
            if ($balances[$coid]['actual'] < 0) {
                $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getFill()->getStartColor()->setRGB('ed95b0');
                
            } 
            if ($balances[$coid]['allocation'] < 0) {
                $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getFill()->getStartColor()->setRGB('ed95b0');
            }
            
    }
    
}
            
$l++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[0] . $l, (string) t('Total'));            
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[1] . $l, round($balances['all']['actual'],2));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($columns[2] . $l, round($balances['all']['allocation'],2));
$objPHPExcel->getActiveSheet()->getStyle($columns[0] . $l)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
$objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->getFill()->getStartColor()->setRGB('99ccff');
$objPHPExcel->getActiveSheet()->getStyle("A$l:C$l")->applyFromArray(
        array(
            'borders' => array(
                'top' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                )
            )
        )
);
if ($balances['all']['actual'] < 0) {
    $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($columns[1] . $l)->getFill()->getStartColor()->setRGB('ed95b0');
} 
if ($balances['all']['allocation'] < 0) {
    $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($columns[2] . $l)->getFill()->getStartColor()->setRGB('ed95b0');
}
            


$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'reporting_compilation' . '-' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');

header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->setPreCalculateFormulas(true);
$objWriter->save('php://output');
exit;