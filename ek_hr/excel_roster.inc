<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;


$access = AccessCheck::GetCompanyByUser();
//$access = implode(',', $access);
$param = unserialize($param);

$coid = $param['coid'];
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'a');
$query->fields('a',['name'])
    ->condition('id', $coid);
$company_name = $query->execute()->fetchField();
$location_array = array();

$input = $param['month'];
$c = $param['cutoff'];
$date = date_create($input . '-01');
$t =  date_format($date, 't');
$Y =  date_format($date, 'Y');
$n =  date_format($date, 'n');

if($c == $t) {
    //full month
    $Y0 = $Y;
    $month0 = $n;
    $month = $n;
    $s0 = 1;
    $c0 = 15;
    $s = 16;
    $c = $t;
} else {
    //span on 2 months
    //retrieve previous
    $Y0 = $Y;
    $month0 = $n-1;
    $month = $n;
    if($month0 == 0) { 
        $month0 = 12; 
        $Y0 = $Y - 1;
    };
    $n0 = $Y0 .'-'. $month0 . '-1';
    $t0 = date_format(date_create($n0), 't');
    $s0 = $c;
    $c0 = $t0;
    $s = 1;
    $c = $c-1;
    if($c == 0) {
        $c = 1;
    }
}

//Collect data 
$query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r')->fields('w',['location','name']);
            $query->leftJoin('ek_hr_workforce', 'w', 'r.emp_id = w.id');
            $condition = $query->orConditionGroup()
                ->condition('period', $month0 . '-' . $Y . '%', 'LIKE')
                ->condition('period', $month0 . '-' . $Y0 . '%','LIKE')
                ->condition('period', $month . '-' . $Y . '%','LIKE');
            $query->condition($condition);
            $query->condition('company_id', $coid);
            $query->orderBy('id');
            $periods = $query->execute(); 

$roster_array = array();

WHILE ($r = $periods->fetchObject()) {
    $roster_array[$r->period][$r->emp_id] = [
        'roster' => $r->roster,
        'status' => $r->status,
        'location' => $r->location,
        'name' => $r->name,
    ];
}


// Public Holidays
//store the public holidays and sundays dates to optimize query
$ph_array = array();
$query_ph = "SELECT * FROM {ek_hr_workforce_ph} WHERE date=:d AND coid=:coid";

for ($i = $s0; $i  <= $c0; $i++) {
    
    $a = array(':d' => $Y0 . '-' . $month0 . '-' . $i, ':coid' => $coid);
    $ph = Database::getConnection('external_db', 'external_db')->query($query_ph, $a)->fetchObject();
    if ($ph) {
        $day = date('j', strtotime($ph->date));
        $ph_array[$day] = $ph->description;
    }
}

for ($i = $s; $i  <= $c; $i++) {

    $a = array(':d' => $Y. '-' . $month . '-' . $i, ':coid' => $coid);
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
    
    $s = is_numeric($t0[0]) ? $t0[0] : 0;
    $m = is_numeric($t0[1]) ? $t0[1] : 0;
    $ta = $s * 3600 + $m * 60;
    $s = is_numeric($t1[0]) ? $t1[0] : 0;
    $m = is_numeric($t1[1]) ? $t1[1] : 0;
    $tb = $s * 3600 + $m * 60;
    $s = is_numeric($t2[0]) ? $t2[0] : 0;
    $m = is_numeric($t2[1]) ? $t2[1] : 0;
    $tc = $s * 3600 + $m * 60;
    $s = is_numeric($t3[0]) ? $t3[0] : 0;
    $m = is_numeric($t3[1]) ? $t3[1] : 0;
    $td = $s * 3600 + $m * 60;
    $s = is_numeric($t4[0]) ? $t4[0] : 0;
    $m = is_numeric($t4[1]) ? $t4[1] : 0;
    $te = $s * 3600 + $m * 60;
    $s = is_numeric($t5[0]) ? $t5[0] : 0;
    $m = is_numeric($t5[1]) ? $t5[1] : 0;
    $tf = $s * 3600 + $m * 60;

    $total = ($tb - $ta) + ($td - $tc) + ($tf - $te);
    if ($total == 86400) {
        return "24:00";
    } else {
        return gmdate('H:i', $total);
    }
}

