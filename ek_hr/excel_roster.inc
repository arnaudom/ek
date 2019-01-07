<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;


$access = AccessCheck::GetCompanyByUser();
$access = implode(',', $access);
$param = unserialize($param);

$coid = $param['coid'];
$query = "SELECT name from {ek_company} WHERE id=:id";
$company_name = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $coid))->fetchField();
$location_array = array();
$month = explode("-", $param['month']);
$year = $month[0];
if ($month[1] < 10) {
    $month = str_replace('0', '', $month[1]);
} else {
    $month = $month[1];
}
$month0 = $month - 1;
if ($month0 == 0) {
    $month0 = 12;
    $year0 = $year - 1;
} else {
    $year0 = $year;
}
if ($month == 1) {
//get previous year data
    $d0 = cal_days_in_month(CAL_GREGORIAN, '12', $year - 1);
} else {
    $d0 = cal_days_in_month(CAL_GREGORIAN, $month - 1, $year);
}
$d = cal_days_in_month(CAL_GREGORIAN, $month, $year);

//Collect data / Public Holidays
$query_ph = "SELECT * FROM {ek_hr_workforce_ph} WHERE date=:d AND coid=:coid";
$query_r = "SELECT * FROM {ek_hr_workforce_roster} r "
        . "INNER JOIN {ek_hr_workforce} w ON r.emp_id = w.id "
        . "WHERE (period like :p1 OR period like :p2) and company_id=:c";

$p1 = $month0 . '-' . $year0 . '-%';
$p2 = $month . '-' . $year . '-%';
$a = [':p1' => $p1, ':p2' => $p2, ':c' => $coid];
$rdata = Database::getConnection('external_db', 'external_db')
        ->query($query_r, $a);
$roster_array = array();

WHILE ($r = $rdata->fetchObject()) {
    $roster_array[$r->period][$r->emp_id] = [
        'roster' => $r->roster,
        'status' => $r->status,
        'location' => $r->location,
        'name' => $r->name,
    ];
}

//store the public holidays and sundays dates to avoid db query
$ph_array = array();


for ($i = $param['cutoff']; $i <= $d0; $i++) {


    if ($month != 1) {
        $a = array(':d' => $year . '-' . $month0 . '-' . $i, ':coid' => $coid);
    } else {
        $a = array(':d' => $year0 . '-' . $month0 . '-' . $i, ':coid' => $coid);
    }
    $ph = Database::getConnection('external_db', 'external_db')->query($query_ph, $a)->fetchObject();
    if ($ph) {
        $day = date('j', strtotime($ph->date));
        $ph_array[$day] = $ph->description;
    }
}

for ($i = 1; $i < $param['cutoff']; $i++) {

    $a = array(':d' => $year . '-' . $month . '-' . $i, ':coid' => $coid);
    $ph = Database::getConnection('external_db', 'external_db')->query($query_ph, $a)->fetchObject();
    if ($ph) {
        $day = date('j', strtotime($ph->date));
        $ph_array[$day] = $ph->description;
    }
}


$col = Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
$colors = array('FF0000', '00FF00', 'FF00FF', '0000FF', '800000', 'FF00FF', '0066CC', '800080', 'FF0000', '993300', '00FF00', 'FF00FF', '0000FF', '993300', '800000', 'FF00FF', '0066CC', '800080');

function timed($t0, $t1, $t2, $t3, $t4, $t5) {

    $t0 = explode(".", $t0);
    $t1 = explode(".", $t1);
    $t2 = explode(".", $t2);
    $t3 = explode(".", $t3);
    $t4 = explode(".", $t4);
    $t5 = explode(".", $t5);

    $ta = $t0[0] * 3600 + $t0[1] * 60;
    $tb = $t1[0] * 3600 + $t1[1] * 60;
    $tc = $t2[0] * 3600 + $t2[1] * 60;
    $td = $t3[0] * 3600 + $t3[1] * 60;
    $te = $t4[0] * 3600 + $t4[1] * 60;
    $tf = $t5[0] * 3600 + $t5[1] * 60;

    $total = ($tb - $ta) + ($td - $tc) + ($tf - $te);
    if ($total == 86400) {
        return "24:00";
    } else {
        return gmdate('H:i', $total);
    }
}

