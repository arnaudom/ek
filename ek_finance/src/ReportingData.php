<?php

namespace Drupal\ek_finance;

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use DateTime;

class ReportingData {
    private $coid;
    private $year;
    private $baseCurrency;
    private $rounding;
    private $divide;
    private $viewE;
    private $viewS;
    private $chart;
    private $accounts_names;
    private $accounts_exp_class;
    private $accounts_income_class;
    private $purchases;
    private $expenses;
    private $income;
    private $internalReceived;
    private $internalPaid;
    private $compilationBalances;


    public function __construct($coid, $year, $baseCurrency, $rounding, $divide, $viewE, $viewS, $chart)     {
        $this->coid = $coid;
        $this->year = $year;
        $this->baseCurrency = $baseCurrency;
        $this->rounding = $rounding;
        $this->divide = $divide;
        $this->viewE = $viewE;
        $this->viewS = $viewS;
        $this->chart = $chart;
        $this->accounts_names = $this->getAccName(); 
        $this->accounts_exp_class = $this->getAccountsExpClass(); 
        $this->accounts_income_class = $this->getAccountsIncomeClass();
        $this->purchases = null;
        $this->expenses = null;
        $this->income = null;
        $this->internalReceived = null;
        $this->internalPaid = null;
        $this->compilationBalances = [];
    }

    private function getAccName() {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('coid', $this->coid);
        $query->orderBy('aid');
        $accounts = $query->execute()->fetchAllKeyed();
        return $accounts;

    }

    private function getAccountsExpClass() {
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $or = $query->orConditionGroup();
        $or->condition('aid', $this->chart['cos'] . '%', 'like');
        $or->condition('aid', $this->chart['expenses'] . '%', 'like');
        $or->condition('aid', $this->chart['other_expenses'] . '%', 'like');
        $query->condition($or);
        $query->condition('atype', 'class');
        $query->condition('astatus', '1');
        if ($this->coid != 'all') {
            $query->condition('coid', $this->coid);
        }
        $query->orderBy('aid');
        return $query->execute()->fetchAllKeyed();
    }

    private function getAccountsIncomeClass() {
        // Get the class array structure
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $or = $query->orConditionGroup();
        $or->condition('aid', $this->chart['income'] . '%', 'like');
        $or->condition('aid', $this->chart['other_income'] . '%', 'like');
        $query->condition($or);
        $query->condition('atype', 'class');
        $query->condition('astatus', '1');
        if ($this->coid != 'all') {
            $query->condition('coid', $this->coid);
        }
        $query->orderBy('aid');
        return $query->execute()->fetchAllKeyed();

    }

    public function getData() {
        if ($this->purchases === null) {
            $this->purchases = $this->getPurchases();
        }
        if ($this->expenses === null) {
            $this->expenses = $this->getExpenses();
        }
        if ($this->income === null) {
            $this->income = $this->getIncome();
        }
        if ($this->internalReceived === null) {
            $this->internalReceived = $this->getInternalReceived();
        }
        if ($this->internalPaid === null) {
            $this->internalPaid = $this->getInternalPaid();
        }
        
        $data = [
            'purchases' => $this->purchases,
            'expenses' => $this->expenses,
            'income' => $this->income,
            'internal_received' => $this->internalReceived,
            'internal_paid' => $this->internalPaid,
            'balances' => $this->getBalances(),
        ];

        return $data;
    }

