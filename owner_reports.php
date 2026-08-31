<?php

require_once __DIR__ . "/admin_auth.php";
requireOwner();
require_once __DIR__ . "/db.php";


/* =========================================================
   REPORT FILTER
========================================================= */

$year =
    isset($_GET["year"])
        ? (int) $_GET["year"]
        : (int) date("Y");


if (
    $year < 2020
    ||
    $year > 2100
) {

    $year =
        (int) date("Y");
}



/* =========================================================
   SUMMARY STATISTICS
========================================================= */

$summarySql =
    "
    SELECT

        COUNT(*) AS total_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(payment_status) = 'paid'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_revenue,

        SUM(
            CASE
                WHEN LOWER(payment_status) = 'paid'
                THEN 1
                ELSE 0
            END
        ) AS paid_bookings,

        SUM(
            CASE
                WHEN LOWER(payment_status) = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_bookings

    FROM bookings

    WHERE YEAR(created_at) = ?
    ";


$summaryStmt =
    $conn->prepare(
        $summarySql
    );


$summary = [
    "total_bookings" => 0,
    "total_revenue" => 0,
    "paid_bookings" => 0,
    "pending_bookings" => 0
];


if ($summaryStmt) {

    $summaryStmt->bind_param(
        "i",
        $year
    );


    $summaryStmt->execute();


    $summaryResult =
        $summaryStmt->get_result();


    if ($summaryResult) {

        $row =
            $summaryResult
            ->fetch_assoc();


        $summary["total_bookings"] =
            (int) (
                $row["total_bookings"]
                ?? 0
            );


        $summary["total_revenue"] =
            (float) (
                $row["total_revenue"]
                ?? 0
            );


        $summary["paid_bookings"] =
            (int) (
                $row["paid_bookings"]
                ?? 0
            );


        $summary["pending_bookings"] =
            (int) (
                $row["pending_bookings"]
                ?? 0
            );
    }


    $summaryStmt->close();
}



/* =========================================================
   MONTHLY BOOKINGS + REVENUE
========================================================= */

$monthlyBookings =
    array_fill(
        1,
        12,
        0
    );


$monthlyRevenue =
    array_fill(
        1,
        12,
        0
    );


$monthlyStmt =
    $conn->prepare(
        "
        SELECT

            MONTH(created_at)
                AS month_number,

            COUNT(*)
                AS total_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(payment_status) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
                AS revenue

        FROM bookings

        WHERE YEAR(created_at) = ?

        GROUP BY MONTH(created_at)

        ORDER BY MONTH(created_at)
        "
    );


if ($monthlyStmt) {

    $monthlyStmt->bind_param(
        "i",
        $year
    );


    $monthlyStmt->execute();


    $monthlyResult =
        $monthlyStmt->get_result();


    while (
        $monthlyResult
        &&
        $row =
            $monthlyResult
            ->fetch_assoc()
    ) {

        $monthNumber =
            (int) (
                $row["month_number"]
                ?? 0
            );


        if (
            $monthNumber >= 1
            &&
            $monthNumber <= 12
        ) {

            $monthlyBookings[$monthNumber] =
                (int) (
                    $row["total_bookings"]
                    ?? 0
                );


            $monthlyRevenue[$monthNumber] =
                (float) (
                    $row["revenue"]
                    ?? 0
                );
        }
    }


    $monthlyStmt->close();
}



/* =========================================================
   TOUR PERFORMANCE
========================================================= */

$tourPerformance = [];


$tourStmt =
    $conn->prepare(
        "
        SELECT

            COALESCE(
                NULLIF(
                    TRIM(tour_name),
                    ''
                ),
                'Not specified'
            )
                AS tour_name,

            COUNT(*)
                AS total_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(payment_status) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
                AS revenue

        FROM bookings

        WHERE YEAR(created_at) = ?

        GROUP BY
            COALESCE(
                NULLIF(
                    TRIM(tour_name),
                    ''
                ),
                'Not specified'
            )

        ORDER BY
            total_bookings DESC,
            revenue DESC

        LIMIT 8
        "
    );


if ($tourStmt) {

    $tourStmt->bind_param(
        "i",
        $year
    );


    $tourStmt->execute();


    $tourResult =
        $tourStmt->get_result();


    while (
        $tourResult
        &&
        $row =
            $tourResult
            ->fetch_assoc()
    ) {

        $tourPerformance[] =
            $row;
    }


    $tourStmt->close();
}



/* =========================================================
   PAYMENT METHOD PERFORMANCE
========================================================= */

$paymentPerformance = [];


$paymentStmt =
    $conn->prepare(
        "
        SELECT

            payment,

            COUNT(*)
                AS total_transactions,

            SUM(
                CASE
                    WHEN LOWER(payment_status) = 'paid'
                    THEN 1
                    ELSE 0
                END
            )
                AS paid_transactions,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(payment_status) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
                AS revenue

        FROM bookings

        WHERE YEAR(created_at) = ?

        GROUP BY payment

        ORDER BY total_transactions DESC
        "
    );


if ($paymentStmt) {

    $paymentStmt->bind_param(
        "i",
        $year
    );


    $paymentStmt->execute();


    $paymentResult =
        $paymentStmt->get_result();


    while (
        $paymentResult
        &&
        $row =
            $paymentResult
            ->fetch_assoc()
    ) {

        $paymentPerformance[] =
            $row;
    }


    $paymentStmt->close();
}



/* =========================================================
   CUSTOMER VALUE
========================================================= */

$topCustomers = [];


$customerStmt =
    $conn->prepare(
        "
        SELECT

            u.id,
            u.name,
            u.email,

            COUNT(b.id)
                AS total_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(b.payment_status) = 'paid'
                        THEN b.amount
                        ELSE 0
                    END
                ),
                0
            )
                AS total_spent

        FROM users u

        LEFT JOIN bookings b
            ON b.user_id = u.id
            AND YEAR(b.created_at) = ?

        GROUP BY
            u.id,
            u.name,
            u.email

        HAVING COUNT(b.id) > 0

        ORDER BY
            total_spent DESC,
            total_bookings DESC

        LIMIT 8
        "
    );


