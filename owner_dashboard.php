
<?php
require_once __DIR__ . "/admin_auth.php";
requireOwner();

require_once __DIR__ . "/db.php";


/* =========================================================
   HELPERS
========================================================= */

function ownerEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   CORE BUSINESS METRICS
========================================================= */

$totalBookings = 0;
$totalRevenue = 0.0;
$totalCustomers = 0;
$paidBookings = 0;
$pendingBookings = 0;
$pendingValue = 0.0;
$failedBookings = 0;
$cancelledBookings = 0;
$testLikeTransactions = 0;


/* TOTAL BOOKINGS */

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    "
);

if ($result && $row = $result->fetch_assoc()) {
    $totalBookings = (int) ($row["total"] ?? 0);
}


/* REVENUE + PAYMENT STATUS SNAPSHOT */

$result = $conn->query(
    "
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                         AND amount > 1
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS paid_revenue,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                     AND amount > 1
                THEN 1
                ELSE 0
            END
        ) AS paid_count,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'pending'
                     AND amount > 1
                THEN 1
                ELSE 0
            END
        ) AS pending_count,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_status, '')) = 'pending'
                         AND amount > 1
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS pending_value,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) IN ('failed', 'timedout')
                     AND amount > 1
                THEN 1
                ELSE 0
            END
        ) AS failed_count,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'cancelled'
                     AND amount > 1
                THEN 1
                ELSE 0
            END
        ) AS cancelled_count

    FROM bookings
    "
);

if ($result && $row = $result->fetch_assoc()) {

    $totalRevenue =
        (float) ($row["paid_revenue"] ?? 0);

    $paidBookings =
        (int) ($row["paid_count"] ?? 0);

    $pendingBookings =
        (int) ($row["pending_count"] ?? 0);

    $pendingValue =
        (float) ($row["pending_value"] ?? 0);

    $failedBookings =
        (int) ($row["failed_count"] ?? 0);

    $cancelledBookings =
        (int) ($row["cancelled_count"] ?? 0);
}


/* DEVELOPMENT / TEST-LIKE TRANSACTIONS */

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE amount <= 1
    "
);

if ($result && $row = $result->fetch_assoc()) {
    $testLikeTransactions = (int) ($row["total"] ?? 0);
}


/* CUSTOMERS */

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM users
    "
);

if ($result && $row = $result->fetch_assoc()) {
    $totalCustomers = (int) ($row["total"] ?? 0);
}


/* =========================================================
   TODAY / OPERATIONS SNAPSHOT
========================================================= */

$todayRevenue = 0.0;
$monthRevenue = 0.0;
$yearRevenue = 0.0;
$todayBookings = 0;
$upcomingTours = 0;
$newCustomersMonth = 0;
$unreadMessages = 0;
$topTourName = "No paid bookings yet";
$topTourBookings = 0;

$result = $conn->query(
    "
    SELECT
        COALESCE(SUM(CASE
            WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                 AND amount > 1
                 AND DATE(created_at) = CURDATE()
            THEN amount ELSE 0 END), 0) AS today_revenue,

        COALESCE(SUM(CASE
            WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                 AND amount > 1
                 AND YEAR(created_at) = YEAR(CURDATE())
                 AND MONTH(created_at) = MONTH(CURDATE())
            THEN amount ELSE 0 END), 0) AS month_revenue,

        COALESCE(SUM(CASE
            WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                 AND amount > 1
                 AND YEAR(created_at) = YEAR(CURDATE())
            THEN amount ELSE 0 END), 0) AS year_revenue,

        SUM(CASE
            WHEN DATE(created_at) = CURDATE()
            THEN 1 ELSE 0 END) AS today_bookings,

        SUM(CASE
            WHEN date >= CURDATE()
                 AND LOWER(COALESCE(payment_status, '')) = 'paid'
                 AND amount > 1
            THEN 1 ELSE 0 END) AS upcoming_tours
    FROM bookings
    "
);

if ($result && $row = $result->fetch_assoc()) {
    $todayRevenue = (float) ($row["today_revenue"] ?? 0);
    $monthRevenue = (float) ($row["month_revenue"] ?? 0);
    $yearRevenue = (float) ($row["year_revenue"] ?? 0);
    $todayBookings = (int) ($row["today_bookings"] ?? 0);
    $upcomingTours = (int) ($row["upcoming_tours"] ?? 0);
}

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE YEAR(created_at) = YEAR(CURDATE())
      AND MONTH(created_at) = MONTH(CURDATE())
    "
);

if ($result && $row = $result->fetch_assoc()) {
    $newCustomersMonth = (int) ($row["total"] ?? 0);
}

$messageTable = $conn->query("SHOW TABLES LIKE 'messages'");
if ($messageTable && $messageTable->num_rows === 1) {
    $result = $conn->query(
        "
        SELECT COUNT(*) AS total
        FROM messages
        WHERE LOWER(COALESCE(status, '')) = 'unread'
        "
    );

    if ($result && $row = $result->fetch_assoc()) {
        $unreadMessages = (int) ($row["total"] ?? 0);
    }
}

