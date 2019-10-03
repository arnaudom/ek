<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

$access = AccessCheck::GetCompanyByUser();
$param = unserialize($param);
$roster = new \Drupal\ek_hr\RosterManager();
$settings = NEW HrSettings($param['coid']);
$HrRoster = $settings->HrRoster;
$coid = $param['coid'];
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_company', 'a');
$query->fields('a', ['name'])
        ->condition('id', $coid);
$company_name = $query->execute()->fetchField();
$location_array = []; //store locations for download
$date_array = []; //store calendar days
$list2_roster_array = []; // store work data for list 2 format
$days = ['1' => t('Monday'), '2' => t('Tuesday'),'3' => t('Wednesday'),'4' => t('Thursday'),'5' => t('Friday'),'6' => t('Saturday'),'7' => t('Sunday'),];
$work_status = ['o' => t('Off'), 'm' => t('Medical'), 'l' => t('Leave'), 'a' => t('Absent')];
$work_status_back = ['o' => 'cccccc', 'm' => 'ffff66', 'l' => '66ff00', 'a' => 'ff0000'];
$input = $param['month'];
$c = $param['cutoff'];
$date = date_create($input . '-01');
$t = date_format($date, 't');
$Y = date_format($date, 'Y');
$n = date_format($date, 'n');

if ($c == $t) {
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
    $month0 = $n - 1;
    $month = $n;
    if ($month0 == 0) {
        $month0 = 12;
        $Y0 = $Y - 1;
    };
    $n0 = $Y0 . '-' . $month0 . '-1';
    $t0 = date_format(date_create($n0), 't');
    $s0 = $c;
    $c0 = $t0;
    $s = 1;
    $c = $c - 1;
    if ($c == 0) {
        $c = 1;
    }
}

//Collect data 
$query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r')
                ->fields('r')->fields('w', ['location', 'name', 'custom_id']);
$query->leftJoin('ek_hr_workforce', 'w', 'r.emp_id = w.id');
$condition = $query->orConditionGroup()
        ->condition('period', $month0 . '-' . $Y . '%', 'LIKE')
        ->condition('period', $month0 . '-' . $Y0 . '%', 'LIKE')
        ->condition('period', $month . '-' . $Y . '%', 'LIKE');
$query->condition($condition);
$query->condition('company_id', $coid);
$query->condition('active', 'working','=');
$query->orderBy('id');
$periods = $query->execute();

$roster_array = [];

WHILE ($r = $periods->fetchObject()) {
    $audit = '';
    if (isset($r->audit) && $r->audit != '') {
        $parts = explode('|', $r->audit);
        $audit = date('Y-m-d [H:m]', $parts[1]) . " " . t('by') . " " . $parts[0];
    }

    $roster_array[$r->period][$r->emp_id] = [
        'custom_id' => $r->custom_id,
        'roster' => $r->roster,
        'status' => $r->status,
        'location' => $r->location,
        'name' => $r->name,
        'time' => '',
        'note' => $r->note,
        'audit' => $audit
    ];
}


