<?php

/**
 * @file
 * Hooks provided by the ek_hr module.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Calculate a tax value.
 * @param array $param
 *  The employee tax payroll data.
 *  'coid' : company id
 *  'value' : salary value
 *  'field1' : category of tax profile (A-Z)
 * @see \Drupal\ek_hr\Controller\PayrollController::payroll()
 * @see \Drupal\ek_hr\Form\PayrollRecord::readtable()
 * @return NULL or array
 * 
 */
function hook_payroll_tax($param) {
    
    //define table per country
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_country', 'a');
    $query->fields('a',['code']);
    $query->leftJoin('ek_company', 'b', 'a.name = b.country');
    $query->condition('b.id', $param['coid']);
    $code = $query->execute()->fetchField();
    $table = 'ek_hr_income_tax_' . strtolower($code);
    $schema = Database::getConnection('external_db','external_db')->schema();
   
   if ($schema->tableExists($table)) {
       
       $query = Database::getConnection('external_db', 'external_db')
                    ->select($table, 't');
        $query->fields('t',['min', 'max', 'rate']);
        $result = $query->execute();
        $cumul = 0;
        $tax_total = 0;
        while ($r = $result->fetchObject()){
            $slice = min($r->max - $r->min, $param['value'] - $cumul);
            $cumul += $slice;
            $tax = round($slice * $r->rate,2);
            $tax_total += $tax;
        }
       
        return array('amount1' => $tax_total, 'amount2' => 0);
       
   } else {
       return NULL;
   }
     
}

/**
 * Compile a fund values.
 * fund contribution are often divided in two values, one for employer, one for employee
 * In most cases it is an amount set between a minimum and maximum wage bracket
 * @param array $param
 *  The employee tax payroll data.
 *  'coid' : company id
 *  'type' : fund type/name (fund1, 2, 3)
 *  'value' : amount used to calculate fund 
 *  'field1' : fund category for employer
 *  'field2' : fund category for employee
 * @see \Drupal\ek_hr\Controller\PayrollController::payroll()
 * @see \Drupal\ek_hr\Form\PayrollRecord::readtable()
 * @return NULL or array
 * 
 */
function hook_payroll_fund($param) {
    
    //define table per country
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_country', 'a');
    $query->fields('a',['code']);
    $query->leftJoin('ek_company', 'b', 'a.name = b.country');
    $query->condition('b.id', $param['coid']);
    $code = $query->execute()->fetchField();
    $table = 'ek_hr_' . $param['type'] . '_' . strtolower($code);
    
    $schema = Database::getConnection('external_db','external_db')->schema();
    $fund_1 = 0;
    $fund_2 = 0;   
    if ($schema->tableExists($table)) {
        
        $fields = [$param['field1']];
        $query = Database::getConnection('external_db', 'external_db')
                    ->select($table, 't');
        if ($param['field2'] !='') {
            $fields[] = $param['field2'];
        }
        $query->fields('t',$fields);
        $query->condition('min', $param['value'], '<');
        $query->condition('max', $param['value'], '>=');
        $result = $query->execute()->fetchObject();
        
        if($result->$param['field1'] != NULL) {
            $fund_1 = $result->$param['field1'];
        } 
        if($param['field2'] !='' && $result->$param['field2'] != NULL) {
            $fund_2 = $result->$param['field2'];
        } 
        
        return array('amount1' => $fund_1, 'amount2' => $fund_2);
        
    } else {
       return NULL;
    }
    
    
}

/**
 * list available fund or tax tables per country.
 * @param array $param
 *  The country code.
 * @see \Drupal\ek_hr\Controller\ParametersController::fundHr()
 * @see \Drupal\ek_hr\Form\FilterFund
 * @return NULL or array
 * 
 */
function hook_list_fund($param) {
        
        $list = NULL;
        $country_code = ''; //i.e. 'sg'
        if ($param ==  $country_code){
        $list = [
            'fund1_' . $country_code => 'Social security ' . $country_code,
            'fund2_' . $country_code => 'Pension fund ' . $country_code,
            'fund3_' . $country_code => 'Fund 3 ' . $country_code,
            'fund4_' . $country_code => 'Fund 4  ' . $country_code,
            'fund5_' . $country_code => 'Fund 5  ' . $country_code,
            'tax_' . $country_code => 'Income tax ' . $country_code,
        ];
        }
        return $list;
}
/**
 * @} End of "addtogroup hooks".
 */