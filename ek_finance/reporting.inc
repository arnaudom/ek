<?php

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

// build accounts array structures for expenses
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
    $query->fields('a', ['aid', 'aname']);
    $or = db_or();
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


/*
 * Purchases
 * journal data linked to purchase table. The reporting consider purchase value from allocation of purchase 
 * NOT from the actual purchasing entity. This is to compare revenue & expenses fron analytical point of view
 * Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
 */
$grandtotal_column_purchases = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0]; //used to combine grand total per column
$classes = [];

foreach($accounts_exp_class as $key => $name) {
    
    $class = substr($key, 0, 2);
    $sum_row_amount = 0;
    $flag = 0;
    $subtotal_column_purchases = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
    $rows = [];
    
    // Get the account type detail
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('aid', $class . '%', 'like');
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');   
    if ($coid != 'all') {
        $query->condition('coid', $coid);
    }
    $query->orderBy('aid');
    $data2 = $query->execute();

        while ($d2 = $data2->fetchObject()) {
            $row = [];
            $aid = $d2->aid;

            for ($m = 1; $m <= 12; $m++) {
                if ($m < 10) {
                    $date1 = $year . "-0" . $m . "-01";
                    $date2 = $year . "-0" . $m . "-31";
                } else {
                    $date1 = $year . "-" . $m . "-01";
                    $date2 = $year . "-" . $m . "-31";
                }

                // Get the type sum per aid         
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $d2->aid);
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'purchase', '=');
                    $query->condition('j.type', 'debit');

                if ($coid != 'all') {           
                    $query->condition($viewS, $coid);
                }

                $Obj = $query->execute();
                $sumValue = $Obj->fetchObject()->sumValue; 

                if ($sumValue == "") {
                    $row[$m]  = 0;
                } else {
                    $row[$m] = round($sumValue / $divide, 2);
                }

                $sum_row_amount = $sum_row_amount + $sumValue;
                $subtotal_column_purchases[$m] += $sumValue;

            } 

            $row['sumRow'] =  round($sum_row_amount / $divide, 2);

            if ($sum_row_amount != 0) {
               $rows[$aid . ' - '. $d2->aname] = $row;
               $flag = TRUE;
            }
            $sum_row_amount = 0;

        } //rows
   
        // Get the class sum - subtotal
        if ($flag) {      
            $subTotal = [];
            for ($m = 1; $m <= 12; $m++) {
                if ($subtotal_column_purchases[$m] == "") {
                    $subTotal[$m] = 0;
                } else {
                    $subTotal[$m] =  round($subtotal_column_purchases[$m] / $divide,2);
                    $sum_row_amount = $sum_row_amount + $subtotal_column_purchases[$m];
                    $grandtotal_column_purchases[$m] += $subtotal_column_purchases[$m];
                }
       
            } 

            $subTotal['sumRow'] = round($sum_row_amount / $divide, 2);
            $sum_row_amount = 0;
            $classes[] = ['id' => $class, 'name' => $name, 'rows' => $rows, 'subTotal' => $subTotal];
        }

} //classes

/*
 * Purchases grand total
*/ 
$grand_total_purchase = [];
$grandtotal_column_purchases['sumRow'] = round(array_sum($grandtotal_column_purchases) / $divide, 2);
for ($m = 1; $m <= 12; $m++) {
    $grandtotal_column_purchases[$m] = round($grandtotal_column_purchases[$m] / $divide, 2);    
} 

array_push($purchases , ['classes' => $classes, 'total' =>  $grandtotal_column_purchases]);

/*
 * Expenses
 * journal data linked to expense table. The reporting consider purchase value from allocation of expense 
 * NOT from the actual spending entity. This is to compare revenue & expenses from analytical point of view
 * Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
*/
$grandtotal_column_expenses = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0]; //used to combine grand total per column
$classes = []; 

