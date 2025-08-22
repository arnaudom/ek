<?php

use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\Journal;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\CurrencyData;


$journal = new Journal();
$settings = new CompanySettings($coid);
$access = AccessCheck::GetCompanyByUser();
$company = implode(',', $access);
$Currencies = CurrencyData::listcurrency(1);

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $settings->get('fiscal_month'), $settings->get('fiscal_year'));
$from = date('Y-m-d', strtotime($settings->get('fiscal_year') . '-' . $settings->get('fiscal_month') . '-' . $daysInMonth . ' - 1 year + 1 day'));
$to = date('Y-m-d');
$total_assets = 0;
$total_assets_exc = 0;
$total_closing_assets = 0;
$total_closing_assets_exc = 0;
$total_liabilities = 0;
$total_liabilities_exc = 0;
$total_closing_liabilities = 0;
$total_closing_liabilities_exc = 0;
$grand_total = 0;
$grand_total_exc = 0;

// Create new PHPExcel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); 

//formatting
$HalignCenter = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
);

$HalignLeft = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
    ),
);
$HalignRight = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
    ),
);
$Fontgrey = array(
    'font' => array(
        'color' => array('rgb' => '6a6a6a'),
        'size' => 10,
        ));

// Set properties
$objPHPExcel->getProperties()->setCreator("ek")
        ->setLastModifiedBy("")
        ->setTitle("Cash flow")
        ->setSubject("Cash flow")
        ->setDescription("Cash flow analysis for Excel generated using PHP classes.")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("file");


$objPHPExcel->getActiveSheet()->setTitle('Cash balance');
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(70);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
$objPHPExcel->getActiveSheet()->setBreak('E1', \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN);
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// title
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(12);
$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
$text = (string) t("Cash flow") . ' '
        . (string) t('from') . ' ' . $from . ' '
        . (string) t('to') . ' ' . $to . ' (' . $items['baseCurrency'] . ')';

$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $text);
$objPHPExcel->getActiveSheet()->getStyle('A3:D3')->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle('A3:D3')->getFont()->setSize(10);
$objPHPExcel->getActiveSheet()->getStyle('A3:D3')->getFont()->setBold(true);

$objPHPExcel->getActiveSheet()->getStyle("A3:D3")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A3:D3")->getFill()->getStartColor()->setRGB('acacac');
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('A3', (string) t('Account'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('B3', (string) t('Name'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('C3', (string) t('Transactions'));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue('D3', (string) t('Closing'));
// Data
$columns = Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');


$i = 3;

/*
 * Cash - bank
 */
$i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Bank'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:D$i");
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('CCE5FF');

$query = "SELECT DISTINCT aid FROM {ek_bank_accounts} a INNER JOIN {ek_bank} b "
        . "ON a.bid = b.id WHERE coid=:coid";
$a = array(':coid' => $coid);
$result = Database::getConnection('external_db', 'external_db')
        ->query($query, $a);
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;

while ($r = $result->fetchObject()) {

    $i++;
    $histo = $journal->history(serialize(array(
        'aid' => $r->aid,
        'coid' => $coid,
        'from' => $from,
        'to' => $to
                    )
            )
    );


    $h = unserialize($histo);
    $total += $h['total_transaction'];
    $total_exc += $h['total_transaction_exchange'];
    $closing += $h['closing'];
    $closing_exc += $h['closing_exchange'];

    $name = AidList::aname($coid, $r->aid);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $r->aid);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $name);
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round(-$h['total_transaction_exchange'], $items['rounding']));
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round(-$h['closing_exchange'], $items['rounding']));
    $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(10);
    $objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(false);
}

$total_assets += -$total;
$total_assets_exc += -$total_exc;
$total_closing_assets += -$closing;
$total_closing_assets_exc += -$closing_exc;


$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Total'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round(-$total_exc, $items['rounding']));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round(-$closing_exc, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

/*
 * Cash - account
 */
$i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Cash'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:D$i");
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('CCE5FF');
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
$list = [];
foreach ($Currencies as $currency => $name) {

    $aid = $settings->get('cash_account', $currency);

    if ($aid && !in_array($aid, $list)) {
        $i++;
        //avoid duplicate account for same coid
        array_push($list, $aid);
        $histo = $journal->history(serialize(array(
            'aid' => $aid,
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                        )
                )
        );


        $h = unserialize($histo);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];

        $name = AidList::aname($coid, $aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $name);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round(-$h['total_transaction_exchange'], $items['rounding']));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round(-$h['closing_exchange'], $items['rounding']));
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getFont()->setSize(10);
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getFont()->setBold(false);
    }
}

$total_assets += -$total;
$total_assets_exc += -$total_exc;
$total_closing_assets += -$closing;
$total_closing_assets_exc += -$closing_exc;

$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Total'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round(-$total_exc, $items['rounding']));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round(-$closing_exc, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);


/*
 * Receivable
 */
$i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Receivable'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:D$i");
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('CCE5FF');
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
$list = [];
foreach ($Currencies as $currency => $name) {

    $aid = $settings->get('asset_account', $currency);

    if ($aid && !in_array($aid, $list)) {
        $i++;
        //avoid duplicate account for same coid
        array_push($list, $aid);
        $histo = $journal->history(serialize(array(
            'aid' => $aid,
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                        )
                )
        );


        $h = unserialize($histo);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];

        $name = AidList::aname($coid, $aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $name);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round(-$h['total_transaction_exchange'], $items['rounding']));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round(-$h['closing_exchange'], $items['rounding']));
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getFont()->setSize(10);
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getFont()->setBold(false);
    }
}


