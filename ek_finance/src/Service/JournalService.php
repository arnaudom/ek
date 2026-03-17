<?php

namespace Drupal\ek_finance\Service;

use DateTime;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\BankData;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\CurrencyData;

/**
 * Journal service for recording journal entries.
 *
 * @service ek_finance.journal
 */
class JournalService {

    protected $tax;
    protected $debit;
    protected $credit;
    protected $financeSettings;
    protected $rounding;

    public function __construct() {
        (double) $this->tax = 0;
        (double) $this->debit = 0;
        (double) $this->credit = 0;
        $this->financeSettings  = new FinanceSettings();
        $this->rounding  = (!null == $this->financeSettings->get('rounding'))
                            ? $this->financeSettings->get('rounding') : 2;
    }

    public function getCredit() {
        return $this->credit;
    }

    public function getDebit() {
        return $this->debit;
    }

    public function getTax() {
        return $this->tax;
    }

    /**
     * Balance sheet
     * @param int $coid
     * @param str $year
     * @param str $month
     * @param bol $summary
     * 
     * @return array $items
     * 
     */

     public function balancesheet($coid, $year, $month, $summary) {

        $company = AccessCheck::GetCompanyByUser();
        $company = implode(',', $company);
        $financesettings = new FinanceSettings();
        $chart = $financesettings->get('chart');
        $items = [];
        $items['baseCurrency'] = $financesettings->get('baseCurrency');
        $items['summary'] = $summary;
        
        /*
        * starting date is based on fiscal year settings 
        * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
        * and has started on 1/07/13
        */
        $items['dates'] = self::getFiscalDates($coid, $year, $month);
        $to = $items['dates']['to'];
        // remove 1 day from stop date as we generate closing amount from opening of next period
        $stop_date = date('Y-m-d', strtotime($items['dates']['stop_date'] . ' - 1 day'));

        $connection = Database::getConnection('external_db', 'external_db');

        $is_archive_requested = !empty($items['dates']['archive']) && $items['dates']['archive'] === TRUE;

        $table_accounts = 'ek_accounts';
        $table_journal  = 'ek_journal';
        $from           = $items['dates']['fiscal_start'];
        $items['title'] = t('Current fiscal year @y', ['@y' => $items['dates']['fiscal_year']]);

        if ($is_archive_requested) {

            $candidate_table = "ek_accounts_{$year}_{$coid}";

            // ── Actual table existence check ───────────────────────────────────────
            if ($connection->schema()->tableExists($candidate_table)) {
                $table_accounts = $candidate_table;
                $table_journal  = "ek_journal_{$year}_{$coid}";
                $from           = $items['dates']['from'];
                $items['title'] = t('Archive data');
            }
            else {
                // Archive was requested but table does not exist
                
                 $items['error'] = t('Archive tables for year @year do not exist.', ['@year' => $year]);
                 return $items;
            }
        }

        $param = [ 'id' => 'bs', 'from' => $from, 'to' => $to, 'coid' => $coid, 'aid' => '', 'archive' => $items['dates']['archive']];

        // ASSETS //
        // header account
        // Other assets
        $items['other_assets'] = [];
        $total_class0 = 0;
        $total_class0_base = 0;
            
        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['other_assets'] . '%', 'like');
        $query->condition('atype', 'header', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $data = $query->execute();
        $rh = $data->fetchObject();
        if($rh){
            $items['other_assets']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
        }
        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['other_assets'] . '%', 'like');
        $query->condition('atype', 'class', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        while ($r = $result->fetchObject()) {
        
            $aid = substr($r->aid, 0, 2);
            $items['other_assets']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
            $total_detail = 0;
            $total_detail_base = 0;

            $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
            $query->fields('t', ['aid','aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result2 = $query->execute();
            $detail = [];
            while ($r2 = $result2->fetchObject()) {

                $b = self::opening(
                        [
                            'aid' => $r2->aid,
                            'coid' => $coid,
                            'from' => $stop_date,
                            'archive' => $items['dates']['archive']
                        ]
                );
                // opening returns an array with key 0 = multicurrency and key 1 = base currency 
                $b0 = $b[0] * -1;
                $b1 = $b[1] * -1;
                if (($b0 != 0 || $b1 != 0) && $summary == 0) {
                    $param['aid'] = $r2->aid;
                    $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
                    $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b1, 'multi' => $b0, 'url' => $url];
                }
                $total_detail += $b0;
                $total_detail_base += $b1;
            }
            $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
            $items['other_assets']['header']['class'][$aid]['data'] = $detail;
            

            $total_class0 += $total_detail;
            $total_class0_base += $total_detail_base;
        }

        $items['other_assets']['header']['total'] = ['base' => $total_class0_base, 'multi' => $total_class0];

        // assets
        $items['assets'] = [];
        $total_class1 = 0;
        $total_class1_base = 0;

        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['assets'] . '%', 'like');
        $query->condition('atype', 'header', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $data = $query->execute();
        $rh = $data->fetchObject();
        if($rh){
            $items['assets']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
        }
        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['assets'] . '%', 'like');
        $query->condition('atype', 'class', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        while ($r = $result->fetchObject()) {

            $aid = substr($r->aid, 0, 2);
            $items['assets']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
            $total_detail = 0;
            $total_detail_base = 0;
            
            $query = Database::getConnection('external_db', 'external_db')
                                ->select($table_accounts, 't');
            $query->fields('t', ['aid','aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result2 = $query->execute();
            $detail = [];
            while ($r2 = $result2->fetchObject()) {

                $b = self::opening(
                        [
                            'aid' => $r2->aid,
                            'coid' => $coid,
                            'from' => $stop_date,
                            'archive' => $items['dates']['archive']
                        ]
                );

                // opening returns an array with key 0 = multicurrency and key 1 = base currency 
                $b0 = $b[0] * -1;
                $b1 = $b[1] * -1;
                if (($b0 != 0 || $b1 != 0) && $summary == 0) {$param['aid'] = $r2->aid;
                    $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
                    $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b1, 'multi' => $b0, 'url' => $url];
                }
                $total_detail += $b0;
                $total_detail_base += $b1;
                
            }
            $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
            $items['assets']['header']['class'][$aid]['data'] = $detail;
            

            $total_class1 += $total_detail;
            $total_class1_base += $total_detail_base;
        }

        $items['assets']['header']['total'] = ['base' => $total_class1_base, 'multi' => $total_class1];
        $items['total_assets'] = ['base' => $total_class0_base + $total_class1_base, 'multi' => $total_class0 + $total_class1];

        // LIABILITIES //
        // header account
        // liabilities
        $items['liabilities'] = [];
        $total_class2 = 0;
        $total_class2_base = 0;

        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['liabilities'] . '%', 'like');
        $query->condition('atype', 'header', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $data = $query->execute();
        $rh = $data->fetchObject();
        if($rh){
            $items['liabilities']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
        }

        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['liabilities'] . '%', 'like');
        $query->condition('atype', 'class', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        while ($r = $result->fetchObject()) {

            $aid = substr($r->aid, 0, 2);
            $items['liabilities']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
            $total_detail = 0;
            $total_detail_base = 0;
            
            $query = Database::getConnection('external_db', 'external_db')
                                ->select($table_accounts, 't');
            $query->fields('t', ['aid','aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result2 = $query->execute();
            $detail = [];      
            while ($r2 = $result2->fetchObject()) {

                $b = self::opening(
                        [
                            'aid' => $r2->aid,
                            'coid' => $coid,
                            'from' => $stop_date,
                            'archive' => $items['dates']['archive']
                        ]
                );

                if (($b[0] != 0 || $b[1] != 0) && $summary == 0) {
                    $param['aid'] = $r2->aid;
                    $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
                    $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b[1], 'multi' => $b[0], 'url' => $url];
                }
                $total_detail += $b[0];
                $total_detail_base += $b[1];
                
            }

            $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
            $items['liabilities']['header']['class'][$aid]['data'] = $detail;
            $total_class2 += $total_detail;
            $total_class2_base += $total_detail_base;
        }

        $items['liabilities']['header']['total'] = ['base' => $total_class2_base, 'multi' => $total_class2];

        // other liabilities
        $items['other_liabilities'] = [];
        $total_class7 = 0;
        $total_class7_base = 0;

        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['other_liabilities'] . '%', 'like');
        $query->condition('atype', 'header', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $data = $query->execute();
        $rh = $data->fetchObject();
        if($rh){
            $items['other_liabilities']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
        }
        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['other_liabilities'] . '%', 'like');
        $query->condition('atype', 'class', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        while ($r = $result->fetchObject()) {

            $aid = substr($r->aid, 0, 2);
            $items['other_liabilities']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
            $total_detail = 0;
            $total_detail_base = 0;

            $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
            $query->fields('t', ['aid','aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result2 = $query->execute();
            $detail = [];
            while ($r2 = $result2->fetchObject()) {

                $b = self::opening(
                        [
                            'aid' => $r2->aid,
                            'coid' => $coid,
                            'from' => $stop_date,
                            'archive' => $items['dates']['archive']
                        ]
                );


                if (($b[0] != 0 || $b[1] != 0) && $summary == 0) {
                    $param['aid'] = $r2->aid;
                    $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
                    $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b[1], 'multi' => $b[0], 'url' => $url];
                }
                $total_detail += $b[0];
                $total_detail_base += $b[1];
            }

            $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
            $items['other_liabilities']['header']['class'][$aid]['data'] = $detail;
            $total_class7 += $total_detail;
            $total_class7_base += $total_detail_base;
        }

        $items['other_liabilities']['header']['total'] = ['base' => $total_class7_base, 'multi' => $total_class7];
        $items['total_liabilities'] = ['base' => $total_class7_base + $total_class2_base, 'multi' => $total_class7 + $total_class2];

        // NET ASSETS //

        $net_assets = $total_class0 + $total_class1 - $total_class2 - $total_class7;
        $net_assets_base = $total_class0_base + $total_class1_base - $total_class2_base - $total_class7_base;

        $items['net_assets'] = ['base' => $net_assets_base, 'multi' => $net_assets];

        // EQUITY //
        // header account
        // equity ref. accounts
        $items['equity'] = [];
        $equity_min = $chart['equity'] * 10000;
        $equity_max = $equity_min + 9999;
        $earnings_account = $equity_min + 9001; // default
        $reserve_account = $equity_min + 8001; // default

        $total_class3 = 0;
        $total_class3_base = 0;
            
        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['equity'] . '%', 'like');
        $query->condition('atype', 'header', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $data = $query->execute();
        $rh = $data->fetchObject();
        if($rh){
            $items['equity']['header'] = ['aid' => $rh->aid, 'name' => $rh->aname];
        }
        $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
        $query->fields('t', ['aid','aname']);
        $query->condition('aid', $chart['equity'] . '%', 'like');
        $query->condition('atype', 'class', '=');
        $query->condition('astatus', '1', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        while ($r = $result->fetchObject()) {

            $aid = substr($r->aid, 0, 2);
            $items['equity']['header']['class'][$aid] = ['aid' => $r->aid, 'name' => $r->aname];
            $total_detail = 0;
            $total_detail_base = 0;

            $query = Database::getConnection('external_db', 'external_db')
                            ->select($table_accounts, 't');
            $query->fields('t', ['aid','aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result2 = $query->execute();
            $detail = []; 
            while ($r2 = $result2->fetchObject()) {

                if ($r2->aid == $earnings_account) {
                    //caculate current year earnings
                    $b = self::current_earning($coid, $from, $to);
                    //add other entries on the account from journal transactions
                    $dt = self::transactions(
                            [
                            'aid' => $earnings_account,
                            'type' => 'debit',
                            'coid' => $coid,
                            'from' => $from,
                            'to' => $to,
                            'archive' => $items['dates']['archive']
                            ]
                        );
            
                    $ct = self::transactions(
                            [ 
                            'aid' => $earnings_account,
                            'type' => 'credit',
                            'coid' => $coid,
                            'from'=> $from,
                            'to'=> $to,
                            'archive' => $items['dates']['archive']
                            ]
                        );    
                    
                    $b[0] = $b[0] + $ct[0]-$dt[0];
                    $b[1] = $b[1] + $ct[1]-$dt[1];
                    
                } else {
                    // look up for balance
                    $b = self::opening(
                            [
                                'aid' => $r2->aid,
                                'coid' => $coid,
                                'from' => $stop_date,
                                'archive' => $items['dates']['archive']
                            ]
                    );
                }

                if (($b[0] != 0 || $b[1] != 0 ) && $summary == 0) {
                    $param['aid'] = $r2->aid;
                    $url = Url::fromRoute('ek_finance_modal', array('param' => serialize($param)))->toString();
                    $detail[$r2->aid] = ['aid' => $r2->aid, 'name' => $r2->aname,'base' => $b[1], 'multi' => $b[0], 'url' => $url];
                }
                $total_detail += $b[0];
                $total_detail_base += $b[1];
            }

            $detail['total'] = ['aid' => $r->aid, 'name' => $r->aname,'base' => $total_detail_base, 'multi' => $total_detail];
            $items['equity']['header']['class'][$aid]['data'] = $detail;
            $total_class3 += $total_detail;
            $total_class3_base += $total_detail_base;
        }

        $items['equity']['header']['total'] = ['base' => $total_class3_base, 'multi' => $total_class3];
        $items['total_equity'] = ['base' => $total_class3_base, 'multi' => $total_class3];

        if (round($net_assets_base,2) != round($total_class3_base,2) ) {
            $items['error'] = number_format(round($net_assets_base,2) - round($total_class3_base,2), 2);
        }

        return $items;

     }

     /**
      * Profit and loss
     * @param int $coid
     * @param str $year
     * @param str $month
     * @param bol $summary
     * 
     * @return array $items
      */
    public function profitloss($coid, $year, $month, $summary) {
        $company = AccessCheck::GetCompanyByUser();
        $company = implode(',', $company);
        $financesettings = new FinanceSettings();
        $chart = $financesettings->get('chart');
        $items = [];
        $items['baseCurrency'] = $financesettings->get('baseCurrency');
        $items['summary'] = $summary; 

        /*
        * starting date is based on fiscal year settings 
        * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
        * and has started on 1/07/13
        */

        $items['dates'] = self::getFiscalDates($coid, $year, $month);
        $to = $items['dates']['to'];
        $stop_date = $items['dates']['stop_date'];

        if ($items['dates']['archive'] == TRUE) {
            $from = $items['dates']['from'];
            //extract data from archive tables
            $table_accounts = "ek_accounts_" . $year . "_" . $coid;
            $table_journal = "ek_journal_" . $year . "_" . $coid;
            $items['title'] = t('Archive data');
            /* $alert = "<div class='messages messages--warning'>" .
            t('Archive data') .
            "</div>"; */
        } else {
            $from = $items['dates']['fiscal_start'];
            //extract data from current tables
            $table_accounts = "ek_accounts";
            $table_journal = "ek_journal";
            $items['title'] = t('Current fiscal year @y', ['@y' => $items['dates']['fiscal_year']]);
        }
        //used to create links
        $param = [ 'id' => 'bs',
            'from' => $from,
            'to' => $to,
            'coid' => $coid,
            'aid' => '',
            'archive' => $items['dates']['archive']
        ];

        // REVENUE //
        // Other income
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

                $d = self::transactions(
                        [
                            'aid' => $r2->aid,
                            'type' => 'debit',
                            'coid' => $coid,
                            'from' => $from,
                            'to' => $to,
                            'archive' => $items['dates']['archive']
                        ]
                );

                $c = self::transactions(
                        [
                            'aid' => $r2->aid,
                            'type' => 'credit',
                            'coid' => $coid,
                            'from' => $from,
                            'to' => $to,
                            'archive' => $items['dates']['archive']
                        ]
                );
                $balance = $c[0] - $d[0];
                $balance_base = $c[1] - $d[1];

                if (($balance != 0 || $balance_base != 0) && $summary == 0) {
                    $param['aid'] = $r2->aid;
                    $url = Url::fromRoute('ek_finance_modal', ['param' => serialize($param)])->toString();
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

        // Income

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

                $d = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'debit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
                );

                $c = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'credit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
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


        // COST of SALES //
        // header account

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

                $d = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'debit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
                );

                $c = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'credit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
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


        // CHARGES //
        // other expenses

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

                $d = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'debit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
                );

                $c = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'credit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
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

        // expenses

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

                $d = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'debit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
                );

                $c = self::transactions(
                    [
                        'aid' => $r2->aid,
                        'type' => 'credit',
                        'coid' => $coid,
                        'from' => $from,
                        'to' => $to,
                        'archive' => $items['dates']['archive']
                    ]
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


        // RESULT //

        $items['result_multi'] = $total_class_oincome + $total_class_income + $total_class_cos + $total_class_oexpenses + $total_class_expenses;
        $items['result_base'] = $total_class_oincome_base + $total_class_income_base + $total_class_cos_base + $total_class_oexpenses_base + $total_class_expenses_base;

        return $items;

    }

    /**
     * calculate start and end dates of fiscal period based on company and given year and month
     * starting date is based on fiscal year settings
     * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
     * and has started on 1/07/13
     * start date are used in report extraction like balance sheet or trial
     *
     * @param int $coid 
     * @param int $year int year i.e 2016
     * @return array
     *  array of dates in string format Y-m-d $from, $to, $stop_date, $fiscal_start, $fiscal_end, bool. archive
     */

    public static function getFiscalDates($coid, $year, $month) {

        /* if previous year has been posted to new year
         * New fiscal year is : start year + 12 months
         * i.e. if year ending 30/06/17 has been posted, new fiscal year is 2018 (30/6/18)
         * if we are in July 2017, the start date is 01/07/17
         */
        $company = new CompanySettings($coid);
        $archive = false;
        $f = new DateTime($company->get('fiscal_year') . '-' . $company->get('fiscal_month'));
        $d = new DateTime($year . '-' . $month);

        // the starting of fiscal year based on settings
        $start_fiscal = date('Y-m-d', strtotime($f->format('Y-m-d') . ' - 11 months'));
        // the end of fiscal year based on settings
        $end_fiscal = $f->format('Y-m-t');

        // last date based on request
        $end_request = $d->format('Y-m-t');
        // start date of request
        $start_request = date('Y-m-d', strtotime($d->format('Y-m-d') . ' - 11 months'));

        if ($end_request < $start_fiscal) {
            $archive = true;
        }

        // a stop date based on request
        $stop_date = date('Y-m-d', strtotime($d->format('Y-m-t') . ' + 1 day'));

        return [
            'month_settings' => $company->get('fiscal_month'),
            'year_settings' => $company->get('fiscal_year'),
            'from' => $start_request,
            'to' => $end_request,
            'stop_date' => $stop_date,
            'fiscal_start' => $start_fiscal,
            'fiscal_end' => $end_fiscal,
            'archive' => $archive,
            'fiscal_year' => $end_fiscal
        ];
    }

    /*
     * return details of a journal entry
     * all details are returned in single array
     * used in reconciliation
     * @param id = the id of the entry
     *  @return array
     *    full keyed array of journal entry
     *
     */

    public static function journalEntryDetails($id) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->fields('j');
        $query->condition('id', $id);
        $result = $query->execute()->fetchObject();
        if($result) {
            $ref = self::reference($result->source, $result->reference);
            if ($result->comment == '') {
                $result->comment = $ref[0];
            }
            if ($result->currency == '') {
                $result->currency = $ref[1];
            }


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 't');
            $query->fields('t', ['aname']);
            $query->condition('coid', $result->coid, '=')
                    ->condition('aid', $result->aid);
            $aname = $query->execute()->fetchField();
            
            return [
                'id' => $result->id,
                'count' => $result->count,
                'aid' => $result->aid,
                'aname' => $aname,
                'exchange' => $result->exchange,
                'coid' => $result->coid,
                'type' => $result->type,
                'source' => $result->source,
                'reference' => $result->reference,
                'date' => $result->date,
                'value' => $result->value,
                'reconcile' => $result->reconcile,
                'comment' => $result->comment,
                'currency' => $result->currency,
                'url' => Url::fromRoute('ek_finance.extract.general_journal', [], ['absolute' => true, 'query' => ['jid' => $result->id]])->toString()
            ];
        }
    }

    /*
     * The main record function for accounting journal
     * All records are based in double entry accounting procedure
     *
     * @param $j = an array of parameters passed to the function
     * $j may contains different variables base on the transaction:
     * aid = an account aid from chart of accounts
     * coid = a company / entity id to which the entry is related
     * type = debit or credit
     * source = a transaction type (ie general, payroll, purchase, invoice, receipt)
     * reference = an id of a transaction type for extended description of the source (ie invoice entry id)
     * date = date of transaction
     * value = value of transaction
     * currency = currency of transaction
     * comment = any optional comment for the transaction
     * fxRate = exchange rate for the transaction if not in base currency
     * fxRate2 = exchange rate when payments received are credited to a different currency account than the payment
     */

    public function record($j) {
        $settings = new FinanceSettings();
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
        $baseCurrency = $settings->get('baseCurrency');
        $companysettings = new CompanySettings($j['coid']);
        $currency = new CurrencyData();


        switch ($j['source']) {
            case 'general':

                $id = self::save($j['aid'], '0', $j['coid'], $j['type'], $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);
                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $cash1 = $companysettings->get('cash_account', $j['currency']);
                    $cash2 = $companysettings->get('cash2_account', $j['currency']);
                    if ($cash1 == $j['aid'] || $cash2 == $j['aid']) {
                        //if transaction between 2 accounts and 1 account is holding cash currencies
                        //the conversion of cash value is not recorded in exchange value
                        //but recorded as real value in base currency account
                        $flag = 1;
                    } else {
                        $flag = 0;
                    }

                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], 1, $j['coid'], $j['type'], $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }
                return $id;

                break;

            case 'general cash':
            case 'general memo':
            case 'expense amortization':

                $id = self::save($j['aid'], '0', $j['coid'], $j['type'], $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], $j['type'], $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }
                return $id;
                break;

            /*
             * RECEIPT , POS
             */
            case 'receipt pos':
                $id = self::save($j['aid'], $j['exchange'], $j['coid'], $j['type'], 'receipt pos', $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);

                break;
            /*
             * RECEIPT , INVOICE
             */

            case 'receipt':
            case 'receipt internal':

                /*
                 * Receipts can be made either to cash account or bank account
                 * need to determine first the nature of debited account
                 */
                if (strpos($j['aid'], "-")) {
                    //paid from cash account
                    $data = explode("-", $j['aid']);
                    $account_currency = $data[0];
                    $account_aid = $data[1];
                } else {
                    // bank account
                    $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_bank_accounts', 'b');
                    $query->fields('b', ['currency', 'aid']);
                    $query->condition('id', $j['aid']);
                    $Obj = $query->execute()->fetchObject();
                    $account_currency = $Obj->currency;
                    $account_aid = $Obj->aid;
                }

                /*
                 * if currency of payment account is different from the currency of the invoice,
                 * the amount received (debited) is converted into the currency of the payment account
                 */
                if ($account_currency <> $j['currency']) {
                    $debit = round($j['fxRate2'] * $j['value'], $rounding);
                    $currency = $account_currency; // keep currency of receiving account
                } else {
                    $debit = $j['value'];
                    $currency = $j['currency'];
                }

                //main  DEBIT
                self::save($account_aid, '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $debit, '0', $currency);

                //exchange
                //account receiving fund is not in base currency
                if ($account_currency <> $baseCurrency) {
                    $trfx = $j['fxRate'];
                    if (isset($j['fxRate2']) && $j['fxRate2'] <> 1) {
                        $trfx = $j['fxRate2'];
                    }
                    $exchange = CurrencyData::journalexchange($account_currency, $debit, $trfx);
                    self::save($account_aid, '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main CREDIT
                $asset = $companysettings->get('asset_account', $j['currency']);
                self::save($asset, '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);
                //exchange
                //currency of payment is not base currency
                if ($j['currency'] <> $baseCurrency) {
                    $trfx = $j['fxRate'];
                    if (isset($j['fxRate2']) && $j['fxRate2'] <> 1 && ($account_currency <> $baseCurrency)) {
                        $trfx = $j['fxRate2'];
                    }
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $trfx);
                    self::save($asset, '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // currency gain || loss
                // When rate of non base currency increase between date of sales and date of receipt, there is an exchange loss
                // loss is recorded with DT of sales account vs CT of debtor
                // When rate of non base currency decrease between date of sales and date of receipt, there is an exchange gain
                // gain is recorded with CT of sales account vs DT of debtor
                if ($account_currency <> $baseCurrency) {
                    $gain = self::exchangeGL($j['currency'], $j['value'], $j['rate'], $j['fxRate']);
                    $aid = $companysettings->get('CurrencyGainLoss');
                    if ($aid == '') {
                        $aid = '49999';
                    }

                    if ($gain > 0) {
                        //gain
                        self::save($aid, '1', $j['coid'], 'credit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($asset, '1', $j['coid'], 'debit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange gain');
                    }

                    if ($gain < 0) {
                        //loss
                        $gain = abs($gain);
                        self::save($aid, '1', $j['coid'], 'debit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($asset, '1', $j['coid'], 'credit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange loss');
                    }
                }


                break;

            /*
             * INVOICE , POS
             */

            case 'pos sale':
                /*
                 * data
                 */
                //main  CREDIT
                self::save($j['aid'], '0', $j['coid'], 'credit', 'pos sale', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'credit', 'pos sale', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main  DEBIT
                $asset = $companysettings->get('asset_account', $j['currency']);
                self::save($asset, '0', $j['coid'], 'debit', 'pos sale', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($asset, '1', $j['coid'], 'debit', 'pos sale', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // tax collect
                if ($j['tax'] <> 0) {
                    $this->tax += $j['tax'];
                }

                break;




            /*
             * RECEIPT - SHORT , INVOICE
             */

            case 'short payment':

                //main  DEBIT -> ACCOUNT SELECTED BY USER
                self::save($j['aid'], '0', $j['coid'], 'debit', 'receipt', $j['reference'], $j['date'], $j['value'], '0', $j['currency'], 'short payment applied to charges');

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', 'receipt', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main CREDIT
                $asset = $companysettings->get('asset_account', $j['currency']);
                self::save($asset, '0', $j['coid'], 'credit', 'receipt', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);
                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($asset, '1', $j['coid'], 'credit', 'receipt', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // currency gain || loss
                // When rate of non base currency increase between date of sales and date of receipt, there is an exchange loss
                // loss is recorded with DT of sales account vs CT of debtor
                // When rate of non base currency decrease between date of sales and date of receipt, there is an exchange gain
                // gain is recorded with CT of sales account vs DT of debtor
                if ($j['currency'] <> $baseCurrency) {
                    $gain = self::exchangeGL($j['currency'], $j['value'], $j['rate']);
                    $aid = $companysettings->get('CurrencyGainLoss');
                    if ($aid == '') {
                        $aid = '49999';
                    }

                    if ($gain > 0) {
                        self::save($aid, '1', $j['coid'], 'credit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($asset, '1', $j['coid'], 'debit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange gain');
                    }

                    if ($gain < 0) {
                        $gain = abs($gain);
                        self::save($aid, '1', $j['coid'], 'debit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($asset, '1', $j['coid'], 'credit', 'receipt', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange loss');
                    }
                }// gain

                break;

            /*
             * PAYMENT , PURCHASE
             */
            case 'payment':

                $sum_credit = 0;
                $sum_debit = 0;
                /*
                 * Payment can be made either from cash account or bank account
                 * need to determine first the nature of credited account
                 */
                if (strpos($j['aid'], "-")) {
                    //paid from cash account
                    $data = explode("-", $j['aid']);
                    $account_currency = $data[0];
                    $account_aid = $data[1];
                } else {
                    // bank account
                    $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_bank_accounts', 'b');
                    $query->fields('b', ['currency', 'aid']);
                    $query->condition('id', $j['aid']);
                    $Obj = $query->execute()->fetchObject();
                    $account_currency = $Obj->currency;
                    $account_aid = $Obj->aid;
                }

                /*
                 * if currency of payment account is different from the currency of the purchase,
                 * the amount paid (credited) is converted into the currency of the payment account
                 */
                if ($account_currency <> $j['currency']) {
                    $credit = round($j['value'] / $j['fxRate'], $rounding);
                } else {
                    $credit = $j['value'];
                }

                //$j['source'] = 'expense' ; //???
                //main  CREDIT
                self::save($account_aid, '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $credit, '0', $account_currency);
                $sum_credit += $credit;
                //exchange
                if ($account_currency <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($account_currency, $credit, $j['fxRate']);
                    self::save($account_aid, '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main DEBIT
                $liability = $companysettings->get('liability_account', $j['currency']);
                self::save($liability, '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);
                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($liability, '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }


                //currency gain || loss
                //When rate of non base currency increase between date of purchase and date of payment, there is an exchange gain
                // gain is recorded with CT of sales account vs DT of debtor
                //When rate of non base currency decrease between date of purchase and date of payment, there is an exchange loss
                // loss is recorded with DT of sales account vs CT of debtor

                if ($j['currency'] <> $baseCurrency) {
                    $gain = self::exchangeGL($j['currency'], $j['value'], $j['rate'], $j['fxRate']);

                    $aid = $companysettings->get('CurrencyGainLoss');
                    if ($aid == '') {
                        $aid = '49999';
                    }

                    if ($gain > 0) {
                        //loss
                        self::save($aid, '1', $j['coid'], 'debit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($liability, '1', $j['coid'], 'credit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange loss');
                    }

                    if ($gain < 0) {
                        //gain
                        $gain = abs($gain);
                        self::save($aid, '1', $j['coid'], 'credit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($liability, '1', $j['coid'], 'debit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange gain');
                    }
                } //gain/loss


                break;
            /*
             * PAYROLL
             */
            case 'expense payroll':
                $totalvalue = 0;
                $totalvalueexchange = 0;
                // main  CREDIT
                // loop array for funds and tax (able to increase number of funds in future
                for ($i = 0; $i < count($j['funds']); $i++) {
                    if (isset($j['funds']["f$i"]) && $j['funds']["f$i"] > 0) {
                        $a = 'f' . $i . 'a';
                        self::save($j['funds'][$a], '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['funds']["f$i"], '0', $j['currency']);
                        $totalvalue += $j['funds']["f$i"];
                        // exchange
                        if ($j['currency'] <> $baseCurrency) {
                            $exchange = CurrencyData::journalexchange($j['currency'], $j['funds']["f$i"], $j['fxRate']);
                            $totalvalueexchange += $exchange;
                            self::save($j['funds'][$a], '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                        }
                    }
                }

                for ($i = 0; $i < count($j['tax']); $i++) {
                    if (isset($j['tax']["t$i"]) && $j['tax']["t$i"] > 0) {
                        $a = 't' . $i . 'a';
                        self::save($j['tax'][$a], '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['tax']["t$i"], '0', $j['currency']);
                        $totalvalue += $j['tax']["t$i"];
                        //exchange
                        if ($j['currency'] <> $baseCurrency) {
                            $exchange = CurrencyData::journalexchange($j['currency'], $j['tax']["t$i"], $j['fxRate']);
                            $totalvalueexchange += $exchange;
                            self::save($j['tax'][$a], '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                        }
                    }
                }

                // main CREDIT
                // credit source is set as payroll
                self::save($j['netpayaccount'], '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['netpay'], '0', $j['currency']);
                $totalvalue +=  $j['netpay'];
                // exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['netpay'], $j['fxRate']);
                    $totalvalueexchange += $exchange;
                    self::save($j['netpayaccount'], '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // main  DEBIT
                // debit source is set to 'expense payroll'
                self::save($j['aid'], '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);
                // exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    $rec = ($exchange == $totalvalueexchange) ? $exchange : $totalvalueexchange;
                    self::save($j['aid'], '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $rec, '0', $baseCurrency);
                }

                break;
            /*
             * EXPENSE
             */
            case 'expense':
            case 'payroll':

                // main  DEBIT
                self::save($j['aid'], '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                // exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);                
                }
                
                //main  CREDIT
                if (isset($j['provision']) && $j['provision'] == '1') {
                    $account_currency = $j['currency'];
                    $aid = $j['bank'];
                } else {
                    if (strpos($j['bank'], "-")) {
                        $account = explode('-', $j['bank']);
                        $aid = $account[1];
                        $account_currency = $account[0];
                    } else {
                        $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_bank_accounts', 'b');
                        $query->fields('b', ['currency', 'aid']);
                        $query->condition('id', $j['bank']);
                        $Obj = $query->execute()->fetchObject();
                        $aid = $Obj->aid;
                        $account_currency = $Obj->currency;
                    }
                }

                /*
                 * if currency of payment account is different from the currency of the purchase,
                 * the amount paid (credited) is converted into the currency of the payment account
                 */
                if ($account_currency <> $j['currency']) {
                    $credit = round($j['fxRate'] * $j['value'], $rounding);
                    $j['tax'] = round($j['fxRate'] * $j['tax'], $rounding);
                } else {
                    $credit = $j['value'];
                }
                if ($j['source'] == 'payroll') {
                    //record payment credit with expense source for optional edit via expense interface
                    self::save($aid, '0', $j['coid'], 'credit', 'payroll', $j['reference'], $j['date'], $credit, '0', $j['currency']);
                } else {
                    self::save($aid, '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $credit, '0', $j['currency']);
                }


                // exchange
                if ($j['currency'] <> $baseCurrency) {
                    if ($j['source'] == 'payroll') {
                        self::save($aid, '1', $j['coid'], 'credit', 'payroll', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    } else {
                        self::save($aid, '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }
                }

                // tax paid
                if ($j['tax'] > 0) {
                    $stax_deduct_aid = $companysettings->get('stax_deduct_aid');
                    self::save($stax_deduct_aid, '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $j['tax'], '0', $j['currency']);

                    //exchange
                    if ($j['currency'] <> $baseCurrency) {
                        $exchange = CurrencyData::journalexchange($j['currency'], $j['tax'], $j['fxRate']);
                        self::save($stax_deduct_aid, '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }

                    self::save($aid, '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['tax'], '0', $j['currency']);

                    //exchange
                    if ($j['currency'] <> $baseCurrency) {
                        self::save($aid, '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }
                }

                break;



            /*
             * PURCHASE
             */

            case 'purchase':
                /*
                 * data
                 */
                // main  DEBIT
                self::save($j['aid'], '0', $j['coid'], 'debit', 'purchase', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                // exchange
                if ($j['currency'] <> $baseCurrency) { 
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', 'purchase', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // main CREDIT
                $liability = $companysettings->get('liability_account', $j['currency']);
                self::save($liability, '0', $j['coid'], 'credit', 'purchase', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                // exchange
                if ($j['currency'] <> $baseCurrency) {
                    self::save($liability, '1', $j['coid'], 'credit', 'purchase', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // tax deduct
                if ($j['tax'] <> 0) {
                    if (!isset($this->tax)) {
                        $this->tax = 0;
                    }
                    $this->tax += $j['tax'];
                } // tax collect



                break;

            /*
             * INVOICE
             */
            case 'invoice':
            case 'invoice internal':
                /*
                 * data
                 */
                //main  CREDIT
                self::save($j['aid'], '0', $j['coid'], 'credit', 'invoice', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'credit', 'invoice', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main  DEBIT
                $asset = $companysettings->get('asset_account', $j['currency']);
                self::save($asset, '0', $j['coid'], 'debit', 'invoice', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($asset, '1', $j['coid'], 'debit', 'invoice', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // tax collect
                if ($j['tax'] <> 0) {
                    if (!isset($this->tax)) {
                        $this->tax = 0;
                    }
                    $this->tax += $j['tax'];
                }

                break;

            case 'credit note':

                //main  CREDIT (receivable)
                $asset = $companysettings->get('asset_account', $j['currency']);
                self::save($asset, '0', $j['coid'], 'credit', 'invoice cn', $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($asset, '1', $j['coid'], 'credit', 'invoice cn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main  DEBIT (sales deduction)
                self::save($j['aid'], '0', $j['coid'], 'debit', 'invoice cn', $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', 'invoice cn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                if ($j['tax'] <> 0) {
                    //Liability Tax payable account need to be DT with CT of receivable account
                    $value = round($j['value'] * $j['tax'] / 100, $rounding);
                    $liability = $companysettings->get('stax_collect_aid');

                    self::save($liability, '0', $j['coid'], 'debit', 'invoice cn', $j['reference'], $j['date'], $value, '0', $j['currency'], $j['comment']);
                    //exchange
                    if ($j['currency'] <> $baseCurrency) {
                        $exchange = CurrencyData::journalexchange($j['currency'], $value, $j['fxRate']);
                        self::save($liability, '1', $j['coid'], 'debit', 'invoice cn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }

                    self::save($asset, '0', $j['coid'], 'credit', 'invoice cn', $j['reference'], $j['date'], $value, '0', $j['currency'], $j['comment']);
                    //exchange
                    if ($j['currency'] <> $baseCurrency) {
                        self::save($asset, '1', $j['coid'], 'credit', 'invoice cn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }
                }

                break;

            case 'debit note':

                //main  DEBIT (payable)
                $liability = $companysettings->get('liability_account', $j['currency']);
                self::save($liability, '0', $j['coid'], 'debit', 'purchase dn', $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($liability, '1', $j['coid'], 'debit', 'purchase dn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main  CREDIT (purchase deduction)
                self::save($j['aid'], '0', $j['coid'], 'credit', 'purchase dn', $j['reference'], $j['date'], $j['value'], '0', $j['currency'], $j['comment']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'credit', 'purchase dn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                if ($j['tax'] <> 0) {
                    //Asset Tax deductible account need to be CT against liability payable

                    $value = round($j['value'] * $j['tax'] / 100, $rounding);
                    $asset = $companysettings->get('stax_deduct_aid');

                    self::save($asset, '0', $j['coid'], 'credit', 'purchase dn', $j['reference'], $j['date'], $value, '0', $j['currency'], $j['comment']);
                    //exchange
                    if ($j['currency'] <> $baseCurrency) {
                        $exchange = CurrencyData::journalexchange($j['currency'], $value, $j['fxRate']);
                        self::save($asset, '1', $j['coid'], 'credit', 'purchase dn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }

                    self::save($liability, '0', $j['coid'], 'debit', 'purchase dn', $j['reference'], $j['date'], $value, '0', $j['currency'], $j['comment']);
                    //exchange
                    if ($j['currency'] <> $baseCurrency) {
                        self::save($liability, '1', $j['coid'], 'debit', 'purchase dn', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                    }
                }

                break;
        }
    }

    //end record

    /*
     * record the tax payable based on $j parameters (see above)
     *
     */

    public function recordtax($j) {
        $settings = new FinanceSettings();
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
        $baseCurrency = $settings->get('baseCurrency');
        $companysettings = new CompanySettings($j['coid']);

        $currency = new CurrencyData();

        switch ($j['type']) {

            case 'stax_deduct_aid':
                $aid = $companysettings->get('stax_deduct_aid');
                $aid2 = $companysettings->get('liability_account', $j['currency']);
                $entry1 = 'debit';
                $entry2 = 'credit';
                break;

            case 'stax_collect_aid':
                $aid = $companysettings->get('stax_collect_aid');
                $aid2 = $companysettings->get('asset_account', $j['currency']);
                $entry1 = 'credit';
                $entry2 = 'debit';
                break;
        }

        if (isset($this->tax) && $this->tax > 0) {
            // 1
            self::save($aid, '0', $j['coid'], $entry1, $j['source'], $j['reference'], $j['date'], round($this->tax,$rounding), '0', $j['currency']);

            //exchange
            if ($j['currency'] <> $baseCurrency) {
                $exchange = CurrencyData::journalexchange($j['currency'], round($this->tax,$rounding), $j['fxRate']);
                self::save($aid, '1', $j['coid'], $entry1, $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
            }

            // 2
            self::save($aid2, '0', $j['coid'], $entry2, $j['source'], $j['reference'], $j['date'], round($this->tax,$rounding), '0', $j['currency']);

            //exchange
            if ($j['currency'] <> $baseCurrency) {
                self::save($aid2, '1', $j['coid'], $entry2, $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
            }
            $this->tax = 0;
            return round($this->tax,$rounding);
        } // tax
    }

    /*
     * @return table insert id
     * @param mixed
     * aid  int chart account id
     * exchange bolean 1,0
     * coid int company id
     * type string transaction type debit|credit
     * source string transaction source
     * ref int source id
     * date string
     * value double transaction value
     * reco bolean 1,0
     * currency string
     * comment string
     */

    private function save($aid, $exchange, $coid, $type, $source, $ref, $date, $value, $reco, $currency = null, $comment = null) {
        $query = "SELECT count('id') FROM {ek_journal} WHERE coid = :c";
        $count = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => $coid])->fetchField();


        $count++;
        $fields = array(
            'count' => $count,
            'aid' => $aid,
            'exchange' => $exchange,
            'coid' => $coid,
            'type' => $type,
            'source' => $source,
            'reference' => $ref,
            'date' => $date,
            'value' => $value,
            'reconcile' => $reco,
            'currency' => $currency,
            'comment' => $comment,
        );
        $insert = Database::getConnection('external_db', 'external_db')
                ->insert('ek_journal')
                ->fields($fields)
                ->execute();
        if (!$insert) {
            \Drupal::messenger()->addError(t('Error journal record (@aid)', ['@aid' => $aid]));
            $insert = null;
        } else {
            //Track user input history for backup and audit procedures
            //Todo implement tracking on delete data (sales doc delete, journal delete)
            $fields = array(
                'jid' => $insert,
                'username' => \Drupal::currentUser()->getAccountName(),
                'action' => 1,
                'timestamp' => date('U')
            );
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_journal_trail')
                    ->fields($fields)
                    ->execute();
        }


        if ($type == 'credit') {
            $this->credit = $this->credit + $value;
        } else {
            $this->debit = $this->debit + $value; 
        }

        return $insert;
    }

    /*
     * Identify journal errors by coid and period
     * @return array
     * @param  array param
     * coid int company id
     * from : date string
     * to : date string
     */

    public function traceError($param) {

        //verify if each journal references as equal debit and credit
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->fields('j', ['id', 'reference']);
        $query->distinct();
        $query->condition('date', $param['to'], '<=');
        $query->condition('date', $param['from'], '>=');
        $query->condition('j.coid', $param['coid'], '=');
        $data = $query->execute();
        $error = [];
        while ($j = $data->fetchObject()) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->addExpression('SUM(value)', 'sumValue');
            $query->condition('j.coid', $param['coid'], '=')
                    ->condition('j.type', 'credit', '=')
                    ->condition('j.reference', $j->reference, '=')
                    ->condition('date', $param['from'], '>=')
                    ->condition('date', $param['to'], '<=');
            $Obj = $query->execute();
            $ct = $Obj->fetchObject()->sumValue;

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->addExpression('SUM(value)', 'sumValue');
            $query->condition('j.coid', $param['coid'], '=')
                    ->condition('j.type', 'debit', '=')
                    ->condition('j.reference', $j->reference, '=')
                    ->condition('date', $param['from'], '>=')
                    ->condition('date', $param['to'], '<=');
            $Obj = $query->execute();
            $dt = $Obj->fetchObject()->sumValue;

            if (round($dt, 2) != round($ct, 2)) {
                $error['reference'][] = self::journalEntryDetails($j->id);
            }
        }

        //verify if account used in journal is active
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->fields('j', ['aid']);
        $query->distinct();
        $query->condition('date', $param['to'], '<=');
        $query->condition('date', $param['from'], '>=');
        $query->condition('j.coid', $param['coid'], '=');
        $data = $query->execute();

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'astatus']);
        $query->condition('coid', $param['coid'], '=');
        $accounts = $query->execute()->fetchAllKeyed();

        while ($j = $data->fetchObject()) {
            if ($accounts[$j->aid] == '0') {
                $error['account'][] = ['aid' => $j->aid, 'status' => 'disabled'];
            } elseif ($accounts[$j->aid] == null) {
                $error['account'][] = ['aid' => $j->aid, 'status' => 'unknown'];
            }
        }



        return $error;
    }

    /*
     * @return INT validate a transaction debit against a credit
     * return value debit >= credit
     */

    public function checkTransactionDebit($j) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.source', $j['source_dt'], '=')
                ->condition('j.reference', $j['reference'], '=')
                ->condition('j.type', 'debit', '=')
                ->condition('j.aid', $j['account'], '=')
                ->condition('j.exchange', 0, '=');

        $Obj = $query->execute();
        $debit = $Obj->fetchObject()->sumValue;

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.source', $j['source_ct'], '=')
                ->condition('j.reference', $j['reference'], '=')
                ->condition('j.type', 'credit', '=')
                ->condition('j.aid', $j['account'], '=')
                ->condition('j.exchange', 0, '=');

        $Obj = $query->execute();
        $credit = $Obj->fetchObject()->sumValue;

        return $debit - $credit;
    }

    /*
     * @return INT validate a transaction credit against a debit return value credit >= debit
     * @param array
     *
     */

    public function checkTransactionCredit($j) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.source', $j['source_dt'], '=')
                ->condition('j.reference', $j['reference'], '=')
                ->condition('j.type', 'debit', '=')
                ->condition('j.aid', $j['account'], '=')
                ->condition('j.exchange', 0, '=');

        $Obj = $query->execute();
        $debit = $Obj->fetchObject()->sumValue;

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.source', $j['source_ct'], '=')
                ->condition('j.reference', $j['reference'], '=')
                ->condition('j.type', 'credit', '=')
                ->condition('j.aid', $j['account'], '=')
                ->condition('j.exchange', 0, '=');

        $Obj = $query->execute();
        $credit = $Obj->fetchObject()->sumValue;

        return $credit - $debit;
    }

    /**
     * calculate total transactions between 2 dates
     * @param
     * aid = account id from chart of accounts
     * type = debit or credit
     * coid = company / entity id
     * from = date from
     * to = date to
     *    $journal->transactions(
     *                    array(
     *                    'aid'=> '00000',
     *                    'type'=> 'debit',
     *                    'coid'=> '1',
     *                    'from'=> '0000-00-00',
     *                    'to'=> '0000-00-00'
     *                    'archive' => TRUE/FALSE
     *                     )
     *                    );
     * @return array
     *   with transaction value , transaction value in base currency
     */

    public function transactions($data) {
        $type = $data['type'];
        $account = $data['aid'];
        $coid = $data['coid'];
        $d1 = $data['from'];
        $d2 = $data['to'];
        $settings = new FinanceSettings();
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;

        if (isset($data['archive']) && $data['archive'] == true) {
            //extract data from archive tables
            $year = date('Y', strtotime($data['to'] . ' - 1 day'));
            $table_journal = "ek_journal_" . $year . "_" . $coid;
        } else {
            $table_journal = "ek_journal";
        }

        //get to total transaction up to d1
        // sum transaction currency
        $query = Database::getConnection('external_db', 'external_db')
                ->select($table_journal, 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.coid', $coid, '=')
                ->condition('j.type', $type, '=')
                ->condition('j.aid', $account, '=')
                ->condition('j.exchange', 0, '=')
                ->condition('date', $d1, '>=')
                ->condition('date', $d2, '<=');

        $Obj = $query->execute();
        $transactions = $Obj->fetchObject()->sumValue;
        if (!$transactions) {
            $transactions = 0;
        }

        // sum transaction exchange
        $query = Database::getConnection('external_db', 'external_db')
                ->select($table_journal, 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.coid', $coid, '=')
                ->condition('j.type', $type, '=')
                ->condition('j.aid', $account, '=')
                ->condition('j.exchange', 1, '=')
                ->condition('date', $d1, '>=')
                ->condition('date', $d2, '<=');

        $Obj = $query->execute();
        $transaction_exc = $Obj->fetchObject()->sumValue;
        if (!$transaction_exc) {
            $transaction_exc = 0;
        }

        return array(round($transactions, $rounding), round($transactions + $transaction_exc, $rounding));
    }

    /*
     * calculate a gain or loss between 2 dates when transactions are not in base currency
     * by comparing current value to past value
     * ie Invoice recorded 30 days previous payment received
     * The rates must be expressed relative to base currency
     * For example if base currency is USD = 1, EUR = 0.9, GBP = 0.7, etc
     * @param $currency = the currency code
     * @param $value = the transaction value
     * @param $rate = the exchange rate of initial transaction
     * @param $current_rate = the current exchange rate
     *
     * @return the value of gain or loss.
     */

    private static function exchangeGL($currency, $value, $rate, $current_rate = null) {
        //exchange gain loss
        if ($current_rate == null) {
            $current_rate = CurrencyData::rate($currency);
        }

        if ($rate <> $current_rate) {
            $settings = new FinanceSettings();
            $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            $variation = round((($value / $current_rate) - $value / $rate), $rounding);
            return $variation;
        } else {
            return 0;
        }
    }

    /*
     * calculate a total value per account between 2 dates
     *
     * $param $table query table (current journal or archive)
     * @param $exchange = exchange flag 0 or 1
     * @param $type = the transaction type, debit or credit
     * @param $aid = the account No
     * @param $coid = the company id
     * @param $date_start = start date (>=)
     * @param $date_end = end date (<)     *
     * @return double value
     */

    private static function sumAccount($table, $exchange, $type, $aid, $coid, $date_start, $date_end) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select($table, 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.exchange', $exchange, '=')
                ->condition('j.type', $type, '=')
                ->condition('j.aid', $aid, '=')
                ->condition('j.coid', $coid, '=')
                ->condition('j.date', $date_start, '>=')
                ->condition('j.date', $date_end, '<=');


        $Obj = $query->execute();
        $sum = $Obj->fetchObject()->sumValue;
        return $sum;
    }

    /*
     * Collect and return data journal id
     *
     * @param $jid id of the journal entry
     */

    public function data_by_jid($jid) {
        $details = self::journalEntryDetails($jid);
        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $account_list = AidList::chartList($details['coid']);

        //collect all data referring to this entry
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->fields('j');
        $query->leftJoin('ek_journal_trail', 't', 'j.id = t.jid');
        $query->fields('t', ['username', 'action', 'timestamp']);
        $query->condition('coid', $details['coid']);
        $query->condition('source', $details['source']);
        $query->condition('reference', $details['reference']);
        $query->condition('date', $details['date']);
        $data = $query->execute();
        $rows = array();
        $transactions = array();
        $references = array();
        $ref = self::reference($details['source'], $details['reference']);
        $query = "SELECT name from {ek_company} WHERE id=:id";
        $name = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':id' => $details['coid']])->fetchField();
        $references = [
            'company' => $name,
            'source' => $details['source'],
            'reference' => $details['reference'],
            'reference_detail' => $ref[0],
            'currency' => $ref[1],
            'date' => $details['date']
        ];

        $sum_d = 0; //sum base currency
        $sum_c = 0;
        $sum_d2 = 0; //sum currency
        $sum_c2 = 0;
        while ($d = $data->fetchobject()) {
            if ($d->exchange == 0) {
                //build an history link
                $d1 = date('Y', strtotime($details['date'])) . '-01-01';
                $param = serialize(
                        array(
                            'id' => 'journal',
                            'from' => $d1,
                            'to' => $details['date'],
                            'coid' => $details['coid'],
                            'aid' => $d->aid
                        )
                );
                $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                $aid = "<a class='use-ajax' href='" . $history . "' >" . $d->aid . "</a>";
            } else {
                $aid = $d->aid;
            }

            $row['id'] = $d->id;
            $row['count'] = $d->count;
            $row['aid'] = $d->aid;
            $row['aname'] = $aid . " - " . $account_list[$d->coid][$d->aid];
            $row['account_description'] = $d->aid . " " . $account_list[$d->coid][$d->aid];
            $row['coid'] = $d->coid;
            $row['exchange'] = $d->exchange;
            $row['value'] = $d->value;
            $row['currency'] = $d->currency;
            $row['source'] = $d->source;
            $row['reference'] = $d->reference;
            $row['type'] = $d->type;
            $row['reconcile'] = $d->reconcile;
            $row['date'] = $d->date;
            $row['trail'] = [
                'username' => $d->username,
                'time' => date('Y-m-d  g:i a', $d->timestamp),
                'action' => $d->action
            ];

            if ($d->type == 'debit') {
                if ($d->exchange == 0) {
                    $sum_d = $sum_d += $d->value;
                }
                if ($d->exchange == 1) {
                    $sum_d2 = $sum_d2 += $d->value;
                }
            }
            if ($d->type == 'credit') {
                if ($d->exchange == 0) {
                    $sum_c = $sum_c += $d->value;
                }
                if ($d->exchange == 1) {
                    $sum_c2 = $sum_c2 += $d->value;
                }
            }
            $transactions[] = $row;
        }

        return [
            'references' => $references,
            'transactions' => $transactions,
            'total_debit' => $sum_d,
            'total_debit_base' => $sum_d2,
            'total_credit' => $sum_c,
            'total_credit_base' => $sum_c2,
            'basecurrency' => $baseCurrency
        ];
    }

    /*
     * Collect and return data to be displayed in journal extractions
     *
     * @param $j
     * date1 = from date
     * date2 = to date
     * company = company / entity id
     * source = a transaction type (ie general, payroll, purchase, invoice, receipt)
     * edit = edit mode
     */

    public function display($j) {
        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $source = $j['source'];
        $edit = $j['edit'];
        $rows = array();
        $account_list = AidList::chartList($j['company']);
        if (isset($j['format'])) {
            $format = $j['format'];
        } else {
            $format = 'html';
        }

        /* Group the journal entry by main reference to transactions */
        $source = $j['source'] . '%';
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->distinct();
        $query->fields('j', ['reference', 'source']);
        $query->condition('date', $j['date1'], '>=')
                ->condition('date', $j['date2'], '<=')
                ->condition('coid', $j['company'])
                ->condition('source', $source, 'like');
        if ($j['source'] == 'expense') {
            //filter doule display of payroll expense
            $query->condition('source', 'expense payroll', '<>');
        }
        $query->orderBy('reference');
        $data = $query->execute();


        while ($line = $data->fetchObject()) {

            /* Group the reference by date */
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->distinct();
            $query->fields('j', ['date']);
            $query->condition('source', $source, 'like')
                    ->condition('reference', $line->reference)
                    ->condition('coid', $j['company']);
            $query->orderBy('date');
            $d = $query->execute();

            $row = array();

            while ($date = $d->fetchObject()) {
                $sum_d = 0; //sum base currency
                $sum_c = 0;
                $sum_d2 = 0; //sum currency
                $sum_c2 = 0;

                $ref = self::reference($line->source, $line->reference);

                $row['source'] = $line->source;
                $row['reference'] = $line->reference;
                $row['reference_detail'] = $ref[0];
                $row['currency'] = $ref[1];
                $row['date'] = $date->date;

                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j');
                $query->fields('j', ['id', 'count', 'aid', 'exchange', 'coid', 'type', 'value', 'reconcile', 'source', 'reference']);
                $query->leftJoin('ek_journal_trail', 't', 't.jid = j.id');

                $query->fields('t', ['username', 'action', 'timestamp']);
                $query->condition('reference', $line->reference)
                        ->condition('source', $source, 'like')
                        ->condition('date', $date->date)
                        ->condition('coid', $j['company']);
                $query->orderBy('id');
                $e = $query->execute();

                $transactions = array();

                while ($entry = $e->fetchObject()) {
                    $aname = $account_list[$j['company']][$entry->aid];
                    if ($entry->exchange == 0 && $format == 'html') {
                        //build an history link
                        $param = serialize(
                                array(
                                    'id' => 'journal',
                                    'from' => $j['date1'],
                                    'to' => $j['date2'],
                                    'coid' => $j['company'],
                                    'aid' => $entry->aid
                                )
                        );
                        $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                        $aid = "<a class='use-ajax' href='" . $history . "' >" . $entry->aid . "</a>";
                    } else {
                        $aid = $entry->aid;
                    }
                    $transaction['id'] = $entry->id;
                    $transaction['count'] = $entry->count;
                    $transaction['aname'] = $aid . "  " . $aname;
                    $transaction['account_description'] = $entry->aid  . "  " . $aname;
                    $transaction['exchange'] = $entry->exchange;
                    $transaction['type'] = $entry->type;
                    if ($entry->type == 'debit') {
                        if ($entry->exchange == 0) {
                            $sum_d = $sum_d += $entry->value;
                        }
                        if ($entry->exchange == 1) {
                            $sum_d2 = $sum_d2 += $entry->value;
                        }
                    }
                    if ($entry->type == 'credit') {
                        if ($entry->exchange == 0) {
                            $sum_c = $sum_c += $entry->value;
                        }
                        if ($entry->exchange == 1) {
                            $sum_c2 = $sum_c2 += $entry->value;
                        }
                    }
                    $transaction['value'] = $entry->value;
                    /**/
                    $transaction['trail'] = [
                        'username' => $entry->username,
                        'time' => date('Y-m-d  g:i a', $entry->timestamp),
                        'action' => $entry->action];

                    $transactions[] = $transaction;
                }//loop transactions

                $row['transactions'] = $transactions;
                $row['total_debit'] = $sum_d;
                $row['total_debit_base'] = $sum_d2;
                $row['total_credit'] = $sum_c;
                $row['total_credit_base'] = $sum_c2;
                $row['basecurrency'] = $baseCurrency;
            }  //date


            $rows[] = $row;
        }//lines

        return $rows;
    }

    /*
     * Pull detail of reference to a journal entry
     *
     * @param source = a transaction type (ie general, payroll, purchase, invoice, receipt)
     * @param reference = a table id entry for the source
     */

    public static function reference($source, $reference) {
        switch ($source) {

            case 'expense':
            case 'expense amortization':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_expenses', 't');
                $query->fields('t', ['comment', 'currency']);
                $query->condition('id', $reference, '=');
                $data = $query->execute()->fetchObject();
                $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 1, 'id' => $reference])->toString();
                $voucher = '<a href="' . $url . '" target="_blank"  title="' . t('voucher')
                        . ' - ' . $reference . '">' . $data->comment . '</a>';
                $comment = ['#markup' => $voucher];
                $currency = $data->currency;

                break;
            case 'invoice':
            case 'invoice cn':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_invoice', 't');
                $query->fields('t', ['client', 'currency']);
                $query->condition('id', $reference, '=');
                $data = $query->execute()->fetchObject();
                $name = \Drupal\ek_address_book\AddressBookData::getname($data->client);
                $option = ['target' => 'blank', 'title' => $name, 'name' => $name];
                if ($source == 'invoice cn') {
                    $option['string'] = t('Credit note');
                }
                $comment = \Drupal\ek_sales\SalesData::DocumentHtml('invoice', $reference, $option);
                $currency = $data->currency;

                break;
            case 'pos sale':
                $comment = t('POS sale');
                $currency = "";
                break;
            case 'general memo':
                $query = "SELECT serial,mission from {ek_expenses_memo} WHERE id=:id";
                $d = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchObject();
                $comment = $d->serial . '/ ' . $d->mission;
                $query = "SELECT currency from {ek_expenses_memo} WHERE id=:id";
                $currency = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                break;

            case 'purchase':
            case 'payment':
            case 'purchase dn':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase', 't');
                $query->fields('t', ['client', 'currency']);
                $query->condition('id', $reference, '=');
                $data = $query->execute()->fetchObject();
                $name = \Drupal\ek_address_book\AddressBookData::getname($data->client);
                $option = ['target' => 'blank', 'title' => $name, 'name' => $name];
                if ($source == 'purchase cn') {
                    $option['string'] = t('Debit note');
                }
                $comment = \Drupal\ek_sales\SalesData::DocumentHtml('purchase', $reference, $option);
                $currency = $data->currency;
                break;
            case 'receipt':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_invoice', 't');
                $query->fields('t', ['client', 'currency']);
                $query->condition('id', $reference, '=');
                $data = $query->execute()->fetchObject();
                $name = \Drupal\ek_address_book\AddressBookData::getname($data->client);
                $option = ['target' => 'blank', 'title' => $name, 'name' => $name];
                $comment = \Drupal\ek_sales\SalesData::DocumentHtml('invoice', $reference, $option);
                $currency = $data->currency;
                break;
            case 'receipt pos':
                $comment = t('POS receipt');
                $currency = "";
                break;
            case 'payroll':
            case 'expense payroll':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_expenses', 't');
                $query->fields('t', ['comment', 'currency']);
                $query->condition('id', $reference, '=');
                $data = $query->execute()->fetchObject();
                $url = Url::fromRoute('ek_finance_voucher.pdf', ['type' => 1, 'id' => $reference])->toString();
                $voucher = '<a href="' . $url . '" target="_blank"  title="' . t('voucher')
                        . ' - ' . $reference . '">' . $data->comment . '</a>';
                $comment = ['#markup' => $voucher];
                $currency = $data->currency;

                break;
            case 'general':

                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 't');
                $query->fields('t', ['id', 'comment', 'currency']);
                $query->condition('reference', $reference, '=');
                $query->condition('source', 'general', '=');
                $data = $query->execute()->fetchObject();
                //$url = Url::fromRoute('ek_finance.extract.general_journal', [],['query' => ['jid' => $data->id]])->toString();
                //$voucher = '<a href="' . $url . '" target="_blank"  title="">' . $data->comment . '</a>';
                //$comment = ['#markup' => $voucher];
                $comment = $data->comment;
                $currency = $data->currency;
                break;

            case 'general cash':
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 't');
                $query->fields('t', ['comment', 'currency']);
                $query->condition('reference', $reference, '=');
                $query->condition('source', 'general cash', '=');
                $data = $query->execute()->fetchObject();

                $comment = $data->comment;
                $currency = $data->currency;
                break;

            default:
                $comment = '';
                $currency = '';
        }

        return array($comment, $currency);
    }

    /*
     * return data by aid to build ledger
     * @param array $l
     *     from aid: aid1
     *     to aid: aid2
     *     from date: date1
     *     to date: date2
     *     company id: coid
     */

    public function ledger($l) {
        
        // determine if query cover closed years, before current fiscal year
        $dates = self::getFiscalDates($l['coid'], date('Y', strtotime($l['date2'])), date('m', strtotime($l['date2'])));
        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
        // data holder
        $data = array();
        $data['baseCurrency'] = $baseCurrency;
        $data['ledger']['accounts'] = array();
        $data['fiscal_start'] = $dates['fiscal_start'];
        $data['fiscal_end'] = $dates['fiscal_end'];

        if (($l['date2'] >= $dates['from'] && $l['date1'] < $dates['from']) || ($dates['month_settings'] == '12' && (date('Y', strtotime($l['date2'])) <> date('Y', strtotime($l['date1']))))
        ) {
            //mixed query
            //return message to select different range
            $data['archive'] = 2;
            return $data;
        } elseif ($l['date2'] < $dates['fiscal_start']) {
            // look into archives
            $ek_accounts = "ek_accounts_" . date('Y', strtotime($l['date2'])) . '_' . $l['coid'];
            $ek_journal = "ek_journal_" . date('Y', strtotime($l['date2'])) . '_' . $l['coid'];
            $schema = Database::getConnection('external_db', 'external_db')->schema();
            // verify that the archive journal tables exists
            // in some cases with older data versions, posted table do not exist
            if (!$schema->tableExists($ek_journal)) {
                $ek_journal = "ek_journal";
            } else {
                $ek_journal = "ek_journal_" . date('Y', strtotime($l['date2'])) . '_' . $l['coid'];
            }

            // verify that the archive accounts tables exists
            // in some cases with older data versions, posted table do not exist
            if (!$schema->tableExists($ek_accounts)) {
                $ek_accounts = "ek_accounts";
            } else {
                $ek_accounts = "ek_accounts_" . date('Y', strtotime($l['date2'])) . '_' . $l['coid'];
            }
            $data['archive'] = true;
        } elseif ($l['date1'] >= $dates['from']) {
            // current year
            $ek_accounts = "ek_accounts";
            $ek_journal = "ek_journal";
            $data['archive'] = false;
        }

        // get array of all chart of accounts structure per coid
        // (to reduce queries call rates)
        $query = Database::getConnection('external_db', 'external_db')
                ->select($ek_accounts, 't');
        $query->fields('t', ['aid', 'aname']);
        $query->condition('coid', $l['coid'], '=');
        $chart = $query->execute()->fetchAllKeyed();


        // list the accounts in journal within the range selected
        $query = Database::getConnection('external_db', 'external_db')
                ->select($ek_accounts, 't');
        $query->fields('t', ['aid']);
        $query->distinct();
        $query->condition('coid', $l['coid'], '=');
        $query->condition('aid', $l['aid1'], '>=');
        $query->condition('aid', $l['aid2'], '<=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();


        while ($r = $result->fetchObject()) {
            $aname = $chart[$r->aid];
            $data['ref'] = ['aid' => $r->aid, 'aname' => $aname];
            // calculate opening balances and get range data per account and date selected
            // opening balance both currency and exchange
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($ek_accounts, 't');
            $query->fields('t', ['balance', 'balance_base', 'balance_date']);
            $query->condition('coid', $l['coid'], '=');
            $query->condition('aid', $r->aid, '=');
            $obj = $query->execute()->fetchObject();
            $system_balance_date = $obj->balance_date;


            // sum transaction currency - CREDIT
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($ek_journal, 'j');
            $query->addExpression('SUM(value)', 'sumValue');
            $query->condition('j.exchange', 0, '=')
                    ->condition('j.type', 'credit', '=')
                    ->condition('j.aid', $r->aid, '=')
                    ->condition('j.coid', $l['coid'], '=')
                    ->condition('j.date', $l['date1'], '<')
                    ->condition('j.date', $system_balance_date, '>');
            $Obj = $query->execute();
            $credit = $Obj->fetchObject()->sumValue;

            // sum transaction exchange - CREDIT
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($ek_journal, 'j');
            $query->addExpression('SUM(value)', 'sumValue');
            $query->condition('j.exchange', 1, '=')
                    ->condition('j.type', 'credit', '=')
                    ->condition('j.aid', $r->aid, '=')
                    ->condition('j.coid', $l['coid'], '=')
                    ->condition('j.date', $l['date1'], '<')
                    ->condition('j.date', $system_balance_date, '>');
            $Obj = $query->execute();
            $credit_exc = $Obj->fetchObject()->sumValue;

            // sum transaction currency - DEBIT
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($ek_journal, 'j');
            $query->addExpression('SUM(value)', 'sumValue');
            $query->condition('j.exchange', 0, '=')
                    ->condition('j.type', 'debit', '=')
                    ->condition('j.aid', $r->aid, '=')
                    ->condition('j.coid', $l['coid'], '=')
                    ->condition('j.date', $l['date1'], '<')
                    ->condition('j.date', $system_balance_date, '>');
            $Obj = $query->execute();
            $debit = $Obj->fetchObject()->sumValue;


            // sum transaction exchange - DEBIT
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($ek_journal, 'j');
            $query->addExpression('SUM(value)', 'sumValue');
            $query->condition('j.exchange', 1, '=')
                    ->condition('j.type', 'debit', '=')
                    ->condition('j.aid', $r->aid, '=')
                    ->condition('j.coid', $l['coid'], '=')
                    ->condition('j.date', $l['date1'], '<')
                    ->condition('j.date', $system_balance_date, '>');
            $Obj = $query->execute();
            $debit_exc = $Obj->fetchObject()->sumValue;


            // balance in local currency (opening + credit - debit during period preceding 'from date'
            $balance_open = $obj->balance + $credit - $debit;
            // balance in base currency (opening + credit & exchange - debit & exchange during period preceding 'from date'
            $balance_open_base = $obj->balance_base + ($credit + $credit_exc) - ($debit + $debit_exc);
            $sum_d_base = 0; //sum base currency
            $sum_c_base = 0;
            $sum_d_loc = 0; //sum currency
            $sum_c_loc = 0;
            $i = 0;


            // calculate transactions per account and range
            $query = Database::getConnection('external_db', 'external_db')
                    ->select($ek_journal, 'j');
            $query->fields('j', ['id', 'type', 'exchange', 'value', 'date', 'reference']);
            $query->condition('j.aid', $r->aid, '=')
                    ->condition('j.date', $l['date1'], '>=')
                    ->condition('j.date', $l['date2'], '<=')
                    ->condition('j.coid', $l['coid'], '=');
            $query->orderBy('date')->orderBy('reference')->orderBy('id');
            $ledger = $query->execute();

            $rows = array();

            while ($lg = $ledger->fetchObject()) {
                $i++;
                $rows['line'][$i] = self::journalEntryDetails($lg->id);

                //compile sums
                if ($lg->type == 'debit') {
                    if ($lg->exchange == 0) {
                        $sum_d_loc = $sum_d_loc + $lg->value;
                    } else {
                        $sum_d_base = $sum_d_base + $lg->value;
                    }
                }

                if ($lg->type == 'credit') {
                    if ($lg->exchange == 0) {
                        $sum_c_loc = $sum_c_loc + $lg->value;
                    } else {
                        $sum_c_base = $sum_c_base + $lg->value;
                    }
                }
                //
            }

            $closing = $balance_open + $sum_c_loc - $sum_d_loc;
            $closing_exchange = $balance_open_base + ($sum_c_loc + $sum_c_base) - ($sum_d_loc + $sum_d_base);
            ($closing < 0) ? $acc = '-' : $acc = '+';


            $rows['line']['total'] = array(
                'aid' => $r->aid,
                'aname' => $aname,
                'balance_open' => round($balance_open, $rounding),
                'balance_open_base' => round($balance_open_base, $rounding),
                'sum_debit' => round($sum_d_loc, $rounding),
                'sum_debit_exchange' => round($sum_d_base, $rounding),
                'sum_credit' => round($sum_c_loc, $rounding),
                'sum_credit_exchange' => round($sum_c_base, $rounding),
                'closing' => abs(round($closing, $rounding)),
                'closing_exchange' => abs(round($closing_exchange, $rounding)),
                'account' => $acc,
            );

            $data['ledger']['accounts'][] = $rows;
        }
        
        return $data;
    }

    /*
     * return data by reference to build a ledger by client
     * @param array $l
     *     string from date: date1
     *     sting to date: date2
     *     int company id: coid
     *     array references: references (list of references ids)
     *     string source1 source2 source3: source
     *
     * @return array
     *     journal entries by reference with opening and closing values
     */

    public function salesledger($l) {

        //get array of all chart of accounts structure per coid
        //(to reduce queries call rates)
        $query = "SELECT aid,aname FROM {ek_accounts} WHERE coid=:coid";
        $chart = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':coid' => $l['coid']))
                ->fetchAllKeyed();

        //calculate opening value
        // sum transaction currency - CREDIT / Invoice , purchase
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.reference', $l['references'], 'IN')
                ->condition('j.exchange', 0, '=')
                ->condition('j.type', 'credit', '=')
                ->condition('j.source', $l['source1'], '=')
                ->condition('j.date', $l['date1'], '<')
                ->condition('j.coid', $l['coid'], '=');

        $Obj = $query->execute();
        $credit = $Obj->fetchObject()->sumValue;

        // sum transaction currency - DEBIT / receipt , payment
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.reference', $l['references'], 'IN')
                ->condition('j.exchange', 0, '=')
                ->condition('j.type', 'debit', '=')
                ->condition('j.date', $l['date1'], '<')
                ->condition('j.coid', $l['coid'], '=');

        $or_src = $query->orConditionGroup();
        $or_src->condition('j.source', $l['source3'], '='); // CN/DN
        $or_src->condition('j.source', $l['source2'], '='); // receipt/pay
        $query->condition($or_src);


        $Obj = $query->execute();
        $debit = $Obj->fetchObject()->sumValue;

        // sum transaction exchange - CREDIT / Invoice , purchase
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.reference', $l['references'], 'IN')
                ->condition('j.exchange', 1, '=')
                ->condition('j.type', 'credit', '=')
                ->condition('j.source', $l['source1'], '=')
                ->condition('j.date', $l['date1'], '<')
                ->condition('j.coid', $l['coid'], '=');


        $Obj = $query->execute();
        $credit_exc = $Obj->fetchObject()->sumValue;

        // sum transaction exchange - DEBIT / receipt - payment
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.reference', $l['references'], 'IN')
                ->condition('j.exchange', 1, '=')
                ->condition('j.type', 'debit', '=')
                //->condition('j.source' , $l['source2'], '=')
                ->condition('j.date', $l['date1'], '<')
                ->condition('j.coid', $l['coid'], '=');
        $query->condition($or_src);

        $Obj = $query->execute();
        $debit_exc = $Obj->fetchObject()->sumValue;

        // balance in local currency (opening + credit - debit during period preceding 'from date'
        $balance_open = $credit - $debit;
        // balance in base currency (opening + credit & exchange - debit & exchange during period preceding 'from date'
        $balance_open_base = ($credit + $credit_exc) - ($debit + $debit_exc);
        $sum_d_base = 0; //sum base currency
        $sum_c_base = 0;
        $sum_d_loc = 0; //sum currency
        $sum_c_loc = 0;
        $i = 0;


        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');

        //list the accounts in journal within the range selected
        $or = $query->orConditionGroup();
        $or->condition('source', $l['source1'], '=');
        $or->condition('source', $l['source2'], '=');
        $or->condition('source', $l['source3'], '=');
        $result = $query->fields('j')
                ->condition('j.reference', $l['references'], 'IN')
                ->condition($or)
                ->condition('j.date', $l['date1'], '>=')
                ->condition('j.date', $l['date2'], '<=')
                ->condition('j.coid', $l['coid'], '=')
                ->execute();

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
        //data holder
        $data = array();
        $data['baseCurrency'] = $baseCurrency;
        $data['ledger'] = array();
        $rows = array();

        while ($r = $result->fetchObject()) {
            //compile selected range values
            $i++;
            if (($r->type == 'debit' && ($r->source == 'receipt' || $r->source == 'payment')) ||
                    ($r->type == 'credit' && ($r->source == 'invoice' || $r->source == 'purchase')) ||
                    ($r->type == 'debit' && $r->source == 'invoice cn') ||
                    ($r->type == 'debit' && $r->source == 'purchase dn')) {
                $rows['line'][$i] = self::journalEntryDetails($r->id);
            }
            //compile sums
            if ($r->type == 'debit' && ($r->source == 'receipt' || $r->source == 'payment' || $r->source == 'invoice cn' || $r->source == 'purchase dn')) {
                if ($r->exchange == 0) {
                    $sum_d_loc = $sum_d_loc + $r->value;
                } else {
                    $sum_d_base = $sum_d_base + $r->value;
                }
            }


            if ($r->type == 'credit' && ($r->source == 'invoice' || $r->source == 'purchase')) {
                if ($r->exchange == 0) {
                    $sum_c_loc = $sum_c_loc + $r->value;
                } else {
                    $sum_c_base = $sum_c_base + $r->value;
                }
            }
        }
        //
        $closing = $balance_open + $sum_c_loc - $sum_d_loc;
        $closing_exchange = $balance_open_base + ($sum_c_loc + $sum_c_base) - ($sum_d_loc + $sum_d_base);
        ($closing < 0) ? $acc = '-' : $acc = '+';


        $rows['line']['total'] = array(
            'balance_open' => round($balance_open, $rounding),
            'balance_open_base' => round($balance_open_base, $rounding),
            'sum_debit' => round($sum_d_loc, $rounding),
            'sum_debit_exchange' => round($sum_d_base, $rounding),
            'sum_credit' => round($sum_c_loc, $rounding),
            'sum_credit_exchange' => round($sum_c_base, $rounding),
            'closing' => abs(round($closing, $rounding)),
            'closing_exchange' => abs(round($closing_exchange, $rounding)),
            'account' => $acc,
        );

        $data['ledger'][] = $rows;

        return $data;
    }

    /*
     * return data to build Trial balance
     * @parameters
     *     $year
     *     $month
     *     int $coid
     *     bolean $active : 0,1 to show active accounts
     *     bolean $null : 0,1 to show acount without transaction
     *      bolean $option : 1 = link history , 0 no link
     */

    public function trial($t, $option = 1) {
        if ($t['active'] == 0) {
            $t['active'] = '%';
        }

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 't');
        $list = $query->fields('t')
                ->condition('atype', 'detail', '=')
                ->condition('astatus', $t['active'], 'like')
                ->condition('coid', $t['coid'], '=')
                ->orderBy('aid', 'ASC')
                ->execute();

        $data = [];
        $total_td = 0;
        $total_tc = 0;
        $total_td_base = 0;
        $total_tc_base = 0;
        $total_ytdd = 0;
        $total_ytdc = 0;
        $total_ytdd_base = 0;
        $total_ytdc_base = 0;
        $total_net = 0;
        $total_net_base = 0;
        $settings = new FinanceSettings();
        $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
        $dates = self::getFiscalDates($t['coid'], $t['year'], $t['month']);
        $ytd = $dates['fiscal_start'];
        $d1 = $t['year'] . '-' . $t['month'] . '-01';
        $d2 = $t['year'] . '-' . $t['month'] . '-' . cal_days_in_month(CAL_GREGORIAN, $t['month'], $t['year']);
        $data['baseCurrency'] = $settings->get('baseCurrency');
        $data['coid'] = $t['coid'];
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_company', 't');
        $query->fields('t', ['name']);
        $query->condition('id', $t['coid']);
        $company = $query->execute()->fetchField();

        $data['company'] = $company;
        $data['year'] = $t['year'];
        $data['month'] = $t['month'];
        $data['transactions'] = [];

        while ($l = $list->fetchObject()) {
            $row = [];

            $row['aname'] = $l->aname;
            $row['active'] = $l->astatus;

            // build an history link
            $param = serialize(
                    [
                        'id' => 'trial',
                        'from' => $ytd ,
                        'to' => $d2,
                        'coid' => $t['coid'],
                        'aid' => $l->aid
                    ]
            );
            if ($option == 1) {
                $history = Url::fromRoute('ek_finance_modal', ['param' => $param], [])->toString();
                $row['aid'] = "<a class='use-ajax' href='" . $history . "' >" . $l->aid . "</a>";
            } else {
                $row['aid'] = $l->aid;
            }

            $row['open'] = round($l->balance,2);
            $row['open_base'] = round($l->balance_base,2);
            $c = unserialize(self::history($param));
            $row['closing'] = round($c['closing'],2);
            $row['closing_base'] = round($c['closing_exchange'],2);
            $row['transaction_debit'] = self::transactions(
                            [
                                'aid' => $l->aid,
                                'type' => 'debit',
                                'coid' => $t['coid'],
                                'from' => $d1,
                                'to' => $d2
                            ]
            );
            $row['transaction_credit'] = self::transactions(
                            [
                                'aid' => $l->aid,
                                'type' => 'credit',
                                'coid' => $t['coid'],
                                'from' => $d1,
                                'to' => $d2
                            ]
            );

            $row['transaction_ytd_debit'] = self::transactions(
                            [
                                'aid' => $l->aid,
                                'type' => 'debit',
                                'coid' => $t['coid'],
                                'from' => $ytd,
                                'to' => $d2
                            ]
            );

            $row['transaction_ytd_credit'] = self::transactions(
                            [
                                'aid' => $l->aid,
                                'type' => 'credit',
                                'coid' => $t['coid'],
                                'from' => $ytd,
                                'to' => $d2
                            ]
            );

            if (
                    ($t['null'] == 1 && ($row['transaction_ytd_debit'][0] != 0 || $row['transaction_ytd_credit'][0] != 0 || $row['transaction_ytd_debit'][1] != 0 || $row['transaction_ytd_credit'][1] != 0)) || $t['null'] == 0
            ) {
                // show
                $data['transactions'][] = $row;

                $total_td += $row['transaction_debit'][0];
                $total_tc += $row['transaction_credit'][0];
                $total_td_base += $row['transaction_debit'][1];
                $total_tc_base += $row['transaction_credit'][1];
                $total_ytdd += $row['transaction_ytd_debit'][0];
                $total_ytdc += $row['transaction_ytd_credit'][0];
                $total_ytdd_base += $row['transaction_ytd_debit'][1];
                $total_ytdc_base += $row['transaction_ytd_credit'][1];
                $total_net += $row['transaction_credit'][0] - $row['transaction_debit'][0];
                $total_net_base += $row['transaction_credit'][1] - $row['transaction_debit'][1];
            }
        } 

        if (abs(round($total_td, $rounding) - round($total_tc, $rounding)) > 0) {
            $error1 = 1;
        } else {
            $error1 = 0;
        }

        if (abs(round($total_ytdd, $rounding) - round($total_ytdc, $rounding)) > 0) {
            $error2 = 1;
        } else {
            $error2 = 0;
        }

        $data['total'] = array(
            'transaction_debit' => $total_td,
            'transaction_credit' => $total_tc,
            'transaction_debit_base' => $total_td_base,
            'transaction_credit_base' => $total_tc_base,
            'transaction_ytd_debit' => $total_ytdd,
            'transaction_ytd_credit' => $total_ytdc,
            'transaction_ytd_debit_base' => $total_ytdd_base,
            'transaction_ytd_credit_base' => $total_ytdc_base,
            'total_net' => $total_net,
            'total_net_base' => $total_net_base,
            'error1' => $error1,
            'error2' => $error2,
        );

        return $data;
    }

    /*
     * calculate the opening value of an account
     * $journal->opening(
      array(
      'aid'=> "62100",
      'coid'=> $coid,
      'from'=> $date , //opening date not included in calculation for balance on 15/06 request 16/06...
      'archive' => TRUE/FALSE
      )
      );
     */

    public function opening($data) {
        $account = $data['aid'];
        $coid = $data['coid'];
        $d1 = $data['from'];

        if (isset($data['archive']) && $data['archive'] == true) {
            //extract data from archive tables
            $year = date('Y', strtotime($data['from'] . ' - 1 day'));
            $table_accounts = "ek_accounts_" . $year . "_" . $coid;
            $table_journal = "ek_journal_" . $year . "_" . $coid;
        } else {
            $table_accounts = "ek_accounts";
            $table_journal = "ek_journal";
        }

        $query = Database::getConnection('external_db', 'external_db')
                ->select($table_accounts, 't');
        $data = $query->fields('t')
                ->condition('aid', $account, '=')
                ->condition('coid', $coid, '=')
                ->orderBy('aid', 'ASC')
                ->execute();
        $result = $data->fetchObject();

        //get to total transaction up to d1
        // sum transaction currency

        $credit = self::sumAccount($table_journal, 0, 'credit', $account, $coid, $result->balance_date, $d1);

        // sum transaction currency
        $debit = self::sumAccount($table_journal, 0, 'debit', $account, $coid, $result->balance_date, $d1);

        // sum transaction exchange
        $credit_exc = self::sumAccount($table_journal, 1, 'credit', $account, $coid, $result->balance_date, $d1);

        // sum transaction exchange
        $debit_exc = self::sumAccount($table_journal, 1, 'debit', $account, $coid, $result->balance_date, $d1);

        //calculate value in local currency
        $balance = $result->balance + $credit - $debit;
        //calculate value in base currency
        $balance_base = $result->balance_base + ($credit + $credit_exc) - ($debit + $debit_exc);

        return array($balance, $balance_base);
    }

    /*
     * Calculate the current year earnings
     * used in B. Sheet
     * Post new year
     * @param int $coid company id
     * @param string $from date YY-mm-dd
     * @param string $to date YY-mm-dd
     * @return array value, value with exchange
     */

    public function current_earning($coid, $from, $to) {
        $settings = new FinanceSettings();
        $chart = $settings->get('chart');

        // REVENUE - class //
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 't');
        $query->fields('t', ['aid', 'aname']);
        $condition = $query->orConditionGroup()
                ->condition('aid', $chart['income'] . '%', 'like')
                ->condition('aid', $chart['other_income'] . '%', 'like');
        $query->condition($condition);
        $query->condition('astatus', '1', '=');
        $query->condition('atype', 'class', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        $total_class4_multi = 0;   // was: $total_class4_l  (local/multi)
        $total_class4_base  = 0;   // was: $total_class4    (base/exchange)

        while ($r = $result->fetchAssoc()) {
            $aid = substr($r['aid'], 0, 2);
            $total_detail_multi = 0;   // was: $total_detail_l
            $total_detail_base  = 0;   // was: $total_detail

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 't');
            $query->fields('t', ['aid', 'aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('astatus', '1', '=');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result3 = $query->execute();

            while ($r3 = $result3->fetchAssoc()) {
                $d = self::transactions([
                    'aid'  => $r3['aid'],
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to'   => $to,
                ]);
                $c = self::transactions([
                    'aid'  => $r3['aid'],
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to'   => $to,
                ]);
                // transactions() returns [0] = multi/local, [1] = base/exchange
                $net_multi = $c[0] - $d[0];   // was: $b     accumulated into $total_detail_l
                $net_base  = $c[1] - $d[1];   // was: $b_exc accumulated into $total_detail

                $total_detail_multi += $net_multi;
                $total_detail_base  += $net_base;
            }

            $total_class4_multi += $total_detail_multi;
            $total_class4_base  += $total_detail_base;
        }

        // COS - class //
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 't');
        $query->fields('t', ['aid', 'aname']);
        $query->condition('aid', $chart['cos'] . '%', 'like');
        $query->condition('astatus', '1', '=');
        $query->condition('atype', 'class', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        $total_class5_multi = 0;   // was: $total_class5_l
        $total_class5_base  = 0;   // was: $total_class5

        while ($r = $result->fetchAssoc()) {
            $aid = substr($r['aid'], 0, 2);
            $total_detail_multi = 0;
            $total_detail_base  = 0;

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 't');
            $query->fields('t', ['aid', 'aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('astatus', '1', '=');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result3 = $query->execute();

            while ($r3 = $result3->fetchAssoc()) {
                $d = self::transactions([
                    'aid'  => $r3['aid'],
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to'   => $to,
                ]);
                $c = self::transactions([
                    'aid'  => $r3['aid'],
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to'   => $to,
                ]);
                $net_multi = $c[0] - $d[0];
                $net_base  = $c[1] - $d[1];

                $total_detail_multi += $net_multi;
                $total_detail_base  += $net_base;
            }

            $total_class5_multi += $total_detail_multi;
            $total_class5_base  += $total_detail_base;
        }

        // CHARGES - class //
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 't');
        $query->fields('t', ['aid', 'aname']);
        $condition = $query->orConditionGroup()
                ->condition('aid', $chart['expenses'] . '%', 'like')
                ->condition('aid', $chart['other_expenses'] . '%', 'like');
        $query->condition($condition);
        $query->condition('astatus', '1', '=');
        $query->condition('atype', 'class', '=');
        $query->condition('coid', $coid, '=');
        $query->orderBy('aid', 'ASC');
        $result = $query->execute();

        $total_class6_multi = 0;   // was: $total_class6_l
        $total_class6_base  = 0;   // was: $total_class6

        while ($r = $result->fetchAssoc()) {
            $aid = substr($r['aid'], 0, 2);
            $total_detail_multi = 0;
            $total_detail_base  = 0;

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 't');
            $query->fields('t', ['aid', 'aname']);
            $query->condition('aid', $aid . '%', 'like');
            $query->condition('astatus', '1', '=');
            $query->condition('atype', 'detail', '=');
            $query->condition('coid', $coid, '=');
            $query->orderBy('aid', 'ASC');
            $result3 = $query->execute();

            while ($r3 = $result3->fetchAssoc()) {
                $d = self::transactions([
                    'aid'  => $r3['aid'],
                    'type' => 'debit',
                    'coid' => $coid,
                    'from' => $from,
                    'to'   => $to,
                ]);
                $c = self::transactions([
                    'aid'  => $r3['aid'],
                    'type' => 'credit',
                    'coid' => $coid,
                    'from' => $from,
                    'to'   => $to,
                ]);
                $net_multi = $c[0] - $d[0];
                $net_base  = $c[1] - $d[1];

                $total_detail_multi += $net_multi;
                $total_detail_base  += $net_base;
            }

            $total_class6_multi += $total_detail_multi;
            $total_class6_base  += $total_detail_base;
        }

        // [0] = multi/local, [1] = base/exchange — consistent with opening() and transactions()
        return [
            $total_class4_multi + $total_class5_multi + $total_class6_multi,  // [0] multi
            $total_class4_base  + $total_class5_base  + $total_class6_base,   // [1] base
        ];
    }

    /**
     * History per account
     * @param aid = account id
     * @param coid = the id of the company
     * @param from = from date
     * @param to = to date
     * @param source = record source type, default is wildcard %
     * @return serialize array with history figures : total_debit, total_credit, total_transaction
     * closing,total_debit_exchange, total_credit_exchange, total_transaction_exchange
     * closing_exchange
     */

    public function history($param) {
        $param = unserialize($param);
        if (!isset($param['source'])) {
            $param['source'] = '%';
        };
        if (!isset($param['reference'])) {
            $param['reference'] = '%';
        };
        $query = "SELECT * FROM {ek_journal} WHERE aid=:aid AND coid=:coid AND source like :s "
                . "AND reference like :r AND date>=:date1 AND date<=:date2 ORDER by date";

        $a = array(
            'aid' => $param['aid'],
            ':s' => $param['source'],
            ':r' => $param['reference'],
            ':coid' => $param['coid'],
            ':date1' => $param['from'],
            ':date2' => $param['to'],
        );
        $result = Database::getConnection('external_db', 'external_db')
                ->query($query, $a);

        $data = array();
        $settings = new FinanceSettings();
        $data['baseCurrency'] = $settings->get('baseCurrency');
        $totaldb = 0;
        $totalcr = 0;
        $totaldb_exc = 0;
        $totalcr_exc = 0;
        $open = self::opening(array('aid' => $param['aid'], 'coid' => $param['coid'], 'from' => $param['from']));
        $data['opening'] = $open[0];
        $data['opening_exchange'] = $open[1];
        $data['transaction'] = array();

        while ($r = $result->fetchObject()) {
            if ($r->source != 'general') {
                $ref = self::reference($r->source, $r->reference);
            } else {
                $ref = array($r->comment, $r->currency);
            }

            if ($r->type == 'debit') {
                if ($r->exchange == 0) {
                    $totaldb += $r->value;
                }
                if ($r->exchange == 1) {
                    $totaldb_exc += $r->value;
                }
            } else {
                if ($r->exchange == 0) {
                    $totalcr += $r->value;
                }
                if ($r->exchange == 1) {
                    $totalcr_exc += $r->value;
                }
            }
            $data['transaction'][] = array(
                'id' => $r->id,
                'count' => $r->count,
                'date' => $r->date,
                'aid' => $r->aid,
                'reference' => $ref[0],
                'type' => $r->type,
                'value' => $r->value,
                'currency' => $r->currency,
                'exchange' => $r->exchange,
            );
        }
        $data['total_debit'] = $totaldb;
        $data['total_credit'] = $totalcr;
        $data['total_transaction'] = $totalcr - $totaldb;
        $data['closing'] = $open[0] + $totalcr - $totaldb;
        $data['total_debit_exchange'] = $totaldb + $totaldb_exc;
        $data['total_credit_exchange'] = $totalcr + $totalcr_exc;
        $data['total_transaction_exchange'] = $totalcr + $totalcr_exc - $totaldb - $totaldb_exc;
        $data['closing_exchange'] = $open[1] + $totalcr - $totaldb + $totalcr_exc - $totaldb_exc;

        return serialize($data);
    }

    /*
     * Return an entity history in array format or read friendly format
     * used in invoice list
     * @param string entity
     *  a db entry , i.e. invoice
     * @param int
     *  id = the id of the entry
     * @param string format
     *  return format, default html or raw
     * @return string
     *  html table format
     * @return array
     */

    public function entity_history($param) {
        if (!isset($param['format'])) {
            $format = 'html';
        } else {
            $format = 'raw';
        }
        switch ($param['entity']) {

            case 'invoice':

                $query = 'SELECT * FROM {ek_journal} WHERE reference = :r AND (source = :s1 OR source = :s2) ORDER by id';
                $a = array(':r' => $param['id'], ':s1' => 'invoice', ':s2' => 'receipt');
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a);

                if ($format == 'html') {
                    $return = '<table><tbody>'
                            . '<tr><td>' . t('Journal') . '</td><td></td><td>' . t('Invoice') . '</td><td>' . t('Credit') . '</td>'
                            . '</tr>';
                } else {
                    $return = [];
                }
                
                $local = [];
                $exchange = [];
                while ($d = $data->fetchObject()) {
                    if ($format == 'html') {
                        if ($d->source == 'invoice' && $d->type == 'credit' && $d->exchange == '0') {
                            $return .= '<tr><td>' . $d->date . '</td><td>' . $d->aid . '</td>'
                                    . '<td>' . $d->currency . ' ' . $d->value . '</td><td></td></tr>';
                        }
                        if ($d->source == 'receipt' && $d->type == 'debit' && $d->exchange == '0') {
                            $return .= '<tr><td>' . $d->date . '</td><td>' . $d->aid . '</td>'
                                    . '<td></td><td>' . $d->currency . ' ' . $d->value . '</td></tr>';
                        }
                    } else {
                        $return[] = self::journalEntryDetails($d->id);
                    }
                }


                break;

            case 'purchase':

                $query = 'SELECT * FROM {ek_journal} WHERE reference = :r AND (source = :s1 OR source = :s2) ORDER by id';
                $a = array(':r' => $param['id'], ':s1' => 'purchase', ':s2' => 'payment');
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, $a);
                if ($format == 'html') {
                    $return = '<table><tbody>'
                            . '<tr><td>' . t('Journal') . '</td><td></td>'
                            . '<td>' . t('Purchase') . '</td><td>' . t('Credit') . '</td>'
                            . '</tr>';
                } else {
                    $return = [];
                }
                
                $local = [];
                $exchange = [];
                while ($d = $data->fetchObject()) {
                    if ($format == 'html') {
                        if ($d->source == 'purchase' && $d->type == 'credit' && $d->exchange == '0') {
                            $return .= '<tr><td>' . $d->date . '</td><td>' . $d->aid . '</td>'
                                    . '<td>' . $d->currency . ' ' . $d->value . '</td><td></td></tr>';
                            $local[0] += $d->value;
                        }
                        if ($d->source == 'purchase' && $d->type == 'credit' && $d->exchange == '1' && $d->comment != 'currency exchange loss') {
                            $exchange[0] += $d->value;
                        }
                        
                        if ($d->source == 'payment' && $d->type == 'debit' && $d->exchange == '0') {
                            $return .= '<tr><td>' . $d->date . '</td><td>' . $d->aid . '</td>'
                                    . '<td></td><td>' . $d->currency . ' ' . $d->value . '</td></tr>';
                            $local[1] += $d->value;
                        }
                        if ($d->source == 'payment' && $d->type == 'debit' && $d->exchange == '1' && $d->comment != 'currency exchange loss') {
                            $exchange[1] += $d->value;
                            
                        }
                        
                        
                    } else {
                        $return[] = self::journalEntryDetails($d->id);
                    }
                }
                /*$return .= '<tr><td>' . t('Exchange rate') . '</td> <td></td>'
                                    . '<td></td><td>' . round($local[0]/($local[0] + $exchange[0]),4) . '</td></tr>';*/

                break;
        }
        if ($format == 'html') {
            $return .= '</tbody></table>';
        }

        return $return;
    }
    
    /*
     * Get journal entries lines that are not reconciled by company, date and account
     * @param int $coid
     *  the company ID
     * 
     * @param int $accunt 
     *  the journal account
     * 
     * @param string $data
     *  the recociliation date
     * 
     * @return array of journal entries
     */
    public function getUnreconciledLines($coid, $account, $date) {
        $currencies      = CurrencyData::listcurrency(1);
        $companysettings = new CompanySettings($coid);
        $account_currency = NULL;

        foreach ($currencies as $key => $value) {
        $cash1 = $companysettings->get('cash_account', $key);
        $cash2 = $companysettings->get('cash2_account', $key);
        if ($cash1 == $account || $cash2 == $account) { $account_currency = $key; }
        if (BankData::currencyByaid($coid, $account) == $key)  { $account_currency = $key; }
        }

        $query = Database::getConnection('external_db', 'external_db')
        ->select('ek_journal', 'j');
        $query->fields('j');
        $query->condition('coid', $coid, '=');
        $query->condition('aid', $account, '=');
        $query->condition('date', $date, '<=');
        $query->condition('reconcile', '0', '=');
        $query->condition('exchange', '0', '=');
        $query->orderBy('date', 'ASC');
        $result = $query->execute();

        $journalLines = [];
        while ($r = $result->fetchObject()) {
            $j = self::journalEntryDetails($r->id);

            // Apply exchange correction before passing values to AI.
            if ($account_currency && ($account_currency != $j['currency'])) {
                $queryEx = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'jr');
                $queryEx->fields('jr', ['value']);
                $queryEx->condition('coid', $j['coid'], '=');
                $queryEx->condition('aid', $j['aid'], '=');
                $queryEx->condition('date', $j['date'], '=');
                $queryEx->condition('type', $j['type'], '=');
                $queryEx->condition('source', $j['source'], '=');
                $queryEx->condition('reference', $j['reference'], '=');
                $queryEx->condition('exchange', 1, '=');
                $exRow = $queryEx->execute()->fetchObject();
                if ($exRow) {
                $j['value'] = $j['value'] + $exRow->value;
                }
            }

            $comment = is_array($j['comment']) ? $j['comment']['#markup'] : $j['comment'];

            $journalLines[] = [
                'id'        => (int) $r->id,
                'date'      => $j['date'],
                'type'      => $j['type'],
                'value'     => round((float) $j['value'], $this->rounding),
                'reference' => $j['reference'],
                'comment'   => substr(strip_tags($comment), 0, 80),
            ];

        }
        
        return $journalLines;
    }

    /*
     * Delete journal rows
     * @param source = entry source
     * @param reference = the source reference
     * @param coid = company id
     * @return array of deleted ids
     */

    public function delete($source = null, $reference = null, $coid = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->fields('j', ['id']);
        $query->condition('source', $source, 'like');
        $query->condition('reference', $reference, '=');
        $query->condition('coid', $coid);
        $query->orderBy('id', 'ASC');
        $Obj = $query->execute();
        $i = 0;
        $journalId = [];
        while ($r = $Obj->fetchObject()) {
            $i++;
            $journalId[$i] = $r->id;
            Database::getConnection('external_db', 'external_db')
                    ->delete('ek_journal_trail')
                    ->condition('jid', $r->id)
                    ->execute();

            Database::getConnection('external_db', 'external_db')
                    ->delete('ek_journal')
                    ->condition('id', $r->id)
                    ->execute();
        }

        return $journalId;
    }

    /*
     * Reset count row per company (after delete)
     * @param coid = company id
     * @param id = the journal id deleted
     * @return
     */

    public function resetCount($coid = null, $id = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('Count(id)', 'count');
        $query->condition('coid', $coid, '=');
        $query->condition('id', $id, '<');

        $Obj = $query->execute();
        $count = $Obj->fetchObject()->count;

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->fields('j', ['id']);
        $query->condition('coid', $coid, '=');
        $query->condition('id', $id, '>');
        $query->orderBy('id', 'ASC');
        $Obj = $query->execute();
        while ($j = $Obj->fetchObject()) {
            $count++;
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_journal')
                    ->condition('id', $j->id)
                    ->fields(['count' => $count])
                    ->execute();
        }

        $return = [];
    }

    /*
     * Audit currency gain or loss records
     * ex. use /finance/audit/currency/i
     */

    public function audit_currency($param) {
        $companies = \Drupal\ek_admin\Access\AccessCheck::CompanyList();
        $items = [];

        if ($param == 'i') {
            $src = 'invoice';
            $acc = 'asset_account';
            $rec = 'receipt';
            $type = 'credit';
            $items = ['title' => t('Currency gains and loss with sales')];
        } elseif ($param == 'p') {
            $src = 'purchase';
            $acc = 'liability_account';
            $rec = 'payment';
            $type = 'debit';
            $items = ['title' => t('Currency gains and loss with purchases')];
        } else {
            return [];
        }
        $items['companies'] = [];
        foreach ($companies as $coid => $name) {
            $total = 0;
            $settings = new \Drupal\ek_finance\FinanceSettings();
            $baseCurrency = $settings->get('baseCurrency');
            $rounding = (!null == $settings->get('rounding')) ? $settings->get('rounding') : 2;
            $company = new \Drupal\ek_admin\CompanySettings($coid);
            $y = $company->get('fiscal_year') - 1;
            $dl = new DateTime($y . '-' . $company->get('fiscal_month') . '-01');
            $from = $dl->format('Y-m-t');
            $aid = $company->get('CurrencyGainLoss');

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j')
                    ->fields('j')
                    ->condition('date', $from, '>')
                    ->condition('aid', $aid, '=')
                    ->condition('coid', $coid, '=')
                    ->condition('source', $rec, '=')
                    ->condition('exchange', '1', '=');
            $data = $query->execute();

            while ($d = $data->fetchObject()) {

                //get invoice/purchase rate
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j')
                        ->fields('j')
                        ->condition('coid', $coid, '=')
                        ->condition('source', $src, '=')
                        ->condition('reference', $d->reference, '=')
                        ->condition('type', 'credit', '=');
                $doc = $query->execute();
                $value = 0;
                $base = 0;
                $currency = '';
                while ($s = $doc->fetchObject()) {
                    if ($s->exchange == 0) {
                        $value = $value + $s->value;
                        $currency = $s->currency;
                    }
                    $base = $base + $s->value;
                }

                $doc_rate = round($value / $base, $rounding);

                $receipt_account = $company->get($acc, $currency);
                //check receipt rate
                $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_journal', 'j')
                        ->fields('j')
                        ->condition('reference', $d->reference, '=')
                        ->condition('coid', $coid, '=')
                        ->condition('source', $rec, '=')
                        ->condition('type', $type, '=')
                        ->condition('aid', $receipt_account, '=')
                        ->isNull('comment');
                $receipt = $query->execute();
                $value = 0;
                $base = 0;

                while ($r = $receipt->fetchObject()) {
                    if ($r->exchange == 0) {
                        $value = $value + $r->value;
                    }
                    $base = $base + $r->value;
                }

                $receipt_rate = round($value / $base, $rounding);

                $ok = '';
                if ($param == 'i') {
                    if ($receipt_rate > $doc_rate) {
                        //loss
                        $total = $total - $d->value;
                        if ($d->type == 'debit') {
                            $ok = 'ok';
                        }

                        $items['companies'][$coid . ' ' . $name][] = [
                            'debit' => $aid,
                            'credit' => $receipt_account,
                            'jid' => $d->id,
                            'fx_in' => $doc_rate,
                            'fx_out' => $receipt_rate,
                            'currency' => $currency,
                            'audit' => $ok,
                        ];
                    }

                    if ($receipt_rate < $doc_rate) {
                        //gain
                        $total = $total + $d->value;
                        if ($d->type == 'credit') {
                            $ok = 'ok';
                        }
                        $items['companies'][$coid . ' ' . $name][] = [
                            'debit' => $receipt_account,
                            'credit' => $aid,
                            'jid' => $d->id,
                            'fx_in' => $doc_rate,
                            'fx_out' => $receipt_rate,
                            'currency' => $currency,
                            'audit' => $ok,
                        ];
                    }
                } elseif ($param == 'p') {
                    if ($receipt_rate < $doc_rate) {
                        //loss
                        $total = $total - $d->value;
                        if ($d->type == 'debit') {
                            $ok = 'ok';
                        }
                        $items['companies'][$coid . ' ' . $name][] = [
                            'debit' => $aid,
                            'credit' => $receipt_account,
                            'jid' => $d->id,
                            'fx_in' => $doc_rate,
                            'fx_out' => $receipt_rate,
                            'currency' => $currency,
                            'audit' => $ok,
                        ];
                    }

                    if ($receipt_rate > $doc_rate) {
                        //gain
                        $total = $total + $d->value;
                        if ($d->type == 'credit') {
                            $ok = 'ok';
                        }

                        $items['companies'][$coid . ' ' . $name][] = [
                            'debit' => $receipt_account,
                            'credit' => $aid,
                            'jid' => $d->id,
                            'fx_in' => $doc_rate,
                            'fx_out' => $receipt_rate,
                            'currency' => $currency,
                            'audit' => $ok,
                        ];
                    }
                }
            }
            $items['companies'][$coid . ' ' . $name]['total'] = $total;
        }
        return $items;
    }

    /*
     * Audit chart of account match with journal
     * @coid int company id
     * ex. use /finance/audit/chart/1
     */

    public function audit_chart($coid) {

        $companies = \Drupal\ek_admin\Access\AccessCheck::CompanyList();
        $items = [];

        if (isset($companies[$coid])) {
            $items['company'] = $companies[$coid];
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
            $query->fields('a', ['aid']);
            $query->condition('coid', $coid);
            $query->orderBy('aid');
            $accounts = $query->execute()->fetchCol();
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->fields('j');
            $query->condition('coid', $coid);
            $query->orderBy('id');
            $journal = $query->execute();
            /**/
            while ($j = $journal->fetchObject()) {
                if (!in_array((int) $j->aid, $accounts)) {
                    $items['journal'][] = $j;
                }
            }
            if (empty($items['journal'])) {
                $items['journal'][] = ['aid' => t('No discrepency found')];
            }
        }

        return $items;
    }

    /*
     * Audit new year post report
     * @aram string coid|year
     * ex. use /finance/audit/post-year/1|2020
     */

    public function audit_newyear($param) {

        $param = explode('|', $param);
        $audit = [];
        $companies = \Drupal\ek_admin\Access\AccessCheck::GetCompanyByUser();
        if (in_array($param[0], $companies)) {
            $q = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal_reco_history', 'a')
                    ->fields('a')
                    ->condition('coid', $param[0])
                    ->condition('aid', 0)
                    ->execute();
            while ($r = $q->fetchObject()) {
                $data = json_decode($r->data);
                if ($data[1] == $param[1]) {
                    $settings = new FinanceSettings();
                    $audit['baseCurrency'] = $settings->get('baseCurrency');
                    $coset = new CompanySettings($param[0]);
                    $fiscal_month = $coset->get('fiscal_month');
                    $audit['dates'] = self::getFiscalDates($param[0], $param[1], $fiscal_month);
                    $audit['data'] = $data;
                    // pull accounts definition archive
                    $table = 'ek_accounts_' . $data[1] . '_' . $data[0];
                    $q = Database::getConnection('external_db', 'external_db')
                            ->select($table, 't')
                            ->fields('t', ['aid', 'aname'])
                            ->execute();
                    $audit['accounts'] = $q->fetchAllKeyed();
                    return $audit;
                }
            }
        }
    }

    /**
     * Audit balance sheet discrepancy
     * Route: /finance/audit/balancesheet/{param}
     * param = serialized [coid, year, month]
     */
    public function auditBalanceSheet($coid, $year, $month): array {

        $report = [];
        $financesettings = new FinanceSettings();
        $chart = $financesettings->get('chart');
        $dates = self::getFiscalDates($coid, $year, $month);
        $stop_date = date('Y-m-d', strtotime($dates['stop_date'] . ' - 1 day'));
        $from = $dates['fiscal_start'];
        $to = $dates['to'];

        // ── Re-run the BS to get current delta ─────────────────────────────────
        $bs = $this->balancesheet($coid, $year, $month, 0);
        $delta_base  = round($bs['net_assets']['base'], 2) - round($bs['total_equity']['base'], 2);
        $delta_multi = round($bs['net_assets']['multi'], 2) - round($bs['total_equity']['multi'], 2);

        $report['delta_base']  = $delta_base;
        $report['delta_multi'] = $delta_multi;
        $report['bs_error']    = isset($bs['error']);

        if ($delta_base == 0 && $delta_multi == 0) {
            $report['status'] = 'pass';
            return $report;
        }

        $report['layout'] = 'balancesheet';
        $report['status'] = 'fail';

        // ── Layer 3: Section Coverage ───────────────────────────────────────────
        $report['layer3'] = $this->auditSectionCoverage($coid, $chart, $stop_date);

        // ── Layer 4: Currency Divergence ────────────────────────────────────────
        $report['layer4'] = $this->auditCurrencyDivergence($delta_base, $delta_multi);

        // ── Layer 5: Earnings Account ───────────────────────────────────────────
        $report['layer5'] = $this->auditEarningsAccount($coid, $year, $month, $chart, $from, $to, $dates);

        // ── Diagnosis summary ───────────────────────────────────────────────────
        $report['diagnosis'] = $this->buildDiagnosis($report);

        return $report;
    }

    private function auditSectionCoverage($coid, $chart, $stop_date): array {

        $result = ['status' => 'pass', 'orphaned' => []];

        // Accounts that belong on the balance sheet
        $bs_sections = [
            'other_assets'      => (string) $chart['other_assets'],
            'assets'            => (string) $chart['assets'],
            'liabilities'       => (string) $chart['liabilities'],
            'other_liabilities' => (string) $chart['other_liabilities'],
            'equity'            => (string) $chart['equity'],
        ];

        // Accounts that intentionally live in the P&L — not BS orphans
        $pl_sections = [
            'income'         => (string) $chart['income'],
            'other_income'   => (string) $chart['other_income'],
            'cos'            => (string) $chart['cos'],
            'expenses'       => (string) $chart['expenses'],
            'other_expenses' => (string) $chart['other_expenses'],
        ];

        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_accounts', 'a');
        $query->fields('a', ['aid', 'aname']);
        $query->condition('coid', $coid);
        $query->condition('atype', 'detail');
        $query->condition('astatus', '1');
        $all_accounts = $query->execute()->fetchAllAssoc('aid');

        foreach ($all_accounts as $aid => $account) {

            $aid_str = (string) $aid;

            // Skip if this account belongs to a known P&L section — intentional
            $is_pl = false;
            foreach ($pl_sections as $prefix) {
                if (str_starts_with($aid_str, $prefix)) {
                    $is_pl = true;
                    break;
                }
            }
            if ($is_pl) {
                continue;
            }

            // Check if it maps to a BS section
            $matched = false;
            foreach ($bs_sections as $prefix) {
                if (str_starts_with($aid_str, $prefix)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                // Truly orphaned — not BS, not P&L
                // Only report if it carries a non-zero balance
                $b = self::opening([
                    'aid'     => $aid,
                    'coid'    => $coid,
                    'from'    => $stop_date,
                    'archive' => false,
                ]);

                if ($b[0] != 0 || $b[1] != 0) {
                    $result['orphaned'][] = [
                        'aid'   => $aid,
                        'name'  => $account->aname,
                        'multi' => $b[0],
                        'base'  => $b[1],
                    ];
                    $result['status'] = 'fail';
                }
            }
        }

        $result['orphaned_sum_base']  = array_sum(array_column($result['orphaned'], 'base'));
        $result['orphaned_sum_multi'] = array_sum(array_column($result['orphaned'], 'multi'));

        return $result;
    }

    private function auditCurrencyDivergence($delta_base, $delta_multi): array {

        $result = ['status' => 'pass'];

        if ($delta_base == 0 && $delta_multi == 0) {
            return $result;
        }

        $result['status']      = 'fail';
        $result['delta_base']  = $delta_base;
        $result['delta_multi'] = $delta_multi;

        if ($delta_multi == 0 && $delta_base != 0) {
            // Multi balances, base does not → pure FX rate inconsistency
            $result['type']    = 'fx_only';
            $result['message'] = 'Discrepancy in base currency only. '
                            . 'Multi-currency is balanced. '
                            . 'Check exchange rates applied during journal entry posting.';

        } elseif ($delta_base == 0 && $delta_multi != 0) {
            // Base balances, multi does not → unusual, likely FX rounding or
            // a multi-currency entry posted without base recalculation
            $result['type']    = 'multi_only';
            $result['message'] = 'Discrepancy in multi-currency only. '
                            . 'Base currency is balanced. '
                            . 'Check foreign currency journal entries for missing exchange conversion.';

        } elseif (($delta_base > 0) === ($delta_multi > 0)) {
            // Both non-zero, same sign → structural classification error
            $result['type']    = 'structural';
            $result['message'] = 'Both currencies diverge in the same direction. '
                            . 'Likely a chart-of-accounts classification or section coverage issue.';

        } else {
            // Both non-zero, opposite signs → mixed FX + structural
            $result['type']    = 'mixed';
            $result['message'] = 'Currencies diverge in opposite directions. '
                            . 'Mixed issue: FX rate inconsistency and structural classification error.';
        }

        return $result;
    }

    private function auditEarningsAccount($coid, $year, $month, $chart, $from, $to, $dates): array {

        $result = [
            'status'  => 'pass',
            'checks'  => [],
            'message' => '',
        ];

        $equity_min      = $chart['equity'] * 10000;
        $earnings_account = $equity_min + 9001;

        // ── Check 1: Archive table mismatch ────────────────────────────────────
        // current_earning() always reads live tables regardless of archive flag
        if (!empty($dates['archive']) && $dates['archive'] === TRUE) {
            $result['status'] = 'fail';
            $result['checks']['archive_mismatch'] = [
                'status'  => 'fail',
                'message' => 'current_earning() always reads live ek_accounts/ek_journal. '
                        . 'For archive year ' . $year . ', earnings are pulled from '
                        . 'the current fiscal year instead of the archive tables. '
                        . 'This will produce an incorrect equity figure on the balance sheet.',
            ];
        } else {
            $result['checks']['archive_mismatch'] = ['status' => 'pass'];
        }

        // ── Check 2: Direct transactions on earnings account ───────────────────
        $dt = self::transactions([
            'aid'     => $earnings_account,
            'type'    => 'debit',
            'coid'    => $coid,
            'from'    => $from,
            'to'      => $to,
            'archive' => $dates['archive'],
        ]);
        $ct = self::transactions([
            'aid'     => $earnings_account,
            'type'    => 'credit',
            'coid'    => $coid,
            'from'    => $from,
            'to'      => $to,
            'archive' => $dates['archive'],
        ]);

        $result['checks']['direct_transactions'] = [
            'debit_multi'   => $dt[0],
            'credit_multi'  => $ct[0],
            'debit_base'    => $dt[1],
            'credit_base'   => $ct[1],
        ];

        if ($dt[0] != 0 || $ct[0] != 0 || $dt[1] != 0 || $ct[1] != 0) {
            $result['status'] = 'warning';
            $result['checks']['direct_transactions']['status']  = 'warning';
            $result['checks']['direct_transactions']['message'] =
                'Direct journal entries exist on earnings account ' . $earnings_account
                . '. These are added on top of current_earning() in balancesheet(). '
                . 'Verify these are intentional closing/adjustment entries and not duplicates.';
        } else {
            $result['checks']['direct_transactions']['status'] = 'pass';
        }

        // ── Check 3: P&L section breakdown ────────────────────────────────────
        // Re-run each P&L section independently so we can report section by section
        $pl_sections = [
            'income'         => $chart['income'],
            'other_income'   => $chart['other_income'],
            'cos'            => $chart['cos'],
            'expenses'       => $chart['expenses'],
            'other_expenses' => $chart['other_expenses'],
        ];

        $pl_totals = [];
        $pl_grand_multi = 0;
        $pl_grand_base  = 0;

        foreach ($pl_sections as $section_name => $prefix) {

            $section_multi = 0;
            $section_base  = 0;

            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_accounts', 't');
            $query->fields('t', ['aid']);
            $query->condition('aid', $prefix . '%', 'like');
            $query->condition('atype', 'detail');
            $query->condition('astatus', '1');
            $query->condition('coid', $coid);
            $aids = $query->execute()->fetchCol();

            foreach ($aids as $aid) {
                $d = self::transactions([
                    'aid' => $aid, 'type' => 'debit',
                    'coid' => $coid, 'from' => $from, 'to' => $to,
                ]);
                $c = self::transactions([
                    'aid' => $aid, 'type' => 'credit',
                    'coid' => $coid, 'from' => $from, 'to' => $to,
                ]);
                $section_multi += $c[0] - $d[0];
                $section_base  += $c[1] - $d[1];
            }

            $pl_totals[$section_name] = [
                'multi' => $section_multi,
                'base'  => $section_base,
            ];
            $pl_grand_multi += $section_multi;
            $pl_grand_base  += $section_base;
        }

        // What current_earning() actually returned
        $ce = self::current_earning($coid, $from, $to);

        $result['checks']['pl_breakdown'] = [
            'sections'              => $pl_totals,
            'audit_grand_multi'     => $pl_grand_multi,
            'audit_grand_base'      => $pl_grand_base,
            'current_earning_multi' => $ce[0],
            'current_earning_base'  => $ce[1],
        ];

        // Compare — rounding tolerance 0.02
        $diff_multi = round($pl_grand_multi, 2) - round($ce[0], 2);
        $diff_base  = round($pl_grand_base,  2) - round($ce[1], 2);

        if (abs($diff_multi) > 0.02 || abs($diff_base) > 0.02) {
            $result['status'] = 'fail';
            $result['checks']['pl_breakdown']['status']  = 'fail';
            $result['checks']['pl_breakdown']['message'] =
                'current_earning() result does not match independent P&L section sum. '
                . 'Difference multi: ' . number_format($diff_multi, 2)
                . ' / base: '          . number_format($diff_base,  2) . '. '
                . 'Likely cause: a class-level account prefix in chart settings '
                . 'skips one or more detail accounts, or a class account has no '
                . 'parent header and is silently excluded from the loop.';
        } else {
            $result['checks']['pl_breakdown']['status'] = 'pass';
        }

        // further note
        $result['checks']['naming_note'] = [];

        return $result;
    }

    private function buildDiagnosis(array $report): string {

        $clues = [];

        if ($report['layer3']['status'] === 'fail') {
            $count = count($report['layer3']['orphaned']);
            $sum   = $report['layer3']['orphaned_sum_base'];
            $clues[] = $count
                    . ' account(s) carry a balance but are not mapped to any '
                    . 'balance sheet section or recognised P&L section. '
                    . 'Combined base balance: ' . number_format($sum, 2) . '.';

            if (abs($sum - abs($report['delta_base'])) < 0.05) {
                $clues[] = '→ This sum matches the balance sheet delta. '
                        . 'Fix: assign these accounts to the correct chart section.';
            }
        }

        // Only include currency analysis if multi is not purely informational
        if (isset($report['layer4']['type']) && !$report['multi_informational']) {
            $clues[] = 'Currency analysis: ' . $report['layer4']['message'];
        } elseif (isset($report['layer4']['type']) && $report['multi_informational']) {
            $clues[] = 'Multi-currency delta is a raw transaction-currency figure '
                    . 'and is not used as a discrepancy indicator for this company.';
        }

        if ($report['layer5']['status'] !== 'pass') {
            $clues[] = 'Earnings account: ' . $report['layer5']['message'];
        }

        if (empty($clues)) {
            return 'Delta detected but root cause not isolated by available layers. '
                . 'Run trial() and audit_chart() for deeper journal-level inspection.';
        }

        return implode(' | ', $clues);
    }

}