//Get day type
$dayType = $roster->dayType($Y0 . '-' . $month0, $Y . '-' . $month, $s0, $s, $c0, $c, $coid);
$col = Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
$colors = array('990033', '990099', '000099', '0000FF', 'FF0000', '6600CC', '006600', '003399','CC0099','333333','663300',
    '990033', '990099', '000099', '0000FF', 'FF0000', '6600CC', '006600', '003399','CC0099','333333','663300',
    '990033', '990099', '000099', '0000FF', 'FF0000', '6600CC', '006600', '003399','CC0099','333333','663300',
    '990033', '990099', '000099', '0000FF', 'FF0000', '6600CC', '006600', '003399','CC0099','333333','663300',);


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
        'size' => 12,
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

    //store location in array
    $location_array[] = $l->location;

    //previous month////////////////////////////////////////////////////////////////////////////////////////////////////////
    if ($month0 != $month) {
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
            if ($day_num <= $c) {
                //the date is not covered by the query
                $color = 'bdbdbd';
            } elseif ($dayType[$day_num] == 'n') {
                $color = 'FFFFCC';
            } elseif ($dayType[$day_num] == 's') {
                //Sunday
                $color = 'FFCC99';
            } else {
                //public holiday
                $color = 'FF99CC';
                //$cal_description = $ph_array[$day_num];
                $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $i)->getText()->createTextRun($dayType[$day_num]);
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

                if (isset($roster_array[$period])) {
                    foreach ($roster_array[$period] as $id => $row) {
                        $time = '';
                        if ($row['location'] == $l->location && $row['roster'] != '') {
                            $time = $roster->shift($row['roster']);
                            if ($time <> '') {
                                $eid = ($row['custom_id'] != '') ? $row['custom_id'] : $id;
                                $objName = $objRichText->createTextRun($eid . " - " . $row['name']);
                                $objName->getFont()->setBold(true);
                                $objName->getFont()->setSize(8);
                                $objName->getFont()->getColor()->setRGB($colors[$co]);
                                $objRichText->createText("\r\n$time");
                                $roster_array[$period][$id]['time'] = $time;
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
            ->setCellValue("A$i", "Roster " . $Y . " - " . $title . " - " . $l->location . " - " . $company_name);
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

        if ($day_num > $c) {
            //the date is not covered by the query
            $color = 'bdbdbd';
        } elseif ($dayType[$day_num] == 'n') {
            $color = 'FFFFCC';
        } elseif ($dayType[$day_num] == 's') {
            //Sunday
            $color = 'FFCC99';
        } else {
            //public holiday
            $color = 'FF99CC';
            //$cal_description = $dayType[$day_num];
            $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $i)->getText()->createTextRun($dayType[$day_num]);
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

            if (isset($roster_array[$period])) {
                foreach ($roster_array[$period] as $id => $row) {
                    $time = '';
                    if ($row['location'] == $l->location && $row['roster'] != '') {
                        $time = $roster->shift($row['roster']);
                        if ($time <> '') {
                            $eid = ($row['custom_id'] != '') ? $row['custom_id'] : $id;
                            $objName = $objRichText->createTextRun($eid . " - " . $row['name']);
                            $objName->getFont()->setBold(true);
                            $objName->getFont()->setSize(8);
                            $objName->getFont()->getColor()->setRGB($colors[$co]);
                            $objRichText->createText("\r\n $time");

                            //addd record to output list 2
                            $roster_array[$period][$id]['time'] = $time;
                        }
                        $co++;
                        if ($co == 19) {
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

    $objPHPExcel->setActiveSheetIndex(1)->setCellValue("A$l", "Roster " . $Y0 . " - " . $title . " - " . $location . " - " . "[" . t('cut-off') . " :" . $c . "/" . $month0 . "] " . $company_name);
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setName('Arial');
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setSize(12);
    $objPHPExcel->getActiveSheet(1)->getStyle("A$l")->getFont()->setBold(true);
    $l++;
    $day = array();
    //HEADER
    $cnt = 1;

    for ($i = $s0; $i <= $c0; $i++) {

        if ($dayType[$i] == 'n') {
            $color = 'FFFFCC';
        } elseif ($dayType[$i] == 's') {
            //Sunday
            $color = 'FFCC99';
        } else {
            //public holiday
            $color = 'FF99CC';
            $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $l)->getText()->createTextRun($dayType[$i]);
        }

        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $i);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($color);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(5);
        $cnt++;
    }

    for ($i = $s; $i <= $c; $i++) {

        if ($dayType[$i] == 'n') {
            $color = 'FFFFCC';
        } elseif ($dayType[$i] == 's') {
            //Sunday
            $color = 'FFCC99';
        } else {
            //public holiday
            $color = 'FF99CC';
            $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $l)->getText()->createTextRun($dayType[$i]);
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
    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $days[$HrRoster[$coid]['last_day']]);
    $objPHPExcel->getActiveSheet(1)->getColumnDimension($col[$cnt])->setWidth(10);
    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(10);

    //EMPLOYEE LIST
    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_hr_workforce', 'w')
            ->fields('w', ['id', 'custom_id', 'name', 'origin', 'ic_no', 'location'])
            ->condition('company_id', $coid)
            ->condition('active', 'working');
    $query->orderBy('name');
    $employees = $query->execute();

    $this_date = '';
    $totalCol = [];
    
    while ($line2 = $employees->fetchAssoc()) {
        if ($location == $line2['location']) {
            //data BEFORE CUT OFF
            $l++;
            $total_h = 0;
            $normal_h = 0;
            $ph_h = 0;
            $sunday_h = 0;
            $eid = ($line2['custom_id'] != '') ? $line2['custom_id'] : $line2['id'];
            $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[0] . $l, $eid . ' ' . $line2['name']);
            $cnt = 1;
            for ($i = $s0; $i <= $c0; $i++) {
                $this_date = $month0 . "-" . $Y0 . "-" . $i;
                if (isset($roster_array[$this_date][$line2['id']])) {
                    $r = $roster_array[$this_date][$line2['id']];
                    $this_time = 0;
                    if (!empty($r) && !null == $r['roster']) {
                        $this_time = $roster->timed($r['roster'], FALSE, $HrRoster[$coid]['roster_hours_format']);
                        if ($this_time == '00:00' || $this_time == 0) {
                            $this_time = $r['status'];
                        } elseif ($r['status'] != '') {
                            $this_time = $r['status'] . "-" . $this_time;
                        }
                    } else {
                        $this_time = $r['status'];
                    }
                    if ($r['status'] != '') {
                        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($work_status_back[$r['status']]);
                    }

                    if (!null == $r['roster'] && $r['note'] == '') {
                        //add edit tracking
                        $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $r['audit']);
                        //$objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
                    } elseif (null == $r['roster'] && !null == $r['note']) {
                        $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $r['note']);
                    } elseif (!null == $r['roster'] && !null == $r['note']) {
                        $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $r['audit']);
                        $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
                        $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $r['note']);
                    }


                    $PHPDateValue = strtotime($this_time);
                    $ExcelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($PHPDateValue);
                    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $this_time);
                    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(8);
                    if ($HrRoster[$coid]['roster_hours_format'] == '1') {
                        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getNumberFormat()->setFormatCode('[h]:mm');
                    }

                    // compute total hours
                    $total_h += $roster->to_second($this_time);
                    if (isset($dayType[$i]) && $dayType[$i] == 'n') {
                        $normal_h += $roster->to_second($this_time);
                    } elseif (isset($day[$i]) && $day[$i] == 's') {
                        $sunday_h += $roster->to_second($this_time);
                    } else {
                        $ph_h += $roster->to_second($this_time);
                    }
                    $totalCol[$i] = isset($totalCol[$i]) ? $totalCol[$i] + $roster->to_second($this_time) : $roster->to_second($this_time);
                } else {
                    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, 0);
                }
                $cnt++;
            }

            for ($i = $s; $i <= $c; $i++) {
                $this_date = $month . "-" . $Y . "-" . $i;
                if (isset($roster_array[$this_date][$line2['id']])) {
                    $r = $roster_array[$this_date][$line2['id']];
                    $this_time = 0;
                    if (!empty($r) && !null == $r['roster']) {
                        $time = explode(',', $r['roster']);
                        $this_time = $roster->timed($r['roster'], FALSE, $HrRoster[$coid]['roster_hours_format']);
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
                        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($work_status_back[$r['status']]);
                    }

                    if (!null == $r['roster'] && $r['note'] == '') {
                        //add edit tracking
                        $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $r['audit']);
                        //$objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
                    } elseif (null == $r['roster'] && !null == $r['note']) {
                        $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $r['note']);
                    } elseif (!null == $r['roster'] && !null == $r['note']) {
                        $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $r['audit']);
                        $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
                        $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $r['note']);
                    }


                    $ExcelDateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($this_time);
                    $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getFont()->setSize(8);
                    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $this_time);
                    if ($HrRoster[$coid]['roster_hours_format'] == '1') {
                        $objPHPExcel->getActiveSheet(1)->getStyle($col[$cnt] . $l)->getNumberFormat()->setFormatCode('[h]:mm');
                    }
                    // compute total hours
                    $total_h += $roster->to_second($this_time);
                    if (isset($dayType[$i]) && $dayType[$i] == 'n') {
                        $normal_h += $roster->to_second($this_time);
                    } elseif (isset($dayType[$i]) && $dayType[$i] == 's') {
                        $sunday_h += $roster->to_second($this_time);
                    } else {
                        $ph_h += $roster->to_second($this_time);
                    }
                    $totalCol[$i] = isset($totalCol[$i]) ? $totalCol[$i] + $roster->to_second($this_time) : $roster->to_second($this_time);
                } else {
                    $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, 0);
                }
                $cnt++;
            }

            // ADD TOTAL HOURS
            if ($HrRoster[$coid]['roster_hours_format'] == '2') {
                $total_h = $total_h / 3600;
                $normal_h = $normal_h / 3600;
                $sunday_h = $sunday_h / 3600;
                $ph_h = $ph_h / 3600;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $total_h);
                $cnt++;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $normal_h);
                $cnt++;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $ph_h);
                $cnt++;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $sunday_h);
            } else {
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $roster->to_hms($total_h));
                $cnt++;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $roster->to_hms($normal_h));
                $cnt++;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $roster->to_hms($ph_h));
                $cnt++;
                $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $roster->to_hms($sunday_h));
            }
        }
    } // END EMPLOYEE LIST
    
    $l++;
    //column total
    $cnt = 0;
    $totalLast = 0;
    if ($HrRoster[$coid]['roster_hours_format'] == '2') {
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[0] . $l, t('TOTAL in hours'));
    } else {
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[0] . $l, t('TOTAL H:m:s'));
    }

    for ($i = $s0; $i <= $c0; $i++) {
        $cnt++;
        $h = 0;
        if (isset($totalCol[$i]) && $totalCol[$i] != 0) {
            $totalLast = $totalLast + $totalCol[$i];
            if ($HrRoster[$coid]['roster_hours_format'] == '2') {
                $h = round($totalCol[$i]/3600,2);
            } else {
                $h = $roster->to_hms($totalCol[$i]);
            }
        }

        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $h);
    }
    for ($i = $s; $i <= $c; $i++) {
        $cnt++;
        $h = 0;
        if (isset($totalCol[$i]) && $totalCol[$i] != 0) {
            $totalLast = $totalLast + $totalCol[$i];
            if ($HrRoster[$coid]['roster_hours_format'] == '2') {
                $h = round($totalCol[$i]/3600,2);
            } else {
                $h = $roster->to_hms($totalCol[$i]);
            }
        }
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue($col[$cnt] . $l, $h);
    }
    
} //END LOCATION


