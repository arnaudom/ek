<?php

namespace Drupal\ek_assets;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_admin\CompanySettings;
use DateTime;
use DateInterval;
use DatePeriod;

/*
 */

/**
 * Amortization and depreciation schedule formulas
 *
 * 
 */
class Amortization {

    /**
     * Database Service Object.
     *
     * @var \Drupal\Core\Database\Database
     */
    protected $database;

    /**
     * Constructs.
     *
     * 
     */
    public function __construct(Database $database) {
        $this->appdata = $database->getConnection('external_db', 'external_db');
        $this->finance = new FinanceSettings();
    }

    /**
     * return an array of amortization schedule
     *
     * @param method : 1 = straight line 
     * @param term_unit : y = year, m = month
     * @param term : number of units
     * @param value : asset value minus salvage
     * @param date : start date
     * @param coid : company id
     *
     */
    public static function schedule($method = 1, $term_unit = NULL, $term = NULL, $value = NULL, $date = NULL, $coid = NULL) {


        $time = strtotime($date);
        $day = date('j', $time);
        $month = date('n', $time);
        $year = date('Y', $time);
        $company = new CompanySettings($coid);
        $fiscalYear = $company->get('fiscal_year');
        $fiscalMonth = $company->get('fiscal_month');

        $next_closing = $year . '-' . $fiscalMonth . '-' . cal_days_in_month(CAL_GREGORIAN, ltrim($fiscalMonth, '0'), $fiscalYear);

        //Set the starting date base on the purchase date
        //if purchased before 15, count full month
        //otherwise set start on following month
        if (date('j', $time) <= 15) {
            $start = date('Y', $time) . '-' . date('m', $time) . '-01' ;
        } else {
            
            $month += 1;
            if ($month == 13) {
                $month = 1;
                $year += 1;
            }
            $start = $year . '-' . $month . '-01' ;
        }


        switch ($method) {

            case '1':
                //Yearly Straight Line depreciation is calculated using the following formula:
                //Net Value x (Number of Periods to Depreciate / Remaining Life)  

                if ($term_unit == 'Y') {
                    $periods = 12 * $term;
                    $years_count = $term;
                } else {
                    $periods = $term;
                    $years_count = $term / 12;
                }

                $depreciation = array();
                $depreciation['start'] = $start;

                $i = 0;
                $sum = 0;
                
                while ($periods > 0) {
                    
                    
                    $NPeriods = self::months_count($start, $next_closing);
                    if($NPeriods > $periods) {
                        $NPeriods = $periods;
                    }
                    $amortisation = round($value * ($NPeriods / $periods));
                    $record_date = $year . '-' . $fiscalMonth . '-' . cal_days_in_month(CAL_GREGORIAN, ltrim($fiscalMonth, '0'), $year);

                    $depreciation['a'][$i] = [
                        'periods_base' => $NPeriods,
                        'periods_balance' => $periods,
                        'value' => $amortisation,
                        'record_date' => $record_date,
                        'journal_reference' => '',
                    ];

                    $sum += $amortisation;
                    $year++;
                    $i++;
                    $periods = $periods - $NPeriods;
                    $start = $next_closing;
                    $next_closing = date('Y-m-d', strtotime($next_closing ." + 12 months")) ;
                    $value = $value - $amortisation;
                }

                $depreciation['years'] = $i;
                $depreciation['total'] = $sum;

                break;
        }


        return $depreciation;
    }

    
    
    public static function months_count($date1, $date2) {
        $begin = new DateTime($date1);
        $end = new DateTime($date2);
        //$end = $end->modify('+1 month');

        $interval = DateInterval::createFromDateString('1 month');

        $period = new DatePeriod($begin, $interval, $end);
        $counter = 0;
        foreach ($period as $dt) {
            $counter++;
        }

        return $counter;
    }

    /**
     * verify if an assets is under maortization
     *
     * @param id : asset id 
     * @return : TRUE or FALSE
     */
    public static function is_amortized($id) {
        $query = "SELECT id,amort_record from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid WHERE id = :id ORDER by id";
               
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query , [':id' => $id]);
                
        $r = $data->fetchObject();
        $schedule = unserialize($r->amort_record);
        $ref = FALSE;
            foreach ($schedule['a'] as $key => $value) {
                if($value['journal_reference'] != '') {
                   $ref = TRUE;
                }
            }
        
        return $ref;
        
    }

}

// class