function sum_h($t, $h) {

    
    $t_ = explode(":", $h);
    $s = 0;
    $m = 0;
    if(is_array($t_)) {
        $s = is_numeric($t_[0]) ? $t_[0] : 0;
        if(isset($t_[1])){
            $m = is_numeric($t_[1]) ? $t_[1] : 0;
        }
        
    }
    return  ($s * 3600 + $m * 60);
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
$styleColorHeader = array(
                'font' => array(
                    'bold' => true,
                    'size'  => 12,
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
            );

$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_hr_workforce', 'w')
                    ->fields('w', ['location'])
                    ->distinct()
                    ->condition('company_id', $access, 'IN')
                    ->condition('company_id', $coid);
            
            if ($param['location'] == 'ANY') {
                $query->condition('location', '%', 'LIKE');
            } else {
                $query->condition('location', $param['location'], 'LIKE');
            }
            $query->orderBy('location');
            $locations = $query->execute();


//LOOP LOCATIONS
// set initial line number
$i = 1;

while ($l = $locations->fetchObject()) {

    //store data
    $location_array[] = $l->location;
    
    //previous month////////////////////////////////////////////////////////////////////////////////////////////////////////
    if($month0 != $month) {
        $first_day = mktime(0, 0, 0, $month0, 1, $Y0);
        $name_of_month = date('F', $first_day);
        $day_of_week = date('D', $first_day);
        $days_in_month = cal_days_in_month(0, $month0, $Y0);
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
                ->setCellValue("A$i", "Roster " . $Y0 . " - " . $name_of_month . " - " . $l->location . " - " . $company_name);
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
                ->setCellValue("F$i", "Fri")
                ->setCellValue("G$i", "Sat");

        $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->applyFromArray($styleColorHeader);
               
        $i++;
        $cnt = 0;
        while ($blank > 0) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$cnt] . $i, "");
            $blank = $blank - 1;
            $day_count++;
            $cnt++;
        }
        $day_num = 1;
        while ($day_num <= $days_in_month) {
            if($day_num <= $c) {
                //the date is not covered by the query
                $color = 'bdbdbd';
            } elseif (isset($ph_array[$day_num])) {
                //it is a public holiday date
                $color = 'FF99CC';
                $cal_description = $ph_array[$day_num];
                $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $i)->getText()->createTextRun($cal_description);
            } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $day_num, $Y0), 1) == 'Sunday') {
                //it is a Sunday date
                $color = 'FFCC99';
            } else {
                //it is a normal day
                $color = 'FFFFCC';
            }

            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$cnt] . $i, $day_num);
            $objPHPExcel->getActiveSheet(0)->getStyle($col[$cnt] . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet(0)->getStyle($col[$cnt] . $i)->getFill()->getStartColor()->setRGB($color);
            $objPHPExcel->getActiveSheet(0)->getStyle($col[$cnt] . $i)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet(0)->getStyle($col[$cnt] . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


            //INSERT ROSTER DATA///////////////////////////////////////////////////////
            if ($day_num >= $param['cutoff']) {
                $display = '';
                $period = $month0 . "-" . $Y0 . "-" . $day_num;
                $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $objRichText->createText('');
                $co = 0;

                if(isset($roster_array[$period])) {
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
                }
            } else {
                $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $objRichText->createText('-');
            }
            $ii = $i + 1;

            $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $ii)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getRowDimension($ii)->setRowHeight(50);
            $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $ii)->getFont()->setSize(8);
            $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $ii)->applyFromArray($styleThinBlackBorderOutline);
            //$objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$c] . $ii, "$display");
            $objPHPExcel->getActiveSheet()->getCell($col[$cnt] . $ii)->setValue($objRichText);
            $day_num++;
            $day_count++;
            $cnt++;
            if ($cnt > 6) {
                $day_count = 1;
                $i+=2;
                $cnt = 0;
            }
        }

        $i+=2;
    }
    
    //current month/////////////////////////////////////////////////////////////////////////////////////////////////////////
    $first_day = mktime(0, 0, 0, $month, 1, $Y);
    $title = date('F', $first_day);
    $day_of_week = date('D', $first_day);
    $days_in_month = cal_days_in_month(0, $month, $Y);
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
            ->setCellValue("A$i", "Roster " . $Y. " - " . $title . " - " . $l->location . " - " . $company_name);
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
    $objPHPExcel->getActiveSheet(0)->getStyle("A$i:G$i")->applyFromArray($styleColorHeader);
    $i++;
    $cnt = 0;
    while ($blank > 0) {

        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$cnt] . $i, "");
        $blank = $blank - 1;
        $day_count++;
        $cnt++;
    }
    $day_num = 1;
    while ($day_num <= $days_in_month) {
        
        if($day_num > $c) {
                //the date is not covered by the query
                $color = 'bdbdbd';
            } elseif (isset($ph_array[$day_num])) {
                //it is a public holiday date
                $color = 'FF99CC';
                $cal_description = $ph_array[$day_num];
                $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $i)->getText()->createTextRun($cal_description);
            } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month, $day_num, $Y), 1) == 'Sunday') {
                //it is a Sunday date
                $color = 'FFCC99';
            } else {
                //it is a normal day
                $color = 'FFFFCC';
            }

        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($col[$cnt] . $i, $day_num);
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $i)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $i)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


        //INSERT HERE THE CODE FOR ROSTER DATA
        if ($day_num <= $param['cutoff']) {
            $display = '';
            $period = $month . "-" . $Y . "-" . $day_num;
            $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $co = 0;

            if(isset($roster_array[$period])) {
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
                        if ($co == 19){
                            $co = 0;
                        }
                    }
                }
            }
        } else {
            $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $objRichText->createText('-');
        }
        $ii = $i + 1;
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $ii)->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getRowDimension($ii)->setRowHeight(50);
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $ii)->getFont()->setSize(8);
        $objPHPExcel->getActiveSheet()->getStyle($col[$cnt] . $ii)->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getCell($col[$cnt] . $ii)->setValue($objRichText);
        $day_num++;
        $day_count++;
        $cnt++;
        if ($cnt > 6) {
            $day_count = 1;
            $i+=2;
            $cnt = 0;
        }
    }

    $i+=5;
} // end location loop


