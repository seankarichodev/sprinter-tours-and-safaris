<?php
include "db.php";

if(isset($_GET['id'])){

$id = $_GET['id'];

$sql = "DELETE FROM bookings WHERE id=$id";

if($conn->query($sql)){
    header("Location: admin_dashboard.php");
}else{
    echo "Error deleting";
}

}
?>
