<?php
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_finance\Journal;
$journal = new Journal();


/*
 * Tax deductibe
 * receivable account, tax paid in expenses, purchases
 */
$total_coll = $journal->transactions(
                   array( 
                    'aid'=> $staxd,
                    'type'=> 'debit',
                    'coid'=> $coid,
                    'from'=> $from,
                    'to'=> $to
                     )
                    );
$total_pay = $journal->transactions(
                   array( 
                    'aid'=> $staxd,
                    'type'=> 'credit',
                    'coid'=> $coid,
                    'from'=> $from,
                    'to'=> $to
                     )
                    );
$param = serialize(
                array(
                    'id' => 'reporting',
                    'from' => $from,
                    'to' => $to,
                    'coid' => $coid,
                    'aid' => $staxd
        ));
$url = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
$staxd_aid = "<a title='" . t('Journal') . "' class='use-ajax' href='" . $url . "' >" . $staxd . "</a>";

$table_1 = "
<table class='' id='' >
    <thead class=>
    <tr><td colspan='4'><span>" .t('TAX Deductible'). " " . $staxd_aid . " </span></td></tr>
    <tr><td colspan='2'>" .t('total paid'). "</td><td colspan='2'>" .t('total credited'). "</td></tr>
    <tr><td>" . t('multi currency') . "</td><td>" . $baseCurrency . "</td>"
        . "<td>" . t('multi currency') . "</td><td>" . $baseCurrency . "</td></tr>
    <tr><td>" . number_format($total_coll[0],2) . "</td><td>" . number_format($total_coll[1],2) . "</td>"
        . "<td>" .number_format($total_pay[0],2) . "</td><td>" . number_format($total_pay[1],2). "</td></tr>
      <tr class='' >
          <th >" . t('date') . "</th>
          <th>" . t('reference') . "</th>           
          <th colspan='2'>" . t('amount'). "</th>
          
       </tr>
      <tr class='' >
          <th ></th>
          <th></th>           
          <th>" . t('collect (debit)'). "</th>
          <th>" . t('credited'). "</th>
       </tr></thead>
           <tbody >";


$query = "SELECT  * FROM {ek_journal} 
            WHERE aid = :aid
            AND coid = :coid
            AND date >= :from
            AND date <= :to";
$a = array(':aid' => $staxd, ':coid' => $coid, ':from' => $from, ':to' => $to );

$data = Database::getConnection('external_db', 'external_db')->query($query, $a);
$total_value_cr = 0;
$total_base_cr = 0;
$total_value_dt = 0;
$total_base_dt = 0;

    while ($d = $data->fetchObject() ) {
       $ref = $journal->reference($d->source, $d->reference);
       
       if($d->exchange == '1') {
           
           $class = 'journal-exchange';
           if($d->type == 'credit') {
                $total_base_cr = $total_base_cr + $d->value;
           } else {
               $total_base_dt = $total_base_dt + $d->value;
           }
       } else {
           $class = '';
           if($d->type == 'credit') {
                $total_value_cr = $total_value_cr + $d->value;
           } else {
               $total_value_dt = $total_value_dt + $d->value;
           }
       }
       $table_1 .= " 
                    <tr class='$class'>
                        <td>" . $d->date . "</td>
                        <td>" . $d->source . " " . $d->reference . " | " . $ref[0]. "</td>";
        
        if($d->type == 'debit') {
            $table_1 .= "<td class='right'>" . number_format($d->value,2) . "</td><td></td></tr>";
        } else {
            $table_1 .= "<td></td><td class='right'>" . number_format($d->value,2) . "</td></tr>";
        }
    }

$table_1 .= "<tr>"
        . "<td colspan='1'>" .t('balance multi currency'). "<td>";
$v = $total_value_dt - $total_value_cr;
        if($v >= 0) {
            $table_1 .= "<td class='right journal-total'>" . number_format($v,2) . "</td><td></td></tr>";
        } else {
            $v = $total_value_cr - $total_value_dt;
            $table_1 .= "<td></td><td class='right journal-total'>" . number_format($v,2) . "</td></tr>";
        }
$table_1 .= "<tr>"
        . "<td colspan='1'>" .t('balance base currency'). " " . $baseCurrency . "<td>";
$v = ($total_value_dt+$total_base_dt) - ($total_value_cr + $total_base_cr);
        if($v >= 0) {
            $table_1 .= "<td class='right journal-total'>" . number_format($v,2) . "</td><td></td></tr>";
        } else {
            $v = ($total_value_cr + $total_base_cr) - ($total_value_dt+$total_base_dt);
            $table_1 .= "<td></td><td class='right journal-total'>" . number_format($v,2) . "</td></tr>";
        }

$table_1 .= "</tbody></table>";




/*
 * Tax collected
 * debit account, tax included in sales
 */

$total_coll = $journal->transactions(
                   array( 
                    'aid'=> $staxc,
                    'type'=> 'credit',
                    'coid'=> $coid,
                    'from'=> $from,
                    'to'=> $to
                     )
                    );
