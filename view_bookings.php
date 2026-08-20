<?php
include "db.php";

$sql = "SELECT * FROM bookings";
$result = $conn->query($sql);

echo "<h2>All Bookings</h2>";

if($result->num_rows > 0){

    echo "<table border='1' cellpadding='10'>";
    echo "<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Date</th>
    <th>Time</th>
    <th>Payment</th>
    </tr>";

    while($row = $result->fetch_assoc()){

        echo "<tr>";
        echo "<td>".$row['id']."</td>";
        echo "<td>".$row['name']."</td>";
        echo "<td>".$row['email']."</td>";
        echo "<td>".$row['date']."</td>";
        echo "<td>".$row['time']."</td>";
        echo "<td>".$row['payment']."</td>";
        echo "</tr>";

    }

    echo "</table>";

}else{
    echo "No data found";
}
?>