$result = $conn->query(
    "
    SELECT
        COALESCE(NULLIF(TRIM(tour_name), ''), 'General Tour') AS tour_name,
        COUNT(*) AS total_bookings
    FROM bookings
    WHERE LOWER(COALESCE(payment_status, '')) = 'paid'
      AND amount > 1
    GROUP BY COALESCE(NULLIF(TRIM(tour_name), ''), 'General Tour')
    ORDER BY total_bookings DESC, tour_name ASC
    LIMIT 1
    "
);

if ($result && $row = $result->fetch_assoc()) {
    $topTourName = (string) ($row["tour_name"] ?? $topTourName);
    $topTourBookings = (int) ($row["total_bookings"] ?? 0);
}

$actionRequired =
    $pendingBookings
    + $failedBookings
    + $unreadMessages;

/* =========================================================
   MONTHLY PERFORMANCE
========================================================= */

$monthNames = [
    1 => "Jan",
    2 => "Feb",
    3 => "Mar",
    4 => "Apr",
    5 => "May",
    6 => "Jun",
    7 => "Jul",
    8 => "Aug",
    9 => "Sep",
    10 => "Oct",
    11 => "Nov",
    12 => "Dec"
];

$monthLabels =
    array_values($monthNames);

$monthlyRevenue =
    array_fill(1, 12, 0.0);

$monthlyBookings =
    array_fill(1, 12, 0);


$result = $conn->query(
    "
    SELECT
        MONTH(created_at) AS month_number,
        COUNT(*) AS total_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                         AND amount > 1
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM bookings

    WHERE YEAR(created_at) = YEAR(CURDATE())
      AND amount > 1

    GROUP BY MONTH(created_at)

    ORDER BY MONTH(created_at)
    "
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $month =
            (int) ($row["month_number"] ?? 0);

        if ($month >= 1 && $month <= 12) {

            $monthlyBookings[$month] =
                (int) ($row["total_bookings"] ?? 0);

            $monthlyRevenue[$month] =
                (float) ($row["revenue"] ?? 0);
        }
    }
}


$monthBookingValues = [];
$monthRevenueValues = [];

foreach ($monthNames as $number => $name) {

    $monthBookingValues[] =
        $monthlyBookings[$number];

    $monthRevenueValues[] =
        $monthlyRevenue[$number];
}


/* =========================================================
   PAYMENT METHOD INTELLIGENCE
========================================================= */

$paymentMethods = [];

$result = $conn->query(
    "
    SELECT
        payment,
        COUNT(*) AS total_transactions,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                     AND amount > 1
                THEN 1
                ELSE 0
            END
        ) AS paid_transactions,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                         AND amount > 1
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM bookings

    WHERE amount > 1

    GROUP BY payment

    ORDER BY revenue DESC, total_transactions DESC
    "
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $paymentMethods[] = $row;
    }
}


/* =========================================================
   RECENT BUSINESS ACTIVITY
========================================================= */

$recentActivity =
    $conn->query(
        "
        SELECT
            id,
            name,
            email,
            tour_name,
            date,
            amount,
            payment,
            payment_status,
            payment_reference,
            mpesa_receipt,
            created_at
        FROM bookings
        ORDER BY created_at DESC, id DESC
        LIMIT 8
        "
    );


/* =========================================================
   AUDIT LOG AVAILABILITY
========================================================= */

$auditAvailable = false;
$auditCount = 0;
$recentAudit = null;

$auditTable =
    $conn->query(
        "
        SHOW TABLES LIKE 'admin_audit_log'
        "
    );

