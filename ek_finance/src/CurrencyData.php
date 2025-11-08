<?php

namespace Drupal\ek_finance;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;
use Drupal\ek_finance\FinanceSettings;


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
     * @param $type = 1 (active) or 0 (all)
     * @return array
     */
    public static function listcurrency($type = null) {
        
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

    public static function journalexchange($currency, $value, $rate = null): float
    {
        // 1. Get Rounding Setting Correctly
        $financeSettings = new FinanceSettings();
        $roundingPrecision = $financeSettings->get('rounding') ?? 2;

        // 2. Validate and Sanitize Inputs
        $value = is_numeric($value) ? (float) $value : 0.0;
        $sanitizedRate = preg_replace('/[^0-9.,]/', '', (string) $rate);
        $rateAsFloat = (float) str_replace(',', '.', $sanitizedRate);

        // 3. Perform Calculation with High-Precision Arithmetic (bcmath)
        if ($rateAsFloat == 0) {
            $rateAsFloat = self::rate($currency);
            if ($rateAsFloat == 0) {
                return 0.0;
            }
        }

        // Perform the calculation using bcmath to avoid float precision issues.
        if (extension_loaded('bcmath')) {
            // bcscale must be set high enough to not lose precision during intermediate steps.
            // A good rule of thumb is to set it to your desired precision + 5.
            bcscale($roundingPrecision + 5);

            // Convert numbers to strings for bcmath functions.
            $valueString = number_format($value, 10, '.', ''); // Use high precision for conversion
            $rateString = number_format($rateAsFloat, 10, '.', '');

            // Perform division: $value / $rate
            $divisionResult = bcdiv($valueString, $rateString);

            // Perform subtraction: (result) - $value
            $rawExchangeString = bcsub($divisionResult, $valueString);

            // Now, round the final, high-precision string result.
            $finalRoundedValue = self::roundFinancially($rawExchangeString, $roundingPrecision);      
            return (float) $finalRoundedValue;
        } else {
            // Fallback for servers without bcmath (less accurate)
            $rawExchange = ($value / $rateAsFloat) - $value;
            return self::roundFinancially($rawExchange, $roundingPrecision);
        }
    }

    /**
     * Rounds a number in a way that is more suitable for financial calculations.
     *
     * This function is static and correctly uses the bcmath extension to perform
     * arbitrary-precision rounding, avoiding floating-point inaccuracies.
     *
     * @param float $number The number to round.
     * @param int $precision The number of decimal places.
     *
     * @return float The rounded number.
     */
    private static function roundFinancially($number, int $precision): string
    {
        if (!extension_loaded('bcmath')) {
            // Fallback to standard round if bcmath is not available
            return (string) round((float) $number, $precision);
        }

        $numberAsString = (string) $number;

        // Check if the number is negative
        $isNegative = $numberAsString[0] === '-';
        if ($isNegative) {
            // 2. Work with the absolute value by removing the negative sign
            $numberAsString = substr($numberAsString, 1);
        }

        // If the number is zero, return a properly formatted zero
        if (bccomp($numberAsString, '0') == 0) {
            $result = '0.' . str_repeat('0', $precision);
            return $isNegative ? '-' . $result : $result;
        }

        // Find the decimal point
        $decimalPosition = strpos($numberAsString, '.');

        // If there is no decimal point, add one
        if ($decimalPosition === false) {
            $numberAsString .= '.';
            $decimalPosition = strlen($numberAsString) - 1;
        }

        // Pad the string with zeros if necessary
        $requiredLength = $decimalPosition + $precision + 1;
        if (strlen($numberAsString) < $requiredLength) {
            $numberAsString = str_pad($numberAsString, $requiredLength, '0');
        }

        // Isolate the part to keep and the next digit for rounding
        $roundingPart = substr($numberAsString, 0, $requiredLength);
        $nextDigit = (int) substr($numberAsString, $requiredLength, 1); // Cast to int for comparison

        // Perform the rounding logic on the absolute value
        if ($nextDigit >= 5) {
            // Create the addend (e.g., 0.01 for precision 2)
            $addend = '0.' . str_repeat('0', $precision - 1) . '1';
            $roundedNumber = bcadd($roundingPart, $addend, $precision);
        } else {
            // Truncate to the desired precision
            $roundedNumber = $roundingPart;
        }

        //. Reapply the negative sign if the original number was negative
        return $isNegative ? '-' . $roundedNumber : $roundedNumber;
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
