<?php

  /* Manage output of payslip in 2 format, Pdf and xls
  * get data from modules and generate a pdf output base on fpdf library and output templates
  * variables : coid = company entity id, from = employee id, to = employee id, month = 
  * payroll month , ex. 2015-01, template = output format
  */
use Drupal\Core\Database\Database;
use Drupal\ek_hr\HrSettings;


$param = unserialize($param);
    $coid = $param[0];
    $month = $param[1];
    $template = $param[2];
    $from = $param[3];
    $to = $param[4];


if ($template == NULL || $template == 'default') {
  $template = 'default-payslip_pdf';
}

$format = explode("_" , $template);

/*
* data
*/
if ($format[1] == 'pdf') {

$query = "SELECT * from {ek_hr_workforce_pay} INNER JOIN {ek_hr_workforce} "
        . "ON ek_hr_workforce.id=ek_hr_workforce_pay.id "
        . "WHERE ek_hr_workforce_pay.id>=:i and ek_hr_workforce_pay.id<=:j "
        . "AND month<>'' and company_id=:c  order by ek_hr_workforce_pay.id";
$a = array(
  ':i' => $from,
  ':j' => $to,
  ':c' => $coid
);
$m = Database::getConnection('external_db', 'external_db')
        ->query($query, $a)
        ->fetchField();

$query = "SELECT * from {ek_company} where id=:id";
$company = Database::getConnection('external_db', 'external_db')
        ->query($query, array(':id' => $coid ))->fetchObject();        
//queries
if($m) {


$query = "SELECT * from {ek_hr_workforce_pay} INNER JOIN {ek_hr_workforce} "
        . "ON ek_hr_workforce.id=ek_hr_workforce_pay.id "
        . "WHERE ek_hr_workforce_pay.id>=:i and ek_hr_workforce_pay.id<=:j "
        . "AND month<>'' and company_id=:c  order by ek_hr_workforce_pay.id";
$a = array(
  ':i' => $from,
  ':j' => $to,
  ':c' => $coid
);

$payroll = Database::getConnection('external_db', 'external_db')->query($query, $a);

} else {
$query = "SELECT * from {ek_hr_post_data} INNER JOIN {ek_hr_workforce} "
        . "ON ek_hr_workforce.id=ek_hr_post_data.emP_id "
        . "WHERE ek_hr_post_data.emp_id>=:i and ek_hr_post_data.emp_id<=:j "
        . "AND month=:m and company_id=:c  order by emp_id";
$a = array(
  ':i' => $from,
  ':j' => $to,
  ':m' => $month,
  ':c' => $coid
);
$archive = TRUE;
$payroll = Database::getConnection('external_db', 'external_db')->query($query, $a);
}

$data = $payroll;
$param = NEW HrSettings($coid);
$category = array('a', 'b','c','d','e');

foreach($category as $c) {

$settings[$c] = array(
    'LAF1' => $param->get('ad', 'LAF1-'.$c, 'description'),
    'LAF2' => $param->get('ad', 'LAF2-'.$c, 'description'),
    'LAF3' => $param->get('ad', 'LAF3-'.$c, 'description'),
    'LAF4' => $param->get('ad', 'LAF4-'.$c, 'description'),
    'LAF5' => $param->get('ad', 'LAF5-'.$c, 'description'),
    'LAF6' => $param->get('ad', 'LAF6-'.$c, 'description'),     
    'LAF1_val' => $param->get('ad', 'LAF1-'.$c, 'value'),
    'LAF2_val' => $param->get('ad', 'LAF2-'.$c, 'value'),
    'LAF3_val' => $param->get('ad', 'LAF3-'.$c, 'value'),
    'LAF4_val' => $param->get('ad', 'LAF4-'.$c, 'value'),
    'LAF5_val' => $param->get('ad', 'LAF5-'.$c, 'value'),
    'LAF6_val' => $param->get('ad', 'LAF6-'.$c, 'value'), 
    'custom_a' => array(
    1 => $param->get('ad', 'LAC1-'.$c, 'description'),
    2 => $param->get('ad', 'LAC2-'.$c, 'description'),
    3 => $param->get('ad', 'LAC3-'.$c, 'description'),
    4 => $param->get('ad', 'LAC4-'.$c, 'description'),
    5 => $param->get('ad', 'LAC5-'.$c, 'description'),
    6 => $param->get('ad', 'LAC6-'.$c, 'description'),
    7 => $param->get('ad', 'LAC7-'.$c, 'description'),
    8 => $param->get('ad', 'LAC8-'.$c, 'description'),
    9 => $param->get('ad', 'LAC9-'.$c, 'description'),
    10 => $param->get('ad', 'LAC10-'.$c, 'description'),
    11 => $param->get('ad', 'LAC11-'.$c, 'description'),
    12 => $param->get('ad', 'LAC12-'.$c, 'description'),
    13 => $param->get('ad', 'LAC13-'.$c, 'description'),
    ),

    'custom_d' => array(
    1 => $param->get('ad', 'LDC1-'.$c, 'description'),    
    2 => $param->get('ad', 'LDC2-'.$c, 'description'),   
    3 => $param->get('ad', 'LDC3-'.$c, 'description'),    
    4 => $param->get('ad', 'LDC4-'.$c, 'description'),     
    5 => $param->get('ad', 'LDC5-'.$c, 'description'),   
    6 => $param->get('ad', 'LDC6-'.$c, 'description'),     
    ),
    'LDF1' => $param->get('ad', 'LDF1-'.$c, 'description'),     
    'LDF2' => $param->get('ad', 'LDF2-'.$c, 'description'), 
    'fund1_name' => $param->get('param', 'c', 'value'),
    'fund1_calc' => $param->get('param', 'd', 'value'),
    'fund1_pc_yer' => $param->get('param', 'e', 'value'),
    'fund1_pc_yee' => $param->get('param', 'f', 'value'),
    'fund1_base' => $param->get('param', 'g', 'value'),
    'fund2_name' => $param->get('param', 'h', 'value'),
    'fund2_calc' => $param->get('param', 'i', 'value'),
    'fund2_pc_yer' => $param->get('param', 'j', 'value'),
    'fund2_pc_yee' => $param->get('param', 'k', 'value'),
    'fund2_base' => $param->get('param', 'l', 'value'), 
    'fund3_name' => $param->get('param', 'q', 'value'),
    'fund3_calc' => $param->get('param', 'r', 'value'),
    'fund3_pc_yer' => $param->get('param', 's', 'value'),
    'fund3_pc_yee' => $param->get('param', 't', 'value'),
    'fund3_base' => $param->get('param', 'u', 'value'), 
    'tax_name' => $param->get('param', 'm', 'value'), 
    'tax_calc' => $param->get('param', 'n', 'value'),     
    'tax_base' => $param->get('param', 'o', 'value'), 
    'tax_pc' => $param->get('param', 'p', 'value'), 
    );

}


if ($template == 'default-payslip_pdf') {
    include_once drupal_get_path('module', 'ek_hr') .'/'. $template  . '.inc';
} else {    
    include_once drupal_realpath("private://hr/payslips/" . $template . '.inc');
}
  header('Cache-Control: private');
  header('Content-Type: application/pdf');
  //header("Content-Disposition: 'attachment'; filename='$fileName' ");

  $f = 'payslips_' . $month . '.pdf';
    echo $pdf->Output($f, 'I');

  exit ;
 
}


  