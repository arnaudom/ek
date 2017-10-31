<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;


$param = unserialize($param);
$coid = $param['coid'];
$year = $param['year'];
$baseCurrency = $param['baseCurrency'];
$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
$query = "SELECT name from {ek_company} WHERE id=:id";
$company_name = Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => $coid))
        ->fetchField();


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
$l = 1;
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

$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(45);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(18);

/*
 * Income
 */
$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue("A1", (string) t("Purchases @c @y @m", array('@c' => $company_name, '@y' => $year, '@m' => $baseCurrency)))
        ->setCellValue('B1', 'JAN')
        ->setCellValue('C1', 'FEB')
        ->setCellValue('D1', 'MAR')
        ->setCellValue('E1', 'APR')
        ->setCellValue('F1', 'MAY')
        ->setCellValue('G1', 'JUN')
        ->setCellValue('H1', 'JUL')
        ->setCellValue('I1', 'AUG')
        ->setCellValue('J1', 'SEP')
        ->setCellValue('K1', 'OCT')
        ->setCellValue('L1', 'NOV')
        ->setCellValue('M1', 'DEC')
        ->setCellValue('N1', 'TOTAL');

$objPHPExcel->getActiveSheet()->getStyle("A1:N1")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A1:N1")->getFill()->getStartColor()->setRGB('ff9933');
$objPHPExcel->getActiveSheet()->getStyle("A1:N1")->applyFromArray(
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
if ($coid <> 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t AND ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s AND coid=:c order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
        ':c' => $coid
    );
}
if ($coid == 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
    );
}

$data = Database::getConnection('external_db', 'external_db')->query($query, $a);
$thisclass = [];


while ($line = $data->fetchObject()) { //loop acc. classe
    $class = substr($line->aid, 0, 2);
    //$l++;
    $c = 0;
    $row = "A" . $l . ":M" . $l;
    $flag = 0;
    $thisclass[$class] = [];
    $thisclass[$class]['name'] = $line->aid . " " . $line->aname;
    // Get the account type
    if ($coid != 'all') {

        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s AND coid=:c ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s  ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
        );
    }

    $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);
    $sum_amount = 0;


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

            if ($coid != 'all') {
                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid = :a AND j.source = :s AND j.type=:t "
                        . "AND j.date >=:d1 AND j.date <=:d2 AND allocation = :c";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':c' => $coid,
                    ':s' => 'purchase',
                    ':t' => 'debit'
                );
            }

            if ($coid == 'all') {
                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid = :a AND j.source = :s AND j.type=:t "
                        . "AND j.date >=:d1 AND j.date <=:d2";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':s' => 'purchase',
                    ':t' => 'debit'
                );
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                
            } else {
                $amount = round($sum_d2, 2);
                
            }
            $thisline[$m] = $amount;
            $sum_amount = $sum_amount + $amount;
            
        } //for loop months

        $thisline[13] = $sum_amount;
        if ($sum_amount <> 0) {
            $flag++;
            $thisclass[$class]['lines'][] = $thisline;
        }
        $sum_amount = 0;
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
                        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $value[13]);
                    
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
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            if ($coid != 'all') {
                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid like :t AND j.source = :s AND j.type=:t2 "
                        . "AND j.date >=:d1 AND j.date <=:d2 AND allocation = :c";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':t' => $class . '%',
                    ':c' => $coid,
                    ':s' => 'purchase',
                    ':t2' => 'debit'
                );
            }

            if ($coid == 'all') {

                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid like :t AND j.source = :s AND j.type=:t2 "
                        . "AND j.date >=:d1 AND j.date <=:d2";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':t' => $class . '%',
                    ':s' => 'purchase',
                    ':t2' => 'debit'
                );
            }

            $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
            $cell = $columns[$m] . $l;
            if ($total_class == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($total_class, 2);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_amount = $sum_amount + $amount;

            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        } // for loop months
        // total subtotal line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($sum_amount, 2));
        $sum_amount = 0;
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
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid != 'all') {
        $query = "SELECT sum(value) "
                . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                . "WHERE j.source = :s  AND allocation=:c AND j.type=:t "
                . "AND j.date >=:d1 AND j.date <=:d2 AND ( aid like :a1 or aid like :a2 or aid like :a3 )";

        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':c' => $coid,
            ':s' => 'purchase',
            ':t' => 'debit',
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
        );
    }

    if ($coid == 'all') {
        $query = "SELECT sum(value) "
                . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                . "WHERE j.source = :s  AND j.type=:t "
                . "AND j.date >=:d1 AND j.date <=:d2 AND ( aid like :a1 or aid like :a2 or aid like :a3 )";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'purchase',
            ':t' => 'debit',
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
        );
    }

    $grand_total = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
    $cell = $columns[$m] . $l;
    if ($grand_total == "") {
        $amount = 0;
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($grand_total, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_amount = $sum_amount + $amount;

    //insert line

    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);

   
    $grand_total_purchases[$m] = $grand_total; 
}  //for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ff9933');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
$sum_amount = 0;

