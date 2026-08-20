<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

include "db.php";

/* LIMIT */
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

/* PAGE */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

/* SEARCH */
$search = "";
$where = "";

if(isset($_GET['search']) && $_GET['search'] != ""){
    $search = $conn->real_escape_string($_GET['search']);
    $where = "WHERE b.name LIKE '%$search%' 
              OR b.email LIKE '%$search%' 
              OR b.time LIKE '%$search%' 
              OR u.name LIKE '%$search%'";
}

/* TOTAL */
$total_sql = "SELECT COUNT(*) as total 
              FROM bookings b 
              LEFT JOIN users u ON b.user_id = u.id 
              $where";

$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

/* FETCH */
$sql = "SELECT b.*, u.name as user_name 
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        $where
        ORDER BY b.id DESC
        LIMIT $start, $limit";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Bookings</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>

body{
    margin:0;
    font-family:Poppins;
    background:#eef2f3;
}

/* TOPBAR */
.topbar{
    background:#0a7a32;
    color:white;
    padding:15px 20px;
    display:flex;
    justify-content:space-between;
}

/* SIDEBAR */
.sidebar{
    width:220px;
    height:100vh;
    background:#0a7a32;
    position:fixed;
    top:50px;
    padding-top:20px;
}

.sidebar a{
    display:block;
    color:white;
    padding:12px;
    text-decoration:none;
}

.sidebar a:hover{
    background:white;
    color:#0a7a32;
}

/* CONTENT */
.content{
    margin-left:240px;
    padding:25px;
}

/* HEADER */
h2{
    text-align:center;
    margin-bottom:15px;
}

/* SEARCH */
.search-box{
    text-align:center;
    margin-bottom:10px;
}

.search-box input{
    padding:8px;
    width:200px;
    border-radius:5px;
    border:1px solid #ccc;
}

.search-box button{
    padding:8px 10px;
    background:#0a7a32;
    color:white;
    border:none;
    border-radius:5px;
}

/* LIMIT */
.limit-box{
    text-align:center;
    margin-bottom:10px;
}

/* EXPORT */
.export{
    text-align:center;
    margin-bottom:15px;
}

.export a{
    padding:7px 12px;
    text-decoration:none;
    color:white;
    border-radius:5px;
    margin:5px;
    font-size:14px;
}

.excel{ background:#1d8f3a; }
.pdf{ background:#222; }

/* TABLE */
table{
    width:95%;
    margin:auto;
    border-collapse:collapse;
    background:white;
    border-radius:10px;
    overflow:hidden;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

th{
    background:#0a7a32;
    color:white;
}

td,th{
    padding:8px;
    border-bottom:1px solid #eee;
    text-align:center;
    font-size:14px;
}

/* ACTION */
.action a{
    margin:0 5px;
    text-decoration:none;
    font-weight:bold;
}

.edit{ color:#007bff; }
.delete{ color:#dc3545; }

/* PAGINATION */
.pagination{
    text-align:center;
    margin-top:15px;
}

.pagination a{
    padding:5px 9px;
    margin:2px;
    background:#ddd;
    text-decoration:none;
    border-radius:4px;
    color:black;
    font-size:13px;
}

.pagination a.active{
    background:#0a7a32;
    color:white;
}

</style>

</head>
<body>

<div class="topbar">
    <div>Bookings Management</div>
    <a href="logout.php" style="color:white;">Logout</a>
</div>

<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="bookings.php">Bookings</a>
    <a href="users.php">Users</a>
    <a href="messages.php">Messages</a>
</div>

<div class="content">

<h2>All Bookings</h2>

<!-- SEARCH -->
<form method="GET" class="search-box">
    <input type="text" name="search" placeholder="Search..." value="<?php echo $search; ?>">
    <button>Search</button>
</form>

<!-- LIMIT -->
<div class="limit-box">
<form method="GET">
    <input type="hidden" name="search" value="<?php echo $search; ?>">
    Show
    <select name="limit" onchange="this.form.submit()">
        <option value="5" <?php if($limit==5) echo "selected"; ?>>5</option>
        <option value="10" <?php if($limit==10) echo "selected"; ?>>10</option>
        <option value="25" <?php if($limit==25) echo "selected"; ?>>25</option>
        <option value="50" <?php if($limit==50) echo "selected"; ?>>50</option>
    </select>
    entries
</form>
</div>

<!-- EXPORT -->
<div class="export">
    <a href="export_excel.php" class="excel">Export Excel</a>
    <a href="export_pdf.php" class="pdf">Export PDF</a>
</div>

<?php
if($result->num_rows > 0){

echo "<table>";

echo "<tr>
<th>ID</th>
<th>User</th>
<th>Name</th>
<th>Email</th>
<th>Date</th>
<th>Time</th>
<th>Payment</th>
<th>Action</th>
</tr>";

while($row = $result->fetch_assoc()){

echo "<tr>";

echo "<td>".$row['id']."</td>";
echo "<td>".($row['user_name'] ?? 'Guest')."</td>";
echo "<td>".$row['name']."</td>";
echo "<td>".$row['email']."</td>";
echo "<td>".$row['date']."</td>";
echo "<td>".$row['time']."</td>";
echo "<td>".$row['payment']."</td>";

echo "<td class='action'>
<a href='edit_booking.php?id=".$row["id"]."' class='edit'>Edit</a>
<a href='delete_booking.php?id=".$row["id"]."' class='delete'>Delete</a>
</td>";

echo "</tr>";
}

echo "</table>";

}else{
echo "<p style='text-align:center; color:red;'>No bookings found</p>";
}
?>

<!-- PAGINATION -->
<div class="pagination">
<?php
for($i = 1; $i <= $total_pages; $i++){
    $active = ($i == $page) ? "active" : "";
    echo "<a class='$active' href='?page=$i&limit=$limit&search=$search'>$i</a>";
}
?>
</div>

</div>

</body>
</html>