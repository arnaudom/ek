<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;


$param = unserialize($param);
$coid = $param['coid'];
$year = $param['year'];
$baseCurrency = $param['baseCurrency'];
$rounding = $param['rounding'];
$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
if($coid != 'all') {
$query = "SELECT name from {ek_company} WHERE id=:id";
$company_name = Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => $coid))
        ->fetchField();
$company_name_short = substr($company_name, 0, 10);
} else {
    $company_name = (string)t('All');
    $company_name_short = (string)t('All');
}

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

$objPHPExcel->getProperties()->setCreator("Ek")
        ->setLastModifiedBy('')
        ->setTitle((string)t("Budget @c @y", array('@c' => $company_name, '@y' => $year)))
        ->setSubject("computer generated")
        ->setDescription((string) t("management report"))
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle($company_name_short);

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
// Add rows
$objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', (string) t("Expenses @c @y @m", array('@c' => $company_name, '@y' => $year, '@m' => $baseCurrency)))
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

$objPHPExcel->getActiveSheet()->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle('A1:N1')->getFill()->getStartColor()->setARGB('ed95b0');
$objPHPExcel->getActiveSheet()->getStyle('A1:N1')->applyFromArray(
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
    //create a storage for class categories total used for charts 
    //$thiscategories[$c] = array();

    $class = substr($line->aid, 0, 2);
    $class_exp = substr($line->aid, 0, 1);
    // insert lines data
    $c = 0;
    $l++;
    $row = "A" . $l . ":M" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
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
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d2->aid . ' ' . $d2->aname);

        for ($m = 1; $m <= 12; $m++) {
            // Get the type sum  
            if ($coid != 'all') {


                $field_name = $d2->aid . "-" . $coid . "-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference = :r";
                $a = [ ':r' => $field_name];
            }

            if ($coid == 'all') {

                $field_name = $d2->aid . "-%-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference like :r";
                $a = [ ':r' => $field_name];
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($sum_d2, $rounding);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_amount = $sum_amount + $sum_d2;

            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        } //for loop months
        // total line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sum_amount = 0;
    } //while aid
    // Get the class sum - subtotal
    $l++;
    $cell = $columns[0] . $l;
    $row = "A" . $l . ":N" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('f3e1b7');
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));


    for ($m = 1; $m <= 12; $m++) {

        if ($coid != 'all') {
            $reference = $class . "%-" . $coid . "-" . $year . "-" . $m;
        }
        if ($coid == 'all') {
            $reference = "%-%-" . $year . "-" . $m;
        }
        $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE reference like :r";
        $a = [ ':r' => $reference];
        $total_class = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)->fetchField();

        $cell = $columns[$m] . $l;
        if ($total_class == "") {
            $amount = 0;
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
        } else {
            $amount = round($total_class, $rounding);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
        }

        $sum_amount = $sum_amount + $amount;

        // insert line
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    }

    // total subtotal line
    $cell = $columns[$m] . $l;
    $sum_amount = round($sum_amount, $rounding);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
    $sum_amount = 0;
    $c++;
} //while class 
//GRAND Total
$l++;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total'));

$sum_amount = 0;
$grand_total_expenses = array();

for ($m = 1; $m <= 12; $m++) {
    if ($coid != 'all') {
        $reference1 = $chart['expenses'] . '%-' . $coid . "-" . $year . "-" . $m;
        $reference2 = $chart['cos'] . '%-' . $coid . "-" . $year . "-" . $m;
        $reference3 = $chart['other_expenses'] . '%-' . $coid . "-" . $year . "-" . $m;
    }

    if ($coid == 'all') {
        $reference1 = $chart['expenses'] . "%-%-" . $year . "-" . $m;
        $reference2 = $chart['cos'] . "%-%-" . $year . "-" . $m;
        $reference3 = $chart['other_expenses'] . "%-%-" . $year . "-" . $m;
    }

    $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE "
            . "reference like :r1 or reference like :r2 or reference like :r3";
    $a = [ ':r1' => $reference1, ':r2' => $reference2, ':r3' => $reference3];

    $cell = $columns[$m] . $l;
    if ($grand_total == "") {
        $amount = "0";
    } else {
        $amount = $grand_total;
    }

    $sum_amount = $sum_amount + $amount;

    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);

    $grand_total_expenses[$m] = $amount;
} //loop months

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('ed95b0');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$sum_amount = round($sum_amount, $rounding);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
$sum_amount = 0;


