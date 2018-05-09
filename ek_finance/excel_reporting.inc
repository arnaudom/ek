<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;


$param = unserialize($param);
$coid = $param['coid'];
$year = $param['year'];
$view = $param['view'];
$baseCurrency = $param['baseCurrency'];
$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
$query = "SELECT name from {ek_company} WHERE id=:id";
$company_name = Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => $coid))
        ->fetchField();
$grandtotal_column_purchases = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
$grandtotal_column_expenses = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
$grandtotal_column_income = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];


// Create new PHPExcel object
$objPHPExcel = new PHPExcel();

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
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(18);

$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A1", $text);
$objPHPExcel->getActiveSheet()->mergeCells('A1:N1');
$objPHPExcel->getActiveSheet()->getStyle('A1:N1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
/*
 * Purchases
 */
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

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setRGB('ff9933');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            )
        )
);

// Get the class 
// build accounts array structures for expenses
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
    $query->fields('a', ['aid', 'aname']);
    $or = db_or();
    $or->condition('aid', $chart['cos'] . '%', 'like');
    $or->condition('aid', $chart['expenses'] . '%', 'like');
    $or->condition('aid', $chart['other_expenses'] . '%', 'like');
    
    $query->condition($or);
    $query->condition('atype', 'class');
    $query->condition('astatus', '1');
    
if ($coid != 'all') {
    $query->condition('coid', $coid);
}
$query->orderBy('aid');
$accounts_exp_class = $query->execute()->fetchAllKeyed();


$thisclass = [];

foreach($accounts_exp_class as $key => $name) {
    $class = substr($key, 0, 2);
    //$l++;
    $c = 0;
    $row = "A" . $l . ":M" . $l;
    $flag = 0;
    $thisclass[$class] = [];
    $thisclass[$class]['name'] = $key . " " . $name;
    // Get the account type
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('aid', $class . '%', 'like');
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');
        
    if ($coid != 'all') {
        $query->condition('coid', $coid);
    }

    $query->orderBy('aid');
    $data2 = $query->execute();
    $sum_row_amount = 0;
    $flag = 0;
    $table_0_detail = '';
    $subtotal_column_purchases = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];

    while ($d2 = $data2->fetchObject()) { //loop aid within acc. class
        $thisline = [];
        $thisline[0] = $d2->aid . " " . $d2->aname;

        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            // Get the type sum               
            // select purchases by date in usd
            // The reporting consider purchases value from allocation of purchase 
            // NOT from the actual purchasing entity. This is to compare revenue & expenses fron analytical point of view

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $d2->aid);
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                
            if ($coid != 'all') {           
                $query->condition($view['S'], $coid);
            }

            $Obj = $query->execute();
            $sum_d2 = $Obj->fetchObject()->sumValue; 

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
            } else {
                $amount = round($sum_d2, 2);
            }
            $thisline[$m] = $amount;
            $sum_row_amount = $sum_row_amount + $sum_d2;
            $subtotal_column_purchases[$m] += $sum_d2;
            
        } //for loop months

        $thisline[13] = $sum_row_amount;
        if ($sum_row_amount <> 0) {
            $flag++;
            $thisclass[$class]['lines'][] = $thisline;
        }
        $sum_row_amount = 0;
    } //loop aid
    // Display if line has data

    if ($flag <> 0) {
        //some lines have data
        //add lines and sub total
        $l++;
        $c = 0;
        $row = "A" . $l . ":M" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ffcc99');
        $cell = $columns[$c] . $l;
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $thisclass[$class]['name']);
        
        if (!empty($thisclass[$class]['lines'])) {
            
            foreach ($thisclass[$class]['lines'] as $key => $value) {
                if ($value[13] <> 0) {
                    
                        //display the line only if it has data
                        $l++;
                        $cell = $columns[0] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $value[0]);

                        for ($m = 1; $m <= 12; $m++) {
                            $cell = $columns[$m] . $l;
                            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $value[$m]);
                            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                            if ($value[$m] == 0) {
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
                            } else {
                                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
                            }
                        }

                        $cell = $columns[13] . $l;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
                        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
                    
                }
            }//if line not empty     
        }//loop thisline

        $l++;

        $cell = $columns[0] . $l;
        $row = "A" . $l . ":N" . $l;
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('f3e1b7');
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));

        for ($m = 1; $m <= 12; $m++) {

            $total_class = $subtotal_column_purchases[$m];
            $cell = $columns[$m] . $l;
            if ($total_class == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($total_class, 2);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }
            $grandtotal_column_purchases[$m] += $subtotal_column_purchases[$m];
            $sum_row_amount = $sum_row_amount + $amount;

            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        } // for loop months
        // total subtotal line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
        $sum_row_amount = 0;
    }//if flag 1
} //while loop acc. classes



