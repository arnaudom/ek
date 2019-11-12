<?php

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\Journal;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;


$journal = new Journal();
$settings = new CompanySettings($coid);
$access = AccessCheck::GetCompanyByUser();
$company = implode(',',$access);
$Currencies = CurrencyData::listcurrency(1);
/*
 * starting date is based on fiscal year settings 
 * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
 * and has started on 1/07/13
 */
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $settings->get('fiscal_month') , $settings->get('fiscal_year') );
$from = date('Y-m-d', strtotime($settings->get('fiscal_year') . '-' . $settings->get('fiscal_month') . '-' . $daysInMonth . ' - 1 year + 1 day'));
$to = date('Y-m-d');
$total_assets = 0;
$total_assets_exc = 0;
$total_closing_assets = 0;
$total_closing_assets_exc = 0;
$total_liabilities = 0;
$total_liabilities_exc = 0;
$total_closing_liabilities = 0;
$total_closing_liabilities_exc = 0;
$total_tax_collect = 0;
$total_tax_collect_exc = 0;
$total_closing_tax_collect = 0;
$total_closing_tax_collect_exc = 0;
$total_tax_deduct = 0;
$total_tax_deduct_exc = 0;
$total_closing_tax_deduct = 0;
$total_closing_tax_deduct_exc = 0;
$grand_total = 0;
$grand_total_exc = 0;
$items['from'] = $from;
$items['to'] = $to;
//caculate current year earnings
 $items['current_earnings'] = $journal->current_earning($coid,$from,$to);
   
/*
 * Cash - bank
 */

$query = "SELECT DISTINCT aid FROM {ek_bank_accounts} a INNER JOIN {ek_bank} b "
        . "ON a.bid = b.id WHERE coid=:coid";
$a = array(':coid' => $coid);
$result = Database::getConnection('external_db', 'external_db')
        ->query($query, $a);
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
    while ($r = $result->fetchObject()) {
   
        $histo = $journal->history(serialize(array( 
                    'aid' => $r->aid,
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to
                    )
                  )
                );
        
        
    $h = unserialize($histo);
    $total += $h['total_transaction'];
    $total_exc += $h['total_transaction_exchange'];
    $closing += $h['closing'];
    $closing_exc += $h['closing_exchange'];
    $param = serialize(
            array (
             'id' => 'bs',
             'from' => $from,
             'to' => $to,
             'coid' => $coid,
             'aid' => $r->aid,
           ));
    $link =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
    $items['cash_bank'][] = [
        'aid' => $r->aid,
        'name' => AidList::aname($coid,$r->aid),
        'balance' => -$h['total_transaction'],
        'balance_base' => -$h['total_transaction_exchange'],
        'closing' => -$h['closing'],
        'closing_base' => -$h['closing_exchange'],
        'link' => $link,
    ];
    
    }
    
    $items['cash_bank_total'] = [
        'balance' => -$total,
        'balance_base' => -$total_exc,
        'closing' => -$closing,
        'closing_base' => -$closing_exc,
    ];
    
    $total_assets += -$total;
    $total_assets_exc += -$total_exc;
    $total_closing_assets += -$closing;
    $total_closing_assets_exc += -$closing_exc;

/*
 * Cash - account
 */
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
$list = [];
foreach($Currencies as $currency => $name) {
    
    $aid = $settings->get('cash_account',$currency);
    
    if($aid && !in_array($aid,$list)) {
        
        //avoid duplicate account for same coid
        array_push($list,$aid);
            $histo = $journal->history(serialize(array( 
                    'aid' => $aid,
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to
                    )
                  )
                );
        
        
    $h = unserialize($histo);    
    $total += $h['total_transaction'];
    $total_exc += $h['total_transaction_exchange'];
    $closing += $h['closing'];
    $closing_exc += $h['closing_exchange'];
    $param = serialize(
            array (
             'id' => 'bs',
             'from' => $from,
             'to' => $to,
             'coid' => $coid,
             'aid' => $aid,
             
           ));
    $link =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();    
    $items['cash'][] = [
        'aid' => $aid,
        'name' => AidList::aname($coid,$aid),
        'balance' => -$h['total_transaction'],
        'balance_base' => -$h['total_transaction_exchange'],
        'closing' => -$h['closing'],
        'closing_base' => -$h['closing_exchange'],
        'link' => $link,
    ];
    
    }
    }
    $items['cash_total'] = [
        'balance' => -$total,
        'balance_base' => -$total_exc, 
        'closing' => -$closing,
        'closing_base' => -$closing_exc,
    ];
    
    $total_assets += -$total;
    $total_assets_exc += -$total_exc;
    $total_closing_assets += -$closing;
    $total_closing_assets_exc += -$closing_exc;
    
