<?php

//use DateTime;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;

$company = AccessCheck::GetCompanyByUser();
$company = implode(',', $company);

$purchases = [];
$expenses = [];
$income = [];
$internal_received = [];
$internal_paid = [];
$balances = [];

// build accounts list for names 
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_accounts', 'a');
$query->fields('a', ['aid', 'aname']);
$query->condition('coid', $coid);
$query->orderBy('aid');
$accounts = $query->execute()->fetchAllKeyed();

// build accounts array structures for expenses
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_accounts', 'a');
$query->fields('a', ['aid', 'aname']);
$or = $query->orConditionGroup();
$or->condition('aid', $chart['cos'] . '%', 'like');
$or->condition('aid', $chart['expenses'] . '%', 'like');
$or->condition('aid', $chart['other_expenses'] . '%', 'like');

$query->condition($or);
$query->condition('atype', 'class');
$query->condition('astatus', '1');

if ($coid != 'all') {
    $query->condition('coid', $coid);
}
$query->orderBy('aid');
$accounts_exp_class = $query->execute()->fetchAllKeyed();


// Purchases
// journal data linked to purchase table. The reporting consider purchase value from allocation of purchase 
// NOT from the actual purchasing entity. This is to compare revenue & expenses fron analytical point of view
// Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
// pull all data in single array
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_journal', 'j');
$query->leftJoin('ek_sales_purchase', 'e', 'e.id = j.reference');
$query->fields('j');
$query->condition('j.type', 'debit');
$query->condition($viewS, $coid); // get actual or allocated
$query->condition('j.date', "$year%", 'LIKE');
$query->orderBy('aid');
$Obj = $query->execute();
$journal_data = $Obj->fetchAll();

$classes = [];
$class_total_p = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
foreach ($accounts_exp_class as $key => $name) {

    $class = substr($key, 0, 2);
    $sum_row_amount = 0;
    $rows = [];
    $column_total['sumRow'] = 0;
    
    for ($m = 1; $m <= 12; $m++) {

        $column_total[$m] = 0;
        
        if ($m < 10) {
            $date1 = $year . "-0" . $m . "-01";
            $d = new DateTime($date1);
            $date2 = $d->format('Y-m-t');
        } else {
            $date1 = $year . "-" . $m . "-01";
            $d = new DateTime($date1);
            $date2 = $d->format('Y-m-t');
        }
        foreach ($journal_data as $key => $values) {
            $a = substr($values->aid, 0, 2);
            if ($a == $class) {

                if ($values->date >= $date1 && $values->date <= $date2 && $values->source == 'purchase') {
                    
                    $param = serialize(
                            [   'id' => 'reporting',
                                'from' => $year . "-01-01",
                                'to' => $year . "-12-31",
                                'coid' => $coid,
                                'aid' => $values->aid
                    ]);
                    $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                    $link = "<a class='use-ajax' href='" . $history . "' >" . $values->aid . "</a>";
                    
                    if (!isset($rows[$values->aid . ' - ' . $accounts[$values->aid]][$m] )) {
                        //$rows[$values->aid . ' - ' . $accounts[$values->aid]] = [];
                        //$rows[$values->aid . ' - ' . $accounts[$values->aid]][$m] = 0;
                        //$rows[$values->aid . ' - ' . $accounts[$values->aid]]['sumRow'] = 0;
                    }
                    $v = round($values->value / $divide, $rounding);
                    $rows[$values->aid]['desc'] = $accounts[$values->aid];
                    $rows[$values->aid]['link'] = $link;
                    $rows[$values->aid][$m] += $v;
                    $rows[$values->aid ]['sumRow'] += $v;
                    $column_total[$m] += $v;
                    $column_total['sumRow'] += $v;
                    $class_total_p[$m] += $v;
                    $class_total_p['sumRow'] += $v;
                }
            } // filter account per class
        } // loop data in month
    } // loop months

    if ($column_total['sumRow'] > 0) {
        // only compile class with positive debit; don't diaplay 0 values
        $classes[] = ['id' => $class, 'name' => $name, 'rows' => $rows, 'subTotal' => $column_total];
    }
} // next class

