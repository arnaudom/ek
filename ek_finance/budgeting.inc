<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);
$table_1 = "
<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th width='20%'><a href='#expenses' id='expenses'>" . t('Expenses') . "</a> $year - $baseCurrency $budgetUnit</th>
      <th  width='6%'>" . t('Jan') . "</th>
      <th  width='6%'>" . t('Feb') . "</th>
      <th  width='6%'>" . t('Mar') . "</th>
      <th  width='6%'>" . t('Apr') . "</th>
      <th  width='6%'>" . t('May') . "</th>
      <th  width='6%'>" . t('Jun') . "</th>
      <th  width='6%'>" . t('Jul') . "</th>
      <th  width='6%'>" . t('Aug') . "</th>
      <th  width='6%'>" . t('Sep') . "</th>
      <th  width='6%'>" . t('Oct') . "</th>
      <th  width='6%'>" . t('Nov') . "</th>
      <th  width='6%'>" . t('Dec') . "</th>
      <th  width='6%'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";


// Get the class 
$c = 0;

if ($coid <> 'all') {

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
    $class_exp = substr($line->aid, 0, 1);
    $table_1 .= "
      <tr>
      <td colspan='3' class='left detail'>" . $line->aid . ' ' . $line->aname . "</td>";


    for ($m = 1; $m <= 10; $m++) {
        $table_1 .= "
          <td class=''></td>";
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

    while ($d2 = $data2->fetchObject()) {

        $sum_amount = 0;
        $table_1 .= "
            <tr class='data1'>
            <td class='left'>" . $d2->aid . ' ' . $d2->aname . "</td>";

        for ($m = 1; $m <= 12; $m++) {


            //build the reference from table aid-coid-year-month
            // Get the type sum by filter (TODO 'all') 
            if ($coid != 'all') {

                $field_name = $d2->aid . "-" . $coid . "-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference = :r";
                $a = [ ':r' => $field_name];
            }

            if ($coid == 'all') {
                $field_name = $d2->aid . "-%-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference like :r";
                $a = [ ':r' => $field_name];
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();

            if ($sum_d2 == "") {
                $amount = "0";
            } else {
                $amount = round($sum_d2, 2);
            }

            $sum_amount = $sum_amount + $sum_d2;

            $id = $d2->aid . "-" . $coid . "-" . $year . "-" . $m;
            //set an input field for the value
            $input = "<INPUT  type='text'"
                    . "name='" . $id . "' "
                    . "id='" . $id . "' "
                    . "class='editinline' size='8' "
                    . "value='" . $amount . "'/>";


            $table_1 .= "
            <td class='right' >" . $input . "</td>";
        } //for loop months

        $id = $d2->aid . "-total";
        $table_1 .= "
           <td class='right total' ><span id='" . $id . "'>" . number_format($sum_amount, 2) . "</span></td></tr>";
    } //while aid
    // Get the class sum - subtotal
    $sum_amount = 0;
    $table_1 .= "
          <tr><td class='right subtotal'>" . t('Sub total') . "</td>";

    for ($m = 1; $m <= 12; $m++) {


        if ($coid != 'all') {
            $reference = $class . "%-" . $coid . "-" . $year . "-" . $m;
        }
        if ($coid == 'all') {
            $reference = "%-%-" . $year . "-" . $m;
        }

        $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE reference like :r";
        $a = [ ':r' => $reference];

        $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        if ($total_class == "") {
            $amount = "0";
        } else {
            $amount = round($total_class, 2);
        }


        $sum_amount = $sum_amount + $amount;
        $id = $class . '-' . $m . '-total';
        $table_1 .= "
                <td class='right subtotal' ><span id='" . $id . "'>" . number_format($amount, 2) . "</span></td>";
    }

    $id = $class . '-subtotal';
    $table_1 .= "
          <td class='right subtotal' ><span id='" . $id . "'>" . number_format($sum_amount, 2) . "</span></td></tr>";
    $sum_amount = 0;
    $c++;
} //while class 
//GRAND Total
$table_1 .= "
  <tr><td></td>
  <tr><td class='grandtotal'>" . t('Grand total') . "</td>";
$sum_amount = 0;
$grand_total_expenses = array();

for ($m = 1; $m <= 12; $m++) {

    if ($coid != 'all') {
        $reference1 = $chart['expenses'] . '%-' . $coid . "-" . $year . "-" . $m;
        $reference2 = $chart['cos'] . '%-' . $coid . "-" . $year . "-" . $m;
        $reference3 = $chart['other_expenses'] . '%-' . $coid . "-" . $year . "-" . $m;
    }

    if ($coid == 'all') {
        $reference1 = $chart['expenses'] . "%-%-" . $year . "-" . $m;
        $reference2 = $chart['cos'] . "%-%-" . $year . "-" . $m;
        $reference3 = $chart['other_expenses'] . "%-%-" . $year . "-" . $m;
    }

    $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE "
            . "reference like :r1 or reference like :r2 or reference like :r3";
    $a = [ ':r1' => $reference1, ':r2' => $reference2, ':r3' => $reference3];

    $grand_total = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
    if ($grand_total == "") {
        $amount = "0";
    } else {
        $amount = $grand_total;
    }

    $sum_amount = $sum_amount + $amount;

    $id = $class_exp . '-' . $m . '-grandtotal';
    $table_1 .= "
          <td class='right grandtotal' ><span id='" . $id . "'>" . number_format($amount, 2) . "</span></td>";


    $grand_total_expenses[$m] = $amount;
} //loop months

$table_1 .= "
    <td class='right grandtotal' ><span id='sumexpense'>" . number_format($sum_amount, 2) . "</span></td></tr>
    </tbody>
    </table><hr>";

$sum_amount = 0;




$table_2 = "
<table class=''>
    <thead class='' >
      <th width='20%'><a href='#income' id='income'>" . t('Income') . "</a> $year - $baseCurrency $budgetUnit</th>
      <th  width='6%'>" . t('Jan') . "</th>
      <th  width='6%'>" . t('Feb') . "</th>
      <th  width='6%'>" . t('Mar') . "</th>
      <th  width='6%'>" . t('Apr') . "</th>
      <th  width='6%'>" . t('May') . "</th>
      <th  width='6%'>" . t('Jun') . "</th>
      <th  width='6%'>" . t('Jul') . "</th>
      <th  width='6%'>" . t('Aug') . "</th>
      <th  width='6%'>" . t('Sep') . "</th>
      <th  width='6%'>" . t('Oct') . "</th>
      <th  width='6%'>" . t('Nov') . "</th>
      <th  width='6%'>" . t('Dec') . "</th>
      <th  width='6%'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";

// Get the class 
if ($coid <> 'all') {
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
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
    $query = "SELECT SQL_CACHE aid,aname FROM {ek_accounts} "
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
    $class_inc = substr($line->aid, 0, 1);
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
        $table_2 .="
            <tr class='data2'>
            <td class='left'>" . $d2->aid . " " . $d2->aname . "</td>";

        for ($m = 1; $m <= 12; $m++) {


            if ($coid != 'all') {

                $field_name = $d2->aid . "-" . $coid . "-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference = :r";
                $a = [ ':r' => $field_name];
            }

            if ($coid == 'all') {
                $field_name = $d2->aid . "-%-" . $year . "-" . $m;
                $query = "SELECT value_base FROM {ek_yearly_budget} WHERE reference like :r";
                $a = [ ':r' => $field_name];
            }

            $sum_d2 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            if ($sum_d2 == "") {
                $amount = "0";
            } else {
                $amount = round($sum_d2, 2);
            }

            $sum_amount = $sum_amount + $amount;
            $id = $d2->aid . "-" . $coid . "-" . $year . "-" . $m;
            //set an input field for the value
            $input = "<INPUT  type='text'"
                    . "name='" . $id . "' "
                    . "id='" . $id . "' "
                    . "class='editinline' size='5' "
                    . "value='" . $amount . "'/>";

            $table_2 .= "
              <td class='right' >" . $input . "</td>";
        } //for loop months

        $id = $d2->aid . "-total";
        $table_2 .= "
            <td class='total right' ><span id='" . $id . "'>" . number_format($sum_amount, 2) . "</span></td></tr>";

        $sum_amount = 0;
    } //while aid
    // Get the class sum - subtotal
    $table_2 .= "
          <tr><td class='right subtotal'>" . t('Sub total') . "</td>";


    for ($m = 1; $m <= 12; $m++) {


        if ($coid != 'all') {
            $reference = $class . "%-" . $coid . "-" . $year . "-" . $m;
        }

        if ($coid == 'all') {
            $reference = "%-%-" . $year . "-" . $m;
        }

        $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE reference like :r";
        $a = [ ':r' => $reference];

        $total_class = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        if ($total_class == "") {
            $amount = "0";
        } else {
            $amount = round($total_class, 2);
        }

        $sum_amount = $sum_amount + $amount;
        $id = $class . '-' . $m . '-total';
        $table_2 .="
          <td class='subtotal right' ><span id='" . $id . "'>" . number_format($amount, 2) . "</span></td>";
    } // for loop months

    $id = $class . '-subtotal';
    $table_2 .="
        <td class='subtotal right' ><span id='" . $id . "'>" . number_format($sum_amount, 2) . "</span></td></tr>";


    $sum_amount = 0;
} //while aid
/////////////////////
//GRAND Total
$table_2 .= "
            <tr><td></td></tr>
            <tr>
            <td class='left grandtotal'>" . t('Grand total') . "</td>";
$grand_total_revenue = array();

for ($m = 1; $m <= 12; $m++) {


    if ($coid != 'all') {
        $reference1 = $chart['income'] . "%-" . $coid . "-" . $year . "-" . $m;
        $reference2 = $chart['other_income'] . "%-" . $coid . "-" . $year . "-" . $m;
    }

    if ($coid == 'all') {
        $reference1 = $chart['income'] . "%-%-" . $year . "-" . $m;
        $reference2 = $chart['other_income'] . "%-%-" . $year . "-" . $m;
    }

    $query = "SELECT sum(value_base) FROM {ek_yearly_budget} WHERE "
            . "reference like :r1 or reference like :r2";
    $a = [ ':r1' => $reference1, ':r2' => $reference2];
    $grand_total = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)->fetchField();

    if ($grand_total == "") {
        $amount = "0";
    } else {
        $amount = round($grand_total, 2);
    }

    $sum_amount = $sum_amount + $amount;
    $id = $class_inc . '-' . $m . '-grandtotal';
    $table_2 .= "
          <td class='right grandtotal' ><span id='" . $id . "'>" . number_format($amount, 2) . "</span></td>";

    //$grand_total_revenue[$m] = $line5["sum(valueusd)"]+$amount_i[$m];//includes internal transactions   
    $grand_total_revenue[$m] = $grand_total; //exclude internal transactions  
}  //for loop month

