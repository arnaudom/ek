<?php

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);

/*
 * Purchases
 */
$table_0 = "
<table class='report' >
    <thead class='' >
      <th class='column_aid'><a href='#' id='purchases'>" . t('Purchases') . "</a> $year - $baseCurrency $budgetUnit</th>
      <th class='column_value'>" . t('Jan') . "</th>
      <th class='column_value'>" . t('Feb') . "</th>
      <th class='column_value'>" . t('Mar') . "</th>
      <th class='column_value'>" . t('Apr') . "</th>
      <th class='column_value'>" . t('May') . "</th>
      <th class='column_value'>" . t('Jun') . "</th>
      <th class='column_value'>" . t('Jul') . "</th>
      <th class='column_value'>" . t('Aug') . "</th>
      <th class='column_value'>" . t('Sep') . "</th>
      <th class='column_value'>" . t('Oct') . "</th>
      <th class='column_value'>" . t('Nov') . "</th>
      <th class='column_value'>" . t('Dec') . "</th>
      <th class='column_value'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";

// Get the class 
if ($coid != 'all') {

    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t AND ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s AND coid=:c order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
        ':c' => $coid
    );
}

if ($coid == 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
    );
}

$data = Database::getConnection('external_db', 'external_db')->query($query, $a);

while ($line = $data->fetchObject()) {
    $class = substr($line->aid, 0, 2);

    $table_0_class = "
      <tr>
      <td colspan='3' class='detail'>" . $line->aid . " " . $line->aname . "</td>";

    for ($m = 1; $m <= 10; $m++) {
        $table_0_class .=" <td class=''></td>";
    }

    // Get the account type
    if ($coid != 'all') {

        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s AND coid=:c ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s  ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
        );
    }

    $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);
    $sum_amount = 0;
    $flag = 0;
    $table_0_detail = '';
    while ($d2 = $data2->fetchObject()) {

        $aid = $d2->aid;
        $table_0_detail = "
            <tr class='data0'>
            <td class='left'>" . $aid . " " . $d2->aname . "</td>";

        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            // Get the type sum               
            // select purchases by date in usd
            // this is taken from purchase table. The reporting consider purchase value from allocation of purchase 
            // NOT from the actual purchasing entity. This is to compare revenue & expenses fron analytical point of view

            if ($coid != 'all') {
                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid = :a AND j.source = :s AND j.type=:t "
                        . "AND j.date >=:d1 AND j.date <=:d2 AND allocation = :c";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':c' => $coid,
                    ':s' => 'purchase',
                    ':t' => 'debit'
                );
            }

            if ($coid == 'all') {
                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid = :a AND j.source = :s AND j.type=:t "
                        . "AND j.date >=:d1 AND j.date <=:d2";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':s' => 'purchase',
                    ':t' => 'debit'
                );
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            if ($sum_d2 == "") {
                $amount = "0";
            } else {

                $amount = round($sum_d2, 2);
            }

            $sum_amount = $sum_amount + $amount;

            $table_0_detail .= "
              <td class='right' >" . number_format($amount / $divide, 2) . "</td>";
        } //for loop months


        $table_0_detail .= "
            <td class='total right' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td></tr>";

        if ($sum_amount <> 0) {
            $table_0_class .= $table_0_detail;
            $flag++;
        }
        $sum_amount = 0;
    } //while aid
    // Get the class sum - subtotal
    if ($flag > 0) {
        $table_0 .= $table_0_class;
        $table_0 .= "
              <tr><td class='right subtotal'>" . t('Sub total') . "</td>";


        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            if ($coid != 'all') {
                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid like :t AND j.source = :s AND j.type=:t2 "
                        . "AND j.date >=:d1 AND j.date <=:d2 AND allocation = :c";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':t' => $class . '%',
                    ':c' => $coid,
                    ':s' => 'purchase',
                    ':t2' => 'debit'
                );
            }

            if ($coid == 'all') {

                $query = "SELECT sum(value) "
                        . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                        . "WHERE aid like :t AND j.source = :s AND j.type=:t2 "
                        . "AND j.date >=:d1 AND j.date <=:d2";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':t' => $class . '%',
                    ':s' => 'purchase',
                    ':t2' => 'debit'
                );
            }

            $total_class = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            if ($total_class == "") {
                $amount = "0";
            } else {
                $amount = round($total_class, 2);
            }

            $sum_amount = $sum_amount + $amount;

            $table_0 .="
              <td class='subtotal right' >" . number_format($amount / $divide, 2) . "</td>";
        } // for loop months


        $table_0 .="
            <td class='subtotal right' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td></tr>";

        $sum_amount = 0;
    }
} //while aid