$l++;

/*
 * Expenses
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

if ($coid <> 'all') {

    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t "
            . "and ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s "
            . "AND coid=:c order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
        ':c' => $coid
    );
}

if ($coid == 'all') {
    $query = "SELECT SQL_CACHE aid,aname "
            . "FROM {ek_accounts} "
            . "WHERE atype=:t and "
            . "( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
    );
}
$data = Database::getConnection('external_db', 'external_db')->query($query, $a);


while ($line = $data->fetchObject()) {

    $class = substr($line->aid, 0, 2);
    // insert lines data
    $c = 0;
    $l++;
    $row = "A" . $l . ":M" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('d2bbc2');
    // insert line
    $cell = $columns[$c] . $l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $line->aid . ' ' . $line->aname);



    // Get the account type
    if ($coid <> 'all') {

        $query = "SELECT SQL_CACHE aid,aname "
                . "FROM {ek_accounts} "
                . "WHERE aid like :cl "
                . "AND atype=:t AND astatus=:s AND coid=:c ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT SQL_CACHE aid,aname "
                . "FROM {ek_accounts} "
                . "WHERE aid like :cl "
                . "AND atype=:t AND astatus=:s  ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
        );
    }

    $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);

    while ($d2 = $data2->fetchObject()) {

        $sum_amount = 0;
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
            // Get the type sum  
            if ($coid != 'all') {

                $query = "SELECT sum(value) FROM {ek_journal} "
                        . "WHERE date>=:d1 AND date<=:d2 AND source <> :s "
                        . "AND type=:t AND aid like :aid AND coid=:c";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':aid' => $d2->aid,
                    ':t' => 'debit',
                    ':c' => $coid,
                    ':s' => 'purchase'
                );
            }

            if ($coid == 'all') {

                $query = "SELECT sum(value) FROM {ek_journal} "
                        . "WHERE date>=:d1 AND date<=:d2 AND type=:t AND source <> :s "
                        . "AND aid like :aid AND FIND_IN_SET(coid, :c ) ";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':aid' => $d2->aid,
                    ':t' => 'debit',
                    ':c' => $company,
                    ':s' => 'purchase'
                );
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($sum_d2, 2);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_amount = $sum_amount + $sum_d2;

            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
        } //for loop months
        // total line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sum_amount = 0;
    } //while aid
    //
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
        if ($m < 10) {
            $date1 = $year . "-0" . $m . "-01";
            $date2 = $year . "-0" . $m . "-31";
        } else {
            $date1 = $year . "-" . $m . "-01";
            $date2 = $year . "-" . $m . "-31";
        }

        if ($coid != 'all') {

            $query = "SELECT sum(value) FROM {ek_journal} "
                    . "WHERE date>=:d1 AND date<=:d2 AND type=:t AND source <> :s "
                    . "AND aid like :aid AND coid=:c";
            $a = array(
                ':d1' => $date1,
                ':d2' => $date2,
                ':aid' => $class . '%',
                ':t' => 'debit',
                ':c' => $coid,
                ':s' => 'purchase'
            );
        }
        if ($coid == 'all') {

            $query = "SELECT sum(value) FROM {ek_journal} "
                    . "WHERE date>=:d1 AND date<=:d2 AND source <> :s "
                    . "AND type=:t AND aid like :aid AND FIND_IN_SET(coid, :c ) ";
            $a = array(
                ':d1' => $date1,
                ':d2' => $date2,
                ':aid' => $class . '%',
                ':t' => 'debit',
                ':c' => $company,
                ':s' => 'purchase'
            );
        }

        $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        $cell = $columns[$m] . $l;
        if ($total_class == "") {
            $amount = 0;
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
        } else {
            $amount = round($total_class, 2);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
        }

        $sum_amount = $sum_amount + $amount;

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
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
    $sum_amount = 0;
    $c++;
} //while class 


/*
 * Grand total expenese
 */

$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total') . ' ' . (string) t('expenses'));