///////////////////////////////////////////////////////////////////////////////////////////////////////
// LIST VIEW 2//////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////
$objPHPExcel->createSheet();
$objPHPExcel->setActiveSheetIndex(2);
$objPHPExcel->getActiveSheet(2)->setTitle((string) t('Summary'));
$l = 1;

$totalCol = [];

//reshape the array data
foreach ($roster_array as $p => $data) {
    $d = explode('-', $p);
    foreach ($data as $id => $val) {
        $eid = ($val['custom_id'] != '') ? $val['custom_id'] : $id;
        $list2_roster_array[$eid . ' - ' . $val['name']][$d[2]] = [
            'location' => $val['location'],
            'time' => $val['time'],
            'roster' => $val['roster'],
            'note' => $val['note'],
            'audit' => $val['audit'],
            'status' => $val['status']
        ];
    }
}

//foreach ($location_array as $key => $location) {

$objPHPExcel->setActiveSheetIndex(2)->setCellValue("A$l", "Roster " . $Y0 . " - " . $title . " - " . "[" . t('cut-off') . " :" . $c . "/" . $month0 . "] " . $company_name);
$objPHPExcel->getActiveSheet(2)->getStyle("A$l")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet(2)->getStyle("A$l")->getFont()->setSize(12);
$objPHPExcel->getActiveSheet(2)->getStyle("A$l")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet(2)->getColumnDimension($col[0])->setWidth(15);
$l++;
//HEADER
$cnt = 1;
for ($i = $s0; $i <= $c0; $i++) {

    if ($dayType[$i] == 'n') {
        $color = 'FFFFCC';
    } elseif ($dayType[$i] == 's') {
        //Sunday
        $color = 'FFCC99';
    } else {
        //public holiday
        $color = 'FF99CC';
        $objPHPExcel->getActiveSheet()->getComment($col[$cnt] . $l)->getText()->createTextRun($dayType[$i]);
    }

    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $i);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($color);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $objPHPExcel->getActiveSheet(2)->getColumnDimension($col[$cnt])->setWidth(15);
    $cnt++;
}

