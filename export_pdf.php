<?php
require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/dompdf/autoload.inc.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$year = isset($_GET["year"]) ? (int) $_GET["year"] : (int) date("Y");
if ($year < 2000 || $year > 2100) {
    $year = (int) date("Y");
}

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
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
        COALESCE(NULLIF(mpesa_receipt, ''), NULLIF(payment_reference, ''), '') AS reference_code,
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
    exit("Unable to prepare PDF export.");
}

$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$totalLiveValue = 0.0;
$confirmedRevenue = 0.0;

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $amount = (float) $row["amount"];
    $totalLiveValue += $amount;

    if (strtolower(trim((string) $row["payment_status"])) === "paid") {
        $confirmedRevenue += $amount;
    }
}

$stmt->close();

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family:DejaVu Sans,sans-serif;color:#17231f;font-size:10px}
h1{font-size:20px;margin:0 0 4px;color:#0b5d3b}
.sub{color:#66736d;margin-bottom:18px}
.summary{margin-bottom:16px;padding:10px;border:1px solid #d9e2dd;background:#f7faf8}
.summary strong{margin-right:20px}
table{width:100%;border-collapse:collapse}
th{background:#0b5d3b;color:#fff;font-size:8px;text-transform:uppercase;padding:7px 5px}
td{border-bottom:1px solid #e3e8e5;padding:6px 5px;vertical-align:top;font-size:8px}
.test-note{margin-top:14px;color:#66736d;font-size:8px}
</style>
</head>
<body>
<h1>Sprinter Tours &amp; Safaris</h1>
<div class="sub">Admin Live Bookings Report · ' . h($year) . '</div>
<div class="summary">
<strong>Live records: ' . count($rows) . '</strong>
<strong>Confirmed revenue: KES ' . number_format($confirmedRevenue, 0) . '</strong>
</div>
<table>
<thead>
<tr>
<th>ID</th><th>Customer</th><th>Tour</th><th>Travel</th><th>Method</th>
<th>Status</th><th>Reference</th><th>Amount</th><th>Created</th>
</tr>
</thead>
<tbody>';

if (!$rows) {
    $html .= '<tr><td colspan="9">No live booking records found for this year.</td></tr>';
} else {
    foreach ($rows as $row) {
        $html .= '<tr>'
            . '<td>#' . (int) $row["id"] . '</td>'
            . '<td><strong>' . h($row["name"]) . '</strong><br>' . h($row["email"]) . '<br>' . h($row["phone"]) . '</td>'
            . '<td>' . h($row["tour_name"] ?: "Not specified") . '</td>'
            . '<td>' . h($row["date"]) . '<br>' . h($row["time"]) . '</td>'
            . '<td>' . h($row["payment"]) . '</td>'
            . '<td>' . h($row["payment_status"] ?: "Pending") . '</td>'
            . '<td>' . h($row["reference_code"] ?: "—") . '</td>'
            . '<td>KES ' . number_format((float) $row["amount"], 0) . '</td>'
            . '<td>' . h($row["created_at"]) . '</td>'
            . '</tr>';
    }
}

$html .= '
</tbody>
</table>
<div class="test-note">
Reporting integrity: transactions of KES 1 or less are excluded from this business export.
Paid revenue is counted only from records whose payment status is Paid.
</div>
</body>
</html>';

$options = new Options();
$options->set("isRemoteEnabled", false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, "UTF-8");
$dompdf->setPaper("A4", "landscape");
$dompdf->render();
$dompdf->stream(
    "sprinter-live-bookings-" . $year . ".pdf",
    ["Attachment" => true]
);
exit();
