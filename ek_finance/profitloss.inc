<?php
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\Journal;
use Drupal\ek_admin\CompanySettings;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',',$company);
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

////////////////////// REVENUE /////////////////////////////
//header account
$table_1 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th colspan='2'>" .  t('REVENUE') . " </th>
      <th >" .  t('Base currency') . " (" . $baseCurrency . ") </th>
      <th >" .  t('Multi currency') . " </th>
    </thead>
    <tbody class=''>"; 
        


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE (aid like :aid1 or aid like :aid2) "
        . "AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array( ':aid1' => $chart['income'] . '%',
            ':aid2' => $chart['other_income'] . '%',
            ':type' => 'header',
            ':status' => 1,
            ':coid' => $coid);
$rh = Database::getConnection('external_db', 'external_db')
        ->query($q,$a)->fetchObject();

$table_1 .= style('header',$rh->aid, $rh->aname );



$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE (aid like :aid1 or aid like :aid2) "
        . "AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
            ':aid1' => $chart['income'] . '%',
            ':aid2' => $chart['other_income'] . '%',
            ':type' => 'class',
            ':status' => 1, 
            ':coid' => $coid
        );
$result = Database::getConnection('external_db', 'external_db')->query($q,$a);



while ($r=$result->fetchObject()) {

if ($summary == 0){
    $table_1 .= style('class', $r->aid, $r->aname);
}

$aid = substr($r->aid, 0 , 2);
$total_detail = 0;
$total_detail_l = 0;

$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND coid=:coid order by aid";
$a = array(':aid'=> $aid . '%' ,':type' => 'detail', ':coid' => $coid);
$result2 = Database::getConnection('external_db', 'external_db')->query($q,$a);

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
                'from'=> $from,
                'to'=> $to,
                'archive' => $dates['archive']
                 )
                );    
    $b = $c[0]-$d[0];
    $b_exc = $c[1]-$d[1];
    
    if (($b != 0 || $b_exc != 0) && $summary == 0){
    
        $param = 'aid=' . $r2->aid . '&from=' . $from . '&to=' . $to . '&company=' . $coid;
        $table_1 .=  style('detail',$r2->aid, $r2->aname ,$b_exc , $b,'', $from, $to, $coid);
        
    }
        $total_detail += $b_exc;
        $total_detail_l += $b;
    }
    
    
    $table_1 .=  style('subtotal', $r->aid, $r->aname,$total_detail , $total_detail_l) ;  
  
  
  $total_class4 += $total_detail;
  $total_class4_l += $total_detail_l;

}


$table_1 .= style('total',$rh->aid, $rh->aname,$total_class4 , $total_class4_l);

$table_1 .= '</tbody></table>';

////////////////////// COST of SALES /////////////////////////////
//header account
$table_2 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th colspan='2'>" .  t('COST OF SALES') . " </th>
      <th >" .  t('Base currency') . " (" . $baseCurrency . ") </th>
      <th >" .  t('Multi currency') . " </th>
    </thead>
    <tbody class=''>"; 


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
            ':aid' => $chart['cos'] . '%', 
            ':type' => 'header',
            ':status' => 1, 
            ':coid' => $coid);
$rh = Database::getConnection('external_db', 'external_db')->query($q,$a)->fetchObject();

$table_2 .= style('header',$rh->aid,$rh->aname );


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
            ':aid' => $chart['cos'] . '%', 
            ':type' => 'class', 
            ':status' => 1, 
            ':coid' => $coid);

$result = Database::getConnection('external_db', 'external_db')->query($q,$a);


while ($r=$result->fetchObject()) {

if ($summary == 0){
    $table_2 .= style('class', $r->aid, $r->aname);
}

$aid = substr($r->aid,0,2);
$total_detail = 0;
$total_detail_l = 0;

$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND coid=:coid order by aid";
$a = array(':aid' => $aid . '%', ':type' => 'detail', ':coid' => $coid);
$result2 = Database::getConnection('external_db', 'external_db')->query($q,$a);

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
        
        $b = $c[0]-$d[0];
        $b_exc = $c[1]-$d[1];
                   

        if ( ($b != 0 || $b_exc != 0) && $summary == 0){

            $param = 'aid=' . $r2->aid . '&from=' . $from . '&to=' . $to . '&company=' . $coid;
            $row = style('detail', $r2->aid , $r2->aname , $b_exc , $b,'', $from, $to, $coid );
            $table_2 .= $row;

        }
            $total_detail += $b_exc;
            $total_detail_l += $b;
        
    }    
    
    
  
  $table_2 .= style('subtotal', $r->aid, $r->aname, $total_detail, $total_detail_l);  
  $total_class5 += $total_detail;
  $total_class5_l += $total_detail_l;
}


  $table_2 .= style('total',$rh->aid, $rh->aname, $total_class5, $total_class5_l);
  $table_2 .= '</tbody></table>';
  
  

  
