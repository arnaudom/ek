<?php

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;

$company = AccessCheck::GetCompanyByUser();
$purchases = [];
$expenses = [];
$income = [];
$balances = [];
$date1 = $year . "-01-01";
$date2 = $year . "-12-31";
$purchasesId = [];
$expensesId = [];
$incomeId = [];
                      
                    
foreach($company as $key => $coid) {
    if($coid > 0) {
// Get the class array structure for income
            $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_accounts', 'a');
                $query->fields('a', ['aid']);
                $or = db_or();
                $or->condition('aid', $chart['income'] . '%', 'like');
                $or->condition('aid', $chart['other_income'] . '%', 'like');
                $query->condition($or);
                $query->condition('atype', 'detail');
                $query->condition('astatus', '1');
                $query->condition('coid', $coid);
                $query->distinct();

            $query->orderBy('aid');
            $accounts_income_class = $query->execute()->fetchCol();
            
             // build accounts array structures for expenses
            $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_accounts', 'a');
                $query->fields('a', ['aid']);
                $or = db_or();
                $or->condition('aid', $chart['cos'] . '%', 'like');
                $or->condition('aid', $chart['expenses'] . '%', 'like');
                $or->condition('aid', $chart['other_expenses'] . '%', 'like');

                $query->condition($or);
                $query->condition('atype', 'detail');
                $query->condition('astatus', '1');
                $query->condition('coid', $coid);
                $query->distinct();

                $accounts_exp_class = $query->execute()->fetchCol(); 
                
            // Get the sum of purchases per 'actual'  
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $accounts_exp_class, 'IN');
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                $query->condition('head', $coid);

            $Obj = $query->execute();
            $purchases[$coid]['actual'] = $Obj->fetchObject()->sumValue; 
  
            // Get the sum of purchases per 'allocation'  
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->addExpression('SUM(value)', 'sumValue');
                $query->condition('aid', $accounts_exp_class, 'IN');
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $purchases[$coid]['allocation'] = $Obj->fetchObject()->sumValue;        

            // Get the sum of expenses per 'actual' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_exp_class, 'IN');
                    $query->condition('date', $date1, '>=');
                    $query->condition('date', $date2, '<=');
                    $query->condition('source', 'purchase', '!=');
                    $query->condition('j.type', 'debit');
                    $query->condition('company', $coid);

            $Obj = $query->execute();
            $expenses[$coid]['actual'] = $Obj->fetchObject()->sumValue; 


            // Get the sum of expenses per 'allocation' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_exp_class, 'IN');
                    $query->condition('date', $date1, '>=');
                    $query->condition('date', $date2, '<=');
                    $query->condition('source', 'purchase', '!=');
                    $query->condition('j.type', 'debit');
                    $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $expenses[$coid]['allocation'] = $Obj->fetchObject()->sumValue;   

            // Get the sum of income per 'actual' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_income_class, 'IN');
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'invoice', '=');
                    $query->condition('j.type', 'credit');
                    $query->condition('head', $coid);

            $Obj = $query->execute();
            $income[$coid]['actual'] = $Obj->fetchObject()->sumValue;

            // Get the sum of income per 'allocation' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                    $query->addExpression('SUM(value)', 'sumValue');
                    $query->condition('aid', $accounts_income_class, 'IN');
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'invoice', '=');
                    $query->condition('j.type', 'credit');
                    $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $income[$coid]['allocation'] = $Obj->fetchObject()->sumValue;        

            $balances[$coid]['actual'] = $income[$coid]['actual'] - $purchases[$coid]['actual'] - $expenses[$coid]['actual'];
            $balances[$coid]['allocation'] = $income[$coid]['allocation'] - $purchases[$coid]['allocation'] - $expenses[$coid]['allocation'];

            $balances['all']['actual'] = $balances['all']['actual'] + $balances[$coid]['actual'];
            $balances['all']['allocation'] = $balances['all']['allocation'] + $balances[$coid]['allocation'];
            
           
            
