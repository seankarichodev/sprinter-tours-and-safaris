<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

include "db.php";

/* LIMIT */
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

/* PAGE */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

/* SEARCH */
$search = "";
$where = "";

if(isset($_GET['search']) && $_GET['search'] != ""){
    $search = $conn->real_escape_string($_GET['search']);
    $where = "WHERE name LIKE '%$search%' OR email LIKE '%$search%'";
}

/* TOTAL USERS */
$total_sql = "SELECT COUNT(*) as total FROM users $where";
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];

$total_pages = ceil($total_records / $limit);

/* FETCH USERS */
$sql = "SELECT * FROM users $where ORDER BY id DESC LIMIT $start, $limit";
$result = $conn->query($sql);

/* DELETE USER */
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=$id");
    header("Location: users.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Users Management</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>

body{
    margin:0;
    font-family:Poppins;
    background:#f4f6f9;
}

/* TOPBAR */
.topbar{
    background:#0a7a32;
    color:white;
    padding:15px;
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
    padding:30px;
}

/* SEARCH */
.search-box{
    text-align:center;
    margin-bottom:10px;
}

.search-box input{
    padding:8px;
    width:220px;
    border-radius:5px;
    border:1px solid #ccc;
}

.search-box button{
    padding:8px 12px;
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

/* TABLE */
table{
    width:85%;
    margin:auto;
    border-collapse:collapse;
    background:white;
    border-radius:10px;
    overflow:hidden;
}

th{
    background:#0a7a32;
    color:white;
}

td,th{
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
}

tr:hover{
    background:#f1f1f1;
}

/* ACTION */
.action a{
    margin:0 5px;
    text-decoration:none;
    font-weight:bold;
}

.delete{
    color:red;
}

/* PAGINATION */
.pagination{
    text-align:center;
    margin-top:15px;
}

.pagination a{
    padding:6px 10px;
    margin:3px;
    background:#ddd;
    text-decoration:none;
    border-radius:5px;
    color:black;
}

.pagination a.active{
    background:#0a7a32;
    color:white;
}

</style>

</head>
<body>

<div class="topbar">
    <div>Users Management</div>
    <a href="logout.php" style="color:white;">Logout</a>
</div>

<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="bookings.php">Bookings</a>
    <a href="users.php">Users</a>
    <a href="messages.php">Messages</a>
</div>

<div class="content">

<h2 style="text-align:center;">All Users</h2>

<!-- SEARCH -->
<form method="GET" class="search-box">
    <input type="text" name="search" placeholder="Search name or email" value="<?php echo $search; ?>">
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

<?php
if($result->num_rows > 0){

echo "<table>";

echo "<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Created</th>
<th>Action</th>
</tr>";

while($row = $result->fetch_assoc()){

echo "<tr>";

echo "<td>".$row['id']."</td>";
echo "<td>".$row['name']."</td>";
echo "<td>".$row['email']."</td>";
echo "<td>".$row['created_at']."</td>";

echo "<td class='action'>
<a href='users.php?delete=".$row["id"]."' class='delete' onclick=\"return confirm('Delete this user?')\">Delete</a>
</td>";

echo "</tr>";
}

echo "</table>";

}else{
echo "<p style='text-align:center; color:red;'>No users found</p>";
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