/*
 * Purchases grand total
 */

$table_0 .= "
            <tr><td></td></tr>
            <tr>
            <td class='left grandtotal'>" . t('Grand total') . ' ' . t('purchases') . "</td>";
$grand_total_purchase = array();

for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid != 'all') {
        $query = "SELECT sum(value) "
                . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                . "WHERE j.source = :s  AND allocation=:c AND j.type=:t "
                . "AND j.date >=:d1 AND j.date <=:d2 AND ( aid like :a1 or aid like :a2 or aid like :a3 )";

        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':c' => $coid,
            ':s' => 'purchase',
            ':t' => 'debit',
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
        );
    }

    if ($coid == 'all') {
        $query = "SELECT sum(value) "
                . "FROM {ek_journal} j INNER JOIN ek_sales_purchase p ON j.reference=p.id "
                . "WHERE j.source = :s  AND j.type=:t "
                . "AND j.date >=:d1 AND j.date <=:d2 AND ( aid like :a1 or aid like :a2 or aid like :a3 )";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'purchase',
            ':t' => 'debit',
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
        );
    }

    $grand_total = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)->fetchField();

    if ($grand_total == "") {
        $amount = "0";
    } else {
        $amount = round($grand_total, 2);
    }

    $sum_amount = $sum_amount + $amount;

    $table_0 .= "
          <td class='right grandtotal' >" . number_format($amount / $divide, 2) . "</td>";

    $grand_total_purchase[$m] = $grand_total;
}  //for loop month

$table_0 .= "
         <td class='right grandtotal' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td>
         </tr>
         ";

$sum_amount = 0;


/*
 * Expenses
 */
$table_1 = "
<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th class='column_aid'><a href='#' id='expenses'>" . t('Expenses') . "</a> $year - $baseCurrency $budgetUnit</th>
      <th  class='column_value'>" . t('Jan') . "</th>
      <th  class='column_value'>" . t('Feb') . "</th>
      <th  class='column_value'>" . t('Mar') . "</th>
      <th  class='column_value'>" . t('Apr') . "</th>
      <th  class='column_value'>" . t('May') . "</th>
      <th  class='column_value'>" . t('Jun') . "</th>
      <th  class='column_value'>" . t('Jul') . "</th>
      <th  class='column_value'>" . t('Aug') . "</th>
      <th  class='column_value'>" . t('Sep') . "</th>
      <th  class='column_value'>" . t('Oct') . "</th>
      <th  class='column_value'>" . t('Nov') . "</th>
      <th  class='column_value'>" . t('Dec') . "</th>
      <th  class='column_value'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";


// Get the class 
$c = 0;

if ($coid != 'all') {

    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s AND coid=:c order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
        ':c' => $coid
    );
}

if ($coid == 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and ( aid like :a1 or aid like :a2 or aid like :a3 ) "
            . "AND astatus=:s order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['cos'] . '%',
        ':a2' => $chart['expenses'] . '%',
        ':a3' => $chart['other_expenses'] . '%',
        ':s' => 1,
    );
}
$data = Database::getConnection('external_db', 'external_db')->query($query, $a);