array_push($purchases, ['classes' => $classes, 'total' => $class_total_p]);

// Expenses
// journal data linked to expense table. The reporting consider purchase value from allocation of expense 
// NOT from the actual spending entity. This is to compare revenue & expenses from analytical point of view
// Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
// pull all data in single array
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_journal', 'j');
$query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');
$query->fields('j');
$query->condition('j.type', 'debit');
$query->condition($viewE, $coid); // get actual or allocated
$query->condition('date', "$year%", 'LIKE');
$query->orderBy('aid');
$Obj = $query->execute();
$journal_data = $Obj->fetchAll();

$classes = [];
$class_total_e = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
foreach ($accounts_exp_class as $key => $name) {

    $class = substr($key, 0, 2);
    $sum_row_amount = 0;
    $rows = [];
    $column_total['sumRow'] = 0;
    for ($m = 1; $m <= 12; $m++) {

        $column_total[$m] = 0;
        
        if ($m < 10) {
            $date1 = $year . "-0" . $m . "-01";
            $d = new DateTime($date1);
            $date2 = $d->format('Y-m-t');
        } else {
            $date1 = $year . "-" . $m . "-01";
            $d = new DateTime($date1);
            $date2 = $d->format('Y-m-t');
        }
        foreach ($journal_data as $key => $values) {
            $a = substr($values->aid, 0, 2);
            if ($a == $class) {
                if ($values->date >= $date1 && $values->date <= $date2 && $values->source != 'purchase') {
                    $param = serialize(
                            ['id' => 'reporting',
                                'from' => $year . "-01-01",
                                'to' => $year . "-12-31",
                                'coid' => $coid,
                                'aid' => $values->aid
                    ]);
                    $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                    $link = "<a class='use-ajax' href='" . $history . "' >" . $values->aid . "</a>";

                    if (!isset($rows[$link . ' - ' . $accounts[$values->aid]][$m])) {
                        //$rows[$link . ' - ' . $accounts[$values->aid]] = [];
                        //$rows[$link . ' - ' . $accounts[$values->aid]][$m] = 0;
                        //$rows[$link . ' - ' . $accounts[$values->aid]]['sumRow'] = 0;
                    }
                    $v = round($values->value / $divide, $rounding);
                    
                    $rows[$values->aid]['desc'] = $accounts[$values->aid];
                    $rows[$values->aid]['link'] = $link;
                    $rows[$values->aid][$m] += $v;
                    $rows[$values->aid ]['sumRow'] += $v;
                    $column_total[$m]  += $v;
                    $column_total['sumRow'] += $v;
                    $class_total_e[$m] += $v;
                    $class_total_e['sumRow'] += $v;
                }
            } // filter account per class
        } // loop data in month
    } // loop months

    if ($column_total['sumRow'] > 0) {
        // only compile class with positive debit; don't diaplay 0 values
        $classes[] = ['id' => $class, 'name' => $name, 'rows' => $rows, 'subTotal' => $column_total];
    }
} // next class

array_push($expenses, ['classes' => $classes, 'total' => $class_total_e]);



// Income
// journal data linked to sales table. The reporting consider sales value from allocation of sales 
// NOT from the actual invoicing entity. This is to compare revenue & expenses from analytical point of view
// Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
// pull all data in single array
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_journal', 'j');
$query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');
$query->fields('j');
$query->condition('j.type', 'credit');
$query->condition($viewS, $coid);
$query->condition('j.date', "$year%", 'LIKE');
$query->orderBy('aid');
$Obj = $query->execute();
$journal_data = $Obj->fetchAll();

// Get the class array structure
$query = Database::getConnection('external_db', 'external_db')
        ->select('ek_accounts', 'a');