/*
 * Grand total purchases
 */

$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total'). ' ' . (string) t('purchases'));

$grand_total_purchases = array();

for ($m = 1; $m <= 12; $m++) {
    
    $cell = $columns[$m] . $l;
    if ($grandtotal_column_purchases[$m] == "") {
        $amount = 0;
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($grandtotal_column_purchases[$m], 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_row_amount = $sum_row_amount + $amount;

    //insert line

    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);

   
    $grand_total_purchases[$m] = $amount; 
}  //for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ff9933');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;

$l++;

/*
 * Expenses
 * journal data linked to expense table. The reporting consider purchase value from allocation of expense 
 * NOT from the actual spending entity. This is to compare revenue & expenses from analytical point of view
 * Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
 */

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

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setARGB('ed95b0');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            )
        )
);


foreach($accounts_exp_class as $key => $name) {
    
    $class = substr($key, 0, 2);

    // insert lines data
    $c = 0;
    $l++;
    $row = "A" . $l . ":M" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('d2bbc2');
    // insert line
    $cell = $columns[$c] . $l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $key . ' ' . $name);

// Get the account type detail
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('aid', $class . '%', 'like');
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');
        
    if ($coid != 'all') {
        $query->condition('coid', $coid);
    }
    
    $query->orderBy('aid');
    $data2 = $query->execute();
    $flag = 0;
    $subtotal_column_expenses = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0]; //used to comine sub total per column

    while ($d2 = $data2->fetchObject()) {

        $sum_row_amount = 0;
        $l++;
        $cell = $columns[$c] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d2->aid . ' ' . $d2->aname);


        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }
            // Get the type sum detail
            //by allocation
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $d2->aid);
                $query->condition('date', $date1, '>=');
                $query->condition('date', $date2, '<=');
                $query->condition('source', 'purchase', '!=');
                $query->condition('j.type', 'debit');
                
                
            if ($coid != 'all') {           
                $query->condition($view['E'], $coid);
            }

            $Obj = $query->execute();
            $sum_d2 = $Obj->fetchObject()->sumValue; 

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($sum_d2, 2);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_row_amount = $sum_row_amount + $sum_d2;
            $subtotal_column_expenses[$m] += $sum_d2;

            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
        } //for loop months
        // total line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sum_amount = 0;
    } //while aid

    
    // Get the class sum - subtotal
    $l++;
    $cell = $columns[0] . $l;
    $row = "A" . $l . ":N" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('f3e1b7');
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));


    for ($m = 1; $m <= 12; $m++) {

        $cell = $columns[$m] . $l;
        if ($subtotal_column_expenses[$m] == "") {
            $amount = 0;
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
        } else {
            $amount = round($subtotal_column_expenses[$m], 2);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
        }

        $sum_row_amount = $sum_row_amount + $amount;
        $grandtotal_column_expenses[$m] += $subtotal_column_expenses[$m];
        // insert line
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    }

    // total subtotal line
    $cell = $columns[$m] . $l;
    $sum_amount = number_format($sum_amount, 2);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
    $sum_row_amount = 0;
    $c++;
} //while class 


/*
 * Grand total expenses
 */

$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total') . ' ' . (string) t('expenses'));

$sum_row_amount = 0;
$grand_total_expenses = [];

for ($m = 1; $m <= 12; $m++) {

    $cell = $columns[$m] . $l;
    if ($grandtotal_column_expenses[$m] == "") {
        $amount = 0;
    } else {
        $amount = $grandtotal_column_expenses[$m];
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_row_amount = $sum_row_amount + $amount;

    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);

    $grand_total_expenses[$m] = $amount;
} //loop months

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ed95b0');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$sum_amount = number_format($sum_amount, 2);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_row_amount = 0;


$l++;

