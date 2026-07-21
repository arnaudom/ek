<?php

namespace Drupal\ek_finance;

use Drupal\Core\Database\Database;
use Drupal\ek_finance\Service\JournalService;
use Drupal\ek_admin\CompanySettings;

/**
 * Aggregates operation performance data for management reporting.
 *
 * Tracks Account Payable, Account Receivable, Gross Margin, and Operating
 * Profit per project/operation, filtered by company and fiscal year.
 *
 * Data flows through ek_journal → ek_sales_invoice / ek_sales_purchase →
 * ek_project (indirect linkage via pcode).
 */
class OperationPerformanceData {

    /**
     * The company ID.
     *
     * @var string|int
     */
    private $coid;

    /**
     * The fiscal year.
     *
     * @var string
     */
    private $year;

    /**
     * The base currency code.
     *
     * @var string
     */
    private $baseCurrency;

    /**
     * Decimal rounding precision.
     *
     * @var int
     */
    private $rounding;

    /**
     * The chart of accounts structure.
     *
     * @var array
     */
    private $chart;

    /**
     * Constructs an OperationPerformanceData object.
     *
     * @param string|int $coid
     *   The company ID.
     * @param string $year
     *   The fiscal year (YYYY).
     * @param string $baseCurrency
     *   The base currency code.
     * @param int $rounding
     *   Decimal rounding precision.
     * @param array $chart
     *   The chart of accounts structure.
     */
    public function __construct($coid, $year, $baseCurrency, $rounding, $chart) {
        $this->coid = $coid;
        $this->year = $year;
        $this->baseCurrency = $baseCurrency;
        $this->rounding = $rounding;
        $this->chart = $chart;
    }

    /**
     * Computes the fiscal year date range based on company fiscal month settings.
     *
     * Uses the same logic as JournalService::getFiscalDates().
     * For a fiscal year ending in month M, the period runs from
     * (M+1)/01/(year-1) to M/end/year.
     *
     * @return array
     *   ['from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD']
     */
    private function getFiscalDates() {
        $company = new CompanySettings($this->coid);
        $fiscalMonth = $company->get('fiscal_month');

        if (empty($fiscalMonth) || $fiscalMonth == '12') {
            // Calendar year: Jan 1 – Dec 31.
            return [
                'from' => $this->year . '-01-01',
                'to'   => $this->year . '-12-31',
            ];
        }

        // Fiscal year ends in month $fiscalMonth of $this->year.
        // It starts on the 1st of ($fiscalMonth + 1) of the previous calendar year.
        $endDate = new \DateTime($this->year . '-' . $fiscalMonth . '-01');
        $endDate->modify('last day of this month');
        $to = $endDate->format('Y-m-d');

        $startMonth = (int) $fiscalMonth + 1;
        $startYear = (int) $this->year - 1;
        $from = $startYear . '-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01';

        return [
            'from' => $from,
            'to'   => $to,
        ];
    }

