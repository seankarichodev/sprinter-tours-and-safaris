<?php
require_once __DIR__ . "/admin_auth.php";
requireOwner();
require_once __DIR__ . "/db.php";

function oe($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

$customerId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($customerId <= 0) {
    header("Location: owner_customers.php");
    exit();
}

/* =========================
   CUSTOMER
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i",$customerId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: owner_customers.php");
    exit();
}

$customer = $result->fetch_assoc();
$stmt->close();

/* =========================
   CUSTOMER STATS
========================= */

$stats = [
    "bookings" => 0,
    "paid" => 0,
    "pending" => 0,
    "cancelled" => 0,
    "spent" => 0
];

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS bookings,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status,''))='paid'
                THEN 1 ELSE 0
            END
        ) AS paid,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status,''))='pending'
                THEN 1 ELSE 0
            END
        ) AS pending,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status,''))='cancelled'
                THEN 1 ELSE 0
            END
        ) AS cancelled,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_status,''))='paid'
     AND amount > 1
THEN amount
ELSE 0
                END
            ),
            0
        ) AS spent

    FROM bookings

    WHERE user_id = ?
");

$stmt->bind_param("i",$customerId);
$stmt->execute();

$r = $stmt->get_result();

if ($r && $x = $r->fetch_assoc()) {
    $stats = array_merge($stats,$x);
}

$stmt->close();

/* =========================
   CONTACT INFORMATION
========================= */

$phone = "";

