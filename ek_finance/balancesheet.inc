<?php

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\Journal;
use Drupal\ek_admin\CompanySettings;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
$journal = new Journal();
$settings = new CompanySettings($coid);

/*
 * starting date is based on fiscal year settings 
 * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
 * and has started on 1/07/13
 */


$dates = $journal->getFiscalDates($coid, $year, $month);
$to = $dates['to'];
$stop_date = $dates['stop_date'];



if($dates['archive'] == TRUE) {
    $from = $dates['from'];
    
    //extract data from archive tables
    $table_accounts = "ek_accounts_" . $year . "_" . $coid;
    $table_journal = "ek_journal_" . $year . "_" . $coid;
    
    $alert = "<div class='messages messages--warning'>" .
                t('Archive data') .
             "</div>";
} else {
    $from = $dates['fiscal_start'];
    //extract data from current tables
    $table_accounts = "ek_accounts";
    $table_journal = "ek_journal";
    
    $alert = "<div>" .
                t('Fiscal year'). ' : ' . $dates['fiscal_year'] .
             "</div>";
}



////////////////////// ASSETS /////////////////////////////
//header account
$table_1 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th colspan='2'>" . t('ASSETS') . " </th>
      <th >" . t('Base currency') . " (" . $baseCurrency . ") </th>
      <th >" . t('Multi currency') . " </th>
    </thead>
    <tbody class=''>";



$q = "SELECT aid,aname FROM {$table_accounts} WHERE aid like :aid AND atype=:type "
        . "AND astatus=:status AND coid=:coid order by aid";
$a = array(
    ':aid' => $chart['assets'] . '%',
    ':type' => 'header',
    ':status' => 1,
    ':coid' => $coid
);
$rh = Database::getConnection('external_db', 'external_db')
        ->query($q, $a)
        ->fetchObject();

$table_1 .= style('header', $rh->aid, $rh->aname);

$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
    ':aid' => $chart['assets'] . '%',
    ':type' => 'class',
    ':status' => 1,
    ':coid' => $coid
);
$result = Database::getConnection('external_db', 'external_db')
        ->query($q, $a);



while ($r = $result->fetchObject()) {

    if ($summary == 0) {
        $table_1 .= style('class', $r->aid, $r->aname);
    }

    $aid = substr($r->aid, 0, 2);
    $total_detail = 0;
    $total_detail_base = 0;

    $q = "SELECT aid,aname FROM {$table_accounts} "
            . "WHERE aid like :aid AND atype=:type AND coid=:coid order by aid";
    $a = array(':aid' => $aid . '%', ':type' => 'detail', ':coid' => $coid);
    $result2 = Database::getConnection('external_db', 'external_db')
            ->query($q, $a);

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

            $param = 'aid=' . $r2->aid . '&from=' . $from . '&to=' . $to . '&company=' . $coid;
            $table_1 .= style('detail', $r2->aid, $r2->aname, $b1, $b0, '', $from, $to, $coid, $dates['archive']);
        }
        $total_detail += $b0;
        $total_detail_base += $b1;
    }



    $table_1 .= style('subtotal', $r->aid, $r->aname, $total_detail_base, $total_detail);

    $total_class1+=$total_detail;
    $total_class1_base+=$total_detail_base;
}


$table_1 .= style('total', $rh->aid, $rh->aname, $total_class1_base, $total_class1);

$table_1 .= '</tbody></table>';

////////////////////// LIABILITIES /////////////////////////////
//header account
$table_2 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th colspan='2'>" . t('LIABILITIES') . " </th>
      <th >" . t('Base currency') . " (" . $baseCurrency . ") </th>
      <th >" . t('Multi currency') . " </th>
    </thead>
    <tbody class=''>";


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE (aid like :aid1 or aid like :aid2) "
        . "AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
    ':aid1' => $chart['liabilities'] . '%',
    ':aid2' => $chart['other_liabilities'] . '%',
    ':type' => 'header',
    ':status' => 1,
    ':coid' => $coid);
$rh = Database::getConnection('external_db', 'external_db')->query($q, $a)->fetchObject();


$table_2 .= style('header', $rh->aid, $rh->aname);


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE (aid like :aid1 or aid like :aid2) "
        . "AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
    ':aid1' => $chart['liabilities'] . '%',
    ':aid2' => $chart['other_liabilities'] . '%',
    ':type' => 'class',
    ':status' => 1,
    ':coid' => $coid
);

$result = Database::getConnection('external_db', 'external_db')->query($q, $a);


while ($r = $result->fetchObject()) {

    if ($summary == 0) {
        $table_2 .= style('class', $r->aid, $r->aname);
    }

    $aid = substr($r->aid, 0, 2);
    $total_detail = 0;
    $total_detail_base = 0;

    $q = "SELECT aid,aname FROM {$table_accounts} WHERE aid like :aid AND atype=:type "
        . "AND coid=:coid order by aid";
    $a = array(':aid' => $aid . '%', ':type' => 'detail', ':coid' => $coid);
    $result2 = Database::getConnection('external_db', 'external_db')
            ->query($q, $a);

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

            $param = 'aid=' . $r2->aid . '&from=' . $from . '&to=' . $to . '&company=' . $coid;
            $row = style('detail', $r2->aid, $r2->aname, $b[1], $b[0], '', $from, $to, $coid, $dates['archive']);
            $table_2 .= $row;
        }
        $total_detail+=$b[0];
        $total_detail_base+=$b[1];
    }



    $table_2 .= style('subtotal', $r->aid, $r->aname, $total_detail_base, $total_detail);
    $total_class2 += $total_detail;
    $total_class2_base += $total_detail_base;
}


$table_2 .= style('total', $rh->aid, $rh->aname, $total_class2_base, $total_class2);
$table_2 .= '</tbody></table>';