foreach($accounts_exp_class as $key => $name) {
    
    $class = substr($key, 0, 2);
    $sum_row_amount = 0;
    $flag = 0;
    $subtotal_column_expenses = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
    $rows = [];

    // Get the account type detail
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('aid', $class . '%', 'like');
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');
        
    if ($coid != 'all') {
        $query->condition('coid', $coid);
    }
    
    $query->orderBy('aid');
    $data2 = $query->execute();
    while ($d2 = $data2->fetchObject()) {
        $row = [];
        $sum_row_amount = 0;
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
        
        
        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }
            // Get the type sum detail
            //by allocation
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $d2->aid);
                $query->condition('date', $date1, '>=');
                $query->condition('date', $date2, '<=');
                $query->condition('source', 'purchase', '!=');
                $query->condition('j.type', 'debit');
                
            if ($coid != 'all') {           
                $query->condition($viewE, $coid);
            }

            $Obj = $query->execute();
            $sumValue = $Obj->fetchObject()->sumValue; 
            
            if ($sumValue == "") {
                $row[$m]  = 0;
            } else {
                $row[$m] = round($sumValue / $divide, 2);
            }

            $sum_row_amount = $sum_row_amount + $sumValue;
            $subtotal_column_expenses[$m] += $sumValue;
            
        } 

        $row['sumRow'] =  round($sum_row_amount / $divide, 2);

        if ($sum_row_amount != 0) {
            $rows[$aid . ' - '. $d2->aname] = $row;
            $flag = TRUE;
        }
        $sum_row_amount = 0;
    } 

    // Get the class sum - subtotal
    if ($flag) {
        $subTotal = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($subtotal_column_expenses[$m] == "") {
                 $subTotal[$m] = 0;
            } else {
                $subTotal[$m] =  round($subtotal_column_expenses[$m] / $divide,2);
                $sum_row_amount = $sum_row_amount + $subtotal_column_expenses[$m];
               $grandtotal_column_expenses[$m] += $subtotal_column_expenses[$m];
            }
        }
            $subTotal['sumRow'] = round($sum_row_amount / $divide, 2);
            $sum_row_amount = 0;
            $classes[] = ['id' => $class, 'name' => $name, 'rows' => $rows, 'subTotal' => $subTotal];
       
    }

} //classes

/*
 * Expenses GRAND Total
*/ 

$grand_total_expenses = [];

$grandtotal_column_expenses['sumRow'] = round(array_sum($grandtotal_column_expenses) / $divide, 2);
for ($m = 1; $m <= 12; $m++) {
    $grandtotal_column_expenses[$m] = round($grandtotal_column_expenses[$m] / $divide, 2);    
} 

array_push($expenses , ['classes' => $classes, 'total' =>  $grandtotal_column_expenses]);


/*
 * Income
 * journal data linked to sales table. The reporting consider sales value from allocation of sales 
 * NOT from the actual invoicing entity. This is to compare revenue & expenses from analytical point of view
 * Note: if aid of main busineess entity does not exist in allocated business entity, the data is not displayed
 */
$grandtotal_column_income = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0]; //used to combine grand total per column
$classes = [];

// Get the class array structure
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
    $query->fields('a', ['aid', 'aname']);
    $or = db_or();
    $or->condition('aid', $chart['income'] . '%', 'like');
    $or->condition('aid', $chart['other_income'] . '%', 'like');
    $query->condition($or);
    $query->condition('atype', 'class');
    $query->condition('astatus', '1');

if ($coid != 'all') {
    $query->condition('coid', $coid);
}  

$query->orderBy('aid');
$accounts_income_class = $query->execute()->fetchAllKeyed();