$sum_amount = 0;
$grand_total_expenses = array();

for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid != 'all') {

        $query = "SELECT sum(value) FROM {ek_journal} "
                . "WHERE date>=:d1 AND date<=:d2 AND type=:t AND coid=:c AND source <> :s "
                . "AND (aid like :a1 OR aid like :a2 OR aid like :a3)";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':t' => 'debit',
            ':c' => $coid,
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
            ':s' => 'purchase'
        );
    }

    if ($coid == 'all') {

        $query = "SELECT sum(value) FROM {ek_journal} "
                . "WHERE date>=:d1 AND date<=:d2 AND source <> :s "
                . "AND type=:t AND FIND_IN_SET(coid, :c ) "
                . "AND (aid like :a1 OR aid like :a2 OR aid like :a3)";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':t' => 'debit',
            ':c' => $company,
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
            ':s' => 'purchase'
        );
    }


    $grand_total = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

    $cell = $columns[$m] . $l;
    if ($grand_total == "") {
        $amount = 0;
    } else {
        $amount = $grand_total;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_amount = $sum_amount + $amount;

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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
$sum_amount = 0;


$l++;

/*
 * Income
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

// Get the class 
if ($coid <> 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and (aid like :a1 or aid like :a2) "
            . "AND astatus=:s AND coid=:c order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['income'] . '%',
        ':a2' => $chart['other_income'] . '%',
        ':s' => 1,
        ':c' => $coid
    );
}
if ($coid == 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and (aid like :a1 or aid like :a2) "
            . "AND astatus=:s order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['income'] . '%',
        ':a2' => $chart['other_income'] . '%',
        ':s' => 1,
    );
}

$data = Database::getConnection('external_db', 'external_db')->query($query, $a);

while ($line = $data->fetchObject()) {
    $class = substr($line->aid, 0, 2);
    $l++;
    $c = 0;
    $row = "A" . $l . ":M" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('d1d5f8');
    $cell = $columns[$c] . $l;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $line->aid . " " . $line->aname);


    // Get the account type
    if ($coid != 'all') {

        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s AND coid=:c ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s  ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
        );
    }

    $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);
    $sum_amount = 0;
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

            // Get the type sum               
            // select invoices by date in usd
            // The reporting consider invoicing value from allocation of invoice 
            // NOT from the actual invoicing entity. This is to compare revenue & expenses fron analytical point of view

            if ($coid != 'all') {

                $query = "select sum(value) FROM ek_journal j "
                        . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                        . "WHERE aid =:a AND j.source = :s "
                        . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                        . "AND j.type= :t";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':c' => $coid,
                    ':s' => 'invoice',
                    ':t' => 'credit'
                );
            }

            if ($coid == 'all') {
                $query = "select sum(value) FROM ek_journal j "
                        . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                        . "WHERE aid =:a AND j.source = :s "
                        . "AND j.date >=:d1 AND j.date <= :d2 "
                        . "AND j.type= :t";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':s' => 'invoice',
                    ':t' => 'credit'
                );
            }
            $sum_d2 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($sum_d2, 2);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_amount = $sum_amount + $amount;
            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
        } //for loop months
        // insert line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);

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
        if ($m < 10) {
            $date1 = $year . "-0" . $m . "-01";
            $date2 = $year . "-0" . $m . "-31";
        } else {
            $date1 = $year . "-" . $m . "-01";
            $date2 = $year . "-" . $m . "-31";
        }

        if ($coid != 'all') {
            $query = "select sum(value) FROM ek_journal j "
                    . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                    . "WHERE aid like :t AND j.source = :s "
                    . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                    . "AND j.type= :t2";

            $a = array(
                ':d1' => $date1,
                ':d2' => $date2,
                ':t' => $class . '%',
                ':c' => $coid,
                ':t2' => 'credit',
                ':s' => 'invoice'
            );
        }

        if ($coid == 'all') {

            $query = "select sum(value) FROM ek_journal j "
                    . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                    . "WHERE aid like :t AND j.source = :s "
                    . "AND j.date >=:d1 AND j.date <= :d2 "
                    . "AND j.type= :t2";

            $a = array(
                ':d1' => $date1,
                ':d2' => $date2,
                ':t' => $class . '%',
                ':t2' => 'credit',
                ':s' => 'invoice'
            );
        }

        $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
        $cell = $columns[$m] . $l;
        if ($total_class == "") {
            $amount = 0;
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
        } else {
            $amount = round($total_class, 2);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
        }

        $sum_amount = $sum_amount + $amount;

        // insert line
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    } // for loop months
    // total subtotal line
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($sum_amount, 2));
    $sum_amount = 0;
} //while aid



/*
 * Grand total income
 */

//$l=$l+2;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total') . ' ' . t('income'));

$grand_total_revenue = array();

for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid != 'all') {

        $query = "select sum(value) FROM ek_journal j "
                . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                . "WHERE j.source = :s "
                . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                . "AND j.type= :t AND (aid like :a1 or aid like :a2) ";

        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':c' => $coid,
            ':s' => 'invoice',
            ':t' => 'credit',
            ':a1' => $chart['income'] . '%',
            ':a2' => $chart['other_income'] . '%',
        );
    }

    if ($coid == 'all') {
        $query = "select sum(value) FROM ek_journal j "
                . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                . "WHERE j.source = :s "
                . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                . "AND j.type= :t AND (aid like :a1 or aid like :a2)";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'invoice',
            ':t' => 'credit',
            ':a1' => $chart['income'] . '%',
            ':a2' => $chart['other_income'] . '%',
        );
    }

    $grand_total = Database::getConnection('external_db', 'external_db')
            ->query($query, $a)->fetchField();
    
    $cell = $columns[$m] . $l;
    if ($grand_total == "") {
        $amount = 0;
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($grand_total, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_amount = $sum_amount + $amount;

    //insert line
    
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);


    //$grand_total_revenue[$m] = $line5["sum(valueusd)"]+$amount_i[$m];//includes internal transactions   
    $grand_total_revenue[$m] = $grand_total; //exclude internal transactions  
}  //for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
$sum_amount = 0;