/*
 * Income
 * journal data linked to sales table. The reporting consider sales value from allocation of sales 
 * NOT from the actual invoicing entity. This is to compare revenue & expenses from analytical point of view
 * Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
 */
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

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            )
        )
);

// Get the class array structure
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
    $query->fields('a', ['aid', 'aname']);
    $or = db_or();
    $or->condition('aid', $chart['income'] . '%', 'like');
    $or->condition('aid', $chart['other_income'] . '%', 'like');
    $query->condition($or);
    $query->condition('atype', 'class');
    $query->condition('astatus', '1');

if ($coid != 'all') {
    $query->condition('coid', $coid);
}  

$query->orderBy('aid');
$accounts_income_class = $query->execute()->fetchAllKeyed();

foreach($accounts_income_class as $key => $name) {
    $class = substr($key, 0, 2);
    $l++;
    $c = 0;
    $row = "A" . $l . ":M" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('d1d5f8');
    $cell = $columns[$c] . $l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $key . " " . $name);


    // Get the account type detail
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('aid', $class . '%', 'like');
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');
        
    if ($coid != 'all') {
        $query->condition('coid', $coid);
    }
 
    $query->orderBy('aid');
    $data2 = $query->execute();
    $sum_row_amount = 0;
    $subtotal_column_income = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
    while ($d2 = $data2->fetchObject()) {

        $l++;
        $cell = $columns[0] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d2->aid . " " . $d2->aname);


        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            //get the sum detail
            //by allocation
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $d2->aid);
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'invoice', '=');
                $query->condition('j.type', 'credit');
                
                
            if ($coid != 'all') {           
                $query->condition($view['S'], $coid);
            }            

            $Obj = $query->execute();
            $sum_d2 = $Obj->fetchObject()->sumValue; 

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($sum_d2, 2);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_row_amount = $sum_row_amount + $amount;
            $subtotal_column_income[$m] += $sum_d2;
            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
        } //for loop months
        // insert line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");

        $sum_row_amount = 0;
    } 
    
    // Get the class sum - subtotal
    $l++;
    $cell = $columns[0] . $l;
    $row = "A" . $l . ":N" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('f3e1b7');
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));

    for ($m = 1; $m <= 12; $m++) {
        
        $cell = $columns[$m] . $l;
        if ($subtotal_column_income[$m] == "") {
            $amount = 0;
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
        } else {
            $amount = round($subtotal_column_income[$m], 2);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
        }

        $sum_row_amount = $sum_row_amount + $amount;
        $grandtotal_column_income[$m] += $subtotal_column_income[$m];
        // insert line
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    } // for loop months
    // total subtotal line
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
    $sum_row_amount = 0;
} 



/*
 * Grand total income
 */


$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total') . ' ' . t('income'));

$grand_total_revenue = [];