foreach($accounts_income_class as $key => $name) {
    $class = substr($key, 0, 2);
    $sum_row_amount = 0;
    $flag = 0;
    $subtotal_column_income = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
    $rows = [];
    
    // Get the account type detail
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('aid', $class . '%', 'like');
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');
        
    if ($coid != 'all') {
        $query->condition('coid', $coid);
    }
 
    $query->orderBy('aid');
    $data2 = $query->execute();
    $sum_row_amount = 0;
    
    while ($d2 = $data2->fetchObject()) {
        $row = [];
        if ($coid != 'all') {
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

        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $year . "-0" . $m . "-01";
                $date2 = $year . "-0" . $m . "-31";
            } else {
                $date1 = $year . "-" . $m . "-01";
                $date2 = $year . "-" . $m . "-31";
            }

            //get the sum detail
            //by allocation
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $d2->aid);
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'invoice', '=');
                $query->condition('j.type', 'credit');
                $query->condition($viewS, $coid);
                

            $Obj = $query->execute();
            $sumValue = $Obj->fetchObject()->sumValue; 
            
            if ($sumValue == "") {
                $row[$m]  = 0;
            } else {
                $row[$m] = round($sumValue / $divide, 2);
            }

            $sum_row_amount = $sum_row_amount + $sumValue;
            $subtotal_column_income[$m] += $sumValue;
            
        } //for loop months

        $row['sumRow'] =  round($sum_row_amount / $divide, 2);
        if ($sum_row_amount != 0) {
               $rows[$aid . ' - '. $d2->aname] = $row;
               $flag = TRUE;
        }
        $sum_row_amount = 0;
    } 
    
    // Get the class sum - subtotal
    if ($flag) {  
        $subTotal = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($subtotal_column_income[$m] == "") {
                    $subTotal[$m] = 0;
                } else {
                    $subTotal[$m] =  round($subtotal_column_income[$m] / $divide,2);
                    $sum_row_amount = $sum_row_amount + $subtotal_column_income[$m];
                    $grandtotal_column_income[$m] += $subtotal_column_income[$m];
                }
        } 
    
    $subTotal['sumRow'] = round($sum_row_amount / $divide, 2);
    $sum_row_amount = 0;
    $classes[] = ['id' => $class, 'name' => $name, 'rows' => $rows, 'subTotal' => $subTotal];        
        
    }

} 

/*
 * Income grand total
*/ 

$grand_total_revenue = [];
$grandtotal_column_income['sumRow'] = round(array_sum($grandtotal_column_income) / $divide, 2);
for ($m = 1; $m <= 12; $m++) {
    $grandtotal_column_income[$m] = round($grandtotal_column_income[$m] / $divide, 2);    
} 

array_push($income , ['classes' => $classes, 'total' =>  $grandtotal_column_income]);

/*
 * Internal Revenue
 */
$classes = []; 
$grandtotal_column_internal_received = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
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
        $grandtotal_column_internal_received[$m] = round($sumValue / $divide, 2);
        $sum += $sumValue;
    }
    
    $grandtotal_column_internal_received['sumRow'] = round($sum / $divide, 2);
}

array_push($internal_received, $grandtotal_column_internal_received);
$sum_row_amount = 0;

$classes = []; 
$grandtotal_column_internal_paid = [1 => 0 ,2 => 0 ,3 => 0 ,4 => 0 ,5 => 0 ,6 => 0 ,7 => 0 ,8 => 0 ,9 => 0 ,10 => 0 ,11 => 0 ,12 => 0 ,'sumRow' => 0];
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
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
        $grandtotal_column_internal_paid[$m] = round($sumValue / $divide, 2);
        $sum += $sumValue;
    }
    
    $grandtotal_column_internal_paid['sumRow'] = round($sum / $divide, 2);
}

array_push($internal_paid, $grandtotal_column_internal_paid);
$sum_row_amount = 0;



/*
 * Balances
 * PL
 */
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    $sum += $grandtotal_column_income[$m] - $grandtotal_column_purchases[$m] - $grandtotal_column_expenses[$m];
    $balances['pl'][$m] = round($grandtotal_column_income[$m] - $grandtotal_column_purchases[$m] - $grandtotal_column_expenses[$m],2);
}
    $balances['pl']['sumRow'] =  round($sum / $divide, 2);

/*
 * Balances
 * INYR
 */
$sum =0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
    }

    // Get the sum of payments not received 
    //TODO get data from journal
    
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
        $balances['inyr'][$m] = round($not_received / $divide, 2);
        $sum += $not_received;
    }

} //for loop months   

$balances['inyr']['sumRow'] =  round($sum / $divide, 2);



/*
 * Balances
 * short payments
 */
$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
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
        $balances['short'][$m] = round($not_paid / $divide, 2);
        $sum += $not_received;
    }
    $balances['short']['sumRow'] =  round($sum / $divide, 2);
} //for loop months   


/*
 * Balances
 * ENYP
 */ 

$sum = 0;
for ($m = 1; $m <= 12; $m++) {
    if ($m < 10) {
        $date1 = $year . "-0" . $m . "-01";
        $date2 = $year . "-0" . $m . "-31";
    } else {
        $date1 = $year . "-" . $m . "-01";
        $date2 = $year . "-" . $m . "-31";
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
        $balances['enyp'][$m] = round($not_paid / $divide, 2);
        $sum += $not_received;
    }
    $balances['enyp']['sumRow'] =  round($sum / $divide, 2);

}