/*
 * Internal Revenue
 */


$l++;
$cell = $columns[0] . $l;
//$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Internal transfers'));


for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }
    // Get the type sum  
    if ($coid != 'all') {

        $query = "SELECT sum(amount_paid_base) from {ek_expenses_memo} "
                . "WHERE date>=:d1 AND date<=:d2 AND entity=:c and category <> :cat and status > :s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
            ':cat' => 5,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT sum(amount_paid_base) from {ek_expenses_memo} "
                . "WHERE date>=:d1 AND date<=:d2 AND FIND_IN_SET(entity, :c ) and category <> :cat and status > :s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
            ':cat' => 5,
            ':c' => $company
        );
    }

    $line4 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
    $cell = $columns[$m] . $l;
    if ($line4 == "") {
        $amount_i[$m] = 0;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount_i[$m] = round($line4, 2);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }
    $sum_amount = $sum_amount + $amount_i[$m];

    // insert line
    
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount_i[$m]);
}

$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
$thissum_i[$m] = $sum_amount;
$sum_amount = 0;



///////////////////////
//P&L /////////////////
$profitloss = array();
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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_pl);
$sum_pl = 0;

///////////////////////////////////
//P&L with Iinternal transfer//////     
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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_pl);

/////////////////////////////  
//Invoice not yet received from unpaid invoices 

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
    if ($coid <> 'all') {
        $query = "SELECT sum(amountbase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND allocation=:c AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
            ':c' => $coid
        );
    }
    if ($coid == 'all') {
        $query = "SELECT sum(amountbase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
        );
    }

    $not_received = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_nyr);
$sum_amount = 0;
$sum_nyr = 0;


////////////////////////////////////////////////  
//Invoice not yet received from paid invoices 
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

    if ($coid <> 'all') {
        $query = "SELECT sum(balancebase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND allocation=:c AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 1,
            ':c' => $coid
        );
    }
    if ($coid == 'all') {
        $query = "SELECT sum(balancebase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 1,
        );
    }

    $not_paid = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_np);
$sum_amount = 0;
$sum_np = 0;

//////////////////////////////// 
//Expenses not yet paid  
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


    if ($coid <> 'all') {

        $query = "SELECT sum(amount) FROM {ek_expenses} "
                . "WHERE pdate>=:d1 AND pdate<=:d2 AND company=:c AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'no',
            ':c' => $coid
        );
    }
    if ($coid == 'all') {
        $query = "SELECT sum(amount) FROM {ek_expenses} "
                . "WHERE pdate>=:d1 AND pdate<=:d2 AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'no',
        );
    }

    $not_paid = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

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
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_np);
$sum_amount = 0;
$sum_np = 0;


$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'reporting_' . str_replace(' ', '_', $company_name) . '-' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');

header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

$objWriter->save('php://output');
exit;