if (
    $auditTable &&
    $auditTable->num_rows === 1
) {

    $auditAvailable = true;

    $result =
        $conn->query(
            "
            SELECT COUNT(*) AS total
            FROM admin_audit_log
            "
        );

    if ($result && $row = $result->fetch_assoc()) {
        $auditCount = (int) ($row["total"] ?? 0);
    }

    $recentAudit =
        $conn->query(
            "
            SELECT
                username,
                role,
                action,
                entity_type,
                entity_id,
                details,
                created_at
            FROM admin_audit_log
            ORDER BY id DESC
            LIMIT 6
            "
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
        Owner Command Center | Sprinter Tours & Safaris
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js"
    ></script>

    <style>

        :root {
            --bg: #090707;
            --panel: #120e0e;
            --panel-2: #171111;
            --panel-3: #1d1414;
            --red: #d92d2d;
            --red-strong: #ef3a3a;
            --red-deep: #721111;
            --gold: #d6a64d;
            --gold-soft: #ebc978;
            --text: #f7f1ed;
            --muted: #aa9e98;
            --border: rgba(255,255,255,.08);
            --success: #41b879;
            --warning: #e8b34d;
            --danger: #e05b5b;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "DM Sans", sans-serif;
            background:
                radial-gradient(
                    circle at 8% 4%,
                    rgba(160,19,19,.20),
                    transparent 27%
                ),
                radial-gradient(
                    circle at 92% 92%,
                    rgba(104,8,8,.18),
                    transparent 31%
                ),
                linear-gradient(
                    135deg,
                    #090707,
                    #120808 48%,
                    #060606
                );
            color: var(--text);
        }

        a {
            color: inherit;
        }

        .owner-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
        }

        /* =====================================================
           SIDEBAR
        ===================================================== */

        .owner-sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 26px 18px;
            display: flex;
            flex-direction: column;
            background:
                linear-gradient(
                    180deg,
                    #250a0a 0%,
                    #140808 46%,
                    #090707 100%
                );
            border-right:
                1px solid rgba(232,55,55,.16);
            overflow: hidden;
        }

        .owner-sidebar::after {
            content: "";
            position: absolute;
            inset: auto -130px -100px auto;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            background:
                radial-gradient(
                    circle,
                    rgba(222,38,38,.16),
                    transparent 67%
                );
            pointer-events: none;
        }

        .owner-brand {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 7px 6px 22px;
            border-bottom:
                1px solid rgba(255,255,255,.07);
        }

        .owner-brand img {
            width: 46px;
            height: 46px;
            object-fit: contain;
            padding: 4px;
            border-radius: 12px;
            background: #fff;
        }

        .owner-brand strong {
            display: block;
            font-size: 14px;
        }

        .owner-brand span {
            display: block;
            margin-top: 3px;
            color: var(--gold-soft);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .owner-nav {
            position: relative;
            z-index: 2;
            margin-top: 26px;
            display: grid;
            gap: 8px;
        }

        .owner-nav-label {
            margin:
                10px 10px 5px;
            color: #7f706b;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 1.6px;
            text-transform: uppercase;
        }

        .owner-nav a {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: 11px;
            color: #cfc2bc;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            transition: .2s ease;
        }

        .owner-nav a i {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background:
                rgba(255,255,255,.04);
            color: var(--gold);
        }

        .owner-nav a:hover,
        .owner-nav a.active {
            color: #fff;
            background:
                linear-gradient(
                    90deg,
                    rgba(180,27,27,.30),
                    rgba(105,10,10,.17)
                );
        }

        .owner-nav a.active {
            box-shadow:
                inset 3px 0 0 var(--red-strong);
        }

        .owner-sidebar-bottom {
            position: relative;
            z-index: 2;
            margin-top: auto;
            padding-top: 18px;
            border-top:
                1px solid rgba(255,255,255,.07);
        }

        .owner-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 8px;
        }

        .owner-avatar {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background:
                linear-gradient(
                    135deg,
                    #8f1717,
                    #d62e2e
                );
            color: var(--gold-soft);
        }

        .owner-profile strong {
            display: block;
            font-size: 11px;
        }

        .owner-profile span {
            display: block;
            margin-top: 2px;
            color: #857772;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .owner-main {
            min-width: 0;
            padding: 30px;
        }

        .owner-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

        .owner-heading small {
            display: block;
            margin-bottom: 7px;
            color: var(--red-strong);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .owner-heading h1 {
            margin: 0;
            font-family:
                "Playfair Display",
                serif;
            font-size:
                clamp(
                    30px,
                    4vw,
                    45px
                );
            font-weight: 600;
            line-height: 1;
        }

        .owner-heading h1 span {
            color: var(--red-strong);
        }

        .owner-heading p {
            margin:
                9px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .owner-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .owner-button {
            min-height: 42px;
            padding: 10px 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border:
                1px solid rgba(255,255,255,.08);
            border-radius: 11px;
            background: rgba(255,255,255,.03);
            color: #ddd0ca;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
        }

        .owner-button-primary {
            border-color:
                rgba(225,47,47,.32);
            background:
                linear-gradient(
                    135deg,
                    #811515,
                    #d62d2d
                );
            color: #fff;
            box-shadow:
                0 12px 28px
                rgba(185,23,23,.18);
        }

        /* =====================================================
           HERO / COMMAND STRIP
        ===================================================== */

        .command-strip {
            position: relative;
            overflow: hidden;
            min-height: 170px;
            padding: 28px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            border:
                1px solid
                rgba(220,52,52,.20);
            border-radius: 20px;
            background:
                linear-gradient(
                    90deg,
                    rgba(88,12,12,.92),
                    rgba(37,9,9,.94) 48%,
                    rgba(13,10,10,.96)
                );
        }

        .command-strip::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(
                    90deg,
                    rgba(37,4,4,.70),
                    rgba(19,4,4,.50) 55%,
                    rgba(4,4,4,.22)
                ),
                url("images/owner-lion.jpg");
            background-repeat: no-repeat;
            background-size: cover;
            background-position: 80% 42%;
            opacity: .24;
            pointer-events: none;
        }

        .command-content,
        .command-status {
            position: relative;
            z-index: 2;
        }

        .command-content small {
            color: var(--gold-soft);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .command-content h2 {
            max-width: 580px;
            margin: 8px 0 7px;
            font-family:
                "Playfair Display",
                serif;
            font-size:
                clamp(
                    26px,
                    3vw,
                    38px
                );
            line-height: 1;
        }

        .command-content h2 span {
            color: var(--red-strong);
        }

        .command-content p {
            max-width: 620px;
            margin: 0;
            color: #c9bbb5;
            font-size: 11px;
            line-height: 1.65;
        }

        .command-status {
            flex: 0 0 auto;
            min-width: 160px;
            padding: 17px 18px;
            border:
                1px solid rgba(255,255,255,.09);
            border-radius: 15px;
            background:
                rgba(9,7,7,.58);
            backdrop-filter:
                blur(8px);
        }

        .command-status span {
            display: block;
            color: #8e817c;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .command-status strong {
            display: block;
            margin-top: 5px;
            color: var(--gold-soft);
            font-size: 15px;
        }

        /* =====================================================
           KPI CARDS
        ===================================================== */

        .owner-kpi-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .owner-kpi {
            min-width: 0;
            padding: 18px;
            border:
                1px solid var(--border);
            border-radius: 15px;
            background:
                linear-gradient(
                    180deg,
                    rgba(28,20,20,.96),
                    rgba(18,14,14,.96)
                );
            box-shadow:
                0 14px 35px
                rgba(0,0,0,.15);
        }

        .owner-kpi-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .owner-kpi-label {
            margin: 0;
            color: #988a85;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .owner-kpi-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background:
                rgba(166,19,19,.18);
            color: var(--gold);
        }

        .owner-kpi-value {
            margin-top: 15px;
            font-family:
                "Playfair Display",
                serif;
            font-size:
                clamp(
                    23px,
                    3vw,
                    31px
                );
            line-height: 1;
        }

        .owner-kpi-note {
            margin:
                7px 0 0;
            color: #776b67;
            font-size: 9px;
        }

        /* =====================================================
           OPERATIONS SNAPSHOT
        ===================================================== */

        .owner-ops-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        .owner-ops-card {
            position: relative;
            min-width: 0;
            padding: 16px;
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px;
            background: rgba(255,255,255,.025);
            overflow: hidden;
        }

        .owner-ops-card::after {
            content: "";
            position: absolute;
            width: 80px;
            height: 80px;
            right: -32px;
            top: -32px;
            border-radius: 50%;
            background: rgba(217,45,45,.08);
        }

        .owner-ops-card .ops-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .owner-ops-card .ops-label {
            color: #8f817c;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .owner-ops-card i {
            color: var(--gold);
        }

        .owner-ops-card strong {
            display: block;
            margin-top: 10px;
            color: #f7f1ed;
            font-family: "Playfair Display", serif;
            font-size: 23px;
            line-height: 1.1;
        }

        .owner-ops-card small {
            display: block;
            margin-top: 6px;
            color: #786d68;
            font-size: 10px;
            line-height: 1.5;
        }

        .owner-ops-card.attention {
            border-color: rgba(232,179,77,.28);
            background: linear-gradient(180deg, rgba(73,48,12,.16), rgba(255,255,255,.02));
        }

        .owner-ops-card.attention strong {
            color: var(--warning);
        }

        .owner-ops-card.tour strong {
            font-family: "DM Sans", sans-serif;
            font-size: 16px;
            line-height: 1.35;
        }

        /* =====================================================
           PANELS
        ===================================================== */

        .owner-grid-2 {
            display: grid;
            grid-template-columns:
                minmax(0, 1.35fr)
                minmax(0, .65fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .owner-panel {
            min-width: 0;
            overflow: hidden;
            border:
                1px solid var(--border);
            border-radius: 16px;
            background:
                linear-gradient(
                    180deg,
                    rgba(24,18,18,.96),
                    rgba(15,12,12,.96)
                );
        }

        .owner-panel-header {
            min-height: 58px;
            padding: 15px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border-bottom:
                1px solid rgba(255,255,255,.06);
        }

        .owner-panel-header h2 {
            margin: 0;
            font-size: 12px;
        }

        .owner-panel-header span,
        .owner-panel-header a {
            color: #887a75;
            font-size: 9px;
            text-decoration: none;
        }

        .owner-panel-body {
            padding: 18px;
        }

        .owner-chart {
            height: 280px;
        }

        .method-list {
            display: grid;
            gap: 10px;
        }

        .method-card {
            padding: 13px;
            border:
                1px solid rgba(255,255,255,.06);
            border-radius: 12px;
            background:
                rgba(255,255,255,.02);
        }

        .method-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .method-name {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ded3ce;
            font-size: 10px;
            font-weight: 700;
        }

        .method-name i {
            color: var(--gold);
        }

        .method-revenue {
            color: var(--gold-soft);
            font-size: 10px;
            font-weight: 800;
        }

        .method-card small {
            display: block;
            margin-top: 5px;
            color: #746762;
            font-size: 8px;
        }

        /* =====================================================
           STATUS STRIP
        ===================================================== */

        .status-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .status-box {
            padding: 14px;
            border:
                1px solid rgba(255,255,255,.06);
            border-radius: 12px;
            background:
                rgba(255,255,255,.02);
        }

        .status-box span {
            display: block;
            color: #837570;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-box strong {
            display: block;
            margin-top: 5px;
            font-size: 19px;
        }

        .status-paid strong {
            color: var(--success);
        }

        .status-pending strong {
            color: var(--warning);
        }

        .status-failed strong,
        .status-cancelled strong {
            color: var(--danger);
        }

        /* =====================================================
           TABLE
        ===================================================== */

        .owner-table-wrapper {
            overflow-x: auto;
        }

        .owner-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .owner-table th {
            padding: 12px 14px;
            color: #776a65;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom:
                1px solid rgba(255,255,255,.06);
        }

        .owner-table td {
            padding: 13px 14px;
            color: #c8bbb5;
            font-size: 9px;
            border-bottom:
                1px solid rgba(255,255,255,.05);
            vertical-align: middle;
        }

        .owner-table strong {
            color: #f2e9e5;
        }

        .owner-table small {
            color: #776a65;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 25px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .pill-paid {
            color: #75d7a1;
            background:
                rgba(44,135,83,.16);
        }

        .pill-pending {
            color: #ebbf63;
            background:
                rgba(169,118,28,.16);
        }

        .pill-failed,
        .pill-cancelled {
            color: #ef8585;
            background:
                rgba(157,34,34,.16);
        }

        .pill-default {
            color: #c0b3ad;
            background:
                rgba(255,255,255,.06);
        }

        /* =====================================================
           AUDIT
        ===================================================== */

        .audit-list {
            display: grid;
            gap: 9px;
        }

        .audit-item {
            padding: 12px 13px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border:
                1px solid rgba(255,255,255,.05);
            border-radius: 11px;
            background:
                rgba(255,255,255,.02);
        }

        .audit-icon {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background:
                rgba(154,19,19,.18);
            color: var(--gold);
        }

        .audit-content strong {
            display: block;
            color: #e9ddd8;
            font-size: 9px;
        }

        .audit-content span {
            display: block;
            margin-top: 3px;
            color: #766a65;
            font-size: 8px;
            line-height: 1.45;
        }

        .owner-empty {
            padding: 18px;
            color: #786b66;
            font-size: 9px;
            text-align: center;
        }

        .mobile-menu {
            display: none;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .owner-shell {
                grid-template-columns: 210px minmax(0, 1fr);
            }

            .owner-kpi-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 860px) {

            .owner-shell {
                display: block;
            }

            .owner-sidebar {
                position: fixed;
                z-index: 100;
                left: -260px;
                width: 250px;
                transition: left .2s ease;
            }

            .owner-sidebar.open {
                left: 0;
            }

            .owner-main {
                padding: 20px;
            }

            .mobile-menu {
                display: inline-flex;
            }

            .owner-grid-2 {
                grid-template-columns: 1fr;
            }

            .command-strip {
                align-items: flex-start;
                flex-direction: column;
            }

            .command-status {
                width: 100%;
            }
        }

        @media (max-width: 600px) {

            .owner-main {
                padding: 15px;
            }

            .owner-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .owner-actions {
                width: 100%;
            }

            .owner-button {
                flex: 1;
            }

            .owner-kpi-grid,
            .owner-ops-grid,
            .status-grid {
                grid-template-columns: 1fr 1fr;
            }

            .command-strip {
                padding: 21px;
            }

            .owner-chart {
                height: 230px;
            }
        }

        @media (max-width: 390px) {

            .owner-kpi-grid,
            .owner-ops-grid,
            .status-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>
<link rel="stylesheet" href="owner_readability.css">
</head>

<body>

<div class="owner-shell">

    <aside
        class="owner-sidebar"
        id="ownerSidebar"
    >

        <div class="owner-brand">

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


        <nav class="owner-nav">

            <div class="owner-nav-label">
                Executive
            </div>

            <a
                href="owner_dashboard.php"
                class="active"
            >
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


            <div class="owner-nav-label">
                Oversight
            </div>

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


        <div class="owner-sidebar-bottom">

            <div class="owner-profile">

                <div class="owner-avatar">
                    <i class="fa-solid fa-crown"></i>
                </div>

                <div>

                    <strong>
                        <?php
                            echo ownerEscape(
                                $adminUsername
                            );
                        ?>
                    </strong>

                    <span>
                        Owner
                    </span>

                </div>

            </div>

            <a
                href="admin_logout.php"
                class="owner-button"
                style="width:100%; margin-top:8px;"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Sign Out
            </a>

        </div>

    </aside>


    <main class="owner-main">

        <header class="owner-topbar">

            <div class="owner-heading">

                <small>
                    Executive Access
                </small>

                <h1>
                    Welcome back,
                    <span>Boss.</span>
                </h1>

                <p>
                    A private view of business performance,
                    revenue and operational activity.
                </p>

            </div>


            <div class="owner-actions">

                <button
                    type="button"
                    class="owner-button mobile-menu"
                    id="ownerMobileMenu"
                >
                    <i class="fa-solid fa-bars"></i>
                    Menu
                </button>

                <a
                    href="index.html"
                    target="_blank"
                    class="owner-button"
                >
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    View Website
                </a>

                <a
                    href="owner_reports.php"
                    class="owner-button owner-button-primary"
                >
                    <i class="fa-solid fa-chart-line"></i>
                    Full Reports
                </a>

            </div>

        </header>


        <section class="command-strip">

            <div class="command-content">

                <small>
                    Owner Intelligence
                </small>

                <h2>
                    Command. Oversee.
                    <span>Grow.</span>
                </h2>

                <p>
                    Track confirmed revenue, customer activity,
                    payment performance and the operational signals
                    that matter to the business.
                </p>

            </div>


            <div class="command-status">

                <span>
                    System Access
                </span>

                <strong>
                    Owner Verified
                </strong>

                <span style="margin-top:12px;">Development Activity</span>
                <strong style="font-size:12px;">
                    <?php echo number_format($testLikeTransactions); ?> test-like transaction<?php echo $testLikeTransactions === 1 ? "" : "s"; ?>
                </strong>

            </div>

        </section>


        <section class="owner-kpi-grid">

            <article class="owner-kpi">

                <div class="owner-kpi-top">

                    <p class="owner-kpi-label">
                        Confirmed Revenue
                    </p>

                    <div class="owner-kpi-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>

                </div>

                <div class="owner-kpi-value">

                    KES
                    <?php
                        echo number_format(
                            $totalRevenue,
                            0
                        );
                    ?>

                </div>

                <p class="owner-kpi-note">
                    KES <?php echo number_format($yearRevenue, 0); ?> confirmed this year
                </p>

            </article>


            <article class="owner-kpi">

                <div class="owner-kpi-top">

                    <p class="owner-kpi-label">
                        Total Bookings
                    </p>

                    <div class="owner-kpi-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>

                </div>

                <div class="owner-kpi-value">
                    <?php
                        echo number_format(
                            $totalBookings
                        );
                    ?>
                </div>

                <p class="owner-kpi-note">
                    All recorded bookings
                </p>

            </article>


            <article class="owner-kpi">

                <div class="owner-kpi-top">

                    <p class="owner-kpi-label">
                        Customers
                    </p>

                    <div class="owner-kpi-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                </div>

                <div class="owner-kpi-value">
                    <?php
                        echo number_format(
                            $totalCustomers
                        );
                    ?>
                </div>

                <p class="owner-kpi-note">
                    <?php echo number_format($newCustomersMonth); ?> new this month · <?php echo number_format($totalCustomers); ?> total
                </p>

            </article>


            <article class="owner-kpi">

                <div class="owner-kpi-top">

                    <p class="owner-kpi-label">
                        Pending Value
                    </p>

                    <div class="owner-kpi-icon">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>

                </div>

                <div class="owner-kpi-value">

                    KES
                    <?php
                        echo number_format(
                            $pendingValue,
                            0
                        );
                    ?>

                </div>

                <p class="owner-kpi-note">
                    Unconfirmed booking value
                </p>

            </article>

        </section>


        <section class="owner-ops-grid" aria-label="Today and operations snapshot">

            <article class="owner-ops-card">
                <div class="ops-top">
                    <span class="ops-label">Revenue Today</span>
                    <i class="fa-solid fa-coins"></i>
                </div>
                <strong>KES <?php echo number_format($todayRevenue, 0); ?></strong>
                <small>KES <?php echo number_format($monthRevenue, 0); ?> confirmed this month</small>
            </article>

            <article class="owner-ops-card">
                <div class="ops-top">
                    <span class="ops-label">Bookings Today</span>
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <strong><?php echo number_format($todayBookings); ?></strong>
                <small><?php echo number_format($upcomingTours); ?> paid upcoming tour<?php echo $upcomingTours === 1 ? "" : "s"; ?></small>
            </article>

            <article class="owner-ops-card tour">
                <div class="ops-top">
                    <span class="ops-label">Top Selling Tour</span>
                    <i class="fa-solid fa-ranking-star"></i>
                </div>
                <strong><?php echo ownerEscape($topTourName); ?></strong>
                <small><?php echo number_format($topTourBookings); ?> confirmed booking<?php echo $topTourBookings === 1 ? "" : "s"; ?></small>
            </article>

            <article class="owner-ops-card attention">
                <div class="ops-top">
                    <span class="ops-label">Action Required</span>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <strong><?php echo number_format($actionRequired); ?></strong>
                <small><?php echo number_format($pendingBookings); ?> pending · <?php echo number_format($failedBookings); ?> failed · <?php echo number_format($unreadMessages); ?> unread</small>
            </article>

        </section>

        <section class="owner-grid-2">

            <article class="owner-panel">

                <div class="owner-panel-header">

                    <h2>
                        Revenue Performance
                    </h2>

                    <span>
                        <?php echo date("Y"); ?>
                    </span>

                </div>

                <div class="owner-panel-body">

                    <div class="owner-chart">

                        <canvas
                            id="revenueChart"
                        ></canvas>

                    </div>

                </div>

            </article>


            <article class="owner-panel">

                <div class="owner-panel-header">

                    <h2>
                        Payment Intelligence
                    </h2>

                    <a href="owner_payments.php">
                        View payments
                    </a>

                </div>

                <div class="owner-panel-body">

                    <div class="method-list">

                        <?php if (count($paymentMethods) > 0): ?>

                            <?php foreach ($paymentMethods as $method): ?>

                                <div class="method-card">

                                    <div class="method-top">

                                        <div class="method-name">

                                            <i class="fa-solid fa-credit-card"></i>

                                            <?php
                                                echo ownerEscape(
                                                    $method["payment"]
                                                    ?? "Unknown"
                                                );
                                            ?>

                                        </div>

                                        <div class="method-revenue">

                                            KES
                                            <?php
                                                echo number_format(
                                                    (float) (
                                                        $method["revenue"]
                                                        ?? 0
                                                    ),
                                                    0
                                                );
                                            ?>

                                        </div>

                                    </div>

                                    <small>

                                        <?php
                                            echo number_format(
                                                (int) (
                                                    $method["paid_transactions"]
                                                    ?? 0
                                                )
                                            );
                                        ?>
                                        paid of
                                        <?php
                                            echo number_format(
                                                (int) (
                                                    $method["total_transactions"]
                                                    ?? 0
                                                )
                                            );
                                        ?>
                                        transactions

                                    </small>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="owner-empty">
                                No payment information yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </article>

        </section>


        <section
            class="owner-panel"
            style="margin-bottom:16px;"
        >

            <div class="owner-panel-header">

                <h2>
                    Booking Health
                </h2>

                <span>
                    Current database
                </span>

            </div>

            <div class="owner-panel-body">

                <div class="status-grid">

                    <div class="status-box status-paid">

                        <span>
                            Paid
                        </span>

                        <strong>
                            <?php
                                echo number_format(
                                    $paidBookings
                                );
                            ?>
                        </strong>

                    </div>


                    <div class="status-box status-pending">

                        <span>
                            Pending
                        </span>

                        <strong>
                            <?php
                                echo number_format(
                                    $pendingBookings
                                );
                            ?>
                        </strong>

                    </div>


                    <div class="status-box status-failed">

                        <span>
                            Failed / Timeout
                        </span>

                        <strong>
                            <?php
                                echo number_format(
                                    $failedBookings
                                );
                            ?>
                        </strong>

                    </div>


                    <div class="status-box status-cancelled">

                        <span>
                            Cancelled
                        </span>

                        <strong>
                            <?php
                                echo number_format(
                                    $cancelledBookings
                                );
                            ?>
                        </strong>

                    </div>

                </div>

            </div>

        </section>


        <section
            class="owner-panel"
            style="margin-bottom:16px;"
        >

            <div class="owner-panel-header">

                <h2>
                    Recent Business Activity
                </h2>

                <a href="owner_bookings.php">
                    View all bookings
                </a>

            </div>

            <div class="owner-table-wrapper">

                <?php if (
                    $recentActivity
                    && $recentActivity->num_rows > 0
                ): ?>

                    <table class="owner-table">

                        <thead>

                            <tr>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Tour</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php while (
                            $booking =
                                $recentActivity
                                ->fetch_assoc()
                        ): ?>

                            <?php

                                $status =
                                    strtolower(
                                        trim(
                                            (string) (
                                                $booking[
                                                    "payment_status"
                                                ]
                                                ?? ""
                                            )
                                        )
                                    );

                                $statusClass =
                                    match ($status) {

                                        "paid"
                                            => "pill-paid",

                                        "pending"
                                            => "pill-pending",

                                        "failed",
                                        "timedout"
                                            => "pill-failed",

                                        "cancelled"
                                            => "pill-cancelled",

                                        default
                                            => "pill-default"
                                    };

                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        #
                                        <?php
                                            echo (int)
                                                $booking["id"];
                                        ?>
                                    </strong>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                            echo ownerEscape(
                                                $booking["name"]
                                                ?? ""
                                            );
                                        ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?php
                                            echo ownerEscape(
                                                $booking["email"]
                                                ?? ""
                                            );
                                        ?>
                                    </small>
                                </td>

                                <td>
                                    <?php
                                        echo ownerEscape(
                                            $booking["tour_name"]
                                            ?? "—"
                                        );
                                    ?>
                                </td>

                                <td>
                                    KES
                                    <?php
                                        echo number_format(
                                            (float) (
                                                $booking["amount"]
                                                ?? 0
                                            ),
                                            0
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo ownerEscape(
                                            $booking["payment"]
                                            ?? "—"
                                        );
                                    ?>
                                </td>

                                <td>
                                    <span
                                        class="status-pill
                                        <?php
                                            echo $statusClass;
                                        ?>"
                                    >
                                        <?php
                                            echo ownerEscape(
                                                $booking[
                                                    "payment_status"
                                                ]
                                                ?? "Unknown"
                                            );
                                        ?>
                                    </span>
                                </td>

                                <td>
                                    <?php

                                        $createdAt =
                                            $booking[
                                                "created_at"
                                            ]
                                            ?? null;

                                        echo $createdAt
                                            ? date(
                                                "d M Y H:i",
                                                strtotime(
                                                    $createdAt
                                                )
                                            )
                                            : "—";

                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="owner-empty">
                        No business activity yet.
                    </div>

                <?php endif; ?>

            </div>

        </section>


        <section
            class="owner-panel"
            id="audit"
        >

            <div class="owner-panel-header">

                <h2>
                    Administrative Oversight
                </h2>

                <span>
                    <?php
                        echo $auditAvailable
                            ? number_format(
                                $auditCount
                            )
                              . " logged actions"
                            : "Audit setup pending";
                    ?>
                </span>

            </div>

            <div class="owner-panel-body">

                <?php if (
                    $auditAvailable
                    && $recentAudit
                    && $recentAudit->num_rows > 0
                ): ?>

                    <div class="audit-list">

                    <?php while (
                        $audit =
                            $recentAudit
                            ->fetch_assoc()
                    ): ?>

                        <div class="audit-item">

                            <div class="audit-icon">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>

                            <div class="audit-content">

                                <strong>
                                    <?php
                                        echo ownerEscape(
                                            $audit[
                                                "username"
                                            ]
                                            ?? "Unknown"
                                        );
                                    ?>
                                    —
                                    <?php
                                        echo ownerEscape(
                                            $audit[
                                                "action"
                                            ]
                                            ?? "Action"
                                        );
                                    ?>
                                </strong>

                                <span>

                                    <?php
                                        echo ownerEscape(
                                            $audit[
                                                "details"
                                            ]
                                            ?? ""
                                        );
                                    ?>

                                    <?php if (
                                        !empty(
                                            $audit[
                                                "created_at"
                                            ]
                                        )
                                    ): ?>

                                        ·
                                        <?php
                                            echo date(
                                                "d M Y H:i",
                                                strtotime(
                                                    $audit[
                                                        "created_at"
                                                    ]
                                                )
                                            );
                                        ?>

                                    <?php endif; ?>

                                </span>

                            </div>

                        </div>

                    <?php endwhile; ?>

                    </div>

                <?php elseif ($auditAvailable): ?>

                    <div class="owner-empty">
                        Audit logging is ready. No actions have been recorded yet.
                    </div>

                <?php else: ?>

                    <div class="owner-empty">
                        The Owner dashboard is ready for audit activity.
                        We will connect the protected admin audit log next.
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>


<script>

const ownerSidebar =
    document.getElementById(
        "ownerSidebar"
    );

const ownerMobileMenu =
    document.getElementById(
        "ownerMobileMenu"
    );

if (
    ownerSidebar
    &&
    ownerMobileMenu
) {

    ownerMobileMenu.addEventListener(
        "click",
        function () {

            ownerSidebar.classList.toggle(
                "open"
            );

        }
    );
}


/* =========================================================
   REVENUE CHART
========================================================= */

const revenueCanvas =
    document.getElementById(
        "revenueChart"
    );

if (revenueCanvas) {

    new Chart(
        revenueCanvas,
        {

            type: "line",

            data: {

                labels:
                    <?php
                        echo json_encode(
                            $monthLabels
                        );
                    ?>,

                datasets: [

                    {

                        label:
                            "Revenue",

                        data:
                            <?php
                                echo json_encode(
                                    $monthRevenueValues
                                );
                            ?>,

                        borderColor:
                            "#ef3a3a",

                        backgroundColor:
                            "rgba(239,58,58,.12)",

                        borderWidth:
                            3,

                        pointRadius:
                            3,

                        pointHoverRadius:
                            5,

                        pointBackgroundColor:
                            "#d6a64d",

                        tension:
                            .35,

                        fill:
                            true

                    },

                    {

                        label:
                            "Bookings",

                        data:
                            <?php
                                echo json_encode(
                                    $monthBookingValues
                                );
                            ?>,

                        borderColor:
                            "#d6a64d",

                        backgroundColor:
                            "rgba(214,166,77,.04)",

                        borderWidth:
                            2,

                        pointRadius:
                            2,

                        tension:
                            .35,

                        yAxisID:
                            "yBookings"

                    }

                ]

            },

            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                interaction: {
                    mode: "index",
                    intersect: false
                },

                plugins: {

                    legend: {

                        labels: {
                            color:
                                "#aa9e98",
                            boxWidth:
                                12,
                            font: {
                                size:
                                    9
                            }
                        }

                    }

                },

                scales: {

                    x: {

                        ticks: {
                            color:
                                "#786b66",
                            font: {
                                size:
                                    9
                            }
                        },

                        grid: {
                            display:
                                false
                        }

                    },

                    y: {

                        beginAtZero:
                            true,

                        ticks: {
                            color:
                                "#786b66",
                            font: {
                                size:
                                    9
                            },
                            callback:
                                function (value) {
                                    return "KES "
                                        + Number(value)
                                            .toLocaleString();
                                }
                        },

                        grid: {
                            color:
                                "rgba(255,255,255,.05)"
                        }

                    },

                    yBookings: {

                        position:
                            "right",

                        beginAtZero:
                            true,

                        ticks: {
                            precision:
                                0,
                            color:
                                "#8e7e75",
                            font: {
                                size:
                                    9
                            }
                        },

                        grid: {
                            display:
                                false
                        }

                    }

                }

            }

        }
    );

}

</script>

</body>
</html>