$total_pay = $journal->transactions(
                   array( 
                    'aid'=> $staxc,
                    'type'=> 'debit',
                    'coid'=> $coid,
                    'from'=> $from,
                    'to'=> $to
                     )
                    );
$param = serialize(
                array(
                    'id' => 'reporting',
                    'from' => $from,
                    'to' => $to,
                    'coid' => $coid,
                    'aid' => $staxc
        ));
$url = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
$staxc_aid = "<a title='" . t('Journal') . "' class='use-ajax' href='" . $url . "' >" . $staxc . "</a>";
$table_2 = "
<table class='' id='' >
    <thead class=>
    <tr><td colspan='5'><span >" .t('TAX Collected'). " " . $staxc_aid . " </span></td></tr>
    <tr><td colspan='2'>" .t('total collected'). "</td><td colspan='3'>" .t('total paid'). "</td></tr>
    <tr><td>" . t('multi currency') . "</td><td>" . $baseCurrency . "</td>"
        . "<td colspan='2'>" . t('multi currency') . "</td><td>" . $baseCurrency . "</td></tr>
    <tr><td>" . number_format($total_coll[0],2) . "</td><td>" . number_format($total_coll[1],2) . "</td>"
        . "<td  colspan='2'>" .number_format($total_pay[0],2) . "</td><td>" . number_format($total_pay[1],2) . "</td></tr>
      <tr class='' >
          <th >" . t('date') . "</th>
          <th>" . t('reference') . "</th>           
          <th colspan='3'>" . t('amount'). "</th>
          
       </tr>
      <tr class='' >
          <th ></th>
          <th></th>           
          <th>" . t('collect'). "</th>
          <th>" . t('status as of'). " " . $to . "</th>
          <th>" . t('paid (debit)'). "</th>
       </tr></thead>
           <tbody >";



$query = "SELECT  * FROM {ek_journal} 
            WHERE aid = :aid
            AND coid = :coid
            AND date >= :from
            AND date <= :to";
$a = array(':aid' => $staxc, ':coid' => $coid, ':from' => $from, ':to' => $to );


$data = Database::getConnection('external_db', 'external_db')->query($query, $a);
$total_value_cr = 0;
$total_base_cr = 0;
$total_value_dt = 0;
$total_base_dt = 0;

    while ($d = $data->fetchObject() ) {
        
       $ref = $journal->reference($d->source, $d->reference);
       $status = '';
       if($d->source == 'invoice'){
        //check if payment received
        $query = "SELECT status, pay_date FROM {ek_sales_invoice} "
                . "WHERE id=:id AND head=:h";
        $a = array(':id' => $d->reference, ':h' => $coid );
        $invoice = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)->fetchObject();

        if($invoice->status =='1' && $invoice->pay_date <= $to){
            $status = t('paid');
        } elseif( $invoice->status =='2' && $invoice->pay_date <= $to) {
            $status = t('partially paid');
        } else {
            $status = t('unpaid');
        }
       }
       
       if($d->exchange == '1') {
           
           $class = 'journal-exchange';
           if($d->type == 'credit') {
                $total_base_cr = $total_base_cr + $d->value;
           } else {
               $total_base_dt = $total_base_dt + $d->value;
           }
       } else {
           $class = '';
           if($d->type == 'credit') {
                $total_value_cr = $total_value_cr + $d->value;
           } else {
               $total_value_dt = $total_value_dt + $d->value;
           }
       }
       $table_2 .= " 
                    <tr class='$class'>
                        <td>" . $d->date . "</td>
                        <td>" . $d->source . " " . $d->reference . " | " . $ref[0]. "</td>";
        
        if($d->type == 'credit') {
            $table_2 .= "<td class='right'>" . number_format($d->value,2) . "</td><td>" . $status . "</td><td></td></tr>";
        } else {
            $table_2 .= "<td></td><td>" . $status . "</td><td class='right'>" . number_format($d->value,2) . "</td></tr>";
        }
    }

$table_2 .= "<tr>"
        . "<td colspan='1'>" .t('balance multi currency'). "<td>";
$v = $total_value_dt - $total_value_cr;
        if($v >= 0) {
            $table_2 .= "<td></td><td class='right journal-total'>" . number_format($v,2) . "</td><td></td></tr>";
        } else {
            $v = $total_value_cr - $total_value_dt;
            $table_2 .= "<td class='right journal-total'>" . number_format($v,2) . "</td><td></td><td></td></tr>";
        }
$table_2 .= "<tr>"
        . "<td colspan='1'>" .t('balance base currency'). " " . $baseCurrency . "<td>";
$v = ($total_value_dt+$total_base_dt) - ($total_value_cr + $total_base_cr);
        if($v >= 0) {
            $table_2 .= "<td></td><td class='right journal-total'>" . number_format($v,2) . "</td><td></td></tr>";
        } else {
            $v = ($total_value_cr + $total_base_cr) - ($total_value_dt+$total_base_dt);
            $table_2 .= "<td class='right journal-total'>" . number_format($v,2) . "</td><td></td><td></td></tr>";
        }

$table_2 .= "</tbody></table>";