$l++;

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
            . "WHERE atype=:t and (aid like :a1 or aid like :a2)  "
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
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
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
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $d2->aid . " " . $d2->aname);


        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            if ($coid != 'all') {

                $field_name = $d2->aid . "-" . $coid . "-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference = :r";
                $a = [ ':r' => $field_name];
            }

            if ($coid == 'all') {
                $field_name = $d2->aid . "-%-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference like :r";
                $a = [ ':r' => $field_name];
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            $cell = $columns[$m] . $l;
            if ($sum_d2 == "") {
                $amount = 0;
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
            } else {
                $amount = round($sum_d2, $rounding);
                $objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
            }

            $sum_amount = $sum_amount + $amount;
            // insert line
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
            $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        } //for loop months
        // insert line
        $cell = $columns[$m] . $l;
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);

        $sum_amount = 0;
    } //while aid
    // Get the class sum - subtotal
    $l++;
    $cell = $columns[0] . $l;
    $row = "A" . $l . ":N" . $l;
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setARGB('f3e1b7');
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Sub total'));

    for ($m = 1; $m <= 12; $m++) {
        if ($coid != 'all') {
            $reference = $class . "%-" . $coid . "-" . $year . "-" . $m;
        }

        if ($coid == 'all') {
            $reference = "%-%-" . $year . "-" . $m;
        }

        $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE reference like :r";
        $a = [ ':r' => $reference];

        $total_class = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)->fetchField();
        $cell = $columns[$m] . $l;
        if ($total_class == "") {
            $amount = "0";
        } else {
            $amount = round($total_class, $rounding);
        }

        $sum_amount = $sum_amount + $amount;

        // insert line
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    } // for loop months
    // total subtotal line
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, round($sum_amount, $rounding));
    $sum_amount = 0;
} //while aid
/////////////////////
//GRAND Total

$l = $l + 2;
$cell = $columns[0] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, (string) t('Grand total'));

$grand_total_revenue = array();

for ($m = 1; $m <= 12; $m++) {
    if ($coid != 'all') {
        $reference1 = $chart['income'] . "%-" . $coid . "-" . $year . "-" . $m;
        $reference2 = $chart['other_income'] . "%-" . $coid . "-" . $year . "-" . $m;
    }

    if ($coid == 'all') {
        $reference1 = $chart['income'] . "%-%-" . $year . "-" . $m;
        $reference2 = $chart['other_income'] . "%-%-" . $year . "-" . $m;
    }

    $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE "
            . "reference like :r1 or reference like :r2";
    $a = [ ':r1' => $reference1, ':r2' => $reference2];
    $grand_total = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)->fetchField();

    if ($grand_total == "") {
        $amount = 0;
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($greyfont);
    } else {
        $amount = round($grand_total, $rounding);
        //$objPHPExcel->getActiveSheet()->getStyle($cell)->applyFromArray($bluefont);
    }

    $sum_amount = $sum_amount + $amount;

    //insert line
    $cell = $columns[$m] . $l;
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $amount);


    //$grand_total_revenue[$m] = $line5["sum(valueusd)"]+$amount_i[$m];//includes internal transactions   
    $grand_total_revenue[$m] = $grand_total; //exclude internal transactions  
}  //for loop month

$cell = $columns[$m] . $l;
$row = "A" . $l . ":N" . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle($row)->getFill()->getStartColor()->setRGB('a9b1f8');
$objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_amount);
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
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $pl = $grand_total_revenue[$m] - $grand_total_expenses[$m];

    if ($pl < 0) {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
    } else {
        $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('a9b1f8');
    }
    //insert line
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $pl);
    $sum_pl = $sum_pl + $pl;
}
$cell = $columns[$m] . $l;
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
if ($sum_pl < 0) {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('ed95b0');
} else {
    $objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->getStartColor()->setRGB('a9b1f8');
}

$objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle($cell)->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue($cell, $sum_pl);
$sum_pl = 0;



$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'budget_' . str_replace(' ', '_', $company_name) . '_' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;


// end generate report