$date = date('Y-m-d h:i:s');
$i+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $date);

$day_mum = 1;
// Rename first worksheet
$objPHPExcel->getActiveSheet()->setTitle((string) t('Calendar view'));



///////////////////////////////////////////////////////////////////////////////////////////////////////
// LIST VIEW //////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////
// Create a new worksheet, after the default sheet

$objPHPExcel->createSheet();
$objPHPExcel->setActiveSheetIndex(1);
$objPHPExcel->getActiveSheet(1)->setTitle((string) t('List view'));
$objPHPExcel->getActiveSheet(1)->getColumnDimension("A")->setWidth(25);
$l = 1;
 
foreach ($location_array as $key => $location) {

    $objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", "Roster " . $Y0 . " - " . $title . " - " . $location . " - " . $company_name);
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setName('Arial');
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setSize(12);
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setBold(true);
    $l++;
    $day = array();
    //HEADER
    $cnt = 1;

    for ($i = $s0; $i  <= $c0; $i++) {

        if (isset($ph_array[$i])) {
            $color = 'FF99CC';
            $cal_description = $ph_array[$i];
            $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $l)->getText()->createTextRun($cal_description);
        } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month0, $i, $Y0), 1) == 'Sunday') {
            $color = 'FFCC99';
        } else {
            $color = 'FFFFCC';
        }

        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $i);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(5);
        $cnt++;
    }

    for ($i = $s; $i  <= $c; $i++) {

        if (isset($ph_array[$i])) {
            $color = 'FF99CC';
            $cal_description = $ph_array[$i];
            $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun($cal_description);
        } elseif (jddayofweek(cal_to_jd(CAL_GREGORIAN, $month, $i, $Y), 1) == 'Sunday') {
            $color = 'FFCC99';
        } else {
            $color = 'FFFFCC';
        }

        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $i);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(5);
        $cnt++;
    }
