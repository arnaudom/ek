<?php

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\Journal;
use Drupal\ek_admin\CompanySettings;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
$journal = new Journal();
$settings = new CompanySettings($coid);

$items['baseCurrency'] = $baseCurrency;
$items['summary'] = $summary;
/*
 * starting date is based on fiscal year settings 
 * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
 * and has started on 1/07/13
 */


$dates = $journal->getFiscalDates($coid, $year, $month);
$to = $dates['to'];
//remove 1 day from stop date as we generate closing amount from opening of next period
$stop_date = date('Y-m-d', strtotime($dates['stop_date'] . ' - 1 day'));

if($dates['archive'] == TRUE) {
    $from = $dates['from'];
    //extract data from archive tables
    $table_accounts = "ek_accounts_" . $year . "_" . $coid;
    $table_journal = "ek_journal_" . $year . "_" . $coid;
    $items['title'] = t('Archive data');
    
} else {
    $from = $dates['fiscal_start'];
    //extract data from current tables
    $table_accounts = "ek_accounts";
    $table_journal = "ek_journal";
    $items['title'] = t('Current fiscal year @y', ['@y' => $dates['fiscal_year']]);
    
}

$param = [ 'id' => 'bs',
           'from' => $from,
           'to' => $to,
           'coid' => $coid,
           'aid' => '',
           'archive' => $dates['archive']
         ];

////////////////////// ASSETS /////////////////////////////
//header account

// Other assets
$items['other_assets'] = [];
$total_class0 = 0;
$total_class0_base = 0;
    