function sum_h($t, $h) {

    $t0 = explode(":", $h);
    $ta = $t0[0] * 3600 + $t0[1] * 60;
    return $ta;
}

// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// Set properties
$objPHPExcel->getProperties()->setCreator("EK")
        ->setLastModifiedBy(\Drupal::currentUser()->getUsername())
        ->setTitle("Duty roster")
        ->setSubject("Duty roster")
        ->setDescription("Duty roster for Excel generated using PHP classes.")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");
// set initial line number
$i = 1;

$objPHPExcel->setActiveSheetIndex(0);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('A')->setWidth(25);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('B')->setWidth(25);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('C')->setWidth(25);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('D')->setWidth(25);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('E')->setWidth(25);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('F')->setWidth(25);
$objPHPExcel->getActiveSheet(0)->getColumnDimension('G')->setWidth(25);
// Set thin black border outline around column
$styleThinBlackBorderOutline = array(
    'borders' => array(
        'outline' => array(
            'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => array('argb' => 'FF000000'),
        ),
    ),
);

if ($param['location'] == 'ANY') {
    $a = array(':c' => $access, ':coid' => $coid, ':l' => '%');
} else {
    $a = array(':c' => $access, ':coid' => $coid, ':l' => $param['location']);
}
$query = "SELECT distinct location FROM {ek_hr_workforce} "
        . "WHERE  FIND_IN_SET(company_id, :c) "
        . "AND  company_id=:coid AND location like :l";
$locations = Database::getConnection('external_db', 'external_db')->query($query, $a);