for ($i = $s; $i <= $c; $i++) {

    if ($dayType[$i] == 'n') {
        $color = 'FFFFCC';
    } elseif ($dayType[$i] == 's') {
        //Sunday
        $color = 'FFCC99';
    } else {
        //public holiday
        $color = 'FF99CC';
        $objPHPExcel->getActiveSheet(2)->getComment($col[$cnt] . $l)->getText()->createTextRun($dayType[$i]);
    }

    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $i);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($color);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $objPHPExcel->getActiveSheet(2)->getColumnDimension($col[$cnt])->setWidth(15);
    $cnt++;
}

// ADD COLUMNS TOTAL
$objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, (string) t("TOTAL HOURS"));
$objPHPExcel->getActiveSheet(2)->getColumnDimension($col[$cnt])->setWidth(15);
$objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFont()->setSize(10);

//loop list 2 data

foreach ($list2_roster_array as $e => $val) {

    //display only if location is set
    if ($val[$c]['location'] == $param['location'] || $param['location'] == 'ANY') {
        $l++;
        $cnt = 0;
        $color = 0;
        $totalRow = 0;
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[0] . $l, $e);
        for ($i = $s0; $i <= $c0; $i++) {
            $objRichText = '';
            $cnt++;
            if($val[$i]['status'] != '') {
                $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($work_status_back[$val[$i]['status']]);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $work_status[$val[$i]['status']]);
            } elseif ($val[$i]['time'] != '') {
                /* */
                $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $objName = $objRichText->createTextRun($val[$i]['location']);
                $objName->getFont()->setBold(true);
                $objName->getFont()->setSize(8);
                $objName->getFont()->getColor()->setRGB($colors[$i]);
                $objRichText->createText("\r\n" . $val[$i]['time']);
                $objPHPExcel->getActiveSheet(2)->getRowDimension($l)->setRowHeight(50);
                $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getAlignment()->setWrapText(true);
                
                $objPHPExcel->getActiveSheet(2)->getCell($col[$cnt] . $l)->setValue($objRichText);
                
                //compile totals
                $t = $roster->timed($val[$i]['roster'], TRUE, $HrRoster[$coid]['roster_hours_format']);
                $totalRow += $t;
                $totalCol[$i] = isset($totalCol[$i]) ? $totalCol[$i] + $t : $t;
            }
            
            $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFont()->setSize(8);
            $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->applyFromArray($styleThinBlackBorderOutline);
                    
            if (!null == $val[$i]['roster'] && $val[$i]['note'] == '') {
                //add edit tracking
                $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $val[$i]['audit']);
                
            } elseif (null == $val[$i]['roster'] && !null == $val[$i]['note']) {
                $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $val[$i]['note']);
            } elseif (!null == $val[$i]['roster'] && !null == $val[$i]['note']) {
                $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $val[$i]['audit']);
                $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
                $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $val[$i]['note']);
            }
            
            
        }

        for ($i = $s; $i <= $c; $i++) {
            $objRichText = '';
            $cnt++;
            if($val[$i]['status'] != '') {
                $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFill()->getStartColor()->setRGB($work_status_back[$val[$i]['status']]);
                $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $work_status[$val[$i]['status']]);
            } elseif ($val[$i]['time'] != '') {
                /* */
                $objRichText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $objName = $objRichText->createTextRun($val[$i]['location']);
                $objName->getFont()->setBold(true);
                $objName->getFont()->setSize(8);
                $objName->getFont()->getColor()->setRGB($colors[$i]);
                $objRichText->createText("\r\n" . $val[$i]['time']);
                $objPHPExcel->getActiveSheet(2)->getRowDimension($l)->setRowHeight(50);
                $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getAlignment()->setWrapText(true);
                
                $objPHPExcel->getActiveSheet(2)->getCell($col[$cnt] . $l)->setValue($objRichText);
                //compile totals
                $t = $roster->timed($val[$i]['roster'], TRUE, $HrRoster[$coid]['roster_hours_format']);
                $totalRow += $t;
                $totalCol[$i] = isset($totalCol[$i]) ? $totalCol[$i] + $t : $t;
            }

            $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->getFont()->setSize(8);
            $objPHPExcel->getActiveSheet(2)->getStyle($col[$cnt] . $l)->applyFromArray($styleThinBlackBorderOutline);
            
            if (!null == $val[$i]['roster'] && $val[$i]['note'] == '') {
                //add edit tracking
                $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $val[$i]['audit']);
                //$objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
            } elseif (null == $val[$i]['roster'] && !null == $val[$i]['note']) {
                $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $val[$i]['note']);
            } elseif (!null == $val[$i]['roster'] && !null == $val[$i]['note']) {
                $objCommentRichText = $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Last edit') . ":" . $val[$i]['audit']);
                $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun("\r\n");
                $objPHPExcel->getActiveSheet(1)->getComment($col[$cnt] . $l)->getText()->createTextRun(t('Note') . ":" . $val[$i]['note']);
            }
            
        }

        //row total
        $cnt++;
        if ($HrRoster[$coid]['roster_hours_format'] == '2') {
            $h = round($totalRow / 3600, 2);
        } else {
            $h = gmdate('H:i', $totalRow);
        }
        $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $h);
    }
}

