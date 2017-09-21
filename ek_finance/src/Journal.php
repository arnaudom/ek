<?php

namespace Drupal\ek_finance;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Database\Database;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;

/**
 * record journal entries
 *
 * 
 *  
 *  
 */
class Journal {

    public function __contruct() {

        $this->tax = 0;
    }

    /* calculate start and end dates of fiscal period based on company and given year and month
     * starting date is based on fiscal year settings 
     * Ie a fiscal year of 2014-06 means the year is ending 30/06/14
     * and has started on 1/07/13
     * start date are used in report extraction like balance sheet or trial 
     * 
     * @param $coid int
     * @param $year int year i.e 2016
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
        $archive = FALSE;

        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $company->get('fiscal_month'), $company->get('fiscal_year'));
        $start_fiscal = date('Y-m-d', strtotime($company->get('fiscal_year') . '-' 
                . $company->get('fiscal_month') . '-' . $daysInMonth . ' - 1 year + 1 day'));
        $end_fiscal = date('Y-m-d', strtotime($company->get('fiscal_year') . '-' 
                . $company->get('fiscal_month') . '-' . $daysInMonth ));
        
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $end_request = date('Y-m-d', strtotime($year . '-' 
                . $month . '-' . $daysInMonth ));
        $n = 12 - date('n', strtotime($end_fiscal)) + date('n', strtotime($end_request));
        // start request is calculated relative to end request minus number $n of months
        // for a fiscal year
        $start_request = date('Y-m', strtotime($end_request .' - ' . $n . ' months' )) . '-01';
        
        if($end_request < $start_fiscal) {
            $archive = TRUE;           
        }

        $stop_date = date('Y-m-d', strtotime($end_request . ' + 1 day'));

        return [
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


        $query = "SELECT * from {ek_journal} where id=:id";
        $result = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();


        $ref = self::reference($result->source, $result->reference);
        if ($result->comment == '')
            $result->comment = $ref[0];
        if ($result->currency == '')
            $result->currency = $ref[1];

        $query = "SELECT aname from {ek_accounts} where aid=:account and coid=:coid";
        $result2 = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':account' => $result->aid, ':coid' => $result->coid))
                ->fetchObject();

        return array(
            'id' => $id,
            'count' => $result->count,
            'aid' => $result->aid,
            'aname' => $result2->aname,
            'exchange' => $result->exchange,
            'coid' => $result->coid,
            'type' => $result->type,
            'source' => $result->source,
            'reference' => $result->reference,
            'date' => $result->date,
            'value' => $result->value,
            'reconcile' => $result->reconcile,
            'comment' => $result->comment,
            'currency' => $result->currency
        );
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
        $baseCurrency = $settings->get('baseCurrency');
        $companysettings = new CompanySettings($j['coid']);
        $currency = new CurrencyData();


        Switch ($j['source']) {
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
                    $query = "SELECT currency,aid from {ek_bank_accounts} where id=:id ";
                    $data = Database::getConnection('external_db', 'external_db')
                                    ->query($query, array(':id' => $j['aid']))->fetchAssoc();
                    $account_currency = $data['currency'];
                    $account_aid = $data['aid'];
                }

                /*
                 * if currency of payment account is different from the currency of the invoice, 
                 * the amount received (debited) is converted into the currency of the payment account
                 */
                if ($account_currency <> $j['currency']) {
                    $debit = round($j['fxRate2'] * $j['value'], 2);
                } else {
                    $debit = $j['value'];
                }

