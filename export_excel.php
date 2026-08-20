<?php
include "db.php";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=bookings.xls");

echo "ID\tName\tEmail\tDate\tTime\tPayment\n";

$sql = "SELECT * FROM bookings";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()){
    echo $row['id']."\t";
    echo $row['name']."\t";
    echo $row['email']."\t";
    echo $row['date']."\t";
    echo $row['time']."\t";
    echo $row['payment']."\n";
}
?>