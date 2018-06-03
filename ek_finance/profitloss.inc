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
$stop_date = $dates['stop_date'];


if ($dates['archive'] == TRUE) {
    $from = $dates['from'];
    //extract data from archive tables
    $table_accounts = "ek_accounts_" . $year . "_" . $coid;
    $table_journal = "ek_journal_" . $year . "_" . $coid;
    $items['title'] = t('Archive data');
    /* $alert = "<div class='messages messages--warning'>" .
      t('Archive data') .
      "</div>"; */
} else {
    $from = $dates['fiscal_start'];
    //extract data from current tables
    $table_accounts = "ek_accounts";
    $table_journal = "ek_journal";
    $items['title'] = t('Current fiscal year @y', ['@y' => $dates['fiscal_year']]);
    /* $alert = "<div>" .
      t('Fiscal year'). ' : ' . $dates['fiscal_year'] .
      "</div>"; */
}
//used to create links
$param = [ 'id' => 'bs',
    'from' => $from,
    'to' => $to,
    'coid' => $coid,
    'aid' => '',
    'archive' => $dates['archive']
];

////////////////////// REVENUE /////////////////////////////
//Other income
$items['other_income'] = [];
$total_class_oincome = 0;
$total_class_oincome_base = 0;

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['other_income'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh) {
    $items['other_income']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['other_income'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['other_income']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
            ->select($table_accounts, 't');
    $query->fields('t', ['aid', 'aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];

    while ($r2 = $result2->fetchObject()) {

        $d = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $c = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );
        $balance = $c[0] - $d[0];
        $balance_base = $c[1] - $d[1];

        if (($balance != 0 || $balance_base != 0) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname, 'base' => $balance_base, 'multi' => $balance, 'url' => $url];
        }
        $total_detail += $balance;
        $total_detail_base += $balance_base;
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname, 'base' => $total_detail_base, 'multi' => $total_detail];
    $items['other_income']['header']['class'][$aid]['data'] = $detail;


    $total_class_oincome += $total_detail;
    $total_class_oincome_base += $total_detail_base;
}

$items['other_income']['header']['total'] = ['base' => $total_class_oincome_base, 'multi' => $total_class_oincome];

//Income

$items['income'] = [];
$total_class_income = 0;
$total_class_income_base = 0;

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['income'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['income']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}
$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['income'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {


    $aid = substr($r->aid, 0, 2);
    $items['income']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
            ->select($table_accounts, 't');
    $query->fields('t', ['aid', 'aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];

    while ($r2 = $result2->fetchObject()) {

        $d = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $c = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );
        $balance = $c[0] - $d[0];
        $balance_base = $c[1] - $d[1];

        if (($balance != 0 || $balance_base != 0) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname, 'base' => $balance_base, 'multi' => $balance, 'url' => $url];
        }
        $total_detail += $balance;
        $total_detail_base += $balance_base;
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname, 'base' => $total_detail_base, 'multi' => $total_detail];
    $items['income']['header']['class'][$aid]['data'] = $detail;


    $total_class_income += $total_detail;
    $total_class_income_base += $total_detail_base;
}

$items['income']['header']['total'] = ['base' => $total_class_income_base, 'multi' => $total_class_income];
$items['total_income'] = ['base' => $total_class_oincome_base + $total_class_income_base, 'multi' => $total_class_oincome + $total_class_income];


////////////////////// COST of SALES /////////////////////////////
//header account

$items['cos'] = [];
$total_class_cos = 0;
$total_class_cos_base = 0;

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['cos'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['cos']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['cos'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['cos']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
            ->select($table_accounts, 't');
    $query->fields('t', ['aid', 'aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];

    while ($r2 = $result2->fetchObject()) {

        $d = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $c = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $balance = $c[0] - $d[0];
        $balance_base = $c[1] - $d[1];


        if (($balance != 0 || $balance_base != 0) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname, 'base' => $balance_base, 'multi' => $balance, 'url' => $url];
        }

        $total_detail += $balance;
        $total_detail_base += $balance_base;
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname, 'base' => $total_detail_base, 'multi' => $total_detail];
    $items['cos']['header']['class'][$aid]['data'] = $detail;

    $total_class_cos += $total_detail;
    $total_class_cos_base += $total_detail_base;
}

$items['cos']['header']['total'] = ['base' => $total_class_cos_base, 'multi' => $total_class_cos];


////////////////////// CHARGES /////////////////////////////
//other expenses

$items['other_expenses'] = [];
$total_class_oexpenses = 0;
$total_class_oexpenses_base = 0;

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['other_expenses'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['other_expenses']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['other_expenses'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['other_expenses']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
            ->select($table_accounts, 't');
    $query->fields('t', ['aid', 'aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];

    while ($r2 = $result2->fetchObject()) {

        $d = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $c = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $balance = $c[0] - $d[0];
        $balance_base = $c[1] - $d[1];

        if (($balance != 0 || $balance_base != 0 ) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname, 'base' => $balance_base, 'multi' => $balance, 'url' => $url];
        }

        $total_detail += $balance;
        $total_detail_base += $balance_base;
    }

    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname, 'base' => $total_detail_base, 'multi' => $total_detail];
    $items['other_expenses']['header']['class'][$aid]['data'] = $detail;

    $total_class_oexpenses += $total_detail;
    $total_class_oexpenses_base += $total_detail_base;
}

$items['other_expenses']['header']['total'] = ['base' => $total_class_oexpenses_base, 'multi' => $total_class_oexpenses];

//expenses

$items['expenses'] = [];
$total_class_expenses = 0;
$total_class_expenses_base = 0;

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['expenses'] . '%', 'like');
$query->condition('atype', 'header', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$data = $query->execute();
$rh = $data->fetchObject();
if($rh){
    $items['expenses']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
}

$query = Database::getConnection('external_db', 'external_db')
        ->select($table_accounts, 't');
$query->fields('t', ['aid', 'aname']);
$query->condition('aid', $chart['expenses'] . '%', 'like');
$query->condition('atype', 'class', '=');
$query->condition('astatus', '1', '=');
$query->condition('coid', $coid, '=');
$query->orderBy('aid', 'ASC');
$result = $query->execute();

while ($r = $result->fetchObject()) {

    $aid = substr($r->aid, 0, 2);
    $items['expenses']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
    $total_detail = 0;
    $total_detail_base = 0;

    $query = Database::getConnection('external_db', 'external_db')
            ->select($table_accounts, 't');
    $query->fields('t', ['aid', 'aname']);
    $query->condition('aid', $aid . '%', 'like');
    $query->condition('atype', 'detail', '=');
    $query->condition('coid', $coid, '=');
    $query->orderBy('aid', 'ASC');
    $result2 = $query->execute();
    $detail = [];

    while ($r2 = $result2->fetchObject()) {

        $d = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $c = $journal->transactions(
                array(
                    'aid' => $r2->aid,
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to' => $to,
                    'archive' => $dates['archive']
                )
        );

        $balance = $c[0] - $d[0];
        $balance_base = $c[1] - $d[1];

        if (($balance != 0 || $balance_base != 0 ) && $summary == 0) {
            $param['aid'] = $r2->aid;
            $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
            $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname, 'base' => $balance_base, 'multi' => $balance, 'url' => $url];
        }

        $total_detail += $balance;
        $total_detail_base += $balance_base;
    }



    $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname, 'base' => $total_detail_base, 'multi' => $total_detail];
    $items['expenses']['header']['class'][$aid]['data'] = $detail;

    $total_class_expenses += $total_detail;
    $total_class_expenses_base += $total_detail_base;
}

$items['expenses']['header']['total'] = ['base' => $total_class_expenses_base, 'multi' => $total_class_expenses];
$items['total_expenses'] = ['base' => $total_class_oexpenses_base + $total_class_expenses_base, 'multi' => $total_class_oexpenses + $total_class_expenses];


////////////////////// RESULT /////////////////////////////

$items['result_multi'] = $total_class_oincome + $total_class_income + $total_class_cos + $total_class_oexpenses + $total_class_expenses;
$items['result_base'] = $total_class_oincome_base + $total_class_income_base + $total_class_cos_base + $total_class_oexpenses_base + $total_class_expenses_base;