                //main  DEBIT
                self::save($account_aid, '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $debit, '0', $j['currency']);

                //exchange
                if ($account_currency <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($account_currency, $debit, $j['fxRate']);
                    self::save($account_aid, '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main CREDIT
                $asset = $companysettings->get('asset_account', $j['currency']);
                self::save($asset, '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);
                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($asset, '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //currency gain / loss
                if ($account_currency <> $baseCurrency) {

                    $gain = self::exchangeGL($j['currency'], $j['value'], $j['rate'], $j['fxRate']);
                    $aid = $companysettings->get('CurrencyGainLoss');
                    if ($aid == '')
                        $aid = '49999';

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
                if ($j['tax'] > 0) {
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

                //currency gain / loss
                if ($j['currency'] <> $baseCurrency) {

                    $gain = self::exchangeGL($j['currency'], $j['value'], $j['rate']);
                    $aid = $companysettings->get('CurrencyGainLoss');
                    if ($aid == '')
                        $aid = '49999';

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
                    $query = "SELECT currency,aid from {ek_bank_accounts} where id=:id ";
                    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $j['aid']))->fetchAssoc();
                    $account_currency = $data['currency'];
                    $account_aid = $data['aid'];
                }

                /*
                 * if currency of payment account is different from the currency of the purchase, 
                 * the amount paid (credited) is converted into the currency of the payment account
                 */
                if ($account_currency <> $j['currency']) {
                    $credit = round($j['value'] / $j['fxRate'], 2);
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


                //currency gain / loss

                if ($j['currency'] <> $baseCurrency) {
                    $gain = self::exchangeGL($j['currency'], $j['value'], $j['rate'], $j['fxRate']);

                    $aid = $companysettings->get('CurrencyGainLoss');
                    if ($aid == '')
                        $aid = '49999';

                    if ($gain > 0) {
                        self::save($aid, '1', $j['coid'], 'credit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($liability, '1', $j['coid'], 'debit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange gain');
                    }

                    if ($gain < 0) {
                        $gain = abs($gain);
                        self::save($aid, '1', $j['coid'], 'debit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency);
                        self::save($liability, '1', $j['coid'], 'credit', 'payment', $j['reference'], $j['date'], $gain, '0', $baseCurrency, 'currency exchange loss');
                    }
                } //gain/loss


                break;
            /*
             * PAYROLL
             */
            case 'expense payroll':

                //main  DEBIT
                self::save($j['aid'], '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // credit payable    
                self::save($j['p1a'], '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['p1'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['p1'], $j['fxRate']);
                    self::save($j['p1a'], '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }
                //add payable creditors
                //loop array for funds and tax (able to increase number of funds in future
                for ($i = 0; $i < count($j['funds']); $i++) {

                    if ($j['funds']["f$i"] > 0) {
                        $a = 'f' . $i . 'a';
                        self::save($j['funds'][$a], '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['funds']["f$i"], '0', $j['currency']);

                        //exchange
                        if ($j['currency'] <> $baseCurrency) {
                            $exchange = CurrencyData::journalexchange($j['currency'], $j['funds']["f$i"], $j['fxRate']);
                            self::save($j['funds'][$a], '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                        }
                    }//if                
                }//for

                for ($i = 0; $i < count($j['tax']); $i++) {

                    if ($j['tax']["t$i"] > 0) {
                        $a = 't' . $i . 'a';
                        self::save($j['tax'][$a], '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $j['tax']["t$i"], '0', $j['currency']);

                        //exchange
                        if ($j['currency'] <> $baseCurrency) {
                            $exchange = CurrencyData::journalexchange($j['currency'], $j['tax']["t$i"], $j['fxRate']);
                            self::save($j['tax'][$a], '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                        }
                    }//if                
                }//for            


                break;



            /*
             * EXPENSE
             */
            case 'expense':
            case 'payroll':

                //main  DEBIT
                self::save($j['aid'], '0', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }
                //main  CREDIT  
                if ($j['provision'] == '1') {
                    $account_currency = $j['currency'];
                    $aid = $j['bank'];
                } else {
                    if (strpos($j['bank'], "-")) {
                        $account = explode('-', $j['bank']);
                        $aid = $account[1];
                        $account_currency = $account[0];
                    } else {
                        $query = "SELECT currency,aid from {ek_bank_accounts} where id=:id ";
                        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $j['bank']))->fetchAssoc();
                        $account_currency = $data['currency'];
                        $aid = $data['aid'];
                    }
                }

                /*
                 * if currency of payment account is different from the currency of the purchase, 
                 * the amount paid (credited) is converted into the currency of the payment account
                 */
                if ($account_currency <> $j['currency']) {
                    $credit = round($j['fxRate'] * $j['value'], 2);
                    $j['tax'] = round($j['fxRate'] * $j['tax'], 2);
                } else {
                    $credit = $j['value'];
                }

                self::save($aid, '0', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $credit, '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    self::save($aid, '1', $j['coid'], 'credit', $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //tax paid
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
                }//tax          

                break;



            /*
             * PURCHASE
             */

            case 'purchase':
                /*
                 * data
                 */
                //main  DEBIT
                self::save($j['aid'], '0', $j['coid'], 'debit', 'purchase', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    $exchange = CurrencyData::journalexchange($j['currency'], $j['value'], $j['fxRate']);
                    self::save($j['aid'], '1', $j['coid'], 'debit', 'purchase', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                //main CREDIT
                $liability = $companysettings->get('liability_account', $j['currency']);
                self::save($liability, '0', $j['coid'], 'credit', 'purchase', $j['reference'], $j['date'], $j['value'], '0', $j['currency']);

                //exchange
                if ($j['currency'] <> $baseCurrency) {
                    self::save($liability, '1', $j['coid'], 'credit', 'purchase', $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
                }

                // tax deduct
                if ($j['tax'] > 0) {
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
                if ($j['tax'] > 0) {
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
                
                if($j['tax'] > 0) {
                    //Liability Tax payable account need to be DT with CT of receivable account
                    $value = round($j['value'] * $j['tax'] / 100, 2);
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
                
                if($j['tax'] > 0) {
                    //Asset Tax deductible account need to be CT against liability payable
                    
                    $value = round($j['value'] * $j['tax'] / 100, 2);
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
        $baseCurrency = $settings->get('baseCurrency');
        $companysettings = new CompanySettings($j['coid']);

        $currency = new CurrencyData();

        switch ($j['type']) {

            case 'stax_deduct_aid' :
                $aid = $companysettings->get('stax_deduct_aid');
                $aid2 = $companysettings->get('liability_account', $j['currency']);
                $entry1 = 'debit';
                $entry2 = 'credit';
                break;

            case 'stax_collect_aid' :
                $aid = $companysettings->get('stax_collect_aid');
                $aid2 = $companysettings->get('asset_account', $j['currency']);
                $entry1 = 'credit';
                $entry2 = 'debit';
                break;
        }

        if ($this->tax > 0) {
            // 1
            self::save($aid, '0', $j['coid'], $entry1, $j['source'], $j['reference'], $j['date'], $this->tax, '0', $j['currency']);

            //exchange
            if ($j['currency'] <> $baseCurrency) {
                $exchange = CurrencyData::journalexchange($j['currency'], $this->tax, $j['fxRate']);
                self::save($aid, '1', $j['coid'], $entry1, $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
            }

            // 2
            self::save($aid2, '0', $j['coid'], $entry2, $j['source'], $j['reference'], $j['date'], $this->tax, '0', $j['currency']);

            //exchange                       
            if ($j['currency'] <> $baseCurrency) {
                self::save($aid2, '1', $j['coid'], $entry2, $j['source'], $j['reference'], $j['date'], $exchange, '0', $baseCurrency);
            }
            $this->tax = 0;
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

    static private function save($aid, $exchange, $coid, $type, $source, $ref, $date, $value, $reco, $currency = NULL, $comment = NULL) {

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
            drupal_set_message(t('Error journal record (@aid)', array('@aid' => $aid)), 'error');
            $insert =  NULL;
        } else {
            //Track user input history for backup and safety procedures
            //Todo implement tracking on delete data (sales doc delete, journal delete)
            $fields = array(
                'jid' => $insert,
                'username' => \Drupal::currentUser()->getUsername(),
                'action' => 1,
            );
            Database::getConnection('external_db', 'external_db')
                ->insert('ek_journal_trail')
                ->fields($fields)
                ->execute();
        }
        
        return $insert;
    }

    /*
     * @return INT validate a transaction debit against a credit
     * return value debit >= credit
     */

    public function checktransactiondebit($j) {


        $a = array(
            ':sdt' => $j['source_dt'],
            ':r' => $j['reference'],
            ':t' => 'debit',
            ':a' => $j['account'],
            ':e' => 0,
        );

        $query = "SELECT sum(value) from {ek_journal} WHERE source=:sdt and reference=:r and type=:t and aid=:a and exchange=:e";
        $debit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        $a = array(
            ':sct' => $j['source_ct'],
            ':r' => $j['reference'],
            ':t' => 'credit',
            ':a' => $j['account'],
            ':e' => 0,
        );
        $query = "SELECT sum(value) from {ek_journal} WHERE source=:sct and reference=:r and type=:t and aid=:a and exchange=:e";
        $credit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        return $debit - $credit;
    }

    /*
     * @return INT validate a transaction credit against a debit return value credit >= debit
     * @param array 
     * 
     */

    public function checktransactioncredit($j) {


        $a = array(
            ':sdt' => $j['source_dt'],
            ':r' => $j['reference'],
            ':t' => 'debit',
            ':a' => $j['account'],
            ':e' => 0,
        );

        $query = "SELECT sum(value) from {ek_journal} WHERE source=:sdt and reference=:r and type=:t and aid=:a and exchange=:e";
        $debit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        $a = array(
            ':sct' => $j['source_ct'],
            ':r' => $j['reference'],
            ':t' => 'credit',
            ':a' => $j['account'],
            ':e' => 0,
        );

        $query = "SELECT sum(value) from {ek_journal} WHERE source=:sct and reference=:r and type=:t and aid=:a and exchange=:e";
        $credit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        return $credit - $debit;
    }

    /*
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
     *   rounding is currently set to 2
     */

    function transactions($data) {

        $type = $data['type'];
        $account = $data['aid'];
        $coid = $data['coid'];
        $d1 = $data['from'];
        $d2 = $data['to'];
        
        if($data['archive'] == TRUE) {
        //extract data from archive tables
            $year = date('Y', strtotime($data['to'] . ' - 1 day'));
            $table_journal = "ek_journal_" . $year . "_" . $coid;
        } else {
            $table_journal = "ek_journal";
        }

        //get to total transaction up to d1
        // sum transaction currency
        $query = "SELECT sum(value) from {$table_journal} "
                . "WHERE exchange=:exc and type=:type and aid=:aid and coid=:coid and date>=:date1 and date<=:date2";
        $a = array(':exc' => 0, ':type' => $type, ':aid' => $account, ':coid' => $coid, ':date1' => $d1, ':date2' => $d2);
        $t = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();
        if ($t == '')
            $t = 0;

        // sum transaction exchange
        $query = "SELECT sum(value) from {$table_journal} "
                . "WHERE exchange=:exc and type=:type and aid=:aid and coid=:coid and date>=:date1 and date<=:date2";
        $a = array(':exc' => 1, ':type' => $type, ':aid' => $account, ':coid' => $coid, ':date1' => $d1, ':date2' => $d2);
        $t_exc = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

        return array(round($t, 2), round($t + $t_exc, 2));
    }

    /*
     * calculate a gain or loss between 2 dates when transactions are not in base currency
     *
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

    private function exchangeGL($currency, $value, $rate, $current_rate = NULL) {
        //exchange gain loss
        if ($current_rate == NULL) {
            $current_rate = CurrencyData::rate($currency);
        }

        if ($rate <> $current_rate) {
            $variation = round((($value / $rate) - $value / $current_rate), 2);
            return $variation;
        } else {
            return 0;
        }
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

    function display($j) {
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
        $query = "select distinct reference,source from {ek_journal} WHERE "
                . "date >=:d1 and date<=:d2 and coid=:coid and source like :s order by reference ";
        $a = array(
            ':d1' => $j['date1'],
            ':d2' => $j['date2'],
            ':coid' => $j['company'],
            ':s' => $j['source'] . '%',
        );

        $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

        while ($line = $data->fetchObject()) {

            /* Group the reference by date */
            $query = "select DISTINCT date from {ek_journal} where reference=:r and source like :s order by date";
            $b = array(
                ':r' => $line->reference,
                ':s' => $source . '%',
            );

            $d = Database::getConnection('external_db', 'external_db')->query($query, $b);
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

                $query = "select id,count,aid,exchange,coid,type,value,reconcile,source,reference "
                        . "from {ek_journal} where reference=:r and source like :s and date=:date ";
                $c = array(
                    ':r' => $line->reference,
                    ':s' => $source . '%',
                    ':date' => $date->date,
                );

                $e = Database::getConnection('external_db', 'external_db')->query($query, $c);

                $transactions = array();

                while ($entry = $e->fetchObject()) {
                    /* To remove
                      $query = "SELECT aname from {ek_accounts} where coid=:coid and aid=:aid";
                      $aname = Database::getConnection('external_db', 'external_db')
                      ->query($query, array(':coid' => $j['company'], ':aid'=> $entry->aid) )
                      ->fetchField();

                     */
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
                        ));
                        $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                        $aid = "<a class='use-ajax' href='" . $history . "' >" . $entry->aid . "</a>";
                    } else {
                        $aid = $entry->aid;
                    }
                    $transaction['id'] = $entry->id;
                    $transaction['count'] = $entry->count;
                    $transaction['aname'] = $aid . "  " . $aname;
                    $transaction['exchange'] = $entry->exchange;
                    $transaction['type'] = $entry->type;
                    if ($entry->type == 'debit') {
                        if ($entry->exchange == 0) {
                            $sum_d = $sum_d+=$entry->value;
                        }
                        if ($entry->exchange == 1) {
                            $sum_d2 = $sum_d2+=$entry->value;
                        }
                    }
                    if ($entry->type == 'credit') {
                        if ($entry->exchange == 0) {
                            $sum_c = $sum_c+=$entry->value;
                        }
                        if ($entry->exchange == 1) {
                            $sum_c2 = $sum_c2+=$entry->value;
                        }
                    }
                    $transaction['value'] = $entry->value;

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

        Switch ($source) {

            case 'expense':
            case 'expense amortization':
                $query = "SELECT comment from {ek_expenses} WHERE id=:id";
                $comment = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                $query = "SELECT currency from {ek_expenses} WHERE id=:id";
                $currency = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                break;
            case 'invoice':
            case 'invoice cn':    
                $query = "SELECT name from {ek_address_book} INNER JOIN {ek_sales_invoice} "
                    . "ON ek_sales_invoice.client=ek_address_book.id WHERE ek_sales_invoice.id=:id";
                $comment = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                $query = "SELECT currency from {ek_sales_invoice} WHERE id=:id";
                $currency = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                if($source == 'invoice cn'){
                   $comment .= " (" . t('Credit note') . ")"; 
                }
                break;
            case 'pos sale':
                $comment = t('POS sale');
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
            case 'purchase cn':
                $query = "SELECT name from {ek_address_book} INNER JOIN {ek_sales_purchase} "
                    . "ON ek_sales_purchase.client=ek_address_book.id WHERE ek_sales_purchase.id=:id";
                $comment = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                $query = "SELECT currency from {ek_sales_purchase} WHERE id=:id";
                $currency = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                if($source == 'purchase cn'){
                   $comment .= " (" . t('Debit note') . ")"; 
                }
                break;
            case 'receipt':
                $query = "SELECT name from {ek_address_book} INNER JOIN {ek_sales_invoice} "
                    . "ON ek_sales_invoice.client=ek_address_book.id WHERE ek_sales_invoice.id=:id";
                $comment = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                $query = "SELECT currency from {ek_sales_invoice} WHERE id=:id";
                $currency = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                break;
            case 'receipt pos':
                $comment = t('POS receipt');
                break;
            case 'payroll':
            case 'expense payroll':
                $query = "SELECT comment from {ek_expenses} WHERE id=:id";
                $comment = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $reference))->fetchField();
                $query = "SELECT currency from {ek_expenses} WHERE id=:id";
                $currency = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $reference))->fetchField();

                break;
            case 'general':
                $query = "SELECT comment,currency from {ek_journal} where source like :s and reference=:r";
                $r = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':s' => 'general', ':r' => $reference))->fetchAssoc();
                $comment = $r['comment'];
                $currency = $r['currency'];
                break;

            case 'general cash':
                $query = "SELECT comment,currency from {ek_journal} where source=:s and reference=:r";
                $r = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':s' => 'general cash', ':r' => $reference))->fetchAssoc();
                $comment = $r['comment'];
                $currency = $r['currency'];
                break;
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

        //get array of all chart of accounts structure per coid
        //(to reduce queries call rates)
        $query = "SELECT aid,aname FROM {ek_accounts} WHERE coid=:coid";
        $chart = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':coid' => $l['coid']))
                ->fetchAllKeyed();

        //list the accounts in journal within the range selected
        $query = "SELECT distinct aid from {ek_accounts} where coid=:coid and aid>=:aid1 and aid<=:aid2 ORDER BY aid";
        $a = array(':coid' => $l['coid'], ':aid1' => $l['aid1'], ':aid2' => $l['aid2']);
        $result = Database::getConnection('external_db', 'external_db')->query($query, $a);


        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
        //data holder
        $data = array();
        $data['baseCurrency'] = $baseCurrency;
        $data['ledger']['accounts'] = array();

        while ($r = $result->fetchObject()) {

            $aname = $chart[$r->aid];
            $data['ref'] = ['aid' => $r->aid, 'aname' => $aname];
            //calculate opening balances and get range data per account and date selected
            // opening balance both currency and exchange
            $query = "SELECT balance as b,balance_base as bb, balance_date as bd from {ek_accounts} where aid=:aid and coid=:coid ";
            $a = array(':aid' => $r->aid, ':coid' => $l['coid']);
            $balance = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchAssoc();
            $system_balance_date = $balance['bd'];


            // sum transaction currency - CREDIT 
            $query = "SELECT sum(value) FROM {ek_journal} where exchange=:exc and type=:type and aid=:aid and coid=:coid and date<:date1 and date>:bd";
            $a = array(':exc' => 0, ':type' => 'credit', ':aid' => $r->aid, ':coid' => $l['coid'], ':date1' => $l['date1'], ':bd' => $system_balance_date);
            $credit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            // sum transaction exchange - CREDIT
            $query = "SELECT sum(value) from {ek_journal} where exchange=:exc and type=:type and aid=:aid and coid=:coid and date<:date1 and date>:bd";
            $a = array(':exc' => 1, ':type' => 'credit', ':aid' => $r->aid, ':coid' => $l['coid'], ':date1' => $l['date1'], ':bd' => $system_balance_date);
            $credit_exc = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            // sum transaction currency - DEBIT
            $query = "SELECT sum(value) from {ek_journal} where exchange=:exc and type=:type and aid=:aid and coid=:coid and date<:date1 and date>:bd";
            $a = array(':exc' => 0, ':type' => 'debit', ':aid' => $r->aid, ':coid' => $l['coid'], ':date1' => $l['date1'], ':bd' => $system_balance_date);
            $debit = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            // sum transaction exchange - DEBIT
            $query = "SELECT sum(value) from {ek_journal} where exchange=:exc and type=:type and aid=:aid and coid=:coid and date<:date1 and date>:bd";
            $a = array(':exc' => 1, ':type' => 'debit', ':aid' => $r->aid, ':coid' => $l['coid'], ':date1' => $l['date1'], ':bd' => $system_balance_date);
            $debit_exc = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchField();

            // balance in local currency (opening + credit - debit during period preceding 'from date'
            $balance_open = $balance['b'] + $credit - $debit;
            // balance in base currency (opening + credit & exchange - debit & exchange during period preceding 'from date'
            $balance_open_base = $balance['bb'] + ($credit + $credit_exc) - ($debit + $debit_exc);
            $sum_d_base = 0; //sum base currency
            $sum_c_base = 0;
            $sum_d_loc = 0; //sum currency
            $sum_c_loc = 0;
            $i = 0;


            // calculate transactions per account and range
            $query = "SELECT id,type,exchange,value,date,reference FROM {ek_journal} "
                    . "WHERE aid=:aid AND date>=:d1 AND date<=:d2 AND coid=:coid "
                    . "ORDER BY date,reference,id";
            $a = array(':aid' => $r->aid, ':d1' => $l['date1'], ':d2' => $l['date2'], ':coid' => $l['coid']);
            $ledger = Database::getConnection('external_db', 'external_db')->query($query, $a);

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
            } //while

            $closing = $balance_open + $sum_c_loc - $sum_d_loc;
            $closing_exchange = $balance_open_base + ($sum_c_loc + $sum_c_base) - ($sum_d_loc + $sum_d_base);
            ($closing < 0) ? $acc = '-' : $acc = '+';


            $rows['line']['total'] = array(
                'aid' => $r->aid,
                'aname' => $aname,
                'balance_open' => round($balance_open, 2),
                'balance_open_base' => round($balance_open_base, 2),
                'sum_debit' => round($sum_d_loc, 2),
                'sum_debit_exchange' => round($sum_d_base, 2),
                'sum_credit' => round($sum_c_loc, 2),
                'sum_credit_exchange' => round($sum_c_base, 2),
                'closing' => abs(round($closing, 2)),
                'closing_exchange' => abs(round($closing_exchange, 2)),
                'account' => $acc,
            );

            $data['ledger']['accounts'][] = $rows;
        } //while


        return $data;
    }

//ledger

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

        //list the accounts in journal within the range selected
        $or = db_or();
        $or->condition('source', $l['source1'], '=');
        $or->condition('source', $l['source2'], '=');
        $or->condition('source', $l['source3'], '=');
       

        //calculate opening value

        // sum transaction currency - CREDIT / Invoice , purchase
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $query->addExpression('SUM(value)', 'sumValue');
        $query->condition('j.reference', $l['references'], 'IN')
                ->condition('j.exchange', 0, '=')
                ->condition('j.type', 'credit', '=')
                ->condition('j.source' , $l['source1'], '=')
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

        $or_src = db_or();
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
                ->condition('j.source' , $l['source1'], '=')
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
        $balance_open_base = ($credit + $credit_exc) - ($debit + $debit_exc) ;
        $sum_d_base = 0; //sum base currency
        $sum_c_base = 0;
        $sum_d_loc = 0; //sum currency
        $sum_c_loc = 0;
        $i = 0;


        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_journal', 'j');
        $result = $query->fields('j')
                ->condition('j.reference', $l['references'], 'IN')
                ->condition($or)
                ->condition('j.date', $l['date1'], '>=')
                ->condition('j.date', $l['date2'], '<=')
                ->condition('j.coid', $l['coid'], '=')
                ->execute();

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');
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
            if ($r->type == 'debit' && ($r->source == 'receipt' || $r->source == 'payment' 
                    || $r->source == 'invoice cn' || $r->source == 'purchase dn')) {
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
                'balance_open' => round($balance_open, 2),
                'balance_open_base' => round($balance_open_base, 2),
                'sum_debit' => round($sum_d_loc, 2),
                'sum_debit_exchange' => round($sum_d_base, 2),
                'sum_credit' => round($sum_c_loc, 2),
                'sum_credit_exchange' => round($sum_c_base, 2),
                'closing' => abs(round($closing, 2)),
                'closing_exchange' => abs(round($closing_exchange, 2)),
                'account' => $acc,
            );

            $data['ledger'][] = $rows;
   
            return $data;
    }

// salesledger

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

        if ($t['active'] == 0)
            $t['active'] = '%';
        $query = "SELECT * from ek_accounts where atype=:atype and astatus like :active and coid=:coid  order by aid";
        $a = array(':atype' => 'detail', ':active' => $t['active'], ':coid' => $t['coid']);
        $list = Database::getConnection('external_db', 'external_db')->query($query, $a);

        $data = array();
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
        //$ytd = $t['year'] . '-01-01';
        $dates = self::getFiscalDates($t['coid'], $t['year'], $t['month']);
        $ytd = $dates['from'];
        $d1 = $t['year'] . '-' . $t['month'] . '-01';
        $d2 = $t['year'] . '-' . $t['month'] . '-' . cal_days_in_month(CAL_GREGORIAN, $t['month'], $t['year']);
        $data['baseCurrency'] = $settings->get('baseCurrency');
        $data['coid'] = $t['coid'];
        $company = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT name from {ek_company} WHERE id=:id', array(':id' => $t['coid']))->fetchField();
        $data['company'] = $company;
        $data['year'] = $t['year'];
        $data['month'] = $t['month'];
        $data['transactions'] = array();


        while ($l = $list->fetchObject()) {

            $row = array();

            $row['aname'] = $l->aname;
            $row['active'] = $l->astatus;

            //build an history link
            $param = serialize(
                    array(
                        'id' => 'trial',
                        'from' => $t['year'] . '-01-01',
                        'to' => date('Y-m-d'),
                        'coid' => $t['coid'],
                        'aid' => $l->aid
            ));
            if ($option == 1) {
                $history = Url::fromRoute('ek_finance_modal', array('param' => $param), array())->toString();
                $row['aid'] = "<a class='use-ajax' href='" . $history . "' >" . $l->aid . "</a>";
            } else {
                $row['aid'] = $l->aid;
            }

            $row['transaction_debit'] = self::transactions(
                            array(
                                'aid' => $l->aid,
                                'type' => 'debit',
                                'coid' => $t['coid'],
                                'from' => $d1,
                                'to' => $d2
                            )
            );
            $row['transaction_credit'] = self::transactions(
                            array(
                                'aid' => $l->aid,
                                'type' => 'credit',
                                'coid' => $t['coid'],
                                'from' => $d1,
                                'to' => $d2
                            )
            );

            $row['transaction_ytd_debit'] = self::transactions(
                            array(
                                'aid' => $l->aid,
                                'type' => 'debit',
                                'coid' => $t['coid'],
                                'from' => $ytd,
                                'to' => $d2
                            )
            );

            $row['transaction_ytd_credit'] = self::transactions(
                            array(
                                'aid' => $l->aid,
                                'type' => 'credit',
                                'coid' => $t['coid'],
                                'from' => $ytd,
                                'to' => $d2
                            )
            );

            if (
                    ($t['null'] == 1 && ($row['transaction_ytd_debit'][0] != 0 || $row['transaction_ytd_credit'][0] != 0 || $row['transaction_ytd_debit'][1] != 0 || $row['transaction_ytd_credit'][1] != 0) ) ||
                    $t['null'] == 0
            ) {
                //show 
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
        }//while

        if (abs(round($total_td, 2) - round($total_tc, 2)) > 0) {
            $error1 = 1;
        } else {
            $error1 = 0;
        }

        if (abs(round($total_ytdd, 2) - round($total_ytdc, 2)) > 0) {
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

//trial

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

    function opening($data) {

        $account = $data['aid'];
        $coid = $data['coid'];
        $d1 = $data['from'];

        if($data['archive'] == TRUE) {
        //extract data from archive tables
            $year = date('Y', strtotime($data['from'] . ' - 1 day'));
            $table_accounts = "ek_accounts_" . $year . "_" . $coid;
            $table_journal = "ek_journal_" . $year . "_" . $coid;
        } else {
            $table_accounts = "ek_accounts";
            $table_journal = "ek_journal";
        }

        $query = "SELECT * FROM {$table_accounts} WHERE aid=:account AND coid=:coid";
        $r = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':account' => $account, ':coid' => $coid))
                ->fetchAssoc();


        //get to total transaction up to d1
        // sum transaction currency
        $query = "SELECT sum(value) as c FROM {$table_journal} "
                . "WHERE exchange=:exc AND type=:type AND aid=:aid "
                . "AND coid=:coid AND date<:date1 AND date>=:dateopen";
        $a = array(
                    ':exc' => 0, 
                    ':type' => 'credit', 
                    ':aid' => $account, 
                    ':coid' => $coid, 
                    ':date1' => $d1, 
                    ':dateopen' => $r['balance_date']
                );
        $credit = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchAssoc();

        // sum transaction currency
        $query = "SELECT sum(value) as d FROM {$table_journal} "
                . "WHERE exchange=:exc AND type=:type AND aid=:aid "
                . "AND coid=:coid ANd date<:date1 and date>=:dateopen";
        $a = array(
                    ':exc' => 0, 
                    ':type' => 'debit', 
                    ':aid' => $account, 
                    ':coid' => $coid, 
                    ':date1' => $d1, 
                    ':dateopen' => $r['balance_date']
                   );
        $debit = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchAssoc();

        // sum transaction exchange
        $query = "SELECT sum(value) as c_exc FROM {$table_journal} "
                . "WHERE exchange=:exc and type=:type AND aid=:aid "
                . "AND coid=:coid and date<:date1 and date>=:dateopen";
        $a = array(
                    ':exc' => 1, 
                    ':type' => 'credit', 
                    ':aid' => $account, 
                    ':coid' => $coid, 
                    ':date1' => $d1, 
                    ':dateopen' => $r['balance_date']
                );
        $credit_exc = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchAssoc();

        // sum transaction exchange
        $query = "SELECT sum(value) as d_exc FROM {$table_journal} "
                . "WHERE exchange=:exc AND type=:type AND aid=:aid "
                . "AND coid=:coid and date<:date1 and date>=:dateopen";
        $a = array(
                    ':exc' => 1, 
                    ':type' => 'debit', 
                    ':aid' => $account, 
                    ':coid' => $coid, 
                    ':date1' => $d1, 
                    ':dateopen' => $r['balance_date']
                );
        $debit_exc = Database::getConnection('external_db', 'external_db')
                ->query($query, $a)
                ->fetchAssoc();

        //calculate value in local currency
        $balance = $r['balance'] + $credit['c'] - $debit['d'];

        //calculate value in base currency
        $balance_base = $r['balance_base'] + ($credit['c'] + $credit_exc['c_exc']) - ($debit['d'] + $debit_exc['d_exc']);

        return array($balance, $balance_base);
    }

    /*
     * Calculate the current year earnings
     * used in B. Sheet
     * Post new year
     */

    function current_earning($coid, $from, $to) {

        $settings = new FinanceSettings();
        $chart = $settings->get('chart');
//REVENUE - class //
        $q = "SELECT aid,aname FROM {ek_accounts} "
                . "WHERE (aid like :aid1 or aid like :aid2) AND atype=:type AND astatus=:status AND coid=:coid order by aid";
        $a = array(
            ':aid1' => $chart['income'] . '%',
            ':aid2' => $chart['other_income'] . '%',
            ':type' => 'class',
            ':status' => 1,
            ':coid' => $coid
        );
        $result = Database::getConnection('external_db', 'external_db')
                ->query($q, $a);


        while ($r = $result->fetchAssoc()) {

            $aid = substr($r['aid'], 0, 2);
            $total_detail = 0;
            $total_detail_l = 0;
            $q = "SELECT aid,aname FROM {ek_accounts} "
                    . "WHERE aid like :aid and atype=:type and astatus=:status and coid=:coid order by aid";
            $a = array(':aid' => $aid . '%', ':type' => 'detail', ':status' => 1, ':coid' => $coid);
            $result3 = Database::getConnection('external_db', 'external_db')
                    ->query($q, $a);

            while ($r3 = $result3->fetchAssoc()) {

                $d = self::transactions(
                                array(
                                    'aid' => $r3['aid'],
                                    'type' => 'debit',
                                    'coid' => $coid,
                                    'from' => $from,
                                    'to' => $to
                                )
                );

                $c = self::transactions(
                                array(
                                    'aid' => $r3['aid'],
                                    'type' => 'credit',
                                    'coid' => $coid,
                                    'from' => $from,
                                    'to' => $to
                                )
                );
                $b = $c[0] - $d[0];
                $b_exc = $c[1] - $d[1];
                $total_detail+=$b_exc;
                $total_detail_l+=$b;
            }

            $total_class4+=$total_detail;
            $total_class4_l+=$total_detail_l;
        }

// COS - class //
        $q = "SELECT aid,aname FROM {ek_accounts} "
                . "WHERE aid like :aid and atype=:type and astatus=:status and coid=:coid order by aid";
        $a = array(
            ':aid' => $chart['cos'] . '%',
            ':type' => 'class',
            ':status' => 1,
            ':coid' => $coid);

        $result = Database::getConnection('external_db', 'external_db')
                ->query($q, $a);

        while ($r = $result->fetchAssoc()) {

            $aid = substr($r['aid'], 0, 2);
            $total_detail = 0;
            $total_detail_l = 0;
            $q = "SELECT aid,aname FROM {ek_accounts} "
                    . "WHERE aid like :aid and atype=:type and astatus=:status and coid=:coid order by aid";
            $a = array(':aid' => $aid . '%', ':type' => 'detail', ':status' => 1, ':coid' => $coid);
            $result3 = Database::getConnection('external_db', 'external_db')
                    ->query($q, $a);

            while ($r3 = $result3->fetchAssoc()) {

                $d = self::transactions(
                                array(
                                    'aid' => $r3['aid'],
                                    'type' => 'debit',
                                    'coid' => $coid,
                                    'from' => $from,
                                    'to' => $to
                                )
                );

                $c = self::transactions(
                                array(
                                    'aid' => $r3['aid'],
                                    'type' => 'credit',
                                    'coid' => $coid,
                                    'from' => $from,
                                    'to' => $to
                                )
                );
                $b = $c[0] - $d[0];
                $b_exc = $c[1] - $d[1];
                $total_detail+=$b_exc;
                $total_detail_l+=$b;
            }

            $total_class5+=$total_detail;
            $total_class5_l+=$total_detail_l;
        }

// CHARGES - class //
        $q = "SELECT aid,aname FROM {ek_accounts} "
                . "WHERE (aid like :aid1 or aid like :aid2) "
                . "AND atype=:type and astatus=:status and coid=:coid order by aid";
        $a = array(
            ':aid1' => $chart['expenses'] . '%',
            ':aid2' => $chart['other_expenses'] . '%',
            ':type' => 'class',
            ':status' => 1,
            ':coid' => $coid);
        $result = Database::getConnection('external_db', 'external_db')
                ->query($q, $a);

        while ($r = $result->fetchAssoc()) {

            $aid = substr($r['aid'], 0, 2);
            $total_detail = 0;
            $total_detail_l = 0;
            $q = "SELECT aid,aname FROM {ek_accounts} "
                    . "WHERE aid like :aid and atype=:type and astatus=:status and coid=:coid order by aid";
            $a = array(':aid' => $aid . '%', ':type' => 'detail', ':status' => 1, ':coid' => $coid);
            $result3 = Database::getConnection('external_db', 'external_db')
                    ->query($q, $a);

            while ($r3 = $result3->fetchAssoc()) {

                $d = self::transactions(
                                array(
                                    'aid' => $r3['aid'],
                                    'type' => 'debit',
                                    'coid' => $coid,
                                    'from' => $from,
                                    'to' => $to
                                )
                );

                $c = self::transactions(
                                array(
                                    'aid' => $r3['aid'],
                                    'type' => 'credit',
                                    'coid' => $coid,
                                    'from' => $from,
                                    'to' => $to
                                )
                );
                $b = $c[0] - $d[0];
                $b_exc = $c[1] - $d[1];
                $total_detail+=$b_exc;
                $total_detail_l+=$b;
            }

            $total_class6+=$total_detail;
            $total_class6_l+=$total_detail_l;
        }


        $result = $total_class4 + $total_class5 + $total_class6;
        $result_l = $total_class4_l + $total_class5_l + $total_class6_l;

        return array($result_l, $result);
    }

    /*
     * History per account
     * @param aid = account id
     * @param coid = the id of the company
     * @param from = from date
     * @param to = to date
     * @param source = record source type, default is wildcard %
     * @return an array with history figures : total_debit, total_credit, total_transaction
     * closing,total_debit_exchange, total_credit_exchange, total_transaction_exchange
     * closing_exchange
     */

    function history($param) {

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
                if ($r->exchange == 0)
                    $totaldb += $r->value;
                if ($r->exchange == 1)
                    $totaldb_exc += $r->value;
            } else {
                if ($r->exchange == 0)
                    $totalcr += $r->value;
                if ($r->exchange == 1)
                    $totalcr_exc += $r->value;
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
        $data['closing_exchange'] = $open[0] + $open[1] + $totalcr - $totaldb + $totalcr_exc - $totaldb_exc;

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

    function entity_history($param) {

        if (!isset($param['format'])) {
            $format = 'html';
        } else {
            $format = 'raw';
        }
        switch ($param['entity']) {

            case 'invoice' :

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

            case 'purchase' :

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

                while ($d = $data->fetchObject()) {
                    if ($format == 'html') {
                        if ($d->source == 'purchase' && $d->type == 'credit' && $d->exchange == '0') {
                            $return .= '<tr><td>' . $d->date . '</td><td>' . $d->aid . '</td>'
                                    . '<td>' . $d->currency . ' ' . $d->value . '</td><td></td></tr>';
                        }
                        if ($d->source == 'payment' && $d->type == 'debit' && $d->exchange == '0') {
                            $return .= '<tr><td>' . $d->date . '</td><td>' . $d->aid . '</td>'
                                    . '<td></td><td>' . $d->currency . ' ' . $d->value . '</td></tr>';
                        }
                    } else {
                        $return[] = self::journalEntryDetails($d->id);
                    }
                }


                break;
        }
        if ($format == 'html') {
            $return .= '</tbody></table>';
        }

        return $return;
    }

}

//class