////////////////////// NET ASSETS /////////////////////////////

$table_3 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <tbody class=''>";
$net_assets = $total_class1 - $total_class2;
$net_assets_base = $total_class1_base - $total_class2_base;
if ($net_assets_base < 0) {
    $c = 'red';
} else {
    $c = null;
}
$table_3 .= style('netasset', t('Net Assets'), ' ', $net_assets_base, $net_assets, $c);
$table_3 .= '</tbody></table>';

////////////////////// EQUITY /////////////////////////////
//header account
$table_4 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th colspan='2'>" . t('EQUITY') . " </th>
      <th >" . t('Base currency') . " (" . $baseCurrency . ") </th>
      <th >" . t('Multi currency') . " </th>
    </thead>
    <tbody class=''>";


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND astatus=:status "
        . "AND coid=:coid order by aid";
$a = array(
    ':aid' => $chart['equity'] . '%',
    ':type' => 'header',
    ':status' => 1,
    ':coid' => $coid
);

$rh = Database::getConnection('external_db', 'external_db')
                ->query($q, $a)
                ->fetchObject();

$table_4 .= style('header', $rh->aid, $rh->aname);


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND astatus=:status "
        . "AND coid=:coid order by aid";
$a = array(
    ':aid' => $chart['equity'] . '%',
    ':type' => 'class',
    ':status' => 1,
    ':coid' => $coid
);

$result = Database::getConnection('external_db', 'external_db')
                    ->query($q, $a);


while ($r = $result->fetchObject()) {

    if ($summary == 0) {
        $table_4 .= style('class', $r->aid, $r->aname);
    }

    $aid = substr($r->aid, 0, 2);
    $total_detail = 0;
    $total_detail_base = 0;

    $q = "SELECT aid,aname FROM {$table_accounts} WHERE aid like :aid "
            . "AND atype=:type AND coid=:coid order by aid";
    $a = array(':aid' => $aid . '%', ':type' => 'detail', ':coid' => $coid);

    $result2 = Database::getConnection('external_db', 'external_db')
                    ->query($q, $a);

    while ($r2 = $result2->fetchObject()) {

        /* TODO : define a variable current year earning account based on chart 
         * structure
         */
        if ($r2->aid == '39001') {
            //caculate current year earnings
            $b = $journal->current_earning($coid, $from, $to);
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
            $param = 'aid=' . $r2->aid . '&from=' . $from . '&to=' . $to . '&company=' . $coid;
            $table_4 .= style('detail', $r2->aid, $r2->aname, $b[1], $b[0], '', $from, $to, $coid, $dates['archive']);
        }
        $total_detail+=$b[0];
        $total_detail_base+=$b[1];
    }



    $table_4 .= style('total', $r->aid, $r->aname, $total_detail_base, $total_detail);
    $total_class3+=$total_detail;
    $total_class3_base+=$total_detail_base;
}


$table_4 .= style('grandtotal', $rh->aid, $rh->aname, $total_class3_base, $total_class3);
$table_4 .= '</tbody></table>';


if (isset($alert)) {
    $table_5 = $alert;
} else {
    $table_5 = "";
}

if (round($net_assets_base,2) != round($total_class3_base,2) ) {
    $error = number_format(round($net_assets_base,2) - round($total_class3_base,2), 2);
    $table_5 .= t('Balance error (net assets - equity)') . ': ' . $error;
} 





/*
 * * formatting of lines
 * s = style
 * a = account
 * n = name
 * from = date
 * to = date
 * coid = company id
 * v = base value
 * vl = local value
 * c = color
 * bool archive 
 */

function style($s, $a, $n, $v = null, $vl = null, $c = NULL, $from = null, $to = null, $coid = null, $archive = null) {

    switch ($s) {

        case 'header':
            $row = '<tr class="header"><td>' . $a . '</td><td colspan="3">' . $n . '</td></tr>';
            break;

        case 'class' :
            $row = '<tr class="classe"><td colspan="1"></td>'
                    . '<td colspan="3"><span>' . $a . '</span> ' . $n . '</td></tr>';
            break;

        case 'detail' :

            //build an history link
            $param = serialize(
                    array(
                        'id' => 'bs',
                        'from' => $from,
                        'to' => $to,
                        'coid' => $coid,
                        'aid' => $a,
                        'archive' => $archive
            ));
            $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
            $aid = "<a class='use-ajax' href='" . $history . "' >" . $a . "</a>";
            $row = '<tr class="detail"><td>' . $aid . '</td>'
                    . '<td><span>' . $n . '</span></td>'
                    . '<td class="right">' . number_format($v, 2) . '</td>'
                    . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
            break;

        case 'subtotal' :
            $row = '<tr class="subtotal"><td colspan="2">' . t('Sub Total') . ' ' . $a . ' '
                    . $n . '</td>'
                    . '<td class="right">' . number_format($v, 2) . '</td>'
                    . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
            break;
        case 'total' :
            $row = '<tr class="total"><td>' . t('Total') . ' ' . $a . '</td>'
                    . '<td>' . $n . '</td>'
                    . '<td class="right">' . number_format($v, 2) . '</td>'
                    . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
            break;
        case 'grandtotal' :
            $row = '<tr class="grandtotal"><td>' . t('Grand Total') . ' ' . $a . '</td>'
                    . '<td>' . $n . '</td>'
                    . '<td class="right">' . number_format($v, 2) . '</td>'
                    . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
            break;
        case 'netasset' :
            $row = '<tr class="netasset ' . $c . '"><td>' . $a . '</td>'
                    . '<td>' . $n . '</td>'
                    . '<td class="right">' . number_format($v, 2) . '</td>'
                    . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
            break;
    }

    return $row;
}