$total_assets += -$total;
$total_assets_exc += -$total_exc;
$total_closing_assets += -$closing;
$total_closing_assets_exc += -$closing_exc;
$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Total'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round(-$total_exc, $items['rounding']));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round(-$closing_exc, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

/*
 * Total assets
 */
$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Total current assets'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round($total_assets_exc, $items['rounding']));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round($total_closing_assets_exc, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('99CCFF');

/*
 * Payable
 */
$i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Payable'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:D$i");
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('CCE5FF');
$total = 0;
$total_exc = 0;
$closing = 0;
$closing_exc = 0;
$list = [];
foreach ($Currencies as $currency => $name) {

    $aid = $settings->get('liability_account', $currency);

    if ($aid && !in_array($aid, $list)) {
        $i++;
        //avoid duplicate account for same coid
        array_push($list, $aid);
        $histo = $journal->history(serialize(array(
            'aid' => $aid,
            'coid' => $coid,
            'from' => $from,
            'to' => $to
                        )
                )
        );


        $h = unserialize($histo);
        $total += $h['total_transaction'];
        $total_exc += $h['total_transaction_exchange'];
        $closing += $h['closing'];
        $closing_exc += $h['closing_exchange'];

        $i++;
        $name = AidList::aname($coid, $aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", $aid);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$i", $name);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round($h['total_transaction_exchange'], $items['rounding']));
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round($h['closing_exchange'], $items['rounding']));
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getFont()->setSize(10);
        $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getFont()->setBold(false);
    }
}

$total_liabilities += -$total;
$total_liabilities_exc += -$total_exc;
$total_closing_liabilities += -$closing;
$total_closing_liabilities_exc += -$closing_exc;

$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Total current liabilities'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round($total_exc, $items['rounding']));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round($closing_exc, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('99CCFF');

/*
 * Grand total
 */
$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Grand total'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round($total_assets_exc + $total_liabilities_exc, $items['rounding']));
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", round($total_closing_assets_exc + $total_closing_liabilities_exc, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFill()->getStartColor()->setRGB('66B2FF');


/*
 * Overhead
 */
$i++;
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setName('Arial');
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Overhead'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:D$i");


/*
 * Average expenses 
 */

$start = date('Y-m', strtotime($to . ' - 1 year'));

$query = "SELECT sum(value) as expenses FROM {ek_journal} "
        . "WHERE coid=:coid AND aid like :aid and type=:type and date >= :d1 and date <= :d2";
$a = [
    ':coid' => $coid,
    ':aid' => $chart['expenses'] . '%',
    ':type' => 'debit',
    ':d1' => $start,
    ':d2' => $to
];

$expenses = Database::getConnection('external_db', 'external_db')
        ->query($query, $a)
        ->fetchField();


$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Per month'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round($expenses / 12, $items['rounding']));
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
$objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
$objPHPExcel->getActiveSheet()->mergeCells("C$i:D$i");


/*
 * Depreciation
 */

if (isset($extract['amortization'])) {

    $items['amortization'] = 0;
    $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
            . "ON a.id = b.asid "
            . "WHERE amort_record <> :r "
            . "AND amort_status <> :s "
            . "AND coid = :coid";
    $a = array(
        ':r' => '',
        ':s' => 1,
        ':coid' => $coid,
    );

    $data = Database::getConnection('external_db', 'external_db')
            ->query($query, $a);

    While ($d = $data->fetchObject()) {

        $schedule = unserialize($d->amort_record);
        foreach ($schedule['a'] as $key => $value) {
            if ($value['journal_reference'] == '' && $value['periods_balance'] > 0) {

                $date = strtotime($value['record_date']);
                if ($date >= $now = date('U')) {
                    $rate = CurrencyData::rate($d->currency);

                    $items['amortization'] += round($value['value'] / $rate, $items['rounding']);
                    break;
                }
            }
        }
    }



    $i++;
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Monthly amortization'));
    $objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", round($items['amortization'], $items['rounding']));
    $objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
    $objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);
    $objPHPExcel->getActiveSheet()->getStyle("C$i:D$i")->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $objPHPExcel->getActiveSheet()->mergeCells("C$i:D$i");
}

/*
 * Ratio 1
 */
$ratio = round(($total_assets_exc + $total_liabilities_exc) / ((($expenses / 12) - $items['amortization'])), $items['rounding']);
$ratio2 = round(($total_closing_assets_exc + $total_closing_liabilities_exc) / (($expenses / 12) - $items['amortization']), $items['rounding']);
$i++;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$i", (STRING) t('Ratio: cash flow / overhead'));
$objPHPExcel->getActiveSheet()->mergeCells("A$i:B$i");
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$i", $ratio);
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$i", $ratio2);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setSize(11);
$objPHPExcel->getActiveSheet()->getStyle("A$i:D$i")->getFont()->setBold(true);


// Set header and footer. When no different headers for odd/even are used, odd header is assumed.;
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddHeader('&L&Bcash balance&RPrinted on &D');
$objPHPExcel->getActiveSheet()->getHeaderFooter()->setOddFooter('&L&B' . $objPHPExcel->getProperties()->getTitle() . '&RPage &P of &N');


// Set page orientation and size
$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$fileName = "cash_flow.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: max-age=0');
header("Content-Disposition: attachment;filename=$fileName");
header('Cache-Control: max-age=0');
$objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit;