for ($m = 1; $m <= 12; $m++) {
    
    $cell = $columns[$m] . $l;
    if ($grandtotal_column_income[$m] == "") {
        $amount = 0;
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($grandtotal_column_income[$m], 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_row_amount = $sum_row_amount + $amount;

    //insert line
    
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
  
    $grand_total_revenue[$m] = $amount; //exclude internal transactions  
    
}  //for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_row_amount = 0;



/*
 * Balances
 * PL
 */
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

$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle("A$l:N$l")->applyFromArray(
        array(
            'font' => array(
                'bold' => true
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'top' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
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
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $pl = $grand_total_revenue[$m] - $grand_total_purchases[$m] - $grand_total_expenses[$m];
    if ($pl < 0) {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('a9b1f8');
    }
    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $pl);
    $sum_pl = $sum_pl + $pl;
}
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
if ($sum_pl < 0) {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
} else {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('a9b1f8');
}
//$thispl=number_format($sum_pl,2);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_pl = 0;

/*
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Profit/Loss with internal transfers'));

for ($m = 1; $m <= 12; $m++) {
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $pl = $grand_total_revenue[$m] - $grand_total_purchases[$m] - $grand_total_expenses[$m] + $amount_i[$m]; //ADD IT
    if ($pl < 0) {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('FFCC99');
    }
    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $pl);
    $sum_pl = $sum_pl + $pl;
} // end For P/L  

$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
if ($sum_pl < 0) {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
} else {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('FFCC99');
}
//$thispl=number_format($sum_pl,2);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
*/

/*
 * Balances
 * INYR
 */
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Payments not yet received'));

$sum_nyr = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    // Get the sum of payments not received 
    //TODO get data from journal
    
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 'i');
        $query->addExpression('SUM(amountbase)', 'sumValue');
        $query->condition('date', $date1, '>=');
        $query->condition('date', $date2, '<=');
        $query->condition('status', '0');
        
    if ($coid != 'all') {
        $query->condition($view['S'], $coid);
    }
    
    $Obj = $query->execute();
    $not_received = $Obj->fetchObject()->sumValue; 
    
    $cell = $columns[$m] . $l;
    if ($not_received == "") {
        $amount = 0;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($not_received, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_nyr = $sum_nyr + $amount;

    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
} //for loop months   
// Line total INR
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;
$sum_nyr = 0;


/*
 * Balances
 * short payments
 */
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Short payments'));

$sum_np = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 'i');
        $query->addExpression('SUM(balancebase)', 'sumValue');
        $query->condition('date', $date1, '>=');
        $query->condition('date', $date2, '<=');
        $query->condition('status', '1');
        
    if ($coid != 'all') {
        $query->condition($view['S'], $coid);
    }
    
    $Obj = $query->execute();
    $not_paid = $Obj->fetchObject()->sumValue; 
    $cell = $columns[$m] . $l;
    if ($not_paid == "") {
        $amount = 0;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($not_paid, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_np = $sum_np + $amount;

    //insert line
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
} //for loop months   
// Line total SP
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;
$sum_np = 0;

/*
 * Balances
 * ENYP
 */   
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Expenses not yet paid'));

$sum_np = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e');
        $query->addExpression('SUM(amount)', 'sumValue');
        $query->condition('pdate', $date1, '>=');
        $query->condition('pdate', $date2, '<=');
        $query->condition('cash', 'p');
        
        if ($coid != 'all') {
            $query->condition($view['E'], $coid);
        }

        $Obj = $query->execute();
        $not_paid = $Obj->fetchObject()->sumValue; 
        
    $cell = $columns[$m] . $l;
    if ($not_paid == "") {
        $amount = 0;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($not_paid, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_np = $sum_np + $amount;

    //insert line

    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
} //for loop months   
// Line total NYP
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
$sum_amount = 0;
$sum_np = 0;

/*
 * Internal Revenue
 */

$l++;
$cell = $columns[0] . $l;
//$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Internal transfers received'));


for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'm');
                $query->addExpression('SUM(amount_paid_base)', 'sumValue');
                $query->condition('date', $date1, '>=');
                $query->condition('date', $date2, '<=');
                $query->condition('category', 5, '<>');
                $query->condition('status', 0, '>');
                $query->condition('entity', $coid);
                
    
        $Obj = $query->execute();
        $sumValue = $Obj->fetchObject()->sumValue; 
        
    $cell = $columns[$m] . $l;
    if ($sumValue == "") {
        $amount_i[$m] = 0;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount_i[$m] = round($sumValue, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }
    $sum_row_amount = $sum_row_amount + $amount_i[$m];

    // insert line
    
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount_i[$m]);
}

$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
//$thissum_i[$m] = $sum_row_amount;
$sum_row_amount = 0;

$l++;
$cell = $columns[0] . $l;

$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Internal transfers paid'));


for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'm');
                $query->addExpression('SUM(amount_paid_base)', 'sumValue');
                $query->condition('date', $date1, '>=');
                $query->condition('date', $date2, '<=');
                $query->condition('category', 5, '<>');
                $query->condition('status', 0, '>');
                $query->condition('entity_to', $coid);
                
    
        $Obj = $query->execute();
        $sumValue = $Obj->fetchObject()->sumValue; 
        
    $cell = $columns[$m] . $l;
    if ($sumValue == "") {
        $amount_i[$m] = 0;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount_i[$m] = round($sumValue, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }
    $sum_row_amount = $sum_row_amount + $amount_i[$m];

    // insert line
    
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount_i[$m]);
}

$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, "=SUM(B$l:M$l)");
//$thissum_i[$m] = $sum_row_amount;
$sum_row_amount = 0;

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'reporting_' . str_replace(' ', '_', $company_name) . '-' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');

header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->setPreCalculateFormulas(true);
$objWriter->save('php://output');
exit;