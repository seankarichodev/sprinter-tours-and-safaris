<?php
require_once __DIR__ . "/admin_auth.php";
requireOwner();
require_once __DIR__ . "/db.php";

$search = trim($_GET["search"] ?? "");

function oe($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

/* =========================
   CUSTOMER SUMMARY
========================= */

$totalCustomers = 0;
$activeCustomers = 0;
$totalCustomerValue = 0;
$returningCustomers = 0;

$r = $conn->query("
    SELECT
        COUNT(*) AS total_customers,
        SUM(
            CASE
                WHEN EXISTS(
                    SELECT 1
                    FROM bookings b
                    WHERE b.user_id = users.id
                )
                THEN 1 ELSE 0
            END
        ) AS active_customers,
        SUM(
            CASE
                WHEN (
                    SELECT COUNT(*)
                    FROM bookings b2
                    WHERE b2.user_id = users.id
                ) > 1
                THEN 1 ELSE 0
            END
        ) AS returning_customers
    FROM users
");

if ($r && $x = $r->fetch_assoc()) {
    $totalCustomers = (int)($x["total_customers"] ?? 0);
    $activeCustomers = (int)($x["active_customers"] ?? 0);
    $returningCustomers = (int)($x["returning_customers"] ?? 0);
}

$r = $conn->query("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_status,'')) = 'paid'
     AND amount > 1
THEN amount
ELSE 0
                END
            ),
            0
        ) AS total_value
    FROM bookings
");

if ($r && $x = $r->fetch_assoc()) {
    $totalCustomerValue = (float)$x["total_value"];
}

/* =========================
   CUSTOMER REGISTER
========================= */

$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.created_at,

        COUNT(b.id) AS total_bookings,

        SUM(
            CASE
                WHEN LOWER(COALESCE(b.payment_status,'')) = 'paid'
                THEN 1
                ELSE 0
            END
        ) AS paid_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(b.payment_status,'')) = 'paid'
                    AND b.amount > 1
                    THEN b.amount
                    ELSE 0
                END
            ),
            0
        ) AS total_spent,

        MAX(
            CASE
                WHEN b.phone IS NOT NULL
                     AND b.phone <> ''
                THEN b.phone
                ELSE NULL
            END
        ) AS phone,

        MAX(b.date) AS latest_travel_date

    FROM users u

    LEFT JOIN bookings b
        ON b.user_id = u.id

    WHERE
        (
            ? = ''
            OR u.name LIKE CONCAT('%', ?, '%')
            OR u.email LIKE CONCAT('%', ?, '%')
        )

    GROUP BY
        u.id,
        u.name,
        u.email,
        u.created_at

    ORDER BY
        total_spent DESC,
        total_bookings DESC,
        u.created_at DESC

    LIMIT 100
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sss",
    $search,
    $search,
    $search
);

$stmt->execute();
$rows = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>Customers | Sprinter Tours & Safaris</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root{
    --bg:#080606;
    --panel:#151010;
    --panel2:#1b1212;
    --red:#e23333;
    --gold:#d6a64d;
    --goldSoft:#edcb7d;
    --text:#f7f1ed;
    --muted:#a69791;
    --border:rgba(255,255,255,.08);
    --green:#52bb7e;
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
        radial-gradient(circle at 95% 90%,rgba(98,5,5,.16),transparent 32%),
        linear-gradient(135deg,#090707,#120808 48%,#050505);
}

a{
    color:inherit;
}

.owner-shell{
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

.nav a:hover,
.nav a.active{
    color:#fff;
    background:linear-gradient(
        90deg,
        rgba(180,27,27,.30),
        rgba(105,10,10,.17)
    );
}

.nav a.active{
    box-shadow:inset 3px 0 0 var(--red);
}

.sidebar-bottom{
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
    margin-top:2px;
    color:#857772;
    font-size:8px;
    text-transform:uppercase;
    letter-spacing:1px;
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
    cursor:pointer;
}

/* MAIN */

.main{
    min-width:0;
    padding:30px;
}

.topbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:24px;
}

.heading small{
    display:block;
    margin-bottom:7px;
    color:var(--red);
    font-size:8px;
    font-weight:800;
    letter-spacing:1.8px;
    text-transform:uppercase;
}

.heading h1{
    margin:0;
    font-family:"Playfair Display",serif;
    font-size:clamp(31px,4vw,45px);
    line-height:1;
}

.heading h1 span{
    color:var(--red);
}

.heading p{
    margin:9px 0 0;
    color:var(--muted);
    font-size:11px;
}

/* HERO */

.hero{
    position:relative;
    overflow:hidden;
    padding:24px 26px;
    margin-bottom:18px;
    border:1px solid rgba(220,52,52,.22);
    border-radius:18px;
    background:
        linear-gradient(90deg,rgba(83,10,10,.94),rgba(30,7,7,.95)),
        url("images/owner-lion.jpg");
    background-size:cover;
    background-position:78% 42%;
}

.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(
        90deg,
        rgba(75,7,7,.90),
        rgba(22,5,5,.66),
        rgba(6,6,6,.15)
    );
}