$table_2 .= "
         <td class='right grandtotal' ><span id='sumincome'>" . number_format($sum_amount, 2) . "</span></td></tr>
         ";

$sum_amount = 0;


$table_2 .= "
  </tbody>
  </table>";


///////////////////////
//P&L /////////////////
$profitloss = array();
$sum_pl = 0;
$table_3 = "
<table style= border=0 cellpadding=1 cellspacing=0 class=''>
    <thead class='' >
      <th width='20%'><a href='#balance' id='balance'>" . t('Balances') . "</a> $year - $baseCurrency $budgetUnit</th>
      <th  width='6%'>" . t('Jan') . "</th>
      <th  width='6%'>" . t('Feb') . "</th>
      <th  width='6%'>" . t('Mar') . "</th>
      <th  width='6%'>" . t('Apr') . "</th>
      <th  width='6%'>" . t('May') . "</th>
      <th  width='6%'>" . t('Jun') . "</th>
      <th  width='6%'>" . t('Jul') . "</th>
      <th  width='6%'>" . t('Aug') . "</th>
      <th  width='6%'>" . t('Sep') . "</th>
      <th  width='6%'>" . t('Oct') . "</th>
      <th  width='6%'>" . t('Nov') . "</th>
      <th  width='6%'>" . t('Dec') . "</th>
      <th  width='6%'>" . t('Total') . "</th>
    </thead>
    <tbody class=''>";

$table_3 .= " 
  <tr><td class=''>" . t('Gain/Loss') . "</td>";

for ($m = 1; $m <= 12; $m++) {
    $pl = $grand_total_revenue[$m] - $grand_total_expenses[$m];
    $id = 'balance-' . $m;
    if ($pl < 0) {
        $table_3 .= "
          <td class='right' ><span class='red' id='" . $id . "'>" . number_format($pl, 2) . "</span></td>";
    } else {
        $table_3 .= "
          <td class='right' ><span id='" . $id . "' class='' >" . number_format($pl, 2) . "</span></td>";
    }

    $sum_pl = $sum_pl + $pl;
}

if ($sum_pl < 0) {
    $table_3 .= "
          <td class='right total'><span id='balancetotal' class='red' > " . number_format($sum_pl, 2) . "</span></td></tr>";
} else {
    $table_3 .= "
          <td class='right total'><span id='balancetotal' class='' > " . number_format($sum_pl, 2) . "</span></td></tr>";
}


/////////////////////////////////////////////////////////////////////////////////////////////////  



$table_3 .= "
  </tbody>
  </table>";

