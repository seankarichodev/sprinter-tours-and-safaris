<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

include "db.php";

/* STATS */
$totalBookings = $conn->query("SELECT COUNT(*) as total FROM bookings")->fetch_assoc()['total'];
$totalRevenue = $conn->query("SELECT COUNT(*)*5000 as total FROM bookings")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

body{
    margin:0;
    font-family:Poppins;
    background:#f9f9f9;
}

/* SIDEBAR */
.sidebar{
    width:220px;
    height:100vh;
    background:#0a7a32;
    color:white;
    position:fixed;
    padding:20px;
}

.sidebar h2{
    margin-bottom:30px;
}

.sidebar a{
    display:block;
    color:white;
    padding:12px;
    text-decoration:none;
    border-radius:8px;
    margin-bottom:10px;
}

.sidebar a:hover{
    background:white;
    color:#0a7a32;
}

/* CONTENT */
.content{
    margin-left:240px;
    padding:30px;
}

/* CARDS */
.cards{
    display:flex;
    gap:20px;
}

.card{
    flex:1;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

.card h3{
    margin:0;
    color:#555;
    font-size:14px;
}

.card h1{
    color:#0a7a32;
}

/* CHART */
.chart{
    margin-top:30px;
    background:white;
    padding:20px;
    border-radius:10px;
}

/* TOPBAR */
.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.logout{
    background:#0a7a32;
    color:white;
    padding:8px 15px;
    border-radius:5px;
    text-decoration:none;
}

</style>

</head>
<body>

<div class="sidebar">
<h2><i class="fa fa-leaf"></i> Admin</h2>

<a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
<a href="bookings.php"><i class="fa fa-calendar"></i> Bookings</a>
<a href="messages.php">Messages</a>

</div>

<div class="content">

<div class="topbar">
<h2>Welcome Admin 👋</h2>
<a href="logout.php" class="logout">Logout</a>
</div>

<div class="cards">

<div class="card">
<h3><i class="fa fa-calendar-check"></i> Total Bookings</h3>
<h1><?php echo $totalBookings; ?></h1>
</div>

<div class="card">
<h3><i class="fa fa-money-bill"></i> Revenue (KES)</h3>
<h1><?php echo number_format($totalRevenue); ?></h1>
</div>

</div>

<div class="chart">
<canvas id="myChart"></canvas>
</div>

</div>

<script>
new Chart(document.getElementById('myChart'), {
    type: 'bar',
    data: {
        labels: ['Jan','Feb','Mar','Apr'],
        datasets: [{
            label: 'Bookings',
            data: [5,8,6,10]
        }]
    }
});
</script>

</body>
</html>