if ($customerStmt) {

    $customerStmt->bind_param(
        "i",
        $year
    );


    $customerStmt->execute();


    $customerResult =
        $customerStmt->get_result();


    while (
        $customerResult
        &&
        $row =
            $customerResult
            ->fetch_assoc()
    ) {

        $topCustomers[] =
            $row;
    }


    $customerStmt->close();
}



/* =========================================================
   AVAILABLE REPORT YEARS
========================================================= */

$availableYears = [];


$yearResult =
    $conn->query(
        "
        SELECT DISTINCT
            YEAR(created_at) AS report_year

        FROM bookings

        WHERE created_at IS NOT NULL

        ORDER BY report_year DESC
        "
    );


if ($yearResult) {

    while (
        $row =
            $yearResult
            ->fetch_assoc()
    ) {

        $reportYear =
            (int) (
                $row["report_year"]
                ?? 0
            );


        if ($reportYear > 0) {

            $availableYears[] =
                $reportYear;
        }
    }
}


if (
    !in_array(
        $year,
        $availableYears,
        true
    )
) {

    $availableYears[] =
        $year;


    rsort(
        $availableYears
    );
}



/* =========================================================
   CHART LABELS
========================================================= */

$monthLabels = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec"
];


$bookingChartData =
    array_values(
        $monthlyBookings
    );


$revenueChartData =
    array_values(
        $monthlyRevenue
    );



/* =========================================================
   HELPERS
========================================================= */