$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['other_assets'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['other_assets']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}
$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['other_assets'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {
   
    $aid = substr($r->aid, 0, 2);
    $items['other_assets']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
    $query->fields('t', ['aid','aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];
    while ($r2 = $result2->fetchObject()) {

        $b = $journal->opening(
                array(
                    'aid' => $r2->aid,
                    'coid' => $coid,
                    'from' => $stop_date,
                    'archive' => $dates['archive']
                )
        );
        //opening returns an array with key 0 = multicurrency and key 1 = base currency 
        $b0 = $b[0] * -1;
        $b1 = $b[1] * -1;
        if (($b0 != 0 || $b1 != 0) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b1, 'multi' => $b0, 'url' => $url];
        }
        $total_detail += $b0;
        $total_detail_base += $b1;
        //$detail[$r2->aid]['total'] = ['base' => $b1, 'multi' => $b0];
    }
    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
    $items['other_assets']['header']['class'][$aid]['data'] = $detail;
    

    $total_class0 += $total_detail;
    $total_class0_base += $total_detail_base;
}

$items['other_assets']['header']['total'] = ['base' => $total_class0_base, 'multi' => $total_class0];

// assets
$items['assets'] = [];
$total_class1 = 0;
$total_class1_base = 0;

$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['assets'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['assets']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}
$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['assets'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['assets']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;
    
    $query = Database::getConnection('external_db', 'external_db')
                        ->select($table_accounts, 't');
    $query->fields('t', ['aid','aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];
    while ($r2 = $result2->fetchObject()) {

        $b = $journal->opening(
                array(
                    'aid' => $r2->aid,
                    'coid' => $coid,
                    'from' => $stop_date,
                    'archive' => $dates['archive']
                )
        );
        //opening returns an array with key 0 = multicurrency and key 1 = base currency 
        $b0 = $b[0] * -1;
        $b1 = $b[1] * -1;
        if (($b0 != 0 || $b1 != 0) && $summary == 0) {$param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b1, 'multi' => $b0, 'url' => $url];
        }
        $total_detail += $b0;
        $total_detail_base += $b1;
        
    }
    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
    $items['assets']['header']['class'][$aid]['data'] = $detail;
    

    $total_class1 += $total_detail;
    $total_class1_base += $total_detail_base;
}

$items['assets']['header']['total'] = ['base' => $total_class1_base, 'multi' => $total_class1];
$items['total_assets'] = ['base' => $total_class0_base + $total_class1_base, 'multi' => $total_class0 + $total_class1];


////////////////////// LIABILITIES /////////////////////////////
//header account

//liabilities
$items['liabilities'] = [];
$total_class2 = 0;
$total_class2_base = 0;

$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['liabilities'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['liabilities']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}

$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['liabilities'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['liabilities']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;
    
    $query = Database::getConnection('external_db', 'external_db')
                        ->select($table_accounts, 't');
    $query->fields('t', ['aid','aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];      
    while ($r2 = $result2->fetchObject()) {

        $b = $journal->opening(
                array(
                    'aid' => $r2->aid,
                    'coid' => $coid,
                    'from' => $stop_date,
                    'archive' => $dates['archive']
                )
        );


        if (($b[0] != 0 || $b[1] != 0) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b[1], 'multi' => $b[0], 'url' => $url];
        }
        $total_detail += $b[0];
        $total_detail_base += $b[1];
        
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
    $items['liabilities']['header']['class'][$aid]['data'] = $detail;
    $total_class2 += $total_detail;
    $total_class2_base += $total_detail_base;
}

$items['liabilities']['header']['total'] = ['base' => $total_class2_base, 'multi' => $total_class2];

//other liabilities
$items['other_liabilities'] = [];
$total_class7 = 0;
$total_class7_base = 0;

$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['other_liabilities'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['other_liabilities']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}
$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['other_liabilities'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['other_liabilities']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
    $query->fields('t', ['aid','aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];
    while ($r2 = $result2->fetchObject()) {

        $b = $journal->opening(
                array(
                    'aid' => $r2->aid,
                    'coid' => $coid,
                    'from' => $stop_date,
                    'archive' => $dates['archive']
                )
        );


        if (($b[0] != 0 || $b[1] != 0) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b[1], 'multi' => $b[0], 'url' => $url];
        }
        $total_detail += $b[0];
        $total_detail_base += $b[1];
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
    $items['other_liabilities']['header']['class'][$aid]['data'] = $detail;
    $total_class7 += $total_detail;
    $total_class7_base += $total_detail_base;
}

$items['other_liabilities']['header']['total'] = ['base' => $total_class7_base, 'multi' => $total_class7];
$items['total_liabilities'] = ['base' => $total_class7_base + $total_class2_base, 'multi' => $total_class7 + $total_class2];

////////////////////// NET ASSETS /////////////////////////////

$net_assets = $total_class0 + $total_class1 - $total_class2 - $total_class7;
$net_assets_base = $total_class0_base + $total_class1_base - $total_class2_base - $total_class7_base;

$items['net_assets'] = ['base' => $net_assets_base, 'multi' => $net_assets];

////////////////////// EQUITY /////////////////////////////
//header account
//equity ref. accounts
$items['equity'] = [];
$equity_min = $chart['equity'] * 10000;
$equity_max = $equity_min + 9999;
$earnings_account = $equity_min + 9001;//default
$reserve_account = $equity_min + 8001;//default

$total_class3 = 0;
$total_class3_base = 0;
    

$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['equity'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['equity']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}
$query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
$query->fields('t', ['aid','aname']);
$query->condition('aid', $chart['equity'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['equity']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
                    ->select($table_accounts, 't');
    $query->fields('t', ['aid','aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = []; 
    while ($r2 = $result2->fetchObject()) {

        if ($r2->aid == $earnings_account) {
            //caculate current year earnings
            $b = $journal->current_earning($coid, $from, $to);
            //add other entries on the account from journal transactions
            $dt = $journal->transactions(
                array( 
                'aid' => $earnings_account,
                'type' => 'debit',
                'coid' => $coid,
                'from' => $from,
                'to' => $to,
                'archive' => $dates['archive']
                 ) 
                );
    
            $ct = $journal->transactions(
                array( 
                'aid' => $earnings_account,
                'type' => 'credit',
                'coid' => $coid,
                'from'=> $from,
                'to'=> $to,
                'archive' => $dates['archive']
                 )
                );    
            $b[0] = $b[0] + $ct[0]-$dt[0];
            $b[1] = $b[1] + $ct[1]-$dt[1];
            
        } else {
            //look up for balance
            $b = $journal->opening(
                    array(
                        'aid' => $r2->aid,
                        'coid' => $coid,
                        'from' => $stop_date,
                        'archive' => $dates['archive']
                    )
            );
        }
        if (($b[0] != 0 || $b[1] != 0 ) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b[1], 'multi' => $b[0], 'url' => $url];
        }
        $total_detail += $b[0];
        $total_detail_base += $b[1];
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
    $items['equity']['header']['class'][$aid]['data'] = $detail;
    $total_class3 += $total_detail;
    $total_class3_base += $total_detail_base;
}

$items['equity']['header']['total'] = ['base' => $total_class3_base, 'multi' => $total_class3];
$items['total_equity'] = ['base' => $total_class3_base, 'multi' => $total_class3];

if (round($net_assets_base,2) != round($total_class3_base,2) ) {
    $items['error'] = number_format(round($net_assets_base,2) - round($total_class3_base,2), 2);
} 




