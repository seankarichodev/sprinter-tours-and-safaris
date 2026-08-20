<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: admin_login.php");
    exit();
}

include "db.php";

/* MARK AS READ */
if(isset($_GET['read'])){
    $id = (int)$_GET['read'];
    $conn->query("UPDATE messages SET status='Read' WHERE id=$id");
}

/* DELETE */
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM messages WHERE id=$id");
}

/* FETCH */
$sql = "SELECT * FROM messages ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Messages</title>

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

/* TABLE */
table{
    width:90%;
    margin:auto;
    border-collapse:collapse;
    background:white;
}

th{
    background:#0a7a32;
    color:white;
}

td,th{
    padding:10px;
    border:1px solid #ccc;
    text-align:center;
}

.unread{
    background:#ffecec;
}

/* ACTION */
.action a{
    margin:0 5px;
    text-decoration:none;
    font-weight:bold;
}

.read{ color:green; }
.delete{ color:red; }

</style>
</head>

<body>

<div class="topbar">
    <div>Messages</div>
    <a href="logout.php" style="color:white;">Logout</a>
</div>

<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="bookings.php">Bookings</a>
    <a href="users.php">Users</a>
    <a href="messages.php">Messages</a>
</div>

<div class="content">

<h2 style="text-align:center;">Customer Messages</h2>

<?php
if($result->num_rows > 0){

echo "<table>";

echo "<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Message</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>
</tr>";

while($row = $result->fetch_assoc()){

$class = ($row['status'] == 'Unread') ? "unread" : "";

echo "<tr class='$class'>";

echo "<td>".$row['id']."</td>";
echo "<td>".$row['name']."</td>";
echo "<td>".$row['email']."</td>";
echo "<td>".$row['message']."</td>";
echo "<td>".$row['status']."</td>";
echo "<td>".$row['created_at']."</td>";

echo "<td class='action'>
<a href='messages.php?read=".$row["id"]."' class='read'>Mark Read</a>
<a href='messages.php?delete=".$row["id"]."' class='delete'>Delete</a>
</td>";

echo "</tr>";
}

echo "</table>";

}else{
echo "<p style='text-align:center;'>No messages yet</p>";
}
?>

</div>

</body>
</html>