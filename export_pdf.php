<?php
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

include "db.php";

$dompdf = new Dompdf();

$html = "<h2>Bookings Report</h2>";

$html .= "<table border='1' width='100%' cellspacing='0' cellpadding='5'>
<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Date</th>
<th>Time</th>
<th>Payment</th>
</tr>";

$sql = "SELECT * FROM bookings";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()){
    $html .= "<tr>
    <td>".$row['id']."</td>
    <td>".$row['name']."</td>
    <td>".$row['email']."</td>
    <td>".$row['date']."</td>
    <td>".$row['time']."</td>
    <td>".$row['payment']."</td>
    </tr>";
}

$html .= "</table>";

$dompdf->loadHtml($html);
$dompdf->setPaper("A4", "portrait");
$dompdf->render();

$dompdf->stream("bookings.pdf");
?>