//LOOP LOCATIONS
while ($l = $locations->fetchObject()) {

    //store data
    $location_array[] = $l->location;
    //previous month////////////////////////////////////////////////////////////////////////////////////////////////////////
    $first_day = mktime(0, 0, 0, $month0, 1, $year0);
    $title = date('F', $first_day);
    $day_of_week = date('D', $first_day);
    $days_in_month = cal_days_in_month(0, $month0, $year0);
    $day_count = 0;
    switch ($day_of_week) {
        case "Sun": $blank = 0;
            break;
        case "Mon": $blank = 1;
            break;
        case "Tue": $blank = 2;
            break;
        case "Wed": $blank = 3;
            break;
        case "Thu": $blank = 4;
            break;
        case "Fri": $blank = 5;
            break;
        case "Sat": $blank = 6;
            break;
    }
    // Add rows
    $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue("A$i", "Roster " . $year0 . " - " . $title . " - " . $l->location . " - " . $company_name);
    // format titles
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i")->getFont()->setName('Arial');
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i")->getFont()->setSize(12);
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i")->getFont()->setBold(true);

    $i++;
    $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue("A$i", "Sun")
            ->setCellValue("B$i", "Mon")
            ->setCellValue("C$i", "Tue")
            ->setCellValue("D$i", "Wed")
            ->setCellValue("E$i", "Thu")
            ->setCellValue("F$i", "Fi")
            ->setCellValue("G$i", "Sat");

    $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->applyFromArray(
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
                ),
                'fill' => array(
                    'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startcolor' => array(
                        'rgb' => 'C0C0C0'
                    ),
                    'endcolor' => array(
                        'argb' => 'FFFFFFFF'
                    )
                )
            )
    );
    $i++;
    $c = 0;
    while ($blank > 0) {

        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$c] . $i, "");
        $blank = $blank - 1;
        $day_count++;
        $c++;
    }
    $day_num = 1;
    while ($day_num <= $days_in_month) {

        if ($ph_array[$day_num] != NULL) {
            $color = 'FF99CC';
            $cal_description = $ph_array[$day_num];
            $objPHPExcel->getActiveSheet()->getComment($col[$c] . $i)->getText()->createTextRun($cal_description);
        } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $day_num, $year0), 1) == 'Sunday') {
            $color = 'FFCC99';
        } else {
            $color = 'FFFFCC';
        }

        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$c] . $i, $day_num);
        $objPHPExcel->getActiveSheet(0)->getStyle($col[$c] . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(0)->getStyle($col[$c] . $i)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet(0)->getStyle($col[$c] . $i)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet(0)->getStyle($col[$c] . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


        //INSERT ROSTER DATA///////////////////////////////////////////////////////
        if ($day_num >= $param['cutoff']) {
            $display = '';
            $period = $month0 . "-" . $year0 . "-" . $day_num;
            $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $objRichText->createText('');
            $co = 0;

            //while ($row = $data2->fetchAssoc() ) {
            foreach ($roster_array[$period] as $id => $row) {
                $time = '';
                if ($row['location'] == $l->location && $row['roster'] != '') {
                    $r = explode(",", $row['roster']);

                    if ($r[5] > $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[3] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[3] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[2] to $r[3] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                        $time .= "S2=from $r[2] to $r[3] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[2] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[4] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                        $time .= "S2=from $r[4] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[2] to $r[3] \r\n";
                        $time .= "S2=from $r[4] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                        $time .= "S2=from $r[2] to $r[3] \r\n";
                        $time .= "S3=from $r[4] to $r[5] \r\n";
                    }
                    if ($time <> '') {
                        $objName = $objRichText->createTextRun($row['name']);
                        $objName->getFont()->setBold(true);
                        $objName->getFont()->setSize(8);
                        $objName->getFont()->getColor()->setRGB($colors[$co]);
                        $objRichText->createText("\r\n$time");
                        //$objRichText->getFont()->setSize(8);
                    }
                    $co++;
                    if ($co == 19)
                        $co = 0;
                }
            }
        } else {
            $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $objRichText->createText('-');
        }
        $ii = $i + 1;

        $objPHPExcel->getActiveSheet(0)->getStyle($col[$c] . $ii)->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $ii)->getFont()->setSize(8);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $ii)->applyFromArray($styleThinBlackBorderOutline);
        //$objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$c] . $ii, "$display");
        $objPHPExcel->getActiveSheet()->getCell($col[$c] . $ii)->setValue($objRichText);
        $day_num++;
        $day_count++;
        $c++;
        if ($c > 6) {
            $day_count = 1;
            $i+=2;
            $c = 0;
        }
    }

    $i+=2;
    //current month/////////////////////////////////////////////////////////////////////////////////////////////////////////
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $title = date('F', $first_day);
    $day_of_week = date('D', $first_day);
    $days_in_month = cal_days_in_month(0, $month, $year);
    $day_count = 0;
    switch ($day_of_week) {
        case "Sun": $blank = 0;
            break;
        case "Mon": $blank = 1;
            break;
        case "Tue": $blank = 2;
            break;
        case "Wed": $blank = 3;
            break;
        case "Thu": $blank = 4;
            break;
        case "Fri": $blank = 5;
            break;
        case "Sat": $blank = 6;
            break;
    }
    // Add rows
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i")->getFont()->setName('Arial');
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i")->getFont()->setSize(12);
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i")->getFont()->setBold(true);
    $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue("A$i", "Roster " . $year . " - " . $title . " - " . $l->location . " - " . $company_name);
    $i++;
    $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue("A$i", "Sun")
            ->setCellValue("B$i", "Mon")
            ->setCellValue("C$i", "Tue")
            ->setCellValue("D$i", "Wed")
            ->setCellValue("E$i", "Thu")
            ->setCellValue("F$i", "Fri")
            ->setCellValue("G$i", "Sat");

    $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->applyFromArray(
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
                ),
                'fill' => array(
                    'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startcolor' => array(
                        'rgb' => 'C0C0C0'
                    ),
                    'endcolor' => array(
                        'argb' => 'FFFFFFFF'
                    )
                )
            )
    );
    $i++;
    $c = 0;
    while ($blank > 0) {

        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$c] . $i, "");
        $blank = $blank - 1;
        $day_count++;
        $c++;
    }
    $day_num = 1;
    while ($day_num <= $days_in_month) {
        if ($ph_array[$day_num] != NULL) {
            $color = 'FF99CC';
            $cal_description = $ph_array[$day_num];
            $objPHPExcel->getActiveSheet()->getComment($col[$c] . $i)->getText()->createTextRun($cal_description);
        } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $day_num, $year0), 1) == 'Sunday') {
            $color = 'FFCC99';
        } else {
            $color = 'FFFFCC';
        }

        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$c] . $i, $day_num);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $i)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $i)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


        //INSERT HERE THE CODE FOR ROSTER DATA
        if ($day_num <= $param['cutoff']) {
            $display = '';
            $period = $month . "-" . $year . "-" . $day_num;
            $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $co = 0;

            //while ($row = $data2->fetchAssoc()) {
            foreach ($roster_array[$period] as $id => $row) {
                $time = '';
                if ($row['location'] == $l->location && $row['roster'] != '') {
                    $r = explode(",", $row['roster']);
                    if ($r[5] > $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[3] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[3] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[2] to $r[3] \r\n";
                    } elseif ($r[5] == $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                        $time .= "S2=from $r[2] to $r[3] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[2] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[4] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                        $time .= "S2=from $r[4] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
                        $time .= "S1=from $r[2] to $r[3] \r\n";
                        $time .= "S2=from $r[4] to $r[5] \r\n";
                    } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
                        $time .= "S1=from $r[0] to $r[1] \r\n";
                        $time .= "S2=from $r[2] to $r[3] \r\n";
                        $time .= "S3=from $r[4] to $r[5] \r\n";
                    }
                    if ($time <> '') {
                        $objName = $objRichText->createTextRun($row['name']);
                        $objName->getFont()->setBold(true);
                        $objName->getFont()->setSize(8);
                        $objName->getFont()->getColor()->setRGB($colors[$co]);
                        $objRichText->createText("\r\n $time");
                    }
                    $co++;
                    if ($co == 19)
                        $co = 0;
                }
            }
        }else {
            $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $objRichText->createText('-');
        }
        $ii = $i + 1;
        $objPHPExcel->getActiveSheet(0)->getStyle($col[$c] . $ii)->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $ii)->getFont()->setSize(8);
        $objPHPExcel->getActiveSheet()->getStyle($col[$c] . $ii)->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getCell($col[$c] . $ii)->setValue($objRichText);
        $day_num++;
        $day_count++;
        $c++;
        if ($c > 6) {
            $day_count = 1;
            $i+=2;
            $c = 0;
        }
    }

    $i+=5;
} // end location loop