//columns total
$l++;
$cnt = 0;
$totalLast = 0;
if ($HrRoster[$coid]['roster_hours_format'] == '2') {
    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[0] . $l, t('TOTAL in hours'));
} else {
    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[0] . $l, t('TOTAL H:m:s'));
}

for ($i = $s0; $i <= $c0; $i++) {
    $cnt++;
    $h = 0;
    if (isset($totalCol[$i])) {
        $totalLast = $totalLast + $totalCol[$i];
        if ($HrRoster[$coid]['roster_hours_format'] == '2') {
            $h = round($totalCol[$i] / 3600, 2);
        } else {
            $h = $roster->to_hms($totalCol[$i]);
        }
    }

    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $h);
}
for ($i = $s; $i <= $c; $i++) {
    $cnt++;
    $h = 0;
    if (isset($totalCol[$i])) {
        $totalLast = $totalLast + $totalCol[$i];
        if ($HrRoster[$coid]['roster_hours_format'] == '2') {
            $h = round($totalCol[$i] / 3600, 2);
        } else {
            $h = $roster->to_hms($totalCol[$i]);
        }
    }
    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $h);
}
$cnt++;
if ($HrRoster[$coid]['roster_hours_format'] == '2') {
    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, round($totalLast / 3600, 2));
} else {
    $objPHPExcel->setActiveSheetIndex(2)->setCellValue($col[$cnt] . $l, $roster->to_hms($totalLast));
}

//}//per location



$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(2)->setCellValue("A$l", $date);
$objPHPExcel->setActiveSheetIndex(2)->freezePane('B3');
$objPHPExcel->setActiveSheetIndex(1)->freezePane('B3');
$objPHPExcel->setActiveSheetIndex(0);

$fileName = 'Roster_' . str_replace(' ', '_', $company_name) . '-' . $Y . '-' . $month . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;

