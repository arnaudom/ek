<?php

namespace Drupal\ek_finance;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;


class CurrencyData {

    /**
     * The CurrencyData.
     *
     * @var \Drupal\ek_finance\CurrencyData
     */
    protected $CurrencyData;

    /**
     * Constructs a CurrencyData.
     *
     */
    public function __construct() {
        
    }

    /**
     * build an array of currencies by currency => name (ie 'USD' => 'United States Dollar')
     *
     * @param $type = 1 (active) or 0 (inactive)
     */
    public static function listcurrency($type = null) {
        if ($type == '1') {
            $condition1 = "where active=:param1";
            $a[':param1'] = 1;
        } else {
            $condition1 = '';
            $a[':param1'] = '';
        }

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_currency', 'c')
                ->fields('c', ['currency', 'name']);
        if ($type == '1') {
            $query->condition('active', 1);
        }
        $query->orderBy('name');
        $data = $query->execute();


        return $data->fetchAllKeyed();
    }

    /**
     * build an array of currencies by name => exchange (ie 'USD' => '1')
     *
     */
    public static function currencyRates() {
        $a[':status'] = 1;
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_currency', 'c')
                ->fields('c', ['currency', 'rate'])
                ->condition('active', 1)
                ->orderBy('name');
        $data = $query->execute();
        return $data->fetchAllKeyed();
    }

    /**
     * calculate value of exchange to convert into base currency value
     * base currency rate must be 1. It is selected in company settings.
     * used in Journal, cash
     * @param sting $currency The currency name to convert value from
     * @param int $value The value to convert
     * @param double $rate The optional rate to use; use parameter default if null
     * @return double $exchange
     *    calculated value
     */
    public static function journalexchange($currency, $value, $rate = null) {
        $financeSettings = new \Drupal\ek_finance\FinanceSettings();
        $rounding = (!null == $financeSettings->get('rounding')) ? $financeSettings->get('rounding') : 2;
        if ($rate == '') {
            // the rate is not given by input , check value in DB
            $rate = self::rate($currency);
            $exchange = round(($value / $rate - $value), $rounding);
        } else {
            // the rate is given by input
            $exchange = round(($value / $rate - $value), $rounding);
        }

        return $exchange;
    }

    /**
     * pull a currency rate against the base currency
     *
     * @param sting $currency = currency code (ie. 'EUR')
     * @return value
     */
    public static function rate($currency) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_currency', 'c')
                ->fields('c', ['rate']);
        $or = $query->orConditionGroup();
        $or->condition('currency', $currency)->condition('name', $currency);
        $query->condition($or);
        $rate = $query->execute()->fetchField();
        return $rate;
    }

    /**
     * update the exchange rate for non base currencies
     *
     * @param $currency = currency code (ie. 'EUR')
     * @param $value = the exchange rate against base currency
     */
    public static function setrate($currency, $value) {
        $update = Database::getConnection('external_db', 'external_db')->update('ek_currency')
                ->condition('currency', $currency)
                ->fields(array('rate' => $value, 'date' => date("Y-m-d H:i:s")))
                ->execute();


        if ($update) {
            return $update;
        }
    }

    /**
     * toggle active setting of a currency
     *
     * @param $currency = currency code (ie. 'EUR')
     * 1 = active, 0 = inactive
     */
    public static function toggle($currency) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_currency', 'c');
        $or = $query->orConditionGroup();
        $or->condition('currency', $currency);
        $or->condition('name', $currency);
        $query->fields('c', ['active'])
                ->condition($or);
        
        //$query = "SELECT active from {ek_currency} WHERE currency=:c or name=:n";
        //$active = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $currency, ':n' => $currency))->fetchField();
        $active = $query->execute()->fetchField();
        if ($active == 1) {
            $active = 0;
        } else {
            $active = 1;
        }
        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_currency')
                ->condition('currency', $currency)
                ->fields(array('active' => $active))
                ->execute();


        if ($update) {
            return $update;
        }
    }

}