$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$day_mum = 1;
// Rename first worksheet
$objPHPExcel->getActiveSheet()->setTitle((string) t('Calendar view'));



///////////////////////////////////////////////////////////////////////////////////////////////////////
// LIST VIEW //////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////
// Create a new worksheet, after the default sheet
/* */
$objPHPExcel->createSheet();
$objPHPExcel->setActiveSheetIndex(1);
$objPHPExcel->getActiveSheet(1)->setTitle((string) t('List view'));
$objPHPExcel->getActiveSheet(1)->getColumnDimension("A")->setWidth(25);
$l = 1;

foreach ($location_array as $key => $location) {

    $objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", "Roster " . $year0 . " - " . $title . " - " . $location . " - " . $company_name);
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setName('Arial');
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setSize(12);
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setBold(true);
    $l++;
    $day = array();
    //HEADER
    $c = 1;

    for ($i = $param['cutoff']; $i <= $d0; $i++) {

        if ($ph_array[$i] != NULL) {
            $color = 'FF99CC';
            $cal_description = $ph_array[$i];
            $objPHPExcel->getActiveSheet()->getComment($col[$c] . $i)->getText()->createTextRun($cal_description);
        } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $day_num, $year0), 1) == 'Sunday') {
            $color = 'FFCC99';
        } else {
            $color = 'FFFFCC';
        }

        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, "$i");
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$c])->setWidth(5);
        $c++;
    }

    for ($i = 1; $i < $param['cutoff']; $i++) {

        if ($ph_array[$i] != NULL) {
            $color = 'FF99CC';
            $cal_description = $ph_array[$i];
            $objPHPExcel->getActiveSheet(1)->getComment($col[$c] . $i)->getText()->createTextRun($cal_description);
        } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $day_num, $year0), 1) == 'Sunday') {
            $color = 'FFCC99';
        } else {
            $color = 'FFFFCC';
        }

        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, "$i");
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->getStartColor()->setRGB("$color");
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet(1)->getColumnDimension("$col[$c]")->setWidth(5);
        $c++;
    }

    // ADD COLUMNS TOTAL
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, (string) t("TOTAL HOURS"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$c])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setSize(10);
    $cc = $c + 1;
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, (string) t("Normal"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cc])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setSize(10);
    $cc = $cc + 1;
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, (string) t("PH"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cc])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setSize(10);
    $cc = $cc + 1;
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, (string) t("Sunday"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cc])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setSize(10);

    //EMPLOYEE LIST
    $query = "SELECT id,name,origin,ic_no FROM {ek_hr_workforce} WHERE company_id=:coid AND location=:l AND active=:a";
    $a = array(':coid' => $coid, ':l' => $location, ':a' => 'working');
    $employees = Database::getConnection('external_db', 'external_db')->query($query, $a);

    $this_date = '';

    while ($line2 = $employees->fetchAssoc()) {
        //data BEFORE CUT OFF
        $l++;
        $total_h = 0;
        $normal_h = 0;
        $ph_h = 0;
        $sunday_h = 0;
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[0] . $l, $line2['id'] . ' ' . $line2['name']);
        $c = 1;
        for ($i = $param['cutoff']; $i <= $d0; $i++) {
            $this_date = $month0 . "-" . $year0 . "-" . $i;
            $r = $roster_array[$this_date][$line2['id']];
            $this_time = '';
            if (!empty($r)) {
                $time = explode(',', $r['roster']);
                $this_time = timed($time[0], $time[1], $time[2], $time[3], $time[4], $time[5]);
                if ($this_time == '00:00') {
                    $this_time = $r['status'];
                } elseif ($r['status'] != '') {
                    $this_time = $r['status'] . "-" . $this_time;
                }
            } else {
                $this_time = $r['status'];
            }
            if ($r['status'] != '') {
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->getStartColor()->setRGB("969696");
            }

            $PHPDateValue = strtotime($this_time);
            $ExcelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($PHPDateValue);
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, $this_time);
            $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setSize(8);
            $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getNumberFormat()->setFormatCode('[h]:mm');
            // compute total hours
            $total_h += sum_h('n', $this_time);
            if ($day[$i] == 'ph') {
                $ph_h += sum_h('ph', $this_time);
            } elseif ($day[$i] == 's') {
                $sunday_h +=sum_h('s', $this_time);
            } else {
                $normal_h+=sum_h('n', $this_time);
            }

            $c++;
        }

        for ($i = 1; $i < $param['cutoff']; $i++) {
            $this_date = $month . "-" . $year . "-" . $i;
            $r = $roster_array[$this_date][$line2['id']];
            $this_time = '';
            if (!empty($r)) {
                $time = explode(',', $r['roster']);
                $this_time = timed($time[0], $time[1], $time[2], $time[3], $time[4], $time[5]);
                if ($this_time == '00:00') {
                    $this_time = $r['status'];
                } elseif ($r['status'] != '') {
                    $this_time = $r['status'] . "-" . $this_time;
                }
            } else {
                $this_time = $r['status'];
            }
            if ($r['status'] != '') {
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFill()->getStartColor()->setRGB("969696");
            }
            $ExcelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($this_time);
            $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getNumberFormat()->setFormatCode('[h]:mm');
            $objPHPExcel->getActiveSheet(1)->getStyle($col[$c] . $l)->getFont()->setSize(8);
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, $this_time);
            // compute total hours
            $total_h += sum_h('n', $this_time);
            if ($day[$i] == 'ph') {
                $ph_h += sum_h('ph', $this_time);
            } elseif ($day[$i] == 's') {
                $sunday_h += sum_h('s', $this_time);
            } else {
                $normal_h += sum_h('n', $this_time);
            }
            $c++;
        }

        // ADD TOTAL HOURS

        $normal_h = $normal_h / 3600;
        $total_h = $total_h / 3600;
        $sunday_h = $sunday_h / 3600;
        $ph_h = $ph_h / 3600;
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, "$total_h");
        $cc = $c + 1;
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, "$normal_h");
        $cc = $cc + 1;
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, "$ph_h");
        $cc = $cc + 1;
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$c] . $l, "$sunday_h");
        $cc = $cc + 1;
    } // END EMPLOYEE LIST
    $l++;
} //END LOCATION

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", $date);

$objPHPExcel->setActiveSheetIndex(0);

$fileName = 'Roster_' . str_replace(' ', '_', $company_name) . '-' . $year . '-' . $month . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;
?>