/*
 * Receivable
 */
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
$list = [];
foreach($Currencies as $currency => $name) {
    
    $aid = $settings->get('asset_account',$currency);
    
    if($aid && !in_array($aid,$list)) {
        
        //avoid duplicate account for same coid
        array_push($list,$aid);
        $histo = $journal->history(serialize(array( 
                    'aid' => $aid,
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to
                    )
                  )
                );
        
        
    $h = unserialize($histo);    
    $total += $h['total_transaction'];
    $total_exc += $h['total_transaction_exchange'];
    $closing += $h['closing'];
    $closing_exc += $h['closing_exchange'];
    $param = serialize(
            array (
             'id' => 'bs',
             'from' => $from,
             'to' => $to,
             'coid' => $coid,
             'aid' => $aid,
           ));
    $link =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();    
    $items['receivable'][] = [
        'aid' => $aid,
        'name' => AidList::aname($coid,$aid),
        'balance' => -$h['total_transaction'],
        'balance_base' => -$h['total_transaction_exchange'],
        'closing' => -$h['closing'],
        'closing_base' => -$h['closing_exchange'],
        'link' => $link,
    ];
    
    }
    }
    $items['receivable_total'] = [
        'balance' => -$total,
        'balance_base' => -$total_exc,
        'closing' => -$closing,
        'closing_base' => -$closing_exc,
    ];
    
    $total_assets += -$total;
    $total_assets_exc += -$total_exc;
    $total_closing_assets += -$closing;
    $total_closing_assets_exc += -$closing_exc;

/*
 * Payable
 */

$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
$list = [];
foreach($Currencies as $currency => $name) {
    
    $aid = $settings->get('liability_account',$currency);
    
    if($aid && !in_array($aid,$list)) {
        
        //avoid duplicate account for same coid
        array_push($list,$aid);
        $histo = $journal->history(serialize(array(
            'aid' => $aid,
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                )
            )
        );


        $h = unserialize($histo);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];
        $param = serialize(
                array (
                 'id' => 'bs',
                 'from' => $from,
                 'to' => $to,
                 'coid' => $coid,
                 'aid' => $aid,
               ));
        $link =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();    
        $items['payable'][] = [
            'aid' => $aid,
            'name' => AidList::aname($coid,$aid),
            'balance' => -$h['total_transaction'],
            'balance_base' => -$h['total_transaction_exchange'],
            'closing' => -$h['closing'],
            'closing_base' => -$h['closing_exchange'],
            'link' => $link,
        ];

        }
    }
    $items['payable_total'] = [
        'balance' => -$total,
        'balance_base' => -$total_exc, 
        'closing' => -$closing,
        'closing_base' => -$closing_exc,
    ];
    
    $total_liabilities += -$total;
    $total_liabilities_exc += -$total_exc;
    $total_closing_liabilities += -$closing;
    $total_closing_liabilities_exc += -$closing_exc;

/*
 * Depreciation
 */
    
    if(isset($amortization)) {
        
        $items['amortization'] = 0;
        $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid "
                . "WHERE amort_record <> :r "
                . "AND amort_status <> :s "
                . "AND coid = :coid";
        $a = array(
            ':r' => '',
            ':s' => 1,
            ':coid' => $coid,
        );
        
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, $a);
        
        While($d = $data->fetchObject()) {
               
            $schedule = unserialize($d->amort_record);
            foreach ($schedule['a'] as $key => $value) {
                if($value['journal_reference'] == '' && $value['periods_balance'] > 0) {
                
                    $date = strtotime($value['record_date']);
                    if($date >= $now = date('U')) {
                        $rate = CurrencyData::rate($d->currency);
                         
                        $items['amortization'] += round($value['value']/$rate, $rounding);
                        break;
                        
                        
                    }            
                }
            }
        }
    }
    
/*
 * Average expenses 
 */    
    
$start = date('Y-m', strtotime($to . ' - 1 year'));

$query = "SELECT sum(value) as expenses FROM {ek_journal} "
        . "WHERE coid=:coid AND aid like :aid and type=:type and date >= :d1 and date <= :d2";
$a = [
    ':coid' => $coid,
    ':aid' => $chart['expenses'] . '%',
    ':type' => 'debit',
    ':d1' => $start,
    ':d2' => $to
];

$expenses = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a)
                        ->fetchField();


/*
 * Tax 
 */
$items['collect_tax_1'] = AidList::aname($coid,$settings->get('stax_collect_aid'));
$items['deduct_tax_1'] = AidList::aname($coid,$settings->get('stax_deduct_aid'));
$items['collect_tax_1_aid'] = $settings->get('stax_collect_aid');
$items['deduct_tax_1_aid']  = $settings->get('stax_deduct_aid');


    $histo = $journal->history(serialize(array(
            'aid' => $items['collect_tax_1_aid'],
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                )
            )
        );
    $h = unserialize($histo);
        $total_tax_collect = $h['total_transaction'];
        $total_tax_collect_exc = $h['total_transaction_exchange'];
        $total_closing_tax_collect = $h['closing'];
        $total_closing_tax_collect_exc = $h['closing_exchange'];
    
    $histo = $journal->history(serialize(array(
            'aid' => $items['deduct_tax_1_aid'],
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                )
            )
        );
    $h = unserialize($histo);
        $total_tax_deduct = $h['total_transaction'];
        $total_tax_deduct_exc = $h['total_transaction_exchange'];
        $total_closing_tax_deduct = $h['closing'];
        $total_closing_tax_deduct_exc = $h['closing_exchange'];
        

    
