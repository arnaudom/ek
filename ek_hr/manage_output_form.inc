<?php

/* Manage output of payslip in 2 format, Pdf and xls
 * get data from modules and generate a pdf output base on fpdf library and output templates
 * variables : source = ie. invoice, purchase, quotation; id = source id, signature = bolean; stamps = 
 * 1,2,3 ; mode = print = 0 or save = 1
 */

use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\ek_finance\FinanceSettings;

$param = unserialize($param);

$coid = $param[0];
$month = $param[1];
$template = $param[2];

if ($template != NULL) {



    $format = explode("_", $template);

    /*
     * Data
     */

    $query = "SELECT * from {ek_company} where id=:id";
    $company = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $coid))->fetchObject();


    $query = "SELECT p.id as eid,name,ic_no,epf_no,epf_yee, epf_er "
            . "FROM  {ek_hr_workforce_pay} p "
            . "INNER JOIN {ek_hr_workforce} w "
            . "ON p.id=w.id "
            . "WHERE company_id=:coid and month=:period";

    $a = array(
        ':period' => $month,
        ':coid' => $coid
    );
    $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

    $query = "SELECT count(p.id) "
            . "FROM  {ek_hr_workforce_pay} p "
            . "INNER JOIN {ek_hr_workforce} w "
            . "ON p.id=w.id "
            . "WHERE company_id=:coid and month=:period";
    $rows = Database::getConnection('external_db', 'external_db')
            ->query($query, $a)
            ->fetchField();

    if ($rows < 1) {
//data not in current payroll
        $query = "SELECT emp_id as eid ,name,ic_no,epf_no,epf_yee, epf_er "
                . "FROM {ek_hr_post_data} p "
                . "INNER JOIN {ek_hr_workforce} w "
                . "ON p.emp_id=w.id where company_id=:coid and month=:period";

        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

        $query = "SELECT count(p.id) "
                . "FROM {ek_hr_post_data} p "
                . "INNER JOIN {ek_hr_workforce} w "
                . "ON p.emp_id=w.id where company_id=:coid and month=:period";

        $rows = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchField();
    }

    $userName = \Drupal::currentUser()->getUsername();
    $settings = new FinanceSettings();
    $baseCurrency = $settings->get('baseCurrency');
    $path = PrivateStream::basePath();

    include_once drupal_realpath("private://hr/forms/" . $template);
    $fileName = 'Borang A ' . $month . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Cache-Control: max-age=0');
    header("Content-Disposition: attachment;filename=$fileName");
    header('Cache-Control: max-age=0');
    $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
    $objWriter->save('php://output');
    exit;
}
  