/* */ 
    // ADD COLUMNS TOTAL
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, (string) t("TOTAL HOURS"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(10);
    $cnt++; 
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, (string) t("Normal"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(10);
    $cnt++;
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, (string) t("PH"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(10);
    $cnt++;
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, (string) t("Sunday"));
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(10);

    //EMPLOYEE LIST
    $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce', 'w')
                ->fields('w',['id','name','origin','ic_no','location'])
                ->condition('company_id',$coid)
                ->condition('active','working');
            $query->orderBy('name');
            $employees = $query->execute();    


    $this_date = '';

    while ($line2 = $employees->fetchAssoc()) {
        if($location == $line2['location']){
            //data BEFORE CUT OFF
            $l++;
            $total_h = 0;
            $normal_h = 0;
            $ph_h = 0;
            $sunday_h = 0;
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[0] . $l, $line2['id'] . ' ' . $line2['name']);
            $cnt = 1;
            for ($i = $s0; $i  <= $c0; $i++) {
                $this_date = $month0 . "-" . $Y0 . "-" . $i;
                $r = $roster_array[$this_date][$line2['id']];
                $this_time = '';
                if (!empty($r) && !null == $r['roster']) {
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
                    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB("969696");
                }

                $PHPDateValue = strtotime($this_time);
                $ExcelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($PHPDateValue);
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $this_time);
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(8);
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getNumberFormat()->setFormatCode('[h]:mm');
                // compute total hours
                $total_h += sum_h('n', $this_time);
                if (isset($day[$i]) && $day[$i] == 'ph') {
                    $ph_h += sum_h('ph', $this_time);
                } elseif (isset($day[$i]) && $day[$i] == 's') {
                    $sunday_h +=sum_h('s', $this_time);
                } else {
                    $normal_h+=sum_h('n', $this_time);
                }

                $cnt++;
            }

            for ($i = $s; $i  <= $c; $i++) {
                $this_date = $month . "-" . $Y. "-" . $i;
                $r = $roster_array[$this_date][$line2['id']];
                $this_time = '';
                if (!empty($r) && !null == $r['roster']) {
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
                    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB("969696");
                }
                $ExcelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($this_time);
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getNumberFormat()->setFormatCode('[h]:mm');
                $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(8);
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $this_time);
                // compute total hours
                $total_h += sum_h('n', $this_time);
                if (isset($day[$i]) && $day[$i] == 'ph') {
                    $ph_h += sum_h('ph', $this_time);
                } elseif (isset($day[$i]) && $day[$i] == 's') {
                    $sunday_h += sum_h('s', $this_time);
                } else {
                    $normal_h += sum_h('n', $this_time);
                }
                $cnt++;
            }

            // ADD TOTAL HOURS

            $normal_h = $normal_h / 3600;
            $total_h = $total_h / 3600;
            $sunday_h = $sunday_h / 3600;
            $ph_h = $ph_h / 3600;
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $total_h);
            $cnt++;
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $normal_h);
            $cnt++;
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $ph_h);
            $cnt++;
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $sunday_h);
            $cnt++;
        }
    
    } // END EMPLOYEE LIST
    $l++;

} //END LOCATION

$date = date('Y-m-d h:i:s');
$l+=2;
//$objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", $date);

//$objPHPExcel->setActiveSheetIndex(0);

$fileName = 'Roster_' . str_replace(' ', '_', $company_name) . '-' . $Y. '-' . $month . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;