/////////////test data

            // Get the ids of purchases per 'actual'  
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->fields('j', ['id']);
                $query->condition('aid', $accounts_exp_class, 'IN');
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                $query->condition('j.exchange', '0');
                $query->condition('head', $coid);

            $Obj = $query->execute();
            $purchasesId[$coid]['actual'] = $Obj->fetchCol(); 
  
            // Get the sum of purchases per 'allocation'  
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
                $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference');    
                $query->fields('j', ['id']);
                $query->condition('aid', $accounts_exp_class, 'IN');
                $query->condition('j.date', $date1, '>=');
                $query->condition('j.date', $date2, '<=');
                $query->condition('source', 'purchase', '=');
                $query->condition('j.type', 'debit');
                $query->condition('j.exchange', '0');
                $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $purchasesId[$coid]['allocation'] = $Obj->fetchCol();        

            // Get the sum of expenses per 'actual' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                    $query->fields('j', ['id']);
                    $query->condition('aid', $accounts_exp_class, 'IN');
                    $query->condition('date', $date1, '>=');
                    $query->condition('date', $date2, '<=');
                    $query->condition('source', 'expense', '!=');
                    $query->condition('j.type', 'debit');
                    $query->condition('j.exchange', '0');
                    $query->condition('company', $coid);

            $Obj = $query->execute();
            $expensesId[$coid]['actual'] = $Obj->fetchCol(); 


            // Get the sum of expenses per 'allocation' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                    $query->fields('j', ['id']);
                    $query->condition('aid', $accounts_exp_class, 'IN');
                    $query->condition('date', $date1, '>=');
                    $query->condition('date', $date2, '<=');
                    $query->condition('source', 'expense', '!=');
                    $query->condition('j.type', 'debit');
                    $query->condition('j.exchange', '0');
                    $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $expensesId[$coid]['allocation'] = $Obj->fetchCol();
            
            // Get the sum of income per 'actual' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                    $query->fields('j', ['id']);
                    $query->condition('aid', $accounts_income_class, 'IN');
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'invoice', '=');
                    $query->condition('j.type', 'credit');
                    $query->condition('j.exchange', '0');
                    $query->condition('head', $coid);

            $Obj = $query->execute();
            $incomeId[$coid]['actual'] = $Obj->fetchCol();

            // Get the sum of income per 'allocation' 
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                    $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference');    
                    $query->fields('j', ['id']);
                    $query->condition('aid', $accounts_income_class, 'IN');
                    $query->condition('j.date', $date1, '>=');
                    $query->condition('j.date', $date2, '<=');
                    $query->condition('source', 'invoice', '=');
                    $query->condition('j.type', 'credit');
                    $query->condition('j.exchange', '0');
                    $query->condition('allocation', $coid);

            $Obj = $query->execute();
            $incomeId[$coid]['allocation'] = $Obj->fetchCol();
            
            

    
    }
}

$AllIncomeIdActual = [];
$AllIncomeIdAllocation = [];
$AllExpenseIdActual = [];
$AllExpenseIdAllocation = [];
$AllPurchaseIdActual = [];
$AllPurchaseIdAllocation = [];
$error = [];
foreach($company as $key => $coid) {
    if($coid > 0){
        $AllIncomeIdActual = array_unique(array_merge($AllIncomeIdActual,  $incomeId[$coid]['actual']));
        $AllIncomeIdAllocation = array_unique(array_merge($AllIncomeIdAllocation,  $incomeId[$coid]['allocation']));
        
        $AllExpenseIdActual = array_unique(array_merge($AllExpenseIdActual,  $expensesId[$coid]['actual']));
        $AllExpenseIdAllocation = array_unique(array_merge($AllExpenseIdAllocation,  $expensesId[$coid]['allocation']));
        
        $AllPurchaseIdActual = array_unique(array_merge($AllPurchaseIdActual,  $purchasesId[$coid]['actual']));
        $AllPurchaseIdAllocation = array_unique(array_merge($AllPurchaseIdAllocation,  $purchasesId[$coid]['allocation']));
    }
    
}


$error['income'] = array_diff($AllIncomeIdActual, $AllIncomeIdAllocation);
if(!empty($error['income'])) {
    $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
        $query->fields('j', ['aid']);
        $query->condition('id', $error['income'], 'IN');
        $query->distinct();
        $Obj = $query->execute();
        $error['income_aid'] = implode(',', $Obj->fetchCol());
        
}
$error['expenses'] = array_diff($AllExpenseIdActual, $AllExpenseIdAllocation);
if(!empty($error['expenses'])) {
    $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
        $query->fields('j', ['aid']);
        $query->condition('id', $error['expenses'], 'IN');
        $query->distinct();
        $Obj = $query->execute();
        $error['expenses_aid'] = implode(',', $Obj->fetchCol());
        
}
$error['purchases'] = array_diff($AllPurchaseIdActual, $AllPurchaseIdAllocation);
if(!empty($error['purchases'])) {
    $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
        $query->fields('j', ['aid']);
        $query->condition('id', $error['purchases'], 'IN');
        $query->distinct();
        $Obj = $query->execute();
        $error['purchases_aid'] = implode(',', $Obj->fetchCol());
        
}    
    