while ($line = $data->fetchObject()) {

    $class = substr($line->aid, 0, 2);

    $table_1_class = "
      <tr>
      <td colspan='3' class='left detail'>" . $line->aid . ' ' . $line->aname . "</td>";

    for ($m = 1; $m <= 10; $m++) {
        $table_1_class .= "
          <td class=''></td>";
    }

    // Get the account type
    if ($coid <> 'all') {

        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s AND coid=:c "
                . "ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s  "
                . "ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
        );
    }

    $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);
    $flag = 0;
    while ($d2 = $data2->fetchObject()) {

        $sum_amount = 0;
        if ($coid <> 'all') {
            $param = serialize(
                    array(
                        'id' => 'reporting',
                        'from' => $year . "-01-01",
                        'to' => $year . "-12-31",
                        'coid' => $coid,
                        'aid' => $d2->aid
            ));
            $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
            $aid = "<a class='use-ajax' href='" . $history . "' >" . $d2->aid . "</a>";
        } else {
            $aid = $d2->aid;
        }
        $table_1_detail = "
            <tr class='data1'>
            <td class='left'>" . $aid . ' ' . $d2->aname . "</td>";

        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }
            // Get the type sum  
            if ($coid != 'all') {

                $query = "SELECT sum(value) FROM {ek_journal} "
                        . "WHERE date>=:d1 AND date<=:d2 AND source <> :s "
                        . "AND type=:t AND aid like :aid AND coid=:c";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':aid' => $d2->aid,
                    ':t' => 'debit',
                    ':c' => $coid,
                    ':s' => 'purchase'
                );
            }

            if ($coid == 'all') {

                $query = "SELECT sum(value) FROM {ek_journal} "
                        . "WHERE date>=:d1 AND date<=:d2 AND type=:t AND source <> :s "
                        . "AND aid like :aid AND FIND_IN_SET(coid, :c ) ";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':aid' => $d2->aid,
                    ':t' => 'debit',
                    ':c' => $company,
                    ':s' => 'purchase'
                );
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            if ($sum_d2 == "") {
                $amount = "0";
            } else {
                $amount = round($sum_d2, 2);
            }

            $sum_amount = $sum_amount + $sum_d2;

            $table_1_detail .= "
            <td class='right' >" . number_format($sum_d2 / $divide, 2) . "</td>";
        } //for loop months


        $table_1_detail .= "
           <td class='right total' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td></tr>";

        if ($sum_amount <> 0) {
            $table_1_class .= $table_1_detail;
            $flag++;
        }
    } //while aid
    // Get the class sum - subtotal
    $sum_amount = 0;
    if ($flag > 0) {
        $table_1 .= $table_1_class;
        $table_1 .= "<tr><td class='right subtotal'>" . t('Sub total') . "</td>";

        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            if ($coid != 'all') {

                $query = "SELECT sum(value) FROM {ek_journal} "
                        . "WHERE date>=:d1 AND date<=:d2 AND type=:t AND source <> :s "
                        . "AND aid like :aid AND coid=:c";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':aid' => $class . '%',
                    ':t' => 'debit',
                    ':c' => $coid,
                    ':s' => 'purchase'
                );
            }
            if ($coid == 'all') {

                $query = "SELECT sum(value) FROM {ek_journal} "
                        . "WHERE date>=:d1 AND date<=:d2 AND source <> :s "
                        . "AND type=:t AND aid like :aid AND FIND_IN_SET(coid, :c ) ";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':aid' => $class . '%',
                    ':t' => 'debit',
                    ':c' => $company,
                    ':s' => 'purchase'
                );
            }

            $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            if ($total_class == "") {
                $amount = "0";
            } else {
                $amount = round($total_class, 2);
            }

            $sum_amount = $sum_amount + $amount;

            $table_1 .= "<td class='right subtotal' >" . number_format($amount / $divide, 2) . "</td>";
        }

        $table_1 .= "<td class='right subtotal' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td></tr>";
        $sum_amount = 0;
    }
    $c++;
} //while class
//

/*
 * Expenses GRAND Total
 */
$table_1 .= "
  <tr><td></td>
  <tr><td class='grandtotal'>" . t('Grand total') . ' ' . t('expenses') . "</td>";
$sum_amount = 0;
$grand_total_expenses = array();