    public function getCompilation() {
        $company = AccessCheck::GetCompanyByUser();
        $date1 = $this->year . "-01-01";
        $date2 = $this->year . "-12-31";
        $purchasesId = [];
        $expensesId = [];
        $incomeId = [];
                            
                            
        foreach($company as $key => $coid) {
            if($coid > 0) {
            // Get the class array structure for income
                    $query = Database::getConnection('external_db', 'external_db')
                                        ->select('ek_accounts', 'a');
                        $query->fields('a', ['aid']);
                        $or = $query->orConditionGroup();
                        $or->condition('aid', $this->chart['income'] . '%', 'like');
                        $or->condition('aid', $this->chart['other_income'] . '%', 'like');
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
                        $or = $query->orConditionGroup();
                        $or->condition('aid', $this->chart['cos'] . '%', 'like');
                        $or->condition('aid', $this->chart['expenses'] . '%', 'like');
                        $or->condition('aid', $this->chart['other_expenses'] . '%', 'like');

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
                    $this->purchases[$coid]['actual'] = $Obj->fetchObject()->sumValue; 
        
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
                    $this->purchases[$coid]['allocation'] = $Obj->fetchObject()->sumValue;        

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
                    $this->expenses[$coid]['actual'] = $Obj->fetchObject()->sumValue; 

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
                    $this->expenses[$coid]['allocation'] = $Obj->fetchObject()->sumValue;   

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
                    $this->income[$coid]['actual'] = $Obj->fetchObject()->sumValue;

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
                    $this->income[$coid]['allocation'] = $Obj->fetchObject()->sumValue;        

                    $this->compilationBalances[$coid]['actual'] = $this->income[$coid]['actual'] - $this->purchases[$coid]['actual'] - $this->expenses[$coid]['actual'];
                    $this->compilationBalances[$coid]['allocation'] = $this->income[$coid]['allocation'] - $this->purchases[$coid]['allocation'] - $this->expenses[$coid]['allocation'];

                    if(!isset($this->compilationBalances['all']['actual'])) {
                        $this->compilationBalances['all']['actual'] = $this->compilationBalances[$coid]['actual'];
                    } else {
                        $this->compilationBalances['all']['actual'] += $this->compilationBalances[$coid]['actual'];
                    }

                    if(!isset($this->compilationBalances['all']['allocation'])) {
                        $this->compilationBalances['all']['allocation'] = $this->compilationBalances[$coid]['allocation'];
                    } else {
                        $this->compilationBalances['all']['allocation'] +=  $this->compilationBalances[$coid]['allocation'];
                    }
                    
                    
                    
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
        
                    // Get the ids of  purchases per 'allocation'  
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

                    // Get the ids of  expenses per 'actual' 
                    $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_journal', 'j');
                            $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                            $query->fields('j', ['id']);
                            $query->condition('aid', $accounts_exp_class, 'IN');
                            $query->condition('date', $date1, '>=');
                            $query->condition('date', $date2, '<=');
                            $query->condition('source', 'expense', '=');
                            $query->condition('j.type', 'debit');
                            $query->condition('j.exchange', '0');
                            $query->condition('company', $coid);

                    $Obj = $query->execute();
                    $expensesId[$coid]['actual'] = $Obj->fetchCol(); 


                    // Get the ids of  expenses per 'allocation' 
                    $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_journal', 'j');
                            $query->leftJoin('ek_expenses', 'e', 'e.id = j.reference');    
                            $query->fields('j', ['id']);
                            $query->condition('aid', $accounts_exp_class, 'IN');
                            $query->condition('date', $date1, '>=');
                            $query->condition('date', $date2, '<=');
                            $query->condition('source', 'expense', '=');
                            $query->condition('j.type', 'debit');
                            $query->condition('j.exchange', '0');
                            $query->condition('allocation', $coid);

                    $Obj = $query->execute();
                    $expensesId[$coid]['allocation'] = $Obj->fetchCol();
                    
                    // Get the ids of income per 'actual' 
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

                    // Get the ids of income per 'allocation' 
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
                
        $data = [
            'income' => $this->income,
            'purchases' => $this->purchases,
            'expenses' => $this->expenses,
            'balances' => $this->compilationBalances,
            'error' => $error,
            'company' => $company,
            
        ];

        return $data;
    }

    private function getPurchases() {
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
        $query->condition($this->viewS, $this->coid); // get actual or allocated
        $query->condition('j.date', $this->year . "%", 'LIKE');
        $query->orderBy('aid');
        $Obj = $query->execute();
        $journal_data = $Obj->fetchAll();

        $classes = [];
        $class_total_p = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        foreach ($this->accounts_exp_class as $key => $name) {
            $class = substr($key, 0, 2);
            $sum_row_amount = 0;
            $rows = [];
            $column_total['sumRow'] = 0;

            for ($m = 1; $m <= 12; $m++) {
                $column_total[$m] = 0;
                if ($m < 10) {
                    $date1 = $this->year . "-0" . $m . "-01";
                    $d = new DateTime($date1);
                    $date2 = $d->format('Y-m-t');
                } else {
                    $date1 = $this->year . "-" . $m . "-01";
                    $d = new DateTime($date1);
                    $date2 = $d->format('Y-m-t');
                }
                foreach ($journal_data as $key => $values) {
                    // compare extracted journal_data withe the class array to match purchase account.
                    $a = substr($values->aid, 0, 2);
                    if ($a == $class) { 
                        if ($values->date >= $date1 && $values->date <= $date2 && $values->source == 'purchase') {
                            
                            $param = serialize(
                                    [   'id' => 'reporting',
                                        'from' => $this->year . "-01-01",
                                        'to' => $this->year . "-12-31",
                                        'coid' => $this->coid,
                                        'aid' => $values->aid
                            ]);
                            $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                            $link = "<a class='use-ajax' href='" . $history . "' >" . $values->aid . "</a>";
                            
                            if (!isset($rows[$values->aid][$m] )) {
                                $rows[$values->aid][$m] = 0;
                            }
                            if (!isset($rows[$values->aid]['sumRow'])) {
                                $rows[$values->aid]['sumRow'] = 0;
                            }
                            $v = round($values->value / $this->divide, $this->rounding);
                            $rows[$values->aid]['desc'] = isset($this->accounts_names[$values->aid]) ? $this->accounts_names[$values->aid] : '--';
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

        //array_push($purchases, ['classes' => $classes, 'total' => $class_total_p]);
        return ['classes' => $classes, 'total' => $class_total_p];

    }

    private function getExpenses() {
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
        $query->condition($this->viewE, $this->coid); // get actual or allocated
        $query->condition('date', $this->year . "%", 'LIKE');
        $query->orderBy('aid');
        $Obj = $query->execute();
        $journal_data = $Obj->fetchAll();

        $classes = [];
        $class_total_e = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        foreach ($this->accounts_exp_class as $key => $name) {

                $class = substr($key, 0, 2);
                $sum_row_amount = 0;
                $rows = [];
                $column_total['sumRow'] = 0;
                for ($m = 1; $m <= 12; $m++) {

                $column_total[$m] = 0;

                if ($m < 10) {
                    $date1 = $this->year . "-0" . $m . "-01";
                    $d = new DateTime($date1);
                    $date2 = $d->format('Y-m-t');
                } else {
                    $date1 = $this->year . "-" . $m . "-01";
                    $d = new DateTime($date1);
                    $date2 = $d->format('Y-m-t');
                }
                foreach ($journal_data as $key => $values) {
                    $a = substr($values->aid, 0, 2);
                    if ($a == $class) {
                        if ($values->date >= $date1 && $values->date <= $date2 && $values->source != 'purchase') {
                            $param = serialize(
                                    ['id' => 'reporting',
                                        'from' => $this->year . "-01-01",
                                        'to' => $this->year . "-12-31",
                                        'coid' => $this->coid,
                                        'aid' => $values->aid
                            ]);
                            $history = Url::fromRoute('ek_finance_modal', array('param' => $param), [])->toString();
                            $link = "<a class='use-ajax' href='" . $history . "' >" . $values->aid . "</a>";

                            if (!isset($rows[$values->aid][$m] )) {
                                $rows[$values->aid][$m] = 0;
                            }
                            if (!isset($rows[$values->aid]['sumRow'])) {
                                $rows[$values->aid]['sumRow'] = 0;
                            }
                            $v = round($values->value / $this->divide, $this->rounding);
                            
                            $rows[$values->aid]['desc'] = isset($this->accounts_names[$values->aid]) ? $this->accounts_names[$values->aid] : '--';
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

        // array_push($expenses, ['classes' => $classes, 'total' => $class_total_e]);
        return ['classes' => $classes, 'total' => $class_total_e];
    }

    private function getIncome() {
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
        $query->condition($this->viewS, $this->coid);
        $query->condition('j.date', $this->year . "%", 'LIKE');
        $query->orderBy('aid');
        $Obj = $query->execute();
        $journal_data = $Obj->fetchAll();

        $classes = [];
        $class_total_i = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        foreach ($this->accounts_income_class as $key => $name) {

            $class = substr($key, 0, 2);
            $sum_row_amount = 0;
            $rows = [];
            $column_total['sumRow'] = 0;
            for ($m = 1; $m <= 12; $m++) {
            $column_total[$m] = 0;       
            if ($m < 10) {
                $date1 = $this->year . "-0" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            } else {
                $date1 = $this->year . "-" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            }
                foreach ($journal_data as $key => $values) {
                    $a = substr($values->aid, 0, 2);
                    if ($a == $class) {
                        if ($values->date >= $date1 && $values->date <= $date2 && $values->source == 'invoice') {
                            $param = serialize(
                                    ['id' => 'reporting',
                                        'from' => $this->year . "-01-01",
                                        'to' => $this->year . "-12-31",
                                        'coid' => $this->coid,
                                        'aid' => $values->aid
                            ]);
                            $history = Url::fromRoute('ek_finance_modal', ['param' => $param], [])->toString();
                            $link = "<a class='use-ajax' href='" . $history . "' >" . $values->aid . "</a>";
                            if (!isset($rows[$values->aid][$m] )) {
                                $rows[$values->aid][$m] = 0;
                            }
                            if (!isset($rows[$values->aid]['sumRow'])) {
                                $rows[$values->aid]['sumRow'] = 0;
                            }
                            $v = round($values->value / $this->divide, $this->rounding);
                            $rows[$values->aid]['desc'] = isset($this->accounts_names[$values->aid]) ? $this->accounts_names[$values->aid] : '--';
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

        //array_push($income, ['classes' => $classes, 'total' => $class_total_i]);
        return ['classes' => $classes, 'total' => $class_total_i];

    }


    private function getInternalReceived() {
        $classes = [];
        $grandtotal_column_internal_received = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $this->year . "-0" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            } else {
                $date1 = $this->year . "-" . $m . "-01";
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
            $query->condition('entity', $this->coid);

            $Obj = $query->execute();
            $sumValue = $Obj->fetchObject()->sumValue;
            if ($sumValue == "") {
                $grandtotal_column_internal_received[$m] = 0;
            } else {
                $grandtotal_column_internal_received[$m] = round($sumValue / $this->divide, $this->rounding);
                $sum += $sumValue;
            }

            $grandtotal_column_internal_received['sumRow'] = round($sum / $this->divide, $this->rounding);
        }

        //array_push($internal_received, $grandtotal_column_internal_received);
        return $grandtotal_column_internal_received;
       
    }

    private function getInternalPaid() {
        $grandtotal_column_internal_paid = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $this->year . "-0" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            } else {
                $date1 = $this->year . "-" . $m . "-01";
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
            $query->condition('entity_to', $this->coid);
            $Obj = $query->execute();
            $sumValue = $Obj->fetchObject()->sumValue;

            if ($sumValue == "") {
                $grandtotal_column_internal_paid[$m] = 0;
            } else {
                $grandtotal_column_internal_paid[$m] = round($sumValue / $this->divide, $this->rounding);
                $sum += $sumValue;
            }

            $grandtotal_column_internal_paid['sumRow'] = round($sum / $this->divide, $this->rounding);
        }

        //array_push($internal_paid, $grandtotal_column_internal_paid);
        return $grandtotal_column_internal_paid;
    }

    private function getBalances() {
        $balances = [];
    
        // PL (Profit/Loss)
        $balances['pl'] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        
        for ($m = 1; $m <= 12; $m++) {
            $balances['pl'][$m] = round(($this->income['total'][$m] - $this->purchases['total'][$m] - $this->expenses['total'][$m]) / $this->divide, $this->rounding);
        }
        $balances['pl']['sumRow'] = round(($this->income['total']['sumRow'] - $this->purchases['total']['sumRow'] - $this->expenses['total']['sumRow']) / $this->divide, $this->rounding);

        // INYR (Invoices Not Yet Received)
        $balances['inyr'] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $this->year . "-0" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            } else {
                $date1 = $this->year . "-" . $m . "-01";
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
        
            if ($this->coid != 'all') {
                $query->condition($this->viewS, $this->coid);
            }
        
            $Obj = $query->execute();
            $not_received = $Obj->fetchObject()->sumValue;
        
            if ($not_received == "") {
                $balances['inyr'][$m] = 0;
            } else {
                $balances['inyr'][$m] = round($not_received / $this->divide, $this->rounding);
                $sum += $not_received;
            }
        }

        // Short payments
        $balances['short'] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $this->year . "-0" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            } else {
                $date1 = $this->year . "-" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            }
        
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 'i');
            $query->addExpression('SUM(balancebase)', 'sumValue');
            $query->condition('date', $date1, '>=');
            $query->condition('date', $date2, '<=');
            $query->condition('status', '1');
            if ($this->coid != 'all') {
                $query->condition($this->viewS, $this->coid);
            }
        
            $Obj = $query->execute();
            $not_paid = $Obj->fetchObject()->sumValue;
        
            if ($not_paid == "") {
                $balances['short'][$m] = 0;
            } else {
                $balances['short'][$m] = round($not_paid / $this->divide, $this->rounding);
                $sum += $not_received;
            }
            $balances['short']['sumRow'] = round($sum / $this->divide, $this->rounding);
        }

        // ENYP (Expenses Not Yet Paid)
        $balances['enyp'] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 'sumRow' => 0];
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            if ($m < 10) {
                $date1 = $this->year . "-0" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            } else {
                $date1 = $this->year . "-" . $m . "-01";
                $d = new DateTime($date1);
                $date2 = $d->format('Y-m-t');
            }
        
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e');
            $query->addExpression('SUM(amount)', 'sumValue');
            $query->condition('pdate', $date1, '>=');
            $query->condition('pdate', $date2, '<=');
            $query->condition('cash', 'p');
            if ($this->coid != 'all') {
                $query->condition($this->viewE, $this->coid);
            }
        
            $Obj = $query->execute();
            $not_paid = $Obj->fetchObject()->sumValue;
        
        
            if ($not_paid == "") {
                $balances['enyp'][$m] = 0;
            } else {
                $balances['enyp'][$m] = round($not_paid / $this->divide, $this->rounding);
                $sum += $not_received;
            }
            $balances['enyp']['sumRow'] = round($sum / $this->divide, $this->rounding);
        }

        return $balances;
    }

    
}