    /**
     * Computes the full operation performance report.
     *
     * @return array
     *   Structured report data with 'projects', 'totals', 'period_activity',
     *   'wip_projects', and 'summary' keys.
     */
    public function computeReport() {
        $projects = $this->getProjectsWithActivity();
        $pcodes = array_keys($projects);

        if (empty($pcodes)) {
            return [
                'projects' => [],
                'totals' => [],
                'period_activity' => [],
                'summary' => [],
                'fiscal_dates' => $this->getFiscalDates(),
            ];
        }

        $statuses = $this->getProjectStatuses($pcodes);
        $outstanding = $this->getOutstandingBalances($pcodes);
        $periodActivity = $this->getPeriodActivity();
        $fiscalDates = $this->getFiscalDates();

        $allProjects = [];
        $totals = [
            'revenue' => 0,
            'cost' => 0,
            'gross_margin' => 0,
            'ar_outstanding' => 0,
            'ap_outstanding' => 0,
            'project_count' => 0,
        ];

        foreach ($projects as $pcode => $data) {
            $revenue = round($data['invoice_amount'] ?? 0, $this->rounding);
            $cost = round($data['purchase_amount'] ?? 0, $this->rounding);
            $status = $statuses[$pcode] ?? '--';
            $arOutstanding = round($outstanding[$pcode]['ar'] ?? 0, $this->rounding);
            $apOutstanding = round($outstanding[$pcode]['ap'] ?? 0, $this->rounding);

            // WIP detection: has invoices but no purchases, or purchases but no invoices.
            $hasInvoices = ($data['invoice_count'] ?? 0) > 0;
            $hasPurchases = ($data['purchase_count'] ?? 0) > 0;
            $wipFlag = ($hasInvoices xor $hasPurchases);

            // Gross margin only accurate for completed projects.
            $isCompleted = ($status === 'completed');
            $grossMargin = $isCompleted ? round($revenue - $cost, $this->rounding) : null;
            $gmPercent = ($isCompleted && $revenue > 0) ? round(($grossMargin / $revenue) * 100, 1) : null;

            $row = [
                'pcode' => $pcode,
                'pcode_url' => \Drupal::service('project.service')->geturl($pcode, null, null, true),
                'status' => $status,
                'revenue' => $revenue,
                'cost' => $cost,
                'gross_margin' => $grossMargin,
                'gm_percent' => $gmPercent,
                'ar_outstanding' => $arOutstanding,
                'ap_outstanding' => $apOutstanding,
                'wip' => $wipFlag,
                'invoice_count' => $data['invoice_count'] ?? 0,
                'purchase_count' => $data['purchase_count'] ?? 0,
            ];

            $allProjects[] = $row;

            // Only non-WIP projects with awarded/completed status contribute to totals.
            if (!$wipFlag && in_array($status, ['awarded', 'completed'])) {
                $totals['revenue'] += $revenue;
                $totals['cost'] += $cost;
                if ($isCompleted) {
                    $totals['gross_margin'] += $grossMargin;
                }
                $totals['ar_outstanding'] += $arOutstanding;
                $totals['ap_outstanding'] += $apOutstanding;
                $totals['project_count']++;
            }
        }

        // Round totals.
        $totals['revenue'] = round($totals['revenue'], $this->rounding);
        $totals['cost'] = round($totals['cost'], $this->rounding);
        $totals['gross_margin'] = round($totals['gross_margin'], $this->rounding);
        $totals['ar_outstanding'] = round($totals['ar_outstanding'], $this->rounding);
        $totals['ap_outstanding'] = round($totals['ap_outstanding'], $this->rounding);
        $totals['gm_percent'] = ($totals['revenue'] > 0)
            ? round(($totals['gross_margin'] / $totals['revenue']) * 100, 1)
            : 0;

        // Summary cards.
        $summary = [
            'total_revenue' => $totals['revenue'],
            'total_cost' => $totals['cost'],
            'gross_margin' => $totals['gross_margin'],
            'gm_percent' => $totals['gm_percent'],
            'ar_outstanding' => $totals['ar_outstanding'],
            'ap_outstanding' => $totals['ap_outstanding'],
            'net_position' => round($totals['ar_outstanding'] - $totals['ap_outstanding'], $this->rounding),
            'project_count' => $totals['project_count'],
        ];

        return [
            'projects' => $allProjects,
            'totals' => $totals,
            'period_activity' => $periodActivity,
            'summary' => $summary,
            'fiscal_dates' => $fiscalDates,
        ];
    }

    /**
     * Queries journal entries linked to sales invoices and purchases,
     * grouped by project code.
     *
     * @return array
     *   Associative array keyed by pcode with invoice/purchase amounts and counts.
     */
    private function getProjectsWithActivity() {
        $fiscalDates = $this->getFiscalDates();
        $date1 = $fiscalDates['from'];
        $date2 = $fiscalDates['to'];

        $extdb = Database::getConnection('external_db', 'external_db');

        // Invoice-side: journal entries with source='invoice', type='credit'.
        $query = $extdb->select('ek_journal', 'j');
        $query->leftJoin('ek_sales_invoice', 'i', 'i.id = j.reference AND j.source = :source_inv',
            [':source_inv' => 'invoice']);
        $query->addExpression('SUM(j.value)', 'invoice_amount');
        $query->addExpression('COUNT(DISTINCT i.id)', 'invoice_count');
        $query->addExpression('i.pcode', 'pcode');
        $query->condition('j.coid', $this->coid);
        $query->condition('j.date', $date1, '>=');
        $query->condition('j.date', $date2, '<=');
        $query->condition('j.source', 'invoice');
        $query->condition('j.type', 'credit');
        //$query->condition('j.exchange', '0');
        $query->isNotNull('i.pcode');
        $query->condition('i.pcode', '', '<>');
        $query->groupBy('i.pcode');
        $invoiceResults = $query->execute()->fetchAllAssoc('pcode');

        // Purchase-side: journal entries with source='purchase', type='debit'.
        $query = $extdb->select('ek_journal', 'j');
        $query->leftJoin('ek_sales_purchase', 'p', 'p.id = j.reference AND j.source = :source_pur',
            [':source_pur' => 'purchase']);
        $query->addExpression('SUM(j.value)', 'purchase_amount');
        $query->addExpression('COUNT(DISTINCT p.id)', 'purchase_count');
        $query->addExpression('p.pcode', 'pcode');
        $query->condition('j.coid', $this->coid);
        $query->condition('j.date', $date1, '>=');
        $query->condition('j.date', $date2, '<=');
        $query->condition('j.source', 'purchase');
        $query->condition('j.type', 'debit');
        //$query->condition('j.exchange', '0');
        $query->isNotNull('p.pcode');
        $query->condition('p.pcode', '', '<>');
        $query->groupBy('p.pcode');
        $purchaseResults = $query->execute()->fetchAllAssoc('pcode');

        // Merge invoice and purchase data by pcode.
        $allPcodes = array_unique(array_merge(array_keys($invoiceResults), array_keys($purchaseResults)));
        $projects = [];

        foreach ($allPcodes as $pcode) {
            $projects[$pcode] = [
                'invoice_amount' => isset($invoiceResults[$pcode]) ? (float) $invoiceResults[$pcode]->invoice_amount : 0,
                'invoice_count' => isset($invoiceResults[$pcode]) ? (int) $invoiceResults[$pcode]->invoice_count : 0,
                'purchase_amount' => isset($purchaseResults[$pcode]) ? (float) $purchaseResults[$pcode]->purchase_amount : 0,
                'purchase_count' => isset($purchaseResults[$pcode]) ? (int) $purchaseResults[$pcode]->purchase_count : 0,
            ];
        }

        return $projects;
    }