$items['collect_tax_1_transaction'] = -$total_tax_collect_exc;
$items['collect_tax_1_closing'] = -$total_closing_tax_collect_exc;
$items['deduct_tax_1_transaction'] = $total_tax_deduct_exc;
$items['deduct_tax_1_closing'] = $total_closing_tax_deduct_exc;

$items['collect_tax_2'] = AidList::aname($coid,$settings->get('wtax_collect_aid'));//not yet implemented
$items['deduct_tax_2'] = AidList::aname($coid,$settings->get('wtax_deduct_aid'));//not yet implemented
$items['collect_tax_2_aid'] = $settings->get('wtax_collect_aid');//not yet implemented
$items['deduct_tax_2_aid'] = $settings->get('wtax_deduct_aid');//not yet implemented

    $histo = $journal->history(serialize(array(
            'aid' => $items['collect_tax_2_aid'],
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                )
            )
        );
    $h = unserialize($histo);
        $total_tax2_collect = $h['total_transaction'];
        $total_tax2_collect_exc = $h['total_transaction_exchange'];
        $total_closing_tax2_collect = $h['closing'];
        $total_closing_tax2_collect_exc = $h['closing_exchange'];
    
    $histo = $journal->history(serialize(array(
            'aid' => $items['deduct_tax_2_aid'],
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                )
            )
        );
    $h = unserialize($histo);
        $total_tax2_deduct = $h['total_transaction'];
        $total_tax2_deduct_exc = $h['total_transaction_exchange'];
        $total_closing_tax2_deduct = $h['closing'];
        $total_closing_tax2_deduct_exc = $h['closing_exchange'];
        

    
$items['collect_tax_2_transaction'] = -$total_tax2_collect_exc;
$items['collect_tax_2_closing'] = -$total_closing_tax2_collect_exc;
$items['deduct_tax_2_transaction'] = $total_tax2_deduct_exc;
$items['deduct_tax_2_closing'] = $total_closing_tax2_deduct_exc;

$items['tax_1_balance_transaction'] = round($total_tax_deduct_exc - $total_tax_collect_exc ,$rounding);
$items['tax_1_balance_closing'] = round($total_closing_tax_deduct_exc - $total_closing_tax_collect_exc,$rounding);
$items['tax_2_balance_transaction'] = round($total_tax2_deduct_exc - $total_tax2_collect_exc ,$rounding);
$items['tax_2_balance_closing'] = round($total_closing_tax2_deduct_exc - $total_closing_tax2_collect_exc,$rounding);
$items['tax_total_balance_transaction'] = round($items['tax_1_balance_transaction'] + $items['tax_2_balance_transaction'] ,$rounding);
$items['tax_total_balance_closing'] = round($items['tax_1_balance_closing'] + $items['tax_2_balance_closing'],$rounding);
        
$param = serialize(
            array (
             'id' => 'journal',
             'from' => $from,
             'to' => $to,
             'coid' => $coid,
             'aid' => $items['collect_tax_2_aid'],
           ));
$items['collect_tax_2_aid_link'] =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
$param = serialize(
            array (
             'id' => 'journal',
             'from' => $from,
             'to' => $to,
             'coid' => $coid,
             'aid' => $items['deduct_tax_2_aid'],
           ));
$items['deduct_tax_2_aid_link'] =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();

$items['average_expenses'] = round($expenses / 12, $rounding); 
$items['total_assets'] = $total_assets;  
$items['total_closing_assets'] = $total_closing_assets;  
$items['total_liabilities'] = $total_liabilities;
$items['total_closing_liabilities'] = $total_closing_liabilities;
$items['total_assets_exc'] = $total_assets_exc; 
$items['total_closing_assets_exc'] = $total_closing_assets_exc; 
$items['total_liabilities_exc'] = $total_liabilities_exc;
$items['total_closing_liabilities_exc'] = $total_closing_liabilities_exc;

$items['grand_total'] = $total_assets + $total_liabilities;    
$items['grand_total_exc'] = $total_assets_exc + $total_liabilities_exc + $items['tax_total_balance_transaction'];

$items['grand_total_closing'] = $total_closing_assets + $total_closing_liabilities;
$items['grand_total_closing_exc'] = $total_closing_assets_exc + $total_closing_liabilities_exc + $items['tax_total_balance_closing']; 

$items['ratio1'] = round($items['grand_total_exc']/($items['average_expenses']-$items['amortization']), $rounding);
$items['ratio2'] = round($items['grand_total_closing_exc']/($items['average_expenses']-$items['amortization']), $rounding);