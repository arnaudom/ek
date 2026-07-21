<?php

/**
 * @file
 * Excel export template for the operation performance report.
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

$extdb = \Drupal\Core\Database\Database::getConnection('external_db', 'external_db');
$companyName = $extdb->query("SELECT name FROM {ek_company} WHERE id = :id", [':id' => $coid])->fetchField();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Operation Performance');
$headerStyleTop = [
    'font' => ['bold' => true, 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF1']],
];
$headerStyle = [
    'font' => ['bold' => true, 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF1']],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM]],
];
$titleStyle = ['font' => ['bold' => true, 'size' => 14]];
$subtitleStyle = ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '555555']]];
$totalStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF1']],
    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
];
$wipStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF9E6']]];
$rightAlign = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]];
$numberFormat = '#,##0.' . str_repeat('0', $rounding);
$percentFormat = '0.0%';

$row = 1;

// Title.
$sheet->setCellValue("A{$row}", 'Operation Performance Report');
$sheet->getStyle("A{$row}")->applyFromArray($titleStyle);
$sheet->mergeCells("A{$row}:J{$row}");
$row++;

$sheet->setCellValue("A{$row}", "Company: {$companyName}  |  Fiscal Year: {$year}  |  Currency: {$baseCurrency}");
$sheet->getStyle("A{$row}")->applyFromArray($subtitleStyle);
$sheet->mergeCells("A{$row}:J{$row}");
$row++;

$sheet->setCellValue("A{$row}", 'Period: ' . $data['fiscal_dates']['from'] . ' to ' . $data['fiscal_dates']['to']);
$sheet->mergeCells("A{$row}:J{$row}");
$row++;

$sheet->setCellValue("A{$row}", 'Generated: ' . date('Y-m-d H:i'));
$sheet->mergeCells("A{$row}:J{$row}");
$row += 2;

// Summary section.
$sheet->setCellValue("A{$row}", 'Summary');
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:B{$row}");
$row++;

$summary = $data['summary'];
$summaryRows = [
    ['Total Revenue', $summary['total_revenue']],
    ['Total Cost', $summary['total_cost']],
    ['Gross Margin', $summary['gross_margin']],
    ['%', $summary['gm_percent'] / 100],
    ['AR Outstanding', $summary['ar_outstanding']],
    ['AP Outstanding', $summary['ap_outstanding']],
    ['Net Position', $summary['net_position']],
    ['Project Count', $summary['project_count']],
];


$row++;

// Period Activity.
$sheet->setCellValue("A{$row}", 'Period Activity');
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:B{$row}");
$row++;

$sheet->setCellValue("A{$row}", 'Account Receivable (period)');
$sheet->setCellValue("B{$row}", $data['period_activity']['ar']);
$sheet->getStyle("B{$row}")->applyFromArray($rightAlign);
$sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($numberFormat);
$row++;

$sheet->setCellValue("A{$row}", 'Account Payable (period)');
$sheet->setCellValue("B{$row}", $data['period_activity']['ap']);
$sheet->getStyle("B{$row}")->applyFromArray($rightAlign);
$sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($numberFormat);
$row += 3;

// Single Project Detail table.
$headers = ['', '','Total for period', '','', '','Total outstanding per project', '', ''];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue("{$col}{$row}", $h);
    $sheet->getStyle("{$col}{$row}")->applyFromArray($headerStyleTop);
    $col++;
}
$sheet->mergeCells("A{$row}:B{$row}");
$sheet->mergeCells("C{$row}:D{$row}");
$sheet->mergeCells("E{$row}:F{$row}");
$sheet->mergeCells("G{$row}:H{$row}");
$row++;
$headers = ['Project Code', 'Status', 'Invoices', 'Purchases', 'Gross Margin', '%', 'Invoices', 'Purchases', ''];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue("{$col}{$row}", $h);
    $sheet->getStyle("{$col}{$row}")->applyFromArray($headerStyle);
    $col++;
}
$row++;
$sum_row_in = $row;
foreach ($data['projects'] as $project) {
    $sheet->setCellValue("A{$row}", $project['pcode']);
    $sheet->setCellValue("B{$row}", $project['status']);
    $sheet->setCellValue("C{$row}", $project['revenue']);
    $sheet->setCellValue("D{$row}", $project['cost']);
    if ($project['revenue'] !== null && $project['revenue'] <= 0) {
        $sheet->getStyle("C{$row}")->getFont()->getColor()->setRGB('C0392B');
    }
    if ($project['cost'] !== null && $project['cost'] <= 0) {
        $sheet->getStyle("D{$row}")->getFont()->getColor()->setRGB('C0392B');
    }

    // Gross margin only for completed projects.
    if ($project['gross_margin'] !== null) {
        $sheet->setCellValue("E{$row}", $project['gross_margin']);
        $sheet->setCellValue("F{$row}", $project['gm_percent'] / 100);
    } else {
        $sheet->setCellValue("E{$row}", '--');
        $sheet->setCellValue("F{$row}", '--');
    }

    $sheet->setCellValue("G{$row}", $project['ar_outstanding']);
    $sheet->setCellValue("H{$row}", $project['ap_outstanding']);
    $sheet->setCellValue("I{$row}", $project['wip'] ? 'incomplete' : '');

    $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode($numberFormat);
    $sheet->getStyle("C{$row}:D{$row}")->applyFromArray($rightAlign);
    if ($project['gross_margin'] !== null) {
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode($numberFormat);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($percentFormat);
    }
    $sheet->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode($numberFormat);
    $sheet->getStyle("G{$row}:H{$row}")->applyFromArray($rightAlign);

    if ($project['wip']) {
        $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($wipStyle);
    }
    if ($project['gross_margin'] !== null && $project['gross_margin'] < 0) {
        $sheet->getStyle("E{$row}")->getFont()->getColor()->setRGB('C0392B');
    }
    $row++;
}
$sum_row_out = $row;
// Totals row.
$totals = $data['totals'];
$sheet->setCellValue("A{$row}", 'Totals');
$sheet->setCellValue("B{$row}", '');
$sheet->setCellValue("C{$row}", "=SUM(C$sum_row_in:C$sum_row_out)");
$sheet->setCellValue("D{$row}", "=SUM(D$sum_row_in:D$sum_row_out)");
$sheet->setCellValue("E{$row}", $totals['gross_margin']);
$sheet->setCellValue("F{$row}", $totals['gm_percent'] / 100);
$sheet->setCellValue("G{$row}", "=SUM(G$sum_row_in:G$sum_row_out)");
$sheet->setCellValue("H{$row}", "=SUM(G$sum_row_in:H$sum_row_out)");
$sheet->setCellValue("I{$row}", '');

$sheet->getStyle("A{$row}:I{$row}")->applyFromArray($totalStyle);
$sheet->getStyle("C{$row}:E{$row}")->getNumberFormat()->setFormatCode($numberFormat);
$sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($percentFormat);
$sheet->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode($numberFormat);

// Column widths.
$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(16);
$sheet->getColumnDimension('D')->setWidth(16);
$sheet->getColumnDimension('E')->setWidth(16);
$sheet->getColumnDimension('F')->setWidth(10);
$sheet->getColumnDimension('G')->setWidth(16);
$sheet->getColumnDimension('H')->setWidth(16);
$sheet->getColumnDimension('I')->setWidth(20);

// Output.
$writer = new Xlsx($spreadsheet);
$filename = 'operation_performance_' . $coid . '_' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;