$stmt = $conn->prepare("
    SELECT phone
    FROM bookings
    WHERE user_id = ?
      AND phone IS NOT NULL
      AND phone <> ''
    ORDER BY id DESC
    LIMIT 1
");

$stmt->bind_param("i",$customerId);
$stmt->execute();

$r = $stmt->get_result();

if ($r && $x = $r->fetch_assoc()) {
    $phone = $x["phone"];
}

$stmt->close();

/* =========================
   BOOKING HISTORY
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        tour_name,
        date,
        time,
        payment,
        amount,
        payment_status,
        payment_reference,
        mpesa_receipt,
        created_at

    FROM bookings

    WHERE user_id = ?

    ORDER BY id DESC
");

$stmt->bind_param("i",$customerId);
$stmt->execute();

$bookings = $stmt->get_result();

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1.0"
>

<title>
<?php echo oe($customer["name"]); ?> | Customer Intelligence
</title>

<link
href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
rel="stylesheet"
>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

:root{
    --red:#e23333;
    --gold:#d6a64d;
    --goldSoft:#edcb7d;
    --text:#f7f1ed;
    --muted:#a69791;
    --border:rgba(255,255,255,.08);
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    font-family:"DM Sans",sans-serif;
    color:var(--text);
    background:
        radial-gradient(circle at 10% 5%,rgba(154,21,21,.18),transparent 28%),
        linear-gradient(135deg,#090707,#120808 48%,#050505);
}

a{
    color:inherit;
}

.shell{
    min-height:100vh;
    display:grid;
    grid-template-columns:250px minmax(0,1fr);
}

/* SIDEBAR */

.sidebar{
    position:sticky;
    top:0;
    height:100vh;
    padding:26px 18px;
    display:flex;
    flex-direction:column;
    background:linear-gradient(180deg,#260909,#140707 50%,#090606);
    border-right:1px solid rgba(230,55,55,.16);
}

.brand{
    display:flex;
    align-items:center;
    gap:12px;
    padding:7px 6px 22px;
    border-bottom:1px solid rgba(255,255,255,.07);
}

.brand img{
    width:46px;
    height:46px;
    object-fit:contain;
    background:#fff;
    padding:4px;
    border-radius:12px;
}

.brand strong{
    display:block;
    font-size:14px;
}

.brand span{
    display:block;
    margin-top:3px;
    color:var(--goldSoft);
    font-size:8px;
    font-weight:800;
    letter-spacing:1.5px;
    text-transform:uppercase;
}

.nav{
    margin-top:26px;
    display:grid;
    gap:8px;
}

.nav-label{
    margin:10px 10px 5px;
    color:#7f706b;
    font-size:8px;
    font-weight:800;
    letter-spacing:1.6px;
    text-transform:uppercase;
}

.nav a{
    min-height:44px;
    padding:10px 12px;
    display:flex;
    align-items:center;
    gap:11px;
    border-radius:11px;
    color:#cfc2bc;
    text-decoration:none;
    font-size:11px;
    font-weight:700;
}

.nav a i{
    width:28px;
    height:28px;
    display:grid;
    place-items:center;
    border-radius:8px;
    color:var(--gold);
    background:rgba(255,255,255,.04);
}

.nav a.active{
    color:#fff;
    background:linear-gradient(
        90deg,
        rgba(180,27,27,.30),
        rgba(105,10,10,.17)
    );
    box-shadow:inset 3px 0 0 var(--red);
}

.bottom{
    margin-top:auto;
    padding-top:18px;
    border-top:1px solid rgba(255,255,255,.07);
}

.profile{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 8px;
}

.avatar{
    width:36px;
    height:36px;
    display:grid;
    place-items:center;
    border-radius:10px;
    color:var(--goldSoft);
    background:linear-gradient(135deg,#8f1717,#d62e2e);
}

.profile strong{
    display:block;
    font-size:11px;
}

.profile span{
    display:block;
    color:#857772;
    font-size:8px;
    text-transform:uppercase;
}

.btn{
    min-height:42px;
    padding:10px 14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:11px;
    background:rgba(255,255,255,.03);
    color:#ddd0ca;
    text-decoration:none;
    font-size:10px;
    font-weight:700;
}

/* MAIN */

.main{
    min-width:0;
    padding:30px;
}

.top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    margin-bottom:22px;
}

.eyebrow{
    color:var(--red);
    font-size:8px;
    font-weight:800;
    letter-spacing:1.8px;
    text-transform:uppercase;
}

.top h1{
    margin:7px 0 8px;
    font-family:"Playfair Display",serif;
    font-size:42px;
}

.top h1 span{
    color:var(--red);
}

.top p{
    margin:0;
    color:var(--muted);
    font-size:12px;
}

/* HERO */

.customer-hero{
    padding:25px;
    margin-bottom:18px;
    border:1px solid rgba(226,51,51,.22);
    border-radius:18px;
    background:
        linear-gradient(
            110deg,
            rgba(92,11,11,.9),
            rgba(20,8,8,.96)
        );
}

.customer-hero h2{
    margin:6px 0;
    font-family:"Playfair Display",serif;
    font-size:30px;
}

.customer-hero p{
    margin:4px 0;
    color:#c4b5ae;
    font-size:11px;
}

/* STATS */

.stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:18px;
}

.stat{
    padding:18px;
    border:1px solid var(--border);
    border-radius:15px;
    background:linear-gradient(180deg,#1a1212,#110d0d);
}

.stat label{
    display:block;
    color:#93847e;
    font-size:8px;
    font-weight:800;
    text-transform:uppercase;
}

.stat strong{
    display:block;
    margin-top:12px;
    font-family:"Playfair Display",serif;
    font-size:25px;
}

/* PANEL */

.panel{
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:16px;
    background:linear-gradient(180deg,#191212,#0f0c0c);
}

.panel-head{
    padding:16px 18px;
    border-bottom:1px solid rgba(255,255,255,.06);
}

.panel-head h2{
    margin:0;
    font-size:13px;
}

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    min-width:850px;
    border-collapse:collapse;
}

th{
    padding:12px 14px;
    color:#776a65;
    font-size:8px;
    text-align:left;
    text-transform:uppercase;
    border-bottom:1px solid rgba(255,255,255,.06);
}

td{
    padding:13px 14px;
    color:#c8bbb5;
    font-size:9px;
    border-bottom:1px solid rgba(255,255,255,.05);
}

.money{
    color:var(--goldSoft);
    font-weight:800;
}

.pill{
    display:inline-flex;
    padding:5px 8px;
    border-radius:999px;
    font-size:8px;
    font-weight:800;
    text-transform:uppercase;
}

.paid{
    color:#75d7a1;
    background:rgba(44,135,83,.16);
}

.pending{
    color:#ebbf63;
    background:rgba(169,118,28,.16);
}

.cancelled,
.failed{
    color:#ef8585;
    background:rgba(157,34,34,.16);
}

.default{
    background:rgba(255,255,255,.06);
}

.action{
    display:inline-flex;
    padding:7px 10px;
    border-radius:8px;
    border:1px solid rgba(214,166,77,.22);
    color:var(--goldSoft);
    text-decoration:none;
    font-size:8px;
    font-weight:800;
}

@media(max-width:1000px){

    .shell{
        grid-template-columns:210px minmax(0,1fr);
    }

    .stats{
        grid-template-columns:repeat(2,1fr);
    }

}

</style>

</head>

<body>

<div class="shell">

<aside class="sidebar">

<div class="brand">

<img
src="images/Wildlife Sprinter Tours & Safaris.png"
alt="Sprinter Tours & Safaris"
>

<div>

<strong>
Sprinter Tours & Safaris
</strong>

<span>
Owner Command Center
</span>

</div>

</div>

<nav class="nav">

<div class="nav-label">
Executive
</div>

<a href="owner_dashboard.php">
<i class="fa-solid fa-crown"></i>
Command Center
</a>

<a href="owner_reports.php">
<i class="fa-solid fa-chart-pie"></i>
Business Reports
</a>

<a href="owner_payments.php">
<i class="fa-solid fa-credit-card"></i>
Payments
</a>

<div class="nav-label">
Oversight
</div>

<a href="owner_bookings.php">
<i class="fa-solid fa-calendar-check"></i>
Bookings
</a>

<a
href="owner_customers.php"
class="active"
>
<i class="fa-solid fa-users"></i>
Customers
</a>

<a href="owner_messages.php">
<i class="fa-solid fa-envelope"></i>
Messages
</a>

<a href="owner_audit.php">
<i class="fa-solid fa-shield-halved"></i>
Audit Activity
</a>

</nav>

<div class="bottom">

<div class="profile">

<div class="avatar">
<i class="fa-solid fa-crown"></i>
</div>

<div>

<strong>
<?php echo oe($adminUsername); ?>
</strong>

<span>
Owner
</span>

</div>

</div>

<a
class="btn"
style="width:100%;"
href="admin_logout.php"
>

<i class="fa-solid fa-right-from-bracket"></i>

Sign Out

</a>

</div>

</aside>

<main class="main">

<header class="top">

<div>

<div class="eyebrow">
Customer Intelligence
</div>

<h1>
<?php echo oe($customer["name"]); ?>
</h1>

<p>
Customer Intelligence Profile
&nbsp;•&nbsp;
Customer ID: #<?php echo $customerId; ?>
</p>

</div>

<a
class="btn"
href="owner_customers.php"
>

<i class="fa-solid fa-arrow-left"></i>

Back to Customers

</a>

</header>

<section class="customer-hero">

<div class="eyebrow">
Customer Intelligence Profile
</div>

<h2>
<?php echo oe($customer["name"]); ?>
</h2>

<p>
Customer ID: #<?php echo $customerId; ?>
</p>

<p>
<i class="fa-solid fa-envelope"></i>
<?php echo oe($customer["email"]); ?>
</p>

<p>
<i class="fa-solid fa-phone"></i>
<?php echo oe($phone ?: "No phone recorded"); ?>
</p>

<p>
Joined
<?php
echo $customer["created_at"]
    ? date("d M Y",strtotime($customer["created_at"]))
    : "—";
?>
</p>

</section>

<section class="stats">

<article class="stat">
<label>Total Bookings</label>
<strong>
<?php echo number_format((int)$stats["bookings"]); ?>
</strong>
</article>

<article class="stat">
<label>Paid Bookings</label>
<strong>
<?php echo number_format((int)$stats["paid"]); ?>
</strong>
</article>

<article class="stat">
<label>Cancelled</label>
<strong>
<?php echo number_format((int)$stats["cancelled"]); ?>
</strong>
</article>

<article class="stat">
<label>Lifetime Value</label>
<strong>
KES <?php echo number_format((float)$stats["spent"],0); ?>
</strong>
</article>

</section>

<section class="panel">

<div class="panel-head">
<h2>Booking & Payment History</h2>
</div>

<div class="table-wrap">

<?php if($bookings && $bookings->num_rows): ?>

<table>

<thead>

<tr>

<th>ID</th>
<th>Tour</th>
<th>Travel</th>
<th>Amount</th>
<th>Method</th>
<th>Status</th>
<th>Created</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($row = $bookings->fetch_assoc()): ?>

<?php

$status = strtolower(
    (string)$row["payment_status"]
);

$class = in_array(
    $status,
    ["paid","pending","cancelled","failed"],
    true
)
    ? $status
    : "default";

?>

<tr>

<td>
#<?php echo (int)$row["id"]; ?>
</td>

<td>
<?php echo oe($row["tour_name"] ?: "Tour Booking"); ?>
</td>

<td>

<?php

echo $row["date"]
    ? date("d M Y",strtotime($row["date"]))
    : "—";

?>

<?php echo oe($row["time"] ?? ""); ?>

</td>

<td class="money">

KES
<?php echo number_format((float)$row["amount"],0); ?>

</td>

<td>
<?php echo oe($row["payment"]); ?>
</td>

<td>

<span class="pill <?php echo $class; ?>">

<?php echo oe($row["payment_status"]); ?>

</span>

</td>

<td>

<?php

echo $row["created_at"]
    ? date("d M Y H:i",strtotime($row["created_at"]))
    : "—";

?>

</td>

<td>

<a
class="action"
href="owner_booking_view.php?id=<?php echo (int)$row["id"]; ?>"
>

<i class="fa-solid fa-eye"></i>

View Booking

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div style="padding:20px;color:#857772;font-size:10px;">
This customer has no bookings yet.
</div>

<?php endif; ?>

</div>

</section>

</main>

</div>

</body>

</html>