////////////////////// CHARGES /////////////////////////////
//header account
$table_3 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th colspan='2'>" .  t('CHARGES') . " </th>
      <th >" .  t('Base currency') . " (" . $baseCurrency . ") </th>
      <th >" .  t('Multi currency') . " </th>
    </thead>
    <tbody class=''>"; 


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE (aid like :aid1 or aid like :aid2) "
        . "AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
            ':aid1' => $chart['expenses'] . '%',
            ':aid2' => $chart['other_expenses'] . '%',
            ':type' => 'header', 
            ':status' => 1,
            ':coid' => $coid);

$rh =  Database::getConnection('external_db', 'external_db')->query($q,$a)->fetchObject();

$table_3 .= style('header', $rh->aid,$rh->aname );


$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE (aid like :aid1 or aid like :aid2) "
        . "AND atype=:type AND astatus=:status AND coid=:coid order by aid";
$a = array(
            ':aid1' => $chart['expenses'] . '%',
            ':aid2' => $chart['other_expenses'] . '%', 
            ':type' => 'class', 
            ':status' => 1, 
            ':coid' => $coid);

$result = Database::getConnection('external_db', 'external_db')->query($q,$a);


while ($r =$result->fetchObject()) {

if ($summary == 0){
    $table_3 .= style('class', $r->aid,$r->aname );
}

$aid = substr($r->aid,0,2);
$total_detail = 0;
$total_detail_l = 0;

$q = "SELECT aid,aname FROM {$table_accounts} "
        . "WHERE aid like :aid AND atype=:type AND coid=:coid order by aid";
$a = array(':aid'=> $aid . '%',':type'=>'detail',':coid'=>$coid);

$result2 = Database::getConnection('external_db', 'external_db')->query($q,$a);

    while ($r2=$result2->fetchObject()) {
   
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
                'to'=> $to,
                'archive' => $dates['archive']
                 )
                );    
        
        $b=$c[0]-$d[0];
        $b_exc=$c[1]-$d[1];   
    
            if ( ($b != 0 || $b_exc != 0 )   && $summary == 0){
                $param = 'aid=' . $r2->aid . '&from=' . $from . '&to=' . $to . '&company=' . $coid;
                $table_3 .= style('detail', $r2->aid , $r2->aname , $b_exc , $b,'',$from, $to, $coid);
            }
            $total_detail += $b_exc;
            $total_detail_l += $b;
            }
    
    
  
  $table_3 .= style('subtotal', $r->aid, $r->aname, $total_detail, $total_detail_l) ;  
  $total_class6 += $total_detail;
  $total_class6_l += $total_detail_l;
}

  
$table_3 .=  style('total', $rh->aid, $rh->aname , $total_class6, $total_class6_l) ;
$table_3 .= '</tbody></table>';


////////////////////// RESULT /////////////////////////////

$result = $total_class4 + $total_class5 + $total_class6;
$result_l = $total_class4_l + $total_class5_l + $total_class6_l;
if($result_l < 0) {
    $c = 'red';
} else {
    $c = null;
}
$table_4 = "<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <tbody class=''>";   


$table_4 .= style('netasset' , t('Profit (loss)') , ' ' ,$result,$result_l, $c);
$table_4 .= '</tbody></table>';


if (isset($alert)) {
    $table_5 = $alert;
} else {
    $table_5 = "";
}



/*
 * * formatting of lines
 * s = style
 * a = account
 * n = name
 * vl = local value
 * v = base value
 * c = color
 */
function style($s,$a,$n,$v = null ,$vl = null, $c = NULL, $from = null, $to = null, $coid = null) {

    switch($s) {
        
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
            array (
             'id' => 'bs',
             'from' => $from,
             'to' => $to,
             'coid' => $coid,
             'aid' => $a,
           ));
           $history =  Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
           $aid = "<a class='use-ajax' href='" . $history . "' >" . $a . "</a>";            
            $row = '<tr class="detail"><td>' . $aid . '</td>'
                . '<td><span>' . $n . '</span></td>'
                . '<td class="right">' . number_format($v, 2) . '</td>'
                . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
        break;
    
        case 'subtotal' :
            $row = '<tr class="subtotal"><td colspan="2">' . t('Sub Total') . ' ' . $a . ' '
                .  $n . '</td>'
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
            $row = '<tr class="netasset ' .$c. '"><td>' . $a . '</td>'
                . '<td>' . $n . '</td>'
                . '<td class="right">' . number_format($v, 2) . '</td>'
                . '<td class="right">' . number_format($vl, 2) . '</td></tr>';
        break;
    }

return $row;
}