    /**
     * Batch-fetches project statuses from ek_project.
     *
     * @param array $pcodes
     *   Array of project codes.
     *
     * @return array
     *   Associative array [pcode => status].
     */
    private function getProjectStatuses(array $pcodes) {
        if (empty($pcodes)) {
            return [];
        }

        $extdb = Database::getConnection('external_db', 'external_db');
        $query = $extdb->select('ek_project', 'p');
        $query->fields('p', ['pcode', 'status']);
        $query->condition('p.pcode', $pcodes, 'IN');
        $results = $query->execute()->fetchAllKeyed();

        return $results ?: [];
    }

    /**
     * Computes outstanding (unpaid/partially paid) balances per project.
     *
     * AR = unpaid invoices (status 0 or 2).
     * AP = unpaid purchases (status 0 or 2).
     *
     * @param array $pcodes
     *   Array of project codes.
     *
     * @return array
     *   Associative array [pcode => ['ar' => amount, 'ap' => amount]].
     */
    private function getOutstandingBalances(array $pcodes) {
        $outstanding = [];
        foreach ($pcodes as $pcode) {
            $outstanding[$pcode] = ['ar' => 0, 'ap' => 0];
        }

        if (empty($pcodes)) {
            return $outstanding;
        }

        $extdb = Database::getConnection('external_db', 'external_db');

        // AR: unpaid/partially paid invoices.
        $query = $extdb->select('ek_sales_invoice', 'i');
        $query->addExpression('SUM(i.balancebase)', 'ar_amount');
        $query->addExpression('i.pcode', 'pcode');
        $query->condition('i.head', $this->coid);
        $query->condition('i.pcode', $pcodes, 'IN');
        $query->condition('i.status', [0, 2], 'IN');
        $query->groupBy('i.pcode');
        $arResults = $query->execute()->fetchAllAssoc('pcode');

        foreach ($arResults as $pcode => $row) {
            $outstanding[$pcode]['ar'] = (float) $row->ar_amount;
        }

        // AP: unpaid/partially paid purchases.
        $query = $extdb->select('ek_sales_purchase', 'p');
        $query->addExpression('SUM(p.balancebase)', 'ap_amount');
        $query->addExpression('p.pcode', 'pcode');
        $query->condition('p.head', $this->coid);
        $query->condition('p.pcode', $pcodes, 'IN');
        $query->condition('p.status', [0, 2], 'IN');
        $query->groupBy('p.pcode');
        $apResults = $query->execute()->fetchAllAssoc('pcode');

        foreach ($apResults as $pcode => $row) {
            $outstanding[$pcode]['ap'] = (float) $row->ap_amount;
        }

        return $outstanding;
    }

    /**
     * Computes total period AR and AP regardless of payment status.
     *
     * AR = sum of all invoice journal entries (source='invoice', type='credit').
     * AP = sum of all purchase journal entries (source='purchase', type='debit').
     *
     * @return array
     *   ['ar' => amount, 'ap' => amount].
     */
    private function getPeriodActivity() {
        $fiscalDates = $this->getFiscalDates();
        $date1 = $fiscalDates['from'];
        $date2 = $fiscalDates['to'];
        $extdb = Database::getConnection('external_db', 'external_db');

        // Total AR for period.
        $query = $extdb->select('ek_journal', 'j');
        $query->addExpression('SUM(j.value)', 'total');
        $query->condition('j.coid', $this->coid);
        $query->condition('j.date', $date1, '>=');
        $query->condition('j.date', $date2, '<=');
        $query->condition('j.source', 'invoice');
        $query->condition('j.type', 'credit');
        //$query->condition('j.exchange', '0');
        $arTotal = $query->execute()->fetchField();

        // Total AP for period.
        $query = $extdb->select('ek_journal', 'j');
        $query->addExpression('SUM(j.value)', 'total');
        $query->condition('j.coid', $this->coid);
        $query->condition('j.date', $date1, '>=');
        $query->condition('j.date', $date2, '<=');
        $query->condition('j.source', 'purchase');
        $query->condition('j.type', 'debit');
        //$query->condition('j.exchange', '0');
        $apTotal = $query->execute()->fetchField();

        return [
            'ar' => round((float) $arTotal, $this->rounding),
            'ap' => round((float) $apTotal, $this->rounding),
        ];
    }

}