.hero > *{
    position:relative;
    z-index:2;
}

.hero small{
    color:var(--goldSoft);
    font-size:8px;
    font-weight:800;
    letter-spacing:1.7px;
    text-transform:uppercase;
}

.hero h2{
    margin:8px 0 5px;
    font-family:"Playfair Display",serif;
    font-size:clamp(26px,3vw,36px);
}

.hero h2 span{
    color:var(--red);
}

.hero p{
    max-width:760px;
    margin:0;
    color:#cbbcb6;
    font-size:11px;
    line-height:1.6;
}

/* STATS */

.stats{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:18px;
}

.stat{
    padding:18px;
    border:1px solid var(--border);
    border-radius:15px;
    background:linear-gradient(180deg,#1a1212,#110d0d);
}

.stat-top{
    display:flex;
    justify-content:space-between;
    gap:10px;
}

.stat-label{
    margin:0;
    color:#968782;
    font-size:8px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:1px;
}

.stat-icon{
    width:34px;
    height:34px;
    display:grid;
    place-items:center;
    border-radius:9px;
    color:var(--gold);
    background:rgba(157,21,21,.18);
}

.stat-value{
    margin-top:15px;
    font-family:"Playfair Display",serif;
    font-size:clamp(23px,3vw,31px);
}

.stat-note{
    margin:7px 0 0;
    color:#756965;
    font-size:9px;
}

/* FILTER */

.filters{
    display:flex;
    gap:10px;
    margin-bottom:16px;
}

.filters input{
    min-height:42px;
    flex:1;
    padding:0 12px;
    border:1px solid rgba(255,255,255,.09);
    border-radius:10px;
    background:#151010;
    color:#fff;
    outline:none;
    font:inherit;
    font-size:10px;
}

/* TABLE */

.panel{
    min-width:0;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:16px;
    background:linear-gradient(180deg,#191212,#0f0c0c);
}

.panel-head{
    min-height:58px;
    padding:15px 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    border-bottom:1px solid rgba(255,255,255,.06);
}

.panel-head h2{
    margin:0;
    font-size:12px;
}

.panel-head span{
    color:#887a75;
    font-size:9px;
}

.table-wrap{
    overflow-x:auto;
}

.table{
    width:100%;
    min-width:850px;
    border-collapse:collapse;
}

.table th{
    padding:12px 14px;
    color:#776a65;
    font-size:8px;
    text-align:left;
    text-transform:uppercase;
    letter-spacing:1px;
    border-bottom:1px solid rgba(255,255,255,.06);
}

.table td{
    padding:13px 14px;
    color:#c8bbb5;
    font-size:9px;
    border-bottom:1px solid rgba(255,255,255,.05);
    vertical-align:middle;
}

.table strong{
    color:#f2e9e5;
}

.table small{
    color:#776a65;
}

.money{
    color:var(--goldSoft);
    font-weight:800;
}

.view-btn{
    min-height:30px;
    padding:7px 10px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    border:1px solid rgba(214,166,77,.22);
    border-radius:8px;
    color:var(--goldSoft);
    background:rgba(214,166,77,.07);
    text-decoration:none;
    font-size:8px;
    font-weight:800;
}

.empty{
    padding:20px;
    color:#796c67;
    font-size:9px;
    text-align:center;
}

.mobile-menu{
    display:none;
}

@media(max-width:1100px){
    .owner-shell{
        grid-template-columns:210px minmax(0,1fr);
    }

    .stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:860px){
    .owner-shell{
        display:block;
    }

    .sidebar{
        position:fixed;
        z-index:100;
        left:-260px;
        width:250px;
        transition:left .2s ease;
    }

    .sidebar.open{
        left:0;
    }

    .main{
        padding:20px;
    }

    .mobile-menu{
        display:inline-flex;
    }
}

@media(max-width:600px){
    .main{
        padding:15px;
    }

    .topbar{
        align-items:flex-start;
        flex-direction:column;
    }

    .stats{
        grid-template-columns:1fr 1fr;
    }

    .filters{
        flex-direction:column;
    }
}

@media(max-width:390px){
    .stats{
        grid-template-columns:1fr;
    }
}
</style>

<link rel="stylesheet" href="owner_readability.css">

</head>

<body>

<div class="owner-shell">

<aside class="sidebar" id="ownerSidebar">

<div class="brand">

<img
src="images/Wildlife Sprinter Tours & Safaris.png"
alt="Sprinter Tours & Safaris"
>

<div>
<strong>Sprinter Tours & Safaris</strong>
<span>Owner Command Center</span>
</div>

</div>

<nav class="nav">

<div class="nav-label">Executive</div>

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

<div class="nav-label">Oversight</div>

<a href="owner_bookings.php">
<i class="fa-solid fa-calendar-check"></i>
Bookings
</a>

<a href="owner_customers.php" class="active">
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

<div class="sidebar-bottom">

<div class="profile">

<div class="avatar">
<i class="fa-solid fa-crown"></i>
</div>

<div>
<strong>
<?php echo oe($adminUsername); ?>
</strong>
<span>Owner</span>
</div>

</div>

<a
href="admin_logout.php"
class="btn"
style="width:100%;margin-top:8px;"
>
<i class="fa-solid fa-right-from-bracket"></i>
Sign Out
</a>

</div>

</aside>

<main class="main">

<header class="topbar">

<div class="heading">

<small>Executive Oversight</small>

<h1>
Customers
<span>Intelligence.</span>
</h1>

<p>
See customer value, booking history and contact information from the owner view.
</p>

</div>

<button
type="button"
class="btn mobile-menu"
id="ownerMenu"
>
<i class="fa-solid fa-bars"></i>
Menu
</button>

</header>

<section class="hero">

<small>Customer Intelligence</small>

<h2>
Know your clients.
<span>Know their value.</span>
</h2>

<p>
View registered customers, booking behaviour, lifetime value and complete customer travel history.
</p>

</section>

<section class="stats">

<article class="stat">

<div class="stat-top">
<p class="stat-label">Registered Customers</p>
<div class="stat-icon">
<i class="fa-solid fa-users"></i>
</div>
</div>

<div class="stat-value">
<?php echo number_format($totalCustomers); ?>
</div>

<p class="stat-note">
Customer accounts
</p>

</article>

<article class="stat">

<div class="stat-top">
<p class="stat-label">Customers With Bookings</p>
<div class="stat-icon">
<i class="fa-solid fa-user-check"></i>
</div>
</div>

<div class="stat-value">
<?php echo number_format($activeCustomers); ?>
</div>

<p class="stat-note">
Customers who have booked
</p>

</article>

<article class="stat">

<div class="stat-top">
<p class="stat-label">Returning Customers</p>
<div class="stat-icon">
<i class="fa-solid fa-repeat"></i>
</div>
</div>

<div class="stat-value">
<?php echo number_format($returningCustomers); ?>
</div>

<p class="stat-note">
More than one booking
</p>

</article>

<article class="stat">

<div class="stat-top">
<p class="stat-label">Customer Lifetime Value</p>
<div class="stat-icon">
<i class="fa-solid fa-sack-dollar"></i>
</div>
</div>

<div class="stat-value">
KES <?php echo number_format($totalCustomerValue,0); ?>
</div>

<p class="stat-note">
Confirmed paid bookings
</p>

</article>

</section>

<form class="filters" method="GET">

<input
type="text"
name="search"
value="<?php echo oe($search); ?>"
placeholder="Search customer name or email"
>

<button class="btn" type="submit">
<i class="fa-solid fa-magnifying-glass"></i>
Search
</button>

<?php if ($search !== ""): ?>

<a
class="btn"
href="owner_customers.php"
>
Clear
</a>

<?php endif; ?>

</form>

<section class="panel">

<div class="panel-head">

<h2>Customer Directory</h2>

<span>
Open a customer to inspect their complete history
</span>

</div>

<div class="table-wrap">

<?php if ($rows && $rows->num_rows): ?>

<table class="table">

<thead>

<tr>

<th>Customer</th>
<th>Phone</th>
<th>Bookings</th>
<th>Paid Bookings</th>
<th>Lifetime Value</th>
<th>Latest Travel</th>
<th>Joined</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while ($row = $rows->fetch_assoc()): ?>

<tr>

<td>

<strong>
<?php echo oe($row["name"]); ?>
</strong>

<br>

<small>
<?php echo oe($row["email"]); ?>
</small>

</td>

<td>
<?php echo oe($row["phone"] ?? "—"); ?>
</td>

<td>
<?php echo number_format((int)$row["total_bookings"]); ?>
</td>

<td>
<?php echo number_format((int)$row["paid_bookings"]); ?>
</td>

<td class="money">
KES <?php echo number_format((float)$row["total_spent"],0); ?>
</td>

<td>

<?php

echo $row["latest_travel_date"]
    ? date("d M Y",strtotime($row["latest_travel_date"]))
    : "—";

?>

</td>

<td>

<?php

echo $row["created_at"]
    ? date("d M Y",strtotime($row["created_at"]))
    : "—";

?>

</td>

<td>

<a
class="view-btn"
href="owner_customer_view.php?id=<?php echo (int)$row["id"]; ?>"
>

<i class="fa-solid fa-eye"></i>

View

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="empty">
No customers match this search.
</div>

<?php endif; ?>

</div>

</section>

</main>

</div>

<script>

const sidebar = document.getElementById("ownerSidebar");
const menu = document.getElementById("ownerMenu");

if(sidebar && menu){

    menu.addEventListener("click",function(){
        sidebar.classList.toggle("open");
    });

}

</script>

</body>
</html>