$query->fields('a', ['aid', 'aname']);
$or = $query->orConditionGroup();
$or->condition('aid', $chart['income'] . '%', 'like');
$or->condition('aid', $chart['other_income'] . '%', 'like');
$query->condition($or);
$query->condition('atype', 'class');
$query->condition('astatus', '1');
$query->condition('coid', $coid);
$query->orderBy('aid');
$accounts_income_class = $query->execute()->fetchAllKeyed();

$classes = [];
$class_total_i = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
foreach ($accounts_income_class as $key => $name) {

    $class = substr($key, 0, 2);
    $sum_row_amount = 0;
    $rows = [];
    $column_total['sumRow'] = 0;
    for ($m = 1; $m <= 12; $m++) {
        $column_total[$m] = 0;       
        if ($m < 10) {
            $date1 = $year . "-0" . $m . "-01";
            $d = new DateTime($date1);
            $date2 = $d->format('Y-m-t');
        } else {
            $date1 = $year . "-" . $m . "-01";
            $d = new DateTime($date1);
            $date2 = $d->format('Y-m-t');
        }
        foreach ($journal_data as $key => $values) {
            $a = substr($values->aid, 0, 2);
            if ($a == $class) {
                if ($values->date >= $date1 && $values->date <= $date2 && $values->source == 'invoice') {
                    $param = serialize(
                            ['id' => 'reporting',
                                'from' => $year . "-01-01",
                                'to' => $year . "-12-31",
                                'coid' => $coid,
                                'aid' => $values->aid
                    ]);
                    $history = Url::fromRoute('ek_finance_modal', ['param' => $param], [])->toString();
                    $link = "<a class='use-ajax' href='" . $history . "' >" . $values->aid . "</a>";
                    if (!isset($rows[$values->aid . ' - ' . $accounts[$values->aid]][$m])) {
                        //$rows[$link . ' - ' . $accounts[$values->aid]] = [];
                        //$rows[$link . ' - ' . $accounts[$values->aid]][$m] = 0;
                        //$rows[$link . ' - ' . $accounts[$values->aid]]['sumRow'] = 0;
                    }
                    $v = round($values->value / $divide, $rounding);
                    $rows[$values->aid]['desc'] = $accounts[$values->aid];
                    $rows[$values->aid]['link'] = $link;
                    $rows[$values->aid][$m] += $v;
                    $rows[$values->aid ]['sumRow'] += $v;
                    $column_total[$m] += $v;
                    $column_total['sumRow'] += $v;
                    $class_total_i[$m] += $v;
                    $class_total_i['sumRow'] += $v;
                }
            } // filter account per class
        } // loop data in month
    } // loop months

    if ($column_total['sumRow'] > 0) {
        // only compile class with positive debit; don't diaplay 0 values
        $classes[] = ['id' => $class, 'name' => $name, 'rows' => $rows, 'subTotal' => $column_total];
    }
} // next class

array_push($income, ['classes' => $classes, 'total' => $class_total_i]);


// Internal Revenue

$classes = [];
$grandtotal_column_internal_received = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    } else {
        $date1 = $year . "-" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    }

    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_expenses_memo', 'm');
    $query->addExpression('SUM(amount_paid_base)', 'sumValue');
    $query->condition('date', $date1, '>=');
    $query->condition('date', $date2, '<=');
    $query->condition('category', 5, '<>');
    $query->condition('status', 0, '>');
    $query->condition('entity', $coid);

    $Obj = $query->execute();
    $sumValue = $Obj->fetchObject()->sumValue;
    if ($sumValue == "") {
        $grandtotal_column_internal_received[$m] = 0;
    } else {
        $grandtotal_column_internal_received[$m] = round($sumValue / $divide, $rounding);
        $sum += $sumValue;
    }

    $grandtotal_column_internal_received['sumRow'] = round($sum / $divide, $rounding);
}

array_push($internal_received, $grandtotal_column_internal_received);
$sum_row_amount = 0;

