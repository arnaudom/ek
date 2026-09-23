<?php

namespace Drupal\ek_finance;

use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\CompanySettings;

/**
 * Aggregates cash flow statement data for management reporting.
 *
 * Builds the bank / cash, receivable, payable, amortization and tax sections
 * used by the ek_finance_cashflow theme, together with the running totals,
 * the monthly overhead average and the cash flow ratios.
 *
 * Figures come from ek_journal through JournalService::history(), where
 * balances are credit positive; values are negated for display to match the
 * convention used by the other finance reports.
 */
class CashData {


  private $coid;
  private $amortization;
  private $baseCurrency;
  private $rounding;
  private $chart;
  private $settings;
  private $journal;
  private $currencies;
  private $from;
  private $to;
  private $bankAids = [];
  private $netTaxLocal = 0;
  private $netTaxClosingLocal = 0;
  private $totalAssets = 0;
  private $totalAssetsExc = 0;
  private $totalClosingAssets = 0;
  private $totalClosingAssetsExc = 0;
  private $totalLiabilities = 0;
  private $totalLiabilitiesExc = 0;
  private $totalClosingLiabilities = 0;
  private $totalClosingLiabilitiesExc = 0;

  /**
   * Constructs a CashData object.
   *
   * @param string|int $coid
   *   The company ID.
   * @param bool|null $amortization
   *   TRUE when the ek_assets amortization section is enabled.
   * @param string $baseCurrency
   *   The base currency code.
   * @param int $rounding
   *   Decimal rounding precision.
   * @param array $chart
   *   The chart of accounts structure.
   */
  public function __construct($coid, $amortization, $baseCurrency, $rounding, $chart) {
    $this->coid = $coid;
    $this->amortization = $amortization;
    $this->baseCurrency = $baseCurrency;
    $this->rounding = $rounding;
    $this->chart = $chart;
    $this->settings = new CompanySettings($coid);
    $this->journal = \Drupal::service('ek_finance.journal');
    $this->currencies = CurrencyData::listcurrency(1);

    // Starting date is based on fiscal year settings; a fiscal year of
    // 2014-06 means the year ends 30/06/14 and has started on 1/07/13.
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $this->settings->get('fiscal_month'), $this->settings->get('fiscal_year'));
    $this->from = date('Y-m-d', strtotime($this->settings->get('fiscal_year') . '-' . $this->settings->get('fiscal_month') . '-' . $daysInMonth . ' - 1 year + 1 day'));
    $this->to = date('Y-m-d');
  }

  /**
   * Builds the cash flow statement data.
   *
   * @return array
   *   The items consumed by the ek_finance_cashflow theme.
   */
  public function getData() {
    $items = [
      'from' => $this->from,
      'to' => $this->to,
      'baseCurrency' => $this->baseCurrency,
      'rounding' => $this->rounding,
    ];

    // Calculate current year earnings.
    $items['current_earnings'] = $this->journal->current_earning($this->coid, $this->from, $this->to);

    $items += $this->cashBank();
    $items += $this->cash();
    $items += $this->receivable();
    $items += $this->payable();

    if (isset($this->amortization)) {
      $items['amortization'] = $this->getAmortization();
    }

    $items += $this->tax();

    $items['average_expenses'] = $this->getAverageExpenses();

    $items['total_assets'] = $this->totalAssets;
    $items['total_closing_assets'] = $this->totalClosingAssets;
    $items['total_liabilities'] = $this->totalLiabilities;
    $items['total_closing_liabilities'] = $this->totalClosingLiabilities;
    $items['total_assets_exc'] = $this->totalAssetsExc;
    $items['total_closing_assets_exc'] = $this->totalClosingAssetsExc;
    $items['total_liabilities_exc'] = $this->totalLiabilitiesExc;
    $items['total_closing_liabilities_exc'] = $this->totalClosingLiabilitiesExc;

    // The local and base grand totals are built from the same components,
    // including the net tax position, so they remain comparable.
    $items['grand_total'] = $this->totalAssets + $this->totalLiabilities + round($this->netTaxLocal, $this->rounding);
    $items['grand_total_exc'] = $this->totalAssetsExc + $this->totalLiabilitiesExc + $items['tax_total_balance_transaction'];
    $items['grand_total_closing'] = $this->totalClosingAssets + $this->totalClosingLiabilities + round($this->netTaxClosingLocal, $this->rounding);
    $items['grand_total_closing_exc'] = $this->totalClosingAssetsExc + $this->totalClosingLiabilitiesExc + $items['tax_total_balance_closing'];

    $ratio_denominator = $items['average_expenses'] - ($items['amortization'] ?? 0);
    // A zero or negative denominator would produce a meaningless or sign-flipped ratio.
    $items['ratio1'] = $ratio_denominator > 0 ? round($items['grand_total_exc'] / $ratio_denominator, $this->rounding) : 0;
    $items['ratio2'] = $ratio_denominator > 0 ? round($items['grand_total_closing_exc'] / $ratio_denominator, $this->rounding) : 0;

    return $items;
  }

  /**
   * Returns the journal history for an account over the analysis period.
   *
   * @param string|int $aid
   *   The account id.
   *
   * @return array
   *   The decoded history figures, or an empty array when unavailable.
   */
  private function getHistory($aid) {
    $histo = $this->journal->history(serialize([
      'aid' => $aid,
      'coid' => $this->coid,
      'from' => $this->from,
      'to' => $this->to,
    ]));
    return $histo !== NULL ? unserialize($histo) : [];
  }

  /**
   * Builds the journal modal link for an account.
   *
   * @param string|int $aid
   *   The account id.
   * @param string $id
   *   The modal source type, 'bs' for balance lines or 'journal' for tax.
   *
   * @return string
   *   The route url.
   */
  private function getLink($aid, $id = 'bs') {
    $param = serialize([
      'id' => $id,
      'from' => $this->from,
      'to' => $this->to,
      'coid' => $this->coid,
      'aid' => $aid,
    ]);
    return Url::fromRoute('ek_finance_modal', ['param' => $param], [])->toString();
  }

  /**
   * Builds the bank linked accounts section.
   *
   * @return array
   *   The cash_bank rows and cash_bank_total.
   */
  private function cashBank() {
    $items = [];
    $query = "SELECT DISTINCT aid FROM {ek_bank_accounts} a INNER JOIN {ek_bank} b "
                . "ON a.bid = b.id WHERE coid=:coid";
    $result = Database::getConnection('external_db', 'external_db')
      ->query($query, [':coid' => $this->coid]);
    $total = 0;
    $total_exc = 0;
    $closing = 0;
    $closing_exc = 0;
    while ($r = $result->fetchObject()) {
      // Track bank linked accounts so the cash block below cannot double count them.
      $this->bankAids[] = $r->aid;
      $h = $this->getHistory($r->aid);
      $total += $h['total_transaction'];
      $total_exc += $h['total_transaction_exchange'];
      $closing += $h['closing'];
      $closing_exc += $h['closing_exchange'];
      $items['cash_bank'][] = [
        'aid' => $r->aid,
        'name' => AidList::aname($this->coid, $r->aid),
        'balance' => -$h['total_transaction'],
        'balance_base' => -$h['total_transaction_exchange'],
        'closing' => -$h['closing'],
        'closing_base' => -$h['closing_exchange'],
        'link' => $this->getLink($r->aid),
      ];
    }
    $items['cash_bank_total'] = [
      'balance' => -$total,
      'balance_base' => -$total_exc,
      'closing' => -$closing,
      'closing_base' => -$closing_exc,
    ];
    $this->totalAssets += -$total;
    $this->totalAssetsExc += -$total_exc;
    $this->totalClosingAssets += -$closing;
    $this->totalClosingAssetsExc += -$closing_exc;
    return $items;
  }

  /**
   * Builds the cash accounts section.
   *
   * @return array
   *   The cash rows and cash_total.
   */
  private function cash() {
    $items = [];
    $total = 0;
    $total_exc = 0;
    $closing = 0;
    $closing_exc = 0;
    $list = $this->bankAids;
    foreach ($this->currencies as $currency => $name) {
      $aid = $this->settings->get('cash_account', $currency);
      if ($aid && !in_array($aid, $list)) {
        // Avoid duplicate account for same coid.
        array_push($list, $aid);
        $h = $this->getHistory($aid);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];
        $items['cash'][] = [
          'aid' => $aid,
          'name' => AidList::aname($this->coid, $aid),
          'balance' => -$h['total_transaction'],
          'balance_base' => -$h['total_transaction_exchange'],
          'closing' => -$h['closing'],
          'closing_base' => -$h['closing_exchange'],
          'link' => $this->getLink($aid),
        ];
      }
    }
    $items['cash_total'] = [
      'balance' => -$total,
      'balance_base' => -$total_exc,
      'closing' => -$closing,
      'closing_base' => -$closing_exc,
    ];
    $this->totalAssets += -$total;
    $this->totalAssetsExc += -$total_exc;
    $this->totalClosingAssets += -$closing;
    $this->totalClosingAssetsExc += -$closing_exc;
    return $items;
  }

  /**
   * Builds the receivable section.
   *
   * @return array
   *   The receivable rows and receivable_total.
   */
  private function receivable() {
    $items = [];
    $total = 0;
    $total_exc = 0;
    $closing = 0;
    $closing_exc = 0;
    $list = [];
    foreach ($this->currencies as $currency => $name) {
      $aid = $this->settings->get('asset_account', $currency);
      if ($aid && !in_array($aid, $list)) {
        // Avoid duplicate account for same coid.
        array_push($list, $aid);
        $h = $this->getHistory($aid);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];
        $items['receivable'][] = [
          'aid' => $aid,
          'name' => AidList::aname($this->coid, $aid),
          'balance' => -$h['total_transaction'],
          'balance_base' => -$h['total_transaction_exchange'],
          'closing' => -$h['closing'],
          'closing_base' => -$h['closing_exchange'],
          'link' => $this->getLink($aid),
        ];
      }
    }
    $items['receivable_total'] = [
      'balance' => -$total,
      'balance_base' => -$total_exc,
      'closing' => -$closing,
      'closing_base' => -$closing_exc,
    ];
    $this->totalAssets += -$total;
    $this->totalAssetsExc += -$total_exc;
    $this->totalClosingAssets += -$closing;
    $this->totalClosingAssetsExc += -$closing_exc;
    return $items;
  }

  /**
   * Builds the payable section.
   *
   * @return array
   *   The payable rows and payable_total.
   */
  private function payable() {
    $items = [];
    $total = 0;
    $total_exc = 0;
    $closing = 0;
    $closing_exc = 0;
    $list = [];
    foreach ($this->currencies as $currency => $name) {
      $aid = $this->settings->get('liability_account', $currency);
      if ($aid && !in_array($aid, $list)) {
        // Avoid duplicate account for same coid.
        array_push($list, $aid);
        $h = $this->getHistory($aid);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];
        $items['payable'][] = [
          'aid' => $aid,
          'name' => AidList::aname($this->coid, $aid),
          'balance' => -$h['total_transaction'],
          'balance_base' => -$h['total_transaction_exchange'],
          'closing' => -$h['closing'],
          'closing_base' => -$h['closing_exchange'],
          'link' => $this->getLink($aid),
        ];
      }
    }
    $items['payable_total'] = [
      'balance' => -$total,
      'balance_base' => -$total_exc,
      'closing' => -$closing,
      'closing_base' => -$closing_exc,
    ];
    $this->totalLiabilities += -$total;
    $this->totalLiabilitiesExc += -$total_exc;
    $this->totalClosingLiabilities += -$closing;
    $this->totalClosingLiabilitiesExc += -$closing_exc;
    return $items;
  }

  /**
   * Computes the monthly amortization amount from pending asset schedules.
   *
   * @return float
   *   The next monthly amortization amount in base currency.
   */
  private function getAmortization() {
    $value_total = 0;
    $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                . "ON a.id = b.asid "
                . "WHERE amort_record <> :r "
                . "AND amort_status <> :s "
                . "AND coid = :coid";
    $data = Database::getConnection('external_db', 'external_db')
      ->query($query, [':r' => '', ':s' => 1, ':coid' => $this->coid]);
    while ($d = $data->fetchObject()) {
      $schedule = $d->amort_record !== NULL ? unserialize($d->amort_record) : [];
      foreach ($schedule['a'] as $key => $value) {
        if ($value['journal_reference'] == '' && $value['periods_balance'] > 0) {
          $date = strtotime($value['record_date']);
          if ($date >= $now = date('U')) {
            $rate = CurrencyData::rate($d->currency);
            $value_total += round($value['value'] / $rate, $this->rounding);
            break;
          }
        }
      }
    }
    return $value_total;
  }

  /**
   * Computes the average monthly overhead over the trailing window.
   *
   * @return float
   *   The average monthly overhead in base currency.
   */
  private function getAverageExpenses() {
    $start = date('Y-m', strtotime($this->to . ' - 1 year'));
    $query = "SELECT sum(value) as expenses, count(distinct left(date,7)) as months FROM {ek_journal} "
                . "WHERE coid=:coid AND aid like :aid and type=:type and date >= :d1 and date <= :d2";
    $expensesData = Database::getConnection('external_db', 'external_db')
      ->query($query, [
        ':coid' => $this->coid,
        ':aid' => $this->chart['expenses'] . '%',
        ':type' => 'debit',
        ':d1' => $start,
        ':d2' => $this->to,
      ])->fetchObject();
    $expenses = $expensesData->expenses ?? 0;
    // Divide by the number of distinct months actually present so the monthly
    // average is not distorted by a hardcoded 12 or by a partial first year.
    $expenses_months = $expensesData->months ?? 0;
    return $expenses_months > 0 ? round($expenses / $expenses_months, $this->rounding) : 0;
  }

  /**
   * Builds the tax sections and computes the net tax position.
   *
   * @return array
   *   The collect / deduct tax rows, balances and journal links.
   */
  private function tax() {
    $items = [];
    $items['collect_tax_1'] = AidList::aname($this->coid, $this->settings->get('stax_collect_aid'));
    $items['deduct_tax_1'] = AidList::aname($this->coid, $this->settings->get('stax_deduct_aid'));
    $items['collect_tax_1_aid'] = $this->settings->get('stax_collect_aid');
    $items['deduct_tax_1_aid'] = $this->settings->get('stax_deduct_aid');

    $h = $this->getHistory($items['collect_tax_1_aid']);
    $total_tax_collect = $h['total_transaction'];
    $total_tax_collect_exc = $h['total_transaction_exchange'];
    $total_closing_tax_collect = $h['closing'];
    $total_closing_tax_collect_exc = $h['closing_exchange'];

    $h = $this->getHistory($items['deduct_tax_1_aid']);
    $total_tax_deduct = $h['total_transaction'];
    $total_tax_deduct_exc = $h['total_transaction_exchange'];
    $total_closing_tax_deduct = $h['closing'];
    $total_closing_tax_deduct_exc = $h['closing_exchange'];

    $items['collect_tax_1_transaction'] = -$total_tax_collect_exc;
    $items['collect_tax_1_closing'] = -$total_closing_tax_collect_exc;
    $items['deduct_tax_1_transaction'] = $total_tax_deduct_exc;
    $items['deduct_tax_1_closing'] = $total_closing_tax_deduct_exc;

    $items['collect_tax_2'] = AidList::aname($this->coid, $this->settings->get('wtax_collect_aid'));
    $items['deduct_tax_2'] = AidList::aname($this->coid, $this->settings->get('wtax_deduct_aid'));
    $items['collect_tax_2_aid'] = $this->settings->get('wtax_collect_aid');
    $items['deduct_tax_2_aid'] = $this->settings->get('wtax_deduct_aid');

    $h = $this->getHistory($items['collect_tax_2_aid']);
    $total_tax2_collect = $h['total_transaction'];
    $total_tax2_collect_exc = $h['total_transaction_exchange'];
    $total_closing_tax2_collect = $h['closing'];
    $total_closing_tax2_collect_exc = $h['closing_exchange'];

    $h = $this->getHistory($items['deduct_tax_2_aid']);
    $total_tax2_deduct = $h['total_transaction'];
    $total_tax2_deduct_exc = $h['total_transaction_exchange'];
    $total_closing_tax2_deduct = $h['closing'];
    $total_closing_tax2_deduct_exc = $h['closing_exchange'];

    $items['collect_tax_2_transaction'] = -$total_tax2_collect_exc;
    $items['collect_tax_2_closing'] = -$total_closing_tax2_collect_exc;
    $items['deduct_tax_2_transaction'] = $total_tax2_deduct_exc;
    $items['deduct_tax_2_closing'] = $total_closing_tax2_deduct_exc;

    $items['tax_1_balance_transaction'] = round($total_tax_deduct_exc - $total_tax_collect_exc, $this->rounding);
    $items['tax_1_balance_closing'] = round($total_closing_tax_deduct_exc - $total_closing_tax_collect_exc, $this->rounding);
    $items['tax_2_balance_transaction'] = round($total_tax2_deduct_exc - $total_tax2_collect_exc, $this->rounding);
    $items['tax_2_balance_closing'] = round($total_closing_tax2_deduct_exc - $total_closing_tax2_collect_exc, $this->rounding);
    $items['tax_total_balance_transaction'] = round($items['tax_1_balance_transaction'] + $items['tax_2_balance_transaction'], $this->rounding);
    $items['tax_total_balance_closing'] = round($items['tax_1_balance_closing'] + $items['tax_2_balance_closing'], $this->rounding);

    // Journal links; the tax 1 links were missing from the legacy template.
    $items['collect_tax_1_aid_link'] = $this->getLink($items['collect_tax_1_aid'], 'journal');
    $items['deduct_tax_1_aid_link'] = $this->getLink($items['deduct_tax_1_aid'], 'journal');
    $items['collect_tax_2_aid_link'] = $this->getLink($items['collect_tax_2_aid'], 'journal');
    $items['deduct_tax_2_aid_link'] = $this->getLink($items['deduct_tax_2_aid'], 'journal');

    // Net tax position in transaction (local) currency, mirroring the base
    // balance so the local and base grand totals use the same components.
    $this->netTaxLocal = ($total_tax_deduct - $total_tax_collect) + ($total_tax2_deduct - $total_tax2_collect);
    $this->netTaxClosingLocal = ($total_closing_tax_deduct - $total_closing_tax_collect) + ($total_closing_tax2_deduct - $total_closing_tax2_collect);

    return $items;
  }

}
