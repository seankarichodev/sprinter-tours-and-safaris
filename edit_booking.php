<?php
include "db.php";

if(isset($_GET['id'])){
    $id = $_GET['id'];

    $sql = "SELECT * FROM bookings WHERE id=$id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
}

if(isset($_POST['update'])){

    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $payment = $_POST['payment'];

    $sql = "UPDATE bookings SET 
            name='$name',
            email='$email',
            date='$date',
            time='$time',
            payment='$payment'
            WHERE id=$id";

    if($conn->query($sql)){
        header("Location: admin_dashboard.php");
    } else {
        echo "Error updating";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Booking</title>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    padding:40px;
}

form{
    width:400px;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:10px;
}

input, select{
    width:100%;
    padding:10px;
    margin:10px 0;
}

button{
    background:#0a7a32;
    color:white;
    border:none;
    padding:10px;
    cursor:pointer;
}
</style>

</head>
<body>

<h2 style="text-align:center;">Edit Booking</h2>

<form method="POST">

<input type="hidden" name="id" value="<?php echo $row['id']; ?>">

<input type="text" name="name" value="<?php echo $row['name']; ?>" required>

<input type="email" name="email" value="<?php echo $row['email']; ?>" required>

<input type="date" name="date" value="<?php echo $row['date']; ?>" required>

<input type="time" name="time" value="<?php echo $row['time']; ?>" required>

<select name="payment">
    <option <?php if($row['payment']=="Mpesa") echo "selected"; ?>>Mpesa</option>
    <option <?php if($row['payment']=="Visa") echo "selected"; ?>>Visa</option>
</select>

<button name="update">Update Booking</button>

</form>

</body>
</html>