function reportEscape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Executive Reports | Sprinter Tours & Safaris
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg:#080606;
            --panel:#151010;
            --panel2:#1b1212;
            --red:#e23333;
            --redDeep:#7c1212;
            --gold:#d6a64d;
            --goldSoft:#edcb7d;
            --text:#f7f1ed;
            --muted:#a69791;
            --border:rgba(255,255,255,.08);
            --green:#52bb7e;
            --amber:#e6b653;
        }

        * { box-sizing:border-box; }

        body {
            margin:0;
            min-height:100vh;
            font-family:"DM Sans",sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at 10% 5%,rgba(154,21,21,.18),transparent 28%),
                radial-gradient(circle at 95% 90%,rgba(98,5,5,.16),transparent 32%),
                linear-gradient(135deg,#090707,#120808 48%,#050505);
        }

        a { color:inherit; }

        .owner-shell {
            min-height:100vh;
            display:grid;
            grid-template-columns:250px minmax(0,1fr);
        }

        .sidebar {
            position:sticky;
            top:0;
            height:100vh;
            padding:26px 18px;
            display:flex;
            flex-direction:column;
            background:linear-gradient(180deg,#260909,#140707 50%,#090606);
            border-right:1px solid rgba(230,55,55,.16);
        }

        .brand {
            display:flex;
            align-items:center;
            gap:12px;
            padding:7px 6px 22px;
            border-bottom:1px solid rgba(255,255,255,.07);
        }

        .brand img {
            width:46px;
            height:46px;
            object-fit:contain;
            background:#fff;
            padding:4px;
            border-radius:12px;
        }

        .brand strong {
            display:block;
            font-size:14px;
        }

        .brand span {
            display:block;
            margin-top:3px;
            color:var(--goldSoft);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.5px;
            text-transform:uppercase;
        }

        .nav {
            margin-top:26px;
            display:grid;
            gap:8px;
        }

        .nav-label {
            margin:10px 10px 5px;
            color:#7f706b;
            font-size:8px;
            font-weight:800;
            letter-spacing:1.6px;
            text-transform:uppercase;
        }

        .nav a {
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

        .nav a i {
            width:28px;
            height:28px;
            display:grid;
            place-items:center;
            border-radius:8px;
            color:var(--gold);
            background:rgba(255,255,255,.04);
        }

        .nav a:hover,
        .nav a.active {
            color:#fff;
            background:linear-gradient(90deg,rgba(180,27,27,.30),rgba(105,10,10,.17));
        }

        .nav a.active {
            box-shadow:inset 3px 0 0 var(--red);
        }

        .sidebar-bottom {
            margin-top:auto;
            padding-top:18px;
            border-top:1px solid rgba(255,255,255,.07);
        }

        .profile {
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px 8px;
        }

        .avatar {
            width:36px;
            height:36px;
            display:grid;
            place-items:center;
            border-radius:10px;
            color:var(--goldSoft);
            background:linear-gradient(135deg,#8f1717,#d62e2e);
        }

        .profile strong {
            display:block;
            font-size:11px;
        }

        .profile span {
            display:block;
            margin-top:2px;
            color:#857772;
            font-size:8px;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .btn {
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

        .main {
            min-width:0;
            padding:30px;
        }

        .topbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
            margin-bottom:24px;
        }

        .heading small {
            display:block;
            margin-bottom:7px;
            color:var(--red);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.8px;
            text-transform:uppercase;
        }

        .heading h1 {
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:clamp(31px,4vw,45px);
            line-height:1;
        }

        .heading h1 span { color:var(--red); }

        .heading p {
            margin:9px 0 0;
            color:var(--muted);
            font-size:11px;
        }

        .year-form select {
            min-height:42px;
            padding:0 36px 0 14px;
            border:1px solid rgba(255,255,255,.09);
            border-radius:11px;
            outline:none;
            background:#151010;
            color:#fff;
            font:inherit;
            font-size:10px;
        }

        .report-hero {
            position:relative;
            overflow:hidden;
            padding:25px 27px;
            margin-bottom:18px;
            border:1px solid rgba(220,52,52,.22);
            border-radius:18px;
            background:
                linear-gradient(90deg,rgba(83,10,10,.94),rgba(30,7,7,.95)),
                url("images/owner-lion.jpg");
            background-size:cover;
            background-position:78% 42%;
        }

        .report-hero::after {
            content:"";
            position:absolute;
            inset:0;
            background:linear-gradient(90deg,rgba(75,7,7,.90),rgba(22,5,5,.66),rgba(6,6,6,.15));
        }

        .report-hero > * {
            position:relative;
            z-index:2;
        }

        .report-hero small {
            color:var(--goldSoft);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.7px;
            text-transform:uppercase;
        }

        .report-hero h2 {
            margin:8px 0 5px;
            font-family:"Playfair Display",serif;
            font-size:clamp(26px,3vw,38px);
        }

        .report-hero h2 span { color:var(--red); }

        .report-hero p {
            max-width:720px;
            margin:0;
            color:#cbbcb6;
            font-size:11px;
            line-height:1.6;
        }

        .stats {
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:14px;
            margin-bottom:18px;
        }

        .stat {
            padding:18px;
            border:1px solid var(--border);
            border-radius:15px;
            background:linear-gradient(180deg,#1a1212,#110d0d);
        }

        .stat-top {
            display:flex;
            justify-content:space-between;
            gap:10px;
        }

        .stat-label {
            margin:0;
            color:#968782;
            font-size:8px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .stat-icon {
            width:34px;
            height:34px;
            display:grid;
            place-items:center;
            border-radius:9px;
            color:var(--gold);
            background:rgba(157,21,21,.18);
        }

        .stat-value {
            margin-top:15px;
            font-family:"Playfair Display",serif;
            font-size:clamp(23px,3vw,31px);
        }

        .stat-note {
            margin:7px 0 0;
            color:#756965;
            font-size:9px;
        }

        .grid2 {
            display:grid;
            grid-template-columns:1.35fr .65fr;
            gap:16px;
            margin-bottom:16px;
        }

        .panel {
            min-width:0;
            overflow:hidden;
            border:1px solid var(--border);
            border-radius:16px;
            background:linear-gradient(180deg,#191212,#0f0c0c);
        }

        .panel-head {
            min-height:58px;
            padding:15px 18px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            border-bottom:1px solid rgba(255,255,255,.06);
        }

        .panel-head h2 {
            margin:0;
            font-size:12px;
        }

        .panel-head span {
            color:#887a75;
            font-size:9px;
        }

        .panel-body { padding:18px; }

        .chart { height:280px; }

        .data-table-wrap { overflow-x:auto; }

        .data-table {
            width:100%;
            min-width:680px;
            border-collapse:collapse;
        }

        .data-table th {
            padding:12px 14px;
            color:#776a65;
            font-size:8px;
            text-align:left;
            text-transform:uppercase;
            letter-spacing:1px;
            border-bottom:1px solid rgba(255,255,255,.06);
        }

        .data-table td {
            padding:13px 14px;
            color:#c8bbb5;
            font-size:9px;
            border-bottom:1px solid rgba(255,255,255,.05);
        }

        .data-table strong { color:#f2e9e5; }

        .money { color:var(--goldSoft); font-weight:800; }

        .empty {
            padding:18px;
            color:#796c67;
            font-size:9px;
            text-align:center;
        }

        .mobile-menu { display:none; }

        @media(max-width:1100px){
            .owner-shell{grid-template-columns:210px minmax(0,1fr);}
            .stats{grid-template-columns:repeat(2,minmax(0,1fr));}
        }

        @media(max-width:860px){
            .owner-shell{display:block;}
            .sidebar{
                position:fixed;
                z-index:100;
                left:-260px;
                width:250px;
                transition:left .2s ease;
            }
            .sidebar.open{left:0;}
            .main{padding:20px;}
            .mobile-menu{display:inline-flex;}
            .grid2{grid-template-columns:1fr;}
        }

        @media(max-width:600px){
            .main{padding:15px;}
            .topbar{align-items:flex-start;flex-direction:column;}
            .stats{grid-template-columns:1fr 1fr;}
            .chart{height:230px;}
        }

        @media(max-width:390px){
            .stats{grid-template-columns:1fr;}
        }
    </style>
    <link rel="stylesheet" href="owner_readability.css">
</head>

<body>

<div class="owner-shell">

    <aside class="sidebar" id="ownerReportsSidebar">

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

            <a
                href="owner_reports.php"
                class="active"
            >
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

            <a href="owner_customers.php">
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
                        <?php echo htmlspecialchars(
                            $adminUsername,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </strong>
                    <span>Owner</span>
                </div>
            </div>

            <a
                href="admin_logout.php"
                class="btn"
                style="width:100%; margin-top:8px;"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Sign Out
            </a>

        </div>

    </aside>

    <main class="main">

        <header class="topbar">

            <div class="heading">
                <small>Executive Intelligence</small>
                <h1>
                    Business <span>Reports.</span>
                </h1>
                <p>
                    Analyse bookings, confirmed revenue,
                    customer value and payment performance.
                </p>
            </div>

            <div style="display:flex; gap:10px; align-items:center;">

                <button
                    type="button"
                    class="btn mobile-menu"
                    id="ownerReportsMenu"
                >
                    <i class="fa-solid fa-bars"></i>
                    Menu
                </button>

                <form
                    method="GET"
                    class="year-form"
                >
                    <select
                        name="year"
                        onchange="this.form.submit()"
                    >

                        <?php foreach ($availableYears as $reportYear): ?>

                            <option
                                value="<?php echo (int) $reportYear; ?>"
                                <?php
                                    echo (int) $reportYear === $year
                                        ? "selected"
                                        : "";
                                ?>
                            >
                                <?php echo (int) $reportYear; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </form>

            </div>

        </header>

        <section class="report-hero">
            <small>Owner Performance Intelligence</small>
            <h2>
                See the business.
                <span>Know the numbers.</span>
            </h2>
            <p>
                This report uses the same live booking and payment
                database as the operations portal, but presents it
                as executive-level performance intelligence.
            </p>
        </section>

        <section class="stats">

            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Total Bookings</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format(
                        (int) $summary["total_bookings"]
                    ); ?>
                </div>
                <p class="stat-note">
                    Bookings created in <?php echo (int) $year; ?>
                </p>
            </article>

            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Confirmed Revenue</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-value">
                    KES <?php echo number_format(
                        (float) $summary["total_revenue"],
                        0
                    ); ?>
                </div>
                <p class="stat-note">Paid transactions only</p>
            </article>

            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Paid Bookings</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format(
                        (int) $summary["paid_bookings"]
                    ); ?>
                </div>
                <p class="stat-note">Successful payments</p>
            </article>

            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Pending</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format(
                        (int) $summary["pending_bookings"]
                    ); ?>
                </div>
                <p class="stat-note">Awaiting confirmation</p>
            </article>

        </section>

        <section class="grid2">

            <article class="panel">
                <div class="panel-head">
                    <h2>Monthly Booking Performance</h2>
                    <span><?php echo (int) $year; ?></span>
                </div>
                <div class="panel-body">
                    <div class="chart">
                        <canvas id="ownerBookingReportChart"></canvas>
                    </div>
                </div>
            </article>

            <article class="panel">
                <div class="panel-head">
                    <h2>Monthly Revenue</h2>
                    <span>KES</span>
                </div>
                <div class="panel-body">
                    <div class="chart">
                        <canvas id="ownerRevenueReportChart"></canvas>
                    </div>
                </div>
            </article>

        </section>

        <section class="panel" style="margin-bottom:16px;">
            <div class="panel-head">
                <h2>Tour Performance</h2>
                <span>Top routes & packages</span>
            </div>

            <div class="data-table-wrap">

                <?php if (count($tourPerformance) > 0): ?>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tour</th>
                                <th>Bookings</th>
                                <th>Paid Revenue</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php foreach ($tourPerformance as $tour): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars(
                                            $tour["tour_name"] ?? "Not specified",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo number_format(
                                        (int) ($tour["total_bookings"] ?? 0)
                                    ); ?>
                                </td>
                                <td class="money">
                                    KES <?php echo number_format(
                                        (float) ($tour["revenue"] ?? 0),
                                        0
                                    ); ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>

                <?php else: ?>

                    <div class="empty">
                        No tour performance available.
                    </div>

                <?php endif; ?>

            </div>
        </section>

        <section class="grid2">

            <article class="panel">
                <div class="panel-head">
                    <h2>Payment Method Performance</h2>
                    <span>Transactions & revenue</span>
                </div>

                <div class="data-table-wrap">

                    <?php if (count($paymentPerformance) > 0): ?>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Transactions</th>
                                    <th>Paid</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php foreach ($paymentPerformance as $payment): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars(
                                                $payment["payment"] ?? "Unknown",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo number_format(
                                            (int) ($payment["total_transactions"] ?? 0)
                                        ); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format(
                                            (int) ($payment["paid_transactions"] ?? 0)
                                        ); ?>
                                    </td>
                                    <td class="money">
                                        KES <?php echo number_format(
                                            (float) ($payment["revenue"] ?? 0),
                                            0
                                        ); ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>

                    <?php else: ?>

                        <div class="empty">
                            No payment performance available.
                        </div>

                    <?php endif; ?>

                </div>
            </article>

            <article class="panel">
                <div class="panel-head">
                    <h2>Top Customers</h2>
                    <span>Customer value</span>
                </div>

                <div class="data-table-wrap">

                    <?php if (count($topCustomers) > 0): ?>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Bookings</th>
                                    <th>Paid Value</th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php foreach ($topCustomers as $customer): ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars(
                                                $customer["name"] ?? "",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </strong>
                                        <br>
                                        <small>
                                            <?php echo htmlspecialchars(
                                                $customer["email"] ?? "",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo number_format(
                                            (int) ($customer["total_bookings"] ?? 0)
                                        ); ?>
                                    </td>
                                    <td class="money">
                                        KES <?php echo number_format(
                                            (float) ($customer["total_spent"] ?? 0),
                                            0
                                        ); ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>

                    <?php else: ?>

                        <div class="empty">
                            No customer activity available.
                        </div>

                    <?php endif; ?>

                </div>
            </article>

        </section>

    </main>

</div>

<script>
const reportsSidebar =
    document.getElementById("ownerReportsSidebar");

const reportsMenu =
    document.getElementById("ownerReportsMenu");

if (reportsSidebar && reportsMenu) {
    reportsMenu.addEventListener("click", function () {
        reportsSidebar.classList.toggle("open");
    });
}

const bookingCtx =
    document.getElementById("ownerBookingReportChart");

if (bookingCtx) {
    new Chart(bookingCtx, {
        type:"bar",
        data:{
            labels:<?php echo json_encode($monthLabels); ?>,
            datasets:[{
                data:<?php echo json_encode($bookingChartData); ?>,
                backgroundColor:"rgba(214,166,77,.32)",
                borderColor:"#d6a64d",
                borderWidth:1.5,
                borderRadius:5
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{
                x:{
                    ticks:{color:"#81736e",font:{size:9}},
                    grid:{display:false}
                },
                y:{
                    beginAtZero:true,
                    ticks:{
                        precision:0,
                        color:"#81736e",
                        font:{size:9}
                    },
                    grid:{color:"rgba(255,255,255,.05)"}
                }
            }
        }
    });
}

const revenueCtx =
    document.getElementById("ownerRevenueReportChart");

if (revenueCtx) {
    new Chart(revenueCtx, {
        type:"line",
        data:{
            labels:<?php echo json_encode($monthLabels); ?>,
            datasets:[{
                data:<?php echo json_encode($revenueChartData); ?>,
                borderColor:"#e23333",
                backgroundColor:"rgba(226,51,51,.12)",
                borderWidth:3,
                pointRadius:3,
                pointBackgroundColor:"#d6a64d",
                tension:.35,
                fill:true
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{
                x:{
                    ticks:{color:"#81736e",font:{size:9}},
                    grid:{display:false}
                },
                y:{
                    beginAtZero:true,
                    ticks:{
                        color:"#81736e",
                        font:{size:9},
                        callback:function(value){
                            return "KES " + Number(value).toLocaleString();
                        }
                    },
                    grid:{color:"rgba(255,255,255,.05)"}
                }
            }
        }
    });
}
</script>

</body>
</html>
