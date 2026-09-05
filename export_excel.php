<?php
require_once __DIR__ . '/admin_auth.php';
requireAdmin();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        tour_name,
        date,
        time,
        payment,
        payment_status,
        COALESCE(
            NULLIF(mpesa_receipt, ''),
            NULLIF(payment_reference, ''),
            NULLIF(checkout_request_id, ''),
            ''
        ) AS reference_code,
        amount,
        created_at
    FROM bookings
    WHERE amount > 1
      AND YEAR(created_at) = ?
    ORDER BY created_at DESC, id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare Excel export.');
}

$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$confirmedRevenue = 0.0;

while ($result && ($row = $result->fetch_assoc())) {
    $rows[] = $row;
    if (strtolower(trim((string)($row['payment_status'] ?? ''))) === 'paid') {
        $confirmedRevenue += (float)($row['amount'] ?? 0);
    }
}
$stmt->close();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Live Bookings');

$spreadsheet->getProperties()
    ->setCreator('Sprinter Tours & Safaris')
    ->setLastModifiedBy('Sprinter Tours & Safaris')
    ->setTitle("Admin Live Bookings Report - {$year}")
    ->setSubject('Live bookings and confirmed revenue')
    ->setDescription('Admin report containing live business booking records only.');

$sheet->mergeCells('A1:L1');
$sheet->setCellValue('A1', 'Sprinter Tours & Safaris');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF0B5D3B');
$sheet->getRowDimension(1)->setRowHeight(28);

$sheet->mergeCells('A2:L2');
$sheet->setCellValue('A2', "Admin Live Bookings Report · {$year}");
$sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setARGB('FF6B756F');
$sheet->getRowDimension(2)->setRowHeight(21);

$sheet->setCellValue('A4', 'Live Records');
$sheet->setCellValue('B4', count($rows));
$sheet->setCellValue('D4', 'Confirmed Revenue');
$sheet->setCellValue('E4', $confirmedRevenue);
$sheet->setCellValue('G4', 'Reporting Integrity');
$sheet->setCellValue('H4', 'KES 1 or less excluded');

$summaryLabel = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FF5D665F']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F6F0']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E5E2']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];
$summaryValue = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FF176B45']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEAF3ED']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E5E2']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$sheet->getStyle('A4')->applyFromArray($summaryLabel);
$sheet->getStyle('D4')->applyFromArray($summaryLabel);
$sheet->getStyle('G4')->applyFromArray($summaryLabel);
$sheet->getStyle('B4')->applyFromArray($summaryValue);
$sheet->getStyle('E4')->applyFromArray($summaryValue);
$sheet->getStyle('E4')->getNumberFormat()->setFormatCode('"KES" #,##0');
$sheet->getStyle('H4')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FF8A651C']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3EEDB']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E5E2']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(4)->setRowHeight(24);

$headers = [
    'Booking ID', 'Customer', 'Email', 'Phone', 'Tour', 'Travel Date',
    'Travel Time', 'Payment Method', 'Payment Status', 'Reference / Receipt',
    'Amount (KES)', 'Created'
];

foreach ($headers as $i => $header) {
    $sheet->setCellValue([$i + 1, 6], $header);
}

$sheet->getStyle('A6:L6')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B5D3B']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E5E2']]],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
]);
$sheet->getRowDimension(6)->setRowHeight(25);

$currentRow = 7;
foreach ($rows as $row) {
    $status = trim((string)($row['payment_status'] ?? ''));
    if ($status === '') {
        $status = 'Pending';
    }

    $sheet->setCellValue("A{$currentRow}", (int)$row['id']);
    $sheet->setCellValue("B{$currentRow}", (string)($row['name'] ?? ''));
    $sheet->setCellValue("C{$currentRow}", (string)($row['email'] ?? ''));
    $sheet->setCellValueExplicit("D{$currentRow}", (string)($row['phone'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("E{$currentRow}", trim((string)($row['tour_name'] ?? '')) !== '' ? $row['tour_name'] : 'Not specified');
    $sheet->setCellValueExplicit("F{$currentRow}", (string)($row['date'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("G{$currentRow}", (string)($row['time'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("H{$currentRow}", (string)($row['payment'] ?? ''));
    $sheet->setCellValue("I{$currentRow}", $status);
    $sheet->setCellValueExplicit("J{$currentRow}", ((string)($row['reference_code'] ?? '')) !== '' ? (string)$row['reference_code'] : '—', DataType::TYPE_STRING);
    $sheet->setCellValue("K{$currentRow}", (float)($row['amount'] ?? 0));
    $sheet->setCellValueExplicit("L{$currentRow}", (string)($row['created_at'] ?? ''), DataType::TYPE_STRING);

    $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E5E2']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getStyle("K{$currentRow}")->getNumberFormat()->setFormatCode('"KES" #,##0');

    $statusLower = strtolower($status);
    if ($statusLower === 'paid') {
        $sheet->getStyle("I{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF176B45']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE6F4EA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    } elseif ($statusLower === 'cancelled') {
        $sheet->getStyle("I{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF8A651C']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF7E8E8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    $sheet->getRowDimension($currentRow)->setRowHeight(21);
    $currentRow++;
}

if (count($rows) === 0) {
    $sheet->mergeCells('A7:L7');
    $sheet->setCellValue('A7', "No live booking records found for {$year}.");
    $sheet->getStyle('A7')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FF6B756F']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F6F0']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
}

$lastRow = max(6, $currentRow - 1);
$sheet->setAutoFilter("A6:L{$lastRow}");
$sheet->freezePane('A7');

$widths = [
    'A' => 12, 'B' => 22, 'C' => 28, 'D' => 17, 'E' => 24, 'F' => 15,
    'G' => 13, 'H' => 17, 'I' => 17, 'J' => 23, 'K' => 16, 'L' => 21,
];
foreach ($widths as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.5);
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 6);

$filename = "sprinter-live-bookings-{$year}.xlsx";

// Prevent stray output/BOM from corrupting the XLSX download.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;
