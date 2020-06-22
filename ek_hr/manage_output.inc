<?php

/* Manage output of payslip in 2 format, Pdf and xls
 * get data from modules and generate a pdf output base on pdf library and output templates
 * variables : coid = company entity id, from = employee id, to = employee id, month = 
 * payroll month , ex. 2015-01, template = output format
 */

use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\ek_hr\HrSettings;
use Drupal\Core\Extension\ModuleHandler;

$param = unserialize($param);
$coid = $param[0];
$month = $param[1];
$template = $param[2];
$from = $param[3];
$to = $param[4];

$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_hr_payroll_cycle', 'c')
        ->fields('c',['current']);
$cycle = $query->execute()->fetchField();

if ($template == NULL || $template == 'default') {
    $template = 'default-payslip_pdf';
}

$format = explode("_", $template);

/*
 * data
 */
if ($format[1] == 'pdf') {

    if($cycle == $month) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_pay', 'p');
        $query->fields('p');
        $query->innerJoin('ek_hr_workforce', 'w', 'w.id=p.id');
        $query->fields('w')
                ->condition('p.id', $from, '>=')
                ->condition('p.id', $to, '<=')
                ->condition('company_id', $coid)
                ->condition('month', $month);
        $data = $query->execute();
    
    } else {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_post_data', 'p');
        $query->fields('p');
        $query->innerJoin('ek_hr_workforce', 'w', 'w.id=p.emp_id');
        $query->fields('w')
                ->condition('p.emp_id', $from, '>=')
                ->condition('p.emp_id', $to, '<=')
                ->condition('company_id', $coid)
                ->condition('month', $month);
        $data = $query->execute();
        $archive = TRUE;
    }
    
    $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_company', 'c');
        $query->fields('c');
        $query->condition('id', $coid);
    $company = $query->execute()->fetchObject();
    
    $apiData['salary'] = new stdClass();
    $apiData['salary']->month = $month;
    if ($invoke = $this->moduleHandler->invokeAll('hr_history', [$apiData])) {
        $apiData = $invoke;
    }
    $param = NEW HrSettings($coid);
    $category = array('a', 'b', 'c', 'd', 'e');

    foreach ($category as $c) {

        $settings[$c] = array(
            'LAF1' => $param->get('ad', 'LAF1-' . $c, 'description'),
            'LAF2' => $param->get('ad', 'LAF2-' . $c, 'description'),
            'LAF3' => $param->get('ad', 'LAF3-' . $c, 'description'),
            'LAF4' => $param->get('ad', 'LAF4-' . $c, 'description'),
            'LAF5' => $param->get('ad', 'LAF5-' . $c, 'description'),
            'LAF6' => $param->get('ad', 'LAF6-' . $c, 'description'),
            'LAF1_val' => $param->get('ad', 'LAF1-' . $c, 'value'),
            'LAF2_val' => $param->get('ad', 'LAF2-' . $c, 'value'),
            'LAF3_val' => $param->get('ad', 'LAF3-' . $c, 'value'),
            'LAF4_val' => $param->get('ad', 'LAF4-' . $c, 'value'),
            'LAF5_val' => $param->get('ad', 'LAF5-' . $c, 'value'),
            'LAF6_val' => $param->get('ad', 'LAF6-' . $c, 'value'),
            'custom_a' => array(
                1 => $param->get('ad', 'LAC1-' . $c, 'description'),
                2 => $param->get('ad', 'LAC2-' . $c, 'description'),
                3 => $param->get('ad', 'LAC3-' . $c, 'description'),
                4 => $param->get('ad', 'LAC4-' . $c, 'description'),
                5 => $param->get('ad', 'LAC5-' . $c, 'description'),
                6 => $param->get('ad', 'LAC6-' . $c, 'description'),
                7 => $param->get('ad', 'LAC7-' . $c, 'description'),
                8 => $param->get('ad', 'LAC8-' . $c, 'description'),
                9 => $param->get('ad', 'LAC9-' . $c, 'description'),
                10 => $param->get('ad', 'LAC10-' . $c, 'description'),
                11 => $param->get('ad', 'LAC11-' . $c, 'description'),
                12 => $param->get('ad', 'LAC12-' . $c, 'description'),
                13 => $param->get('ad', 'LAC13-' . $c, 'description'),
            ),
            'custom_d' => array(
                1 => $param->get('ad', 'LDC1-' . $c, 'description'),
                2 => $param->get('ad', 'LDC2-' . $c, 'description'),
                3 => $param->get('ad', 'LDC3-' . $c, 'description'),
                4 => $param->get('ad', 'LDC4-' . $c, 'description'),
                5 => $param->get('ad', 'LDC5-' . $c, 'description'),
                6 => $param->get('ad', 'LDC6-' . $c, 'description'),
            ),
            'LDF1' => $param->get('ad', 'LDF1-' . $c, 'description'),
            'LDF2' => $param->get('ad', 'LDF2-' . $c, 'description'),
            'fund1_name' => $param->get('param', 'fund_1', ['name', 'value']),
            'fund1_calc' => $param->get('param', 'fund_1', ['calcul', 'value']),
            'fund1_pc_yer' => $param->get('param', 'fund_1', ['employer', 'value']),
            'fund1_pc_yee' => $param->get('param', 'fund_1', ['employee', 'value']),
            'fund1_base' => $param->get('param', 'fund_1', ['base', 'value']),
            'fund2_name' => $param->get('param', 'fund_2', ['name', 'value']),
            'fund2_calc' => $param->get('param', 'fund_2', ['calcul', 'value']),
            'fund2_pc_yer' => $param->get('param', 'fund_2', ['employer', 'value']),
            'fund2_pc_yee' => $param->get('param', 'fund_2', ['employee', 'value']),
            'fund2_base' => $param->get('param', 'fund_2', ['base', 'value']),
            'fund3_name' => $param->get('param', 'fund_3', ['name', 'value']),
            'fund3_calc' => $param->get('param', 'fund_3', ['calcul', 'value']),
            'fund3_pc_yer' => $param->get('param', 'fund_3', ['employer', 'value']),
            'fund3_pc_yee' => $param->get('param', 'fund_3', ['employee', 'value']),
            'fund3_base' => $param->get('param', 'fund_3', ['base', 'value']),
            'fund4_name' => $param->get('param', 'fund_4', ['name', 'value']),
            'fund4_calc' => $param->get('param', 'fund_4', ['calcul', 'value']),
            'fund4_pc_yer' => $param->get('param', 'fund_4', ['employer', 'value']),
            'fund4_pc_yee' => $param->get('param', 'fund_4', ['employee', 'value']),
            'fund4_base' => $param->get('param', 'fund_4', ['base', 'value']),
            'fund5_name' => $param->get('param', 'fund_5', ['name', 'value']),
            'fund5_calc' => $param->get('param', 'fund_5', ['calcul', 'value']),
            'fund5_pc_yer' => $param->get('param', 'fund_5', ['employer', 'value']),
            'fund5_pc_yee' => $param->get('param', 'fund_5', ['employee', 'value']),
            'fund5_base' => $param->get('param', 'fund_5', ['base', 'value']),
            'tax_calc' => $param->get('param', 'tax', ['calcul', 'value']),
            'tax_base' => $param->get('param', 'tax', ['base', 'value']),
            'tax_pc' => $param->get('param', 'tax', ['employee', 'value']),
            'tax_pcr' => $param->get('param', 'tax', ['employer', 'value']),
        );
    }


    if ($template == 'default-payslip_pdf') {
        include_once drupal_get_path('module', 'ek_hr') . '/' . $template . '.inc';
    } else {
        //if print template is a custom file, copy from private to public before include
        //use this feature when storage of data is remote.
        $filesystem = \Drupal::service('file_system');
        $path = PublicStream::basePath() . "/" . $template;
        $filesystem->copy("private://hr/payslips/" . $source . '/' . $template, $path, FileSystemInterface::EXISTS_REPLACE);
        $template = $path;
        include_once $template;
    }
    header('Cache-Control: private');
    header('Content-Type: application/pdf');
    //header("Content-Disposition: 'attachment'; filename='$fileName' ");

    $f = 'payslips_' . $month . '.pdf';
    echo $pdf->Output($f, 'I');

    exit;
}


  