for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid != 'all') {

        $query = "SELECT sum(value) FROM {ek_journal} "
                . "WHERE date>=:d1 AND date<=:d2 AND type=:t AND coid=:c AND source <> :s "
                . "AND (aid like :a1 OR aid like :a2 OR aid like :a3)";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':t' => 'debit',
            ':c' => $coid,
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
            ':s' => 'purchase'
        );
    }

    if ($coid == 'all') {

        $query = "SELECT sum(value) FROM {ek_journal} "
                . "WHERE date>=:d1 AND date<=:d2 AND source <> :s "
                . "AND type=:t AND FIND_IN_SET(coid, :c ) "
                . "AND (aid like :a1 OR aid like :a2 OR aid like :a3)";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':t' => 'debit',
            ':c' => $company,
            ':a1' => $chart['cos'] . '%',
            ':a2' => $chart['expenses'] . '%',
            ':a3' => $chart['other_expenses'] . '%',
            ':s' => 'purchase'
        );
    }

    $grand_total = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
    if ($grand_total == "") {
        $amount = "0";
    } else {
        $amount = $grand_total;
    }

    $sum_amount = $sum_amount + $amount;

    $table_1 .= "
          <td class='right grandtotal' >" . number_format($amount / $divide, 2) . "</td>";


    $grand_total_expenses[$m] = $amount;
} //loop months

$table_1 .= "
    <td class='right grandtotal' <span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td>
    </tr>
    </tbody>
    </table><hr>";

$sum_amount = 0;



/*
 * Income
 */
$table_2 = "
<table class='report'>
    <thead class='' >
      <th class='column_aid'><a href='#' id='income'>" . t('Income') . "</a> $year - $baseCurrency $budgetUnit</th>
      <th  class='column_value'>" . t('Jan') . "</th>
      <th  class='column_value'>" . t('Feb') . "</th>
      <th  class='column_value'>" . t('Mar') . "</th>
      <th  class='column_value'>" . t('Apr') . "</th>
      <th  class='column_value'>" . t('May') . "</th>
      <th  class='column_value'>" . t('Jun') . "</th>
      <th  class='column_value'>" . t('Jul') . "</th>
      <th  class='column_value'>" . t('Aug') . "</th>
      <th  class='column_value'>" . t('Sep') . "</th>
      <th  class='column_value'>" . t('Oct') . "</th>
      <th  class='column_value'>" . t('Nov') . "</th>
      <th  class='column_value'>" . t('Dec') . "</th>
      <th  class='column_value'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";

// Get the class 
if ($coid <> 'all') {
    $query = "SELECT aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and (aid like :a1 or aid like :a2) "
            . "AND astatus=:s AND coid=:c order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['income'] . '%',
        ':a2' => $chart['other_income'] . '%',
        ':s' => 1,
        ':c' => $coid
    );
}
if ($coid == 'all') {
    $query = "SELECT aid,aname FROM {ek_accounts} "
            . "WHERE atype=:t and (aid like :a1 or aid like :a2) "
            . "AND astatus=:s order by aid";
    $a = array(
        ':t' => 'class',
        ':a1' => $chart['income'] . '%',
        ':a2' => $chart['other_income'] . '%',
        ':s' => 1,
    );
}

$data = Database::getConnection('external_db', 'external_db')->query($query, $a);