$classes = [];
$grandtotal_column_internal_paid = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    } else {
        $date1 = $year . "-" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    }

    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_expenses_memo', 'm');
    $query->addExpression('SUM(amount_paid_base)', 'sumValue');
    $query->condition('date', $date1, '>=');
    $query->condition('date', $date2, '<=');
    $query->condition('category', 5, '<>');
    $query->condition('status', 0, '>');
    $query->condition('entity_to', $coid);
    $Obj = $query->execute();
    $sumValue = $Obj->fetchObject()->sumValue;

    if ($sumValue == "") {
        $grandtotal_column_internal_paid[$m] = 0;
    } else {
        $grandtotal_column_internal_paid[$m] = round($sumValue / $divide, $rounding);
        $sum += $sumValue;
    }

    $grandtotal_column_internal_paid['sumRow'] = round($sum / $divide, $rounding);
}

array_push($internal_paid, $grandtotal_column_internal_paid);
$sum_row_amount = 0;



//
// Balances
// PL
//
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    $balances['pl'][$m] = round(($class_total_i[$m] - $class_total_p[$m] - $class_total_e[$m])/ $divide, $rounding);
}
$balances['pl']['sumRow'] = round(($class_total_i['sumRow'] - $class_total_p['sumRow'] - $class_total_e['sumRow']) / $divide, $rounding);

//
// Balances
// INYR
//
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    } else {
        $date1 = $year . "-" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    }

    // Get the sum of payments not received 
    // TODO get data from journal

    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_sales_invoice', 'i');
    $query->addExpression('SUM(amountbase)', 'sumValue');
    $query->condition('date', $date1, '>=');
    $query->condition('date', $date2, '<=');
    $query->condition('status', '0');

    if ($coid != 'all') {
        $query->condition($viewS, $coid);
    }

    $Obj = $query->execute();
    $not_received = $Obj->fetchObject()->sumValue;

    if ($not_received == "") {
        $balances['inyr'][$m] = 0;
    } else {
        $balances['inyr'][$m] = round($not_received / $divide, $rounding);
        $sum += $not_received;
    }
} // loop months   

$balances['inyr']['sumRow'] = round($sum / $divide, $rounding);



//
// Balances
// short payments
//
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    } else {
        $date1 = $year . "-" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    }

    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_sales_invoice', 'i');
    $query->addExpression('SUM(balancebase)', 'sumValue');
    $query->condition('date', $date1, '>=');
    $query->condition('date', $date2, '<=');
    $query->condition('status', '1');

    if ($coid != 'all') {
        $query->condition($viewS, $coid);
    }

    $Obj = $query->execute();
    $not_paid = $Obj->fetchObject()->sumValue;

    if ($not_paid == "") {
        $balances['short'][$m] = 0;
    } else {
        $balances['short'][$m] = round($not_paid / $divide, $rounding);
        $sum += $not_received;
    }
    $balances['short']['sumRow'] = round($sum / $divide, $rounding);
} //for loop months   
//
// Balances
// ENYP
//

$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    } else {
        $date1 = $year . "-" . $m . "-01";
        $d = new DateTime($date1);
        $date2 = $d->format('Y-m-t');
    }

    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_expenses', 'e');
    $query->addExpression('SUM(amount)', 'sumValue');
    $query->condition('pdate', $date1, '>=');
    $query->condition('pdate', $date2, '<=');
    $query->condition('cash', 'p');

    if ($coid != 'all') {
        $query->condition($viewE, $coid);
    }

    $Obj = $query->execute();
    $not_paid = $Obj->fetchObject()->sumValue;


    if ($not_paid == "") {
        $balances['enyp'][$m] = 0;
    } else {
        $balances['enyp'][$m] = round($not_paid / $divide, $rounding);
        $sum += $not_received;
    }
    $balances['enyp']['sumRow'] = round($sum / $divide, $rounding);
}