while ($line = $data->fetchObject()) {
    $class = substr($line->aid, 0, 2);

    $table_2 .="
      <tr>
      <td colspan='3' class='detail'>" . $line->aid . " " . $line->aname . "</td>";


    for ($m = 1; $m <= 10; $m++) {
        $table_2 .=" <td class=''></td>";
    }

    // Get the account type
    if ($coid <> 'all') {

        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s AND coid=:c ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
                . "WHERE aid like :cl AND atype=:t AND astatus=:s  ORDER by aid";
        $a = array(
            ':cl' => $class . '%',
            ':t' => 'detail',
            ':s' => 1,
        );
    }

    $data2 = Database::getConnection('external_db', 'external_db')->query($query, $a);
    $sum_amount = 0;
    while ($d2 = $data2->fetchObject()) {
        if ($coid <> 'all') {
            $param = serialize(
                    array(
                        'id' => 'reporting',
                        'from' => $year . "-01-01",
                        'to' => $year . "-12-31",
                        'coid' => $coid,
                        'aid' => $d2->aid
            ));
            $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
            $aid = "<a class='use-ajax' href='" . $history . "' >" . $d2->aid . "</a>";
        } else {
            $aid = $d2->aid;
        }
        $table_2 .="
            <tr class='data2'>
            <td class='left'>" . $aid . " " . $d2->aname . "</td>";

        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            // Get the type sum               
            // select invoices by date in usd
            // The reporting consider invoicing value from allocation of invoice 
            // NOT from the actual invoicing entity. This is to compare revenue & expenses fron analytical point of view


            if ($coid != 'all') {

                $query = "select sum(value) FROM ek_journal j "
                        . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                        . "WHERE aid =:a AND j.source = :s "
                        . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                        . "AND j.type= :t";

                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':c' => $coid,
                    ':s' => 'invoice',
                    ':t' => 'credit'
                );
            }

            if ($coid == 'all') {
                $query = "select sum(value) FROM ek_journal j "
                        . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                        . "WHERE aid =:a AND j.source = :s "
                        . "AND j.date >=:d1 AND j.date <= :d2 "
                        . "AND j.type= :t";
                $a = array(
                    ':d1' => $date1,
                    ':d2' => $date2,
                    ':a' => $d2->aid,
                    ':s' => 'invoice',
                    ':t' => 'credit'
                );
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            if ($sum_d2 == "") {
                $amount = "0";
            } else {
                $amount = round($sum_d2, 2);
            }

            $sum_amount = $sum_amount + $amount;

            $table_2 .= "
              <td class='right' >" . number_format($amount / $divide, 2) . "</td>";
        } //for loop months


        $table_2 .= "
            <td class='total right' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td></tr>";

        $sum_amount = 0;
    } //while aid
    // Get the class sum - subtotal
    $table_2 .= "
          <tr><td class='right subtotal'>" . t('Sub total') . "</td>";


    for ($m = 1; $m <= 12; $m++) {
        if ($m < 10) {
            $date1 = $year . "-0" . $m . "-01";
            $date2 = $year . "-0" . $m . "-31";
        } else {
            $date1 = $year . "-" . $m . "-01";
            $date2 = $year . "-" . $m . "-31";
        }

        if ($coid != 'all') {
            $query = "select sum(value) FROM ek_journal j "
                    . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                    . "WHERE aid like :t AND j.source = :s "
                    . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                    . "AND j.type= :t2";

            $a = array(
                ':d1' => $date1,
                ':d2' => $date2,
                ':t' => $class . '%',
                ':c' => $coid,
                ':t2' => 'credit',
                ':s' => 'invoice'
            );
        }

        if ($coid == 'all') {

            $query = "select sum(value) FROM ek_journal j "
                    . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                    . "WHERE aid like :t AND j.source = :s "
                    . "AND j.date >=:d1 AND j.date <= :d2 "
                    . "AND j.type= :t2";

            $a = array(
                ':d1' => $date1,
                ':d2' => $date2,
                ':t' => $class . '%',
                ':t2' => 'credit',
                ':s' => 'invoice'
            );
        }

        $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        if ($total_class == "") {
            $amount = "0";
        } else {
            $amount = round($total_class, 2);
        }

        $sum_amount = $sum_amount + $amount;

        $table_2 .="
          <td class='subtotal right' >" . number_format($amount / $divide, 2) . "</td>";
    } // for loop months


    $table_2 .="
        <td class='subtotal right' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td></tr>";


    $sum_amount = 0;
} //while aid


/*
 * Income grand total
 */

$table_2 .= "
            <tr><td></td></tr>
            <tr>
            <td class='left grandtotal'>" . t('Grand total') . " " . t('income') . "</td>";
$grand_total_revenue = array();

for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid != 'all') {

        $query = "select sum(value) FROM {ek_journal} j "
                . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                . "WHERE j.source = :s "
                . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                . "AND j.type= :t AND (aid like :a1 or aid like :a2) ";

        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':c' => $coid,
            ':s' => 'invoice',
            ':t' => 'credit',
            ':a1' => $chart['income'] . '%',
            ':a2' => $chart['other_income'] . '%',
        );
    }

    if ($coid == 'all') {
        $query = "select sum(value) FROM {ek_journal} j "
                . "INNER JOIN ek_sales_invoice i ON i.id=j.reference "
                . "WHERE j.source = :s "
                . "AND j.date >=:d1 AND j.date <= :d2 AND allocation = :c "
                . "AND j.type= :t AND (aid like :a1 or aid like :a2)";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'invoice',
            ':t' => 'credit',
            ':a1' => $chart['income'] . '%',
            ':a2' => $chart['other_income'] . '%',
        );
    }

    $grand_total = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

    if ($grand_total == "") {
        $amount = "0";
    } else {
        $amount = round($grand_total, 2);
    }

    $sum_amount = $sum_amount + $amount;

    $table_2 .= "
          <td class='right grandtotal' >" . number_format($amount / $divide, 2) . "</td>";

    //$grand_total_revenue[$m] = $line5["sum(valueusd)"]+$amount_i[$m];//includes internal transactions   
    $grand_total_revenue[$m] = $grand_total; //exclude internal transactions  
}  //for loop month

$table_2 .= "
         <td class='right grandtotal' ><span id=''>" . number_format($sum_amount / $divide, 2) . "</span></td>
         </tr>
         ";

$sum_amount = 0;

/*
 * Internal Revenue
 */
$table_2 .= "
            <tr>
            <td class='detail left'><span>" . t('Internal transfers') . "</span></td>";



for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }
    // Get the type sum  
    if ($coid != 'all') {

        $query = "SELECT sum(amount_paid_base) from {ek_expenses_memo} "
                . "WHERE date>=:d1 AND date<=:d2 AND entity=:c and category <> :cat "
                . "and status > :s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
            ':cat' => 5,
            ':c' => $coid
        );
    }

    if ($coid == 'all') {
        $query = "SELECT sum(amount_paid_base) from {ek_expenses_memo} "
                . "WHERE date>=:d1 AND date<=:d2 AND FIND_IN_SET(entity, :c ) "
                . "and category <> :cat and status > :s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
            ':cat' => 5,
            ':c' => $company
        );
    }

    $line4 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

    if ($line4 == "") {
        $amount_i[$m] = "0";
    } else {
        $amount_i[$m] = round($line4, 2);
    }
    $sum_amount = $sum_amount + $amount_i[$m];

    $table_2 .= "
            <td class='right'>" . number_format($amount_i[$m] / $divide, 2) . "</td>";
}
$table_2 .= "<td class='right grandtotal'>" . number_format($sum_amount / $divide, 2) . "</td></tr></tbody></table>";
$thissum_i[$m] = $sum_amount;
//

$sum_amount = 0;





///////////////////////
//P&L /////////////////
$profitloss = array();

$table_3 = "
<table class='report'>
    <thead class='' >
      <th class='column_aid'><a href='#' id='balance'>" . t('Balances') . "</a> $year - $baseCurrency $budgetUnit </th>
      <th  class='column_value'>" . t('Jan') . "</th>
      <th  class='column_value'>" . t('Feb') . "</th>
      <th  class='column_value'>" . t('Mar') . "</th>
      <th  class='column_value'>" . t('Apr') . "</th>
      <th  class='column_value'>" . t('May') . "</th>
      <th  class='column_value'>" . t('Jun') . "</th>
      <th  class='column_value'>" . t('Jul') . "</th>
      <th  class='column_value'>" . t('Aug') . "</th>
      <th  class='column_value'>" . t('Sep') . "</th>
      <th  class='column_value'>" . t('Oct') . "</th>
      <th  class='column_value'>" . t('Nov') . "</th>
      <th  class='column_value'>" . t('Dec') . "</th>
      <th  class='column_value'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";

$table_3 .= " 
  <tr><td class=''>" . t('Gain/Loss') . "</td>";
$sum_pl = 0;
for ($m = 1; $m <= 12; $m++) {
    $pl = $grand_total_revenue[$m] - $grand_total_purchase[$m] - $grand_total_expenses[$m];
    if ($pl < 0) {
        $table_3 .= "
          <td class='red right' ><span id=''>" . number_format($pl / $divide, 2) . "</span></td>";
    } else {
        $table_3 .= "
          <td class='right' ><span id='' class='' >" . number_format($pl / $divide, 2) . "</span></td>";
    }

    $sum_pl = $sum_pl + $pl;
}


$table_3 .= "
    <td class='right total'> " . number_format($sum_pl / $divide, 2) . "</td></tr>";

/////////////////////////////////////////////////////////////////////////////////////////////////  
//Invoice not yet received from unpaid invoices 

$table_3 .= "
            <tr>
            <td class=''><span title='" . t('open invoices') . "'>" . t('Payments not yet received') . "</span></td>";
$sum_nyr = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    // Get the sum of payments not received  
    if ($coid <> 'all') {
        $query = "SELECT sum(amountbase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND allocation=:c AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
            ':c' => $coid
        );
    }
    if ($coid == 'all') {
        $query = "SELECT sum(amountbase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 0,
        );
    }

    $not_received = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

    if ($not_received == "") {
        $amount = "0";
    } else {
        $amount = round($not_received, 2);
    }

    $sum_nyr = $sum_nyr + $amount;

    $table_3 .= "
              <td class='right' >" . number_format($amount / $divide, 2) . "</td>";
} //for loop months   

$table_3 .= "
            <td class='right total' ><span id=''>" . number_format($sum_nyr / $divide, 2) . "</span></td></tr>";



/////////////////////////////////////////////////////////////////////////////////////////////////  
//Invoice not yet received from paid invoices 
$table_3 .= "
            <tr>
            <td ><span title='" . t('closed invoices') . "'>" . t('Payments not received') . "</span></td>";
$sum_np = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    if ($coid <> 'all') {
        $query = "SELECT sum(balancebase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND allocation=:c AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 1,
            ':c' => $coid
        );
    }
    if ($coid == 'all') {
        $query = "SELECT sum(balancebase) FROM {ek_sales_invoice} "
                . "WHERE date>=:d1 and date<=:d2 AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 1,
        );
    }

    $not_paid = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

    if ($not_paid == "") {
        $amount = "0";
    } else {
        $amount = round($not_paid, 2);
    }

    $sum_np = $sum_np + $amount;

    $table_3 .= "
              <td class='right' >" . number_format($amount / $divide, 2) . "</td>";
} //for loop months   

$table_3 .= "
            <td class='right total' ><span id=''>" . number_format($sum_np / $divide, 2) . "</span></td></tr>";

///////////////////////////////////////////////////////////////////////////////////////////////// 
//Expenses not yet paid  
$table_3 .= "
            <tr>
            <td '><span>" . t('Expenses not yet paid') . "</span></td>";
$sum_np = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }


    if ($coid <> 'all') {

        $query = "SELECT sum(amount) FROM {ek_expenses} "
                . "WHERE pdate>=:d1 AND pdate<=:d2 AND company=:c AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'no',
            ':c' => $coid
        );
    }
    if ($coid == 'all') {
        $query = "SELECT sum(amount) FROM {ek_expenses} "
                . "WHERE pdate>=:d1 AND pdate<=:d2 AND status=:s";
        $a = array(
            ':d1' => $date1,
            ':d2' => $date2,
            ':s' => 'no',
        );
    }

    $not_paid = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

    if ($not_paid == "") {
        $amount = "0";
    } else {
        $amount = round($not_paid, 2);
    }

    $sum_np = $sum_np + $amount;

    $table_3 .= "
              <td class='right' >" . number_format($amount / $divide, 2) . "</td>";
} //for loop months   

$table_3 .= "
            <td class='right total' ><span id=''>" . number_format($sum_np / $divide, 2) . "</span></td></tr>";

$table_3 .= "
  </tbody>
  </table>";

