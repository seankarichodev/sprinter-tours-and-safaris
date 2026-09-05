<?php

require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";


/* =========================================================
   CSRF
========================================================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}


/* =========================================================
   HELPERS
========================================================= */

function adminEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function getPaymentStatusClass(string $status): string
{
    return match (strtolower(trim($status))) {
        "paid" => "status-paid",
        "pending" => "status-pending",
        "failed", "timedout" => "status-failed",
        "cancelled" => "status-cancelled",
        default => "status-default"
    };
}

function getEnvironmentClass(float $amount): string
{
    return $amount <= 1 ? "environment-test" : "environment-live";
}

function getEnvironmentLabel(float $amount): string
{
    return $amount <= 1 ? "Test-like" : "Live";
}


/* =========================================================
   FILTERS
========================================================= */

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");
$methodFilter = trim($_GET["method"] ?? "");
$travelFilter = trim($_GET["travel"] ?? "");

$allowedStatuses = [
    "",
    "Pending",
    "Paid",
    "Failed",
    "Cancelled",
    "TimedOut"
];

$allowedMethods = [
    "",
    "Mpesa",
    "Card",
    "PayPal"
];

$allowedTravelFilters = [
    "",
    "today",
    "upcoming",
    "past"
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "";
}

if (!in_array($methodFilter, $allowedMethods, true)) {
    $methodFilter = "";
}

if (!in_array($travelFilter, $allowedTravelFilters, true)) {
    $travelFilter = "";
}

$allowedLimits = [5, 10, 25, 50];

$limit = isset($_GET["limit"])
    ? (int) $_GET["limit"]
    : 10;

if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$page = isset($_GET["page"])
    ? max(1, (int) $_GET["page"])
    : 1;


/* =========================================================
   OPERATIONS SUMMARY
========================================================= */

$summary = [
    "total" => 0,
    "upcoming" => 0,
    "pending" => 0,
    "cancelled" => 0,
    "test_like" => 0
];

$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total,

        SUM(
            CASE
                WHEN date >= CURDATE()
                     AND LOWER(COALESCE(payment_status, '')) <> 'cancelled'
                THEN 1
                ELSE 0
            END
        ) AS upcoming,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled,

        SUM(
            CASE
                WHEN amount <= 1
                THEN 1
                ELSE 0
            END
        ) AS test_like

    FROM bookings
");

if ($summaryResult && ($row = $summaryResult->fetch_assoc())) {
    $summary["total"] = (int) ($row["total"] ?? 0);
    $summary["upcoming"] = (int) ($row["upcoming"] ?? 0);
    $summary["pending"] = (int) ($row["pending"] ?? 0);
    $summary["cancelled"] = (int) ($row["cancelled"] ?? 0);
    $summary["test_like"] = (int) ($row["test_like"] ?? 0);
}


/* =========================================================
   TRAVEL FILTER SQL
========================================================= */

$travelSql = "";

if ($travelFilter === "today") {
    $travelSql = " AND b.date = CURDATE() ";
} elseif ($travelFilter === "upcoming") {
    $travelSql = " AND b.date > CURDATE() ";
} elseif ($travelFilter === "past") {
    $travelSql = " AND b.date < CURDATE() ";
}


/* =========================================================
   COUNT FILTERED BOOKINGS
========================================================= */

$countSql = "
    SELECT COUNT(*) AS total

    FROM bookings b

    LEFT JOIN users u
        ON b.user_id = u.id

    WHERE
    (
        ? = ''
        OR b.name LIKE CONCAT('%', ?, '%')
        OR b.email LIKE CONCAT('%', ?, '%')
        OR b.tour_name LIKE CONCAT('%', ?, '%')
        OR b.phone LIKE CONCAT('%', ?, '%')
        OR b.payment_reference LIKE CONCAT('%', ?, '%')
        OR b.mpesa_receipt LIKE CONCAT('%', ?, '%')
        OR CAST(b.id AS CHAR) LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''
        OR LOWER(COALESCE(b.payment_status, '')) = LOWER(?)
    )

    AND
    (
        ? = ''
        OR LOWER(COALESCE(b.payment, '')) = LOWER(?)
    )

    $travelSql
";

$countStmt = $conn->prepare($countSql);
$totalRecords = 0;

if ($countStmt) {
    $countStmt->bind_param(
        "ssssssssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $statusFilter,
        $statusFilter,
        $methodFilter,
        $methodFilter
    );

    $countStmt->execute();
    $countResult = $countStmt->get_result();

    if ($countResult && ($countRow = $countResult->fetch_assoc())) {
        $totalRecords = (int) ($countRow["total"] ?? 0);
    }

    $countStmt->close();
}

$totalPages = max(1, (int) ceil($totalRecords / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;


/* =========================================================
   FETCH BOOKINGS
========================================================= */

$sql = "
    SELECT
        b.id,
        b.user_id,
        b.name,
        b.email,
        b.tour_name,
        b.date,
        b.time,
        b.payment,
        b.phone,
        b.amount,
        b.payment_status,
        b.payment_reference,
        b.created_at,
        b.merchant_request_id,
        b.checkout_request_id,
        b.mpesa_receipt,
        u.name AS account_name

    FROM bookings b

    LEFT JOIN users u
        ON b.user_id = u.id

    WHERE
    (
        ? = ''
        OR b.name LIKE CONCAT('%', ?, '%')
        OR b.email LIKE CONCAT('%', ?, '%')
        OR b.tour_name LIKE CONCAT('%', ?, '%')
        OR b.phone LIKE CONCAT('%', ?, '%')
        OR b.payment_reference LIKE CONCAT('%', ?, '%')
        OR b.mpesa_receipt LIKE CONCAT('%', ?, '%')
        OR CAST(b.id AS CHAR) LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''
        OR LOWER(COALESCE(b.payment_status, '')) = LOWER(?)
    )

    AND
    (
        ? = ''
        OR LOWER(COALESCE(b.payment, '')) = LOWER(?)
    )

    $travelSql

    ORDER BY b.created_at DESC, b.id DESC

    LIMIT $limit
    OFFSET $offset
";

$stmt = $conn->prepare($sql);
$result = null;

if ($stmt) {
    $stmt->bind_param(
        "ssssssssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $statusFilter,
        $statusFilter,
        $methodFilter,
        $methodFilter
    );

    $stmt->execute();
    $result = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bookings | Sprinter Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link rel="stylesheet" href="admin.css">

    <style>
        .booking-summary-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
            margin-bottom:20px;
        }

        .booking-summary-card{
            position:relative;
            overflow:hidden;
            padding:18px;
            border:1px solid var(--admin-border);
            border-radius:var(--radius-md);
            background:var(--admin-card);
            box-shadow:var(--shadow-sm);
        }

        .booking-summary-card::after{
            content:"";
            position:absolute;
            width:76px;
            height:76px;
            top:-28px;
            right:-28px;
            border-radius:50%;
            background:rgba(23,107,69,.05);
        }

        .booking-summary-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
        }

        .booking-summary-label{
            color:var(--admin-muted);
            font-size:11px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .booking-summary-icon{
            width:36px;
            height:36px;
            display:grid;
            place-items:center;
            border-radius:10px;
            background:var(--admin-green-soft);
            color:var(--admin-green);
        }

        .booking-summary-value{
            margin-top:14px;
            color:var(--admin-text);
            font-size:27px;
            font-weight:800;
        }

        .booking-summary-note{
            margin:5px 0 0;
            color:var(--admin-muted);
            font-size:11px;
        }

        .environment-badge{
            display:inline-flex;
            align-items:center;
            gap:6px;
            min-height:25px;
            padding:5px 9px;
            border-radius:999px;
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.3px;
        }

        .environment-live{
            color:#145c3b;
            background:#e5f4ea;
        }

        .environment-test{
            color:#7f6123;
            background:#f8efd9;
        }

        .booking-action-group{
            display:flex;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
        }

        .booking-view-btn{
            white-space:nowrap;
        }

        .booking-subline{
            display:block;
            margin-top:3px;
            color:var(--admin-muted);
            font-size:11px;
        }

        .booking-tour{
            display:block;
        }

        @media(max-width:1100px){
            .booking-summary-grid{
                grid-template-columns:repeat(2,minmax(0,1fr));
            }
        }

        @media(max-width:760px){
            .booking-summary-grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <?php require __DIR__ . "/admin_sidebar.php"; ?>

    <div class="admin-main">

        <?php require __DIR__ . "/admin_topbar.php"; ?>

        <main class="admin-content">

            <section class="admin-page-header">

                <div>
                    <h1>Bookings</h1>
                    <p>
                        Manage customer reservations, travel details and payment-backed booking activity.
                    </p>
                </div>

                <div class="admin-toolbar-group">
                    <a href="export_excel.php" class="admin-button admin-button-light">
                        <i class="fa-solid fa-file-excel"></i>
                        Excel
                    </a>

                    <a href="export_pdf.php" class="admin-button admin-button-light">
                        <i class="fa-solid fa-file-pdf"></i>
                        PDF
                    </a>
                </div>

            </section>


            <section class="booking-summary-grid">

                <article class="booking-summary-card">
                    <div class="booking-summary-top">
                        <span class="booking-summary-label">Total Bookings</span>
                        <div class="booking-summary-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="booking-summary-value">
                        <?php echo number_format($summary["total"]); ?>
                    </div>
                    <p class="booking-summary-note">
                        All operational booking records
                    </p>
                </article>

                <article class="booking-summary-card">
                    <div class="booking-summary-top">
                        <span class="booking-summary-label">Upcoming</span>
                        <div class="booking-summary-icon">
                            <i class="fa-solid fa-plane-departure"></i>
                        </div>
                    </div>
                    <div class="booking-summary-value">
                        <?php echo number_format($summary["upcoming"]); ?>
                    </div>
                    <p class="booking-summary-note">
                        Future non-cancelled travel
                    </p>
                </article>

                <article class="booking-summary-card">
                    <div class="booking-summary-top">
                        <span class="booking-summary-label">Pending</span>
                        <div class="booking-summary-icon">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="booking-summary-value">
                        <?php echo number_format($summary["pending"]); ?>
                    </div>
                    <p class="booking-summary-note">
                        Awaiting payment confirmation
                    </p>
                </article>

                <article class="booking-summary-card">
                    <div class="booking-summary-top">
                        <span class="booking-summary-label">Cancelled</span>
                        <div class="booking-summary-icon">
                            <i class="fa-solid fa-ban"></i>
                        </div>
                    </div>
                    <div class="booking-summary-value">
                        <?php echo number_format($summary["cancelled"]); ?>
                    </div>
                    <p class="booking-summary-note">
                        <?php echo number_format($summary["test_like"]); ?>
                        test-like transaction<?php echo $summary["test_like"] === 1 ? "" : "s"; ?> retained
                    </p>
                </article>

            </section>


            <form
                method="GET"
                action="bookings.php"
                class="admin-toolbar"
            >

                <div class="admin-toolbar-group">

                    <div class="admin-search">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            name="search"
                            value="<?php echo adminEscape($search); ?>"
                            placeholder="Booking ID, customer, tour, phone or reference..."
                        >
                    </div>

                    <select name="status" class="admin-select">
                        <option value="">All payment statuses</option>

                        <?php foreach (["Pending","Paid","Failed","Cancelled","TimedOut"] as $status): ?>
                            <option
                                value="<?php echo adminEscape($status); ?>"
                                <?php echo $statusFilter === $status ? "selected" : ""; ?>
                            >
                                <?php echo adminEscape($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="method" class="admin-select">
                        <option value="">All payment methods</option>

                        <?php foreach (["Mpesa","Card","PayPal"] as $method): ?>
                            <option
                                value="<?php echo adminEscape($method); ?>"
                                <?php echo $methodFilter === $method ? "selected" : ""; ?>
                            >
                                <?php echo adminEscape($method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="travel" class="admin-select">
                        <option value="">All travel dates</option>
                        <option value="today" <?php echo $travelFilter === "today" ? "selected" : ""; ?>>
                            Today
                        </option>
                        <option value="upcoming" <?php echo $travelFilter === "upcoming" ? "selected" : ""; ?>>
                            Upcoming
                        </option>
                        <option value="past" <?php echo $travelFilter === "past" ? "selected" : ""; ?>>
                            Past
                        </option>
                    </select>

                    <button type="submit" class="admin-button admin-button-primary">
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <?php if (
                        $search !== ""
                        || $statusFilter !== ""
                        || $methodFilter !== ""
                        || $travelFilter !== ""
                    ): ?>
                        <a href="bookings.php" class="admin-button admin-button-light">
                            Clear
                        </a>
                    <?php endif; ?>

                </div>

                <div class="admin-toolbar-group">
                    <label for="limit">
                        <small>Show</small>
                    </label>

                    <select
                        name="limit"
                        id="limit"
                        class="admin-select"
                        onchange="this.form.submit()"
                    >
                        <?php foreach ($allowedLimits as $option): ?>
                            <option
                                value="<?php echo $option; ?>"
                                <?php echo $limit === $option ? "selected" : ""; ?>
                            >
                                <?php echo $option; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </form>


            <section class="admin-panel">

                <div class="admin-panel-header">
                    <h2>Booking Operations</h2>

                    <span style="font-size:12px;color:var(--admin-muted);">
                        <?php echo number_format($totalRecords); ?>
                        record<?php echo $totalRecords === 1 ? "" : "s"; ?>
                    </span>
                </div>

                <div class="admin-table-wrapper">

                    <?php if ($result && $result->num_rows > 0): ?>

                        <table class="admin-table">

                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Tour / Travel</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Environment</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php while ($booking = $result->fetch_assoc()): ?>

                                <?php
                                    $paymentStatus =
                                        (string) ($booking["payment_status"] ?? "Unknown");

                                    $statusClass =
                                        getPaymentStatusClass($paymentStatus);

                                    $tourName =
                                        trim((string) ($booking["tour_name"] ?? ""));

                                    $travelDate =
                                        $booking["date"] ?? null;

                                    $travelTime =
                                        $booking["time"] ?? null;

                                    $amount =
                                        (float) ($booking["amount"] ?? 0);

                                    $environmentClass =
                                        getEnvironmentClass($amount);

                                    $environmentLabel =
                                        getEnvironmentLabel($amount);
                                ?>

                                <tr>

                                    <td>
                                        <span class="booking-reference">
                                            #<?php echo (int) $booking["id"]; ?>
                                        </span>

                                        <span class="booking-subline">
                                            <?php
                                                $created = $booking["created_at"] ?? null;

                                                echo $created
                                                    ? date("d M Y H:i", strtotime($created))
                                                    : "—";
                                            ?>
                                        </span>
                                    </td>

                                    <td class="customer-cell">
                                        <strong>
                                            <?php echo adminEscape($booking["name"] ?? ""); ?>
                                        </strong>

                                        <span>
                                            <?php echo adminEscape($booking["email"] ?? ""); ?>
                                        </span>

                                        <?php if (!empty($booking["phone"])): ?>
                                            <span>
                                                <?php echo adminEscape($booking["phone"]); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <strong class="booking-tour">
                                            <?php
                                                echo $tourName !== ""
                                                    ? adminEscape($tourName)
                                                    : "Not specified";
                                            ?>
                                        </strong>

                                        <span class="booking-subline">
                                            <?php
                                                echo $travelDate
                                                    ? date("d M Y", strtotime($travelDate))
                                                    : "—";
                                            ?>

                                            <?php if ($travelTime): ?>
                                                · <?php echo date("H:i", strtotime($travelTime)); ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>

                                    <td class="amount-cell">
                                        KES <?php echo number_format($amount, 0); ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php echo adminEscape($booking["payment"] ?? "—"); ?>
                                        </strong>

                                        <?php
                                            $reference =
                                                $booking["mpesa_receipt"]
                                                ?: ($booking["payment_reference"] ?? "");
                                        ?>

                                        <?php if ($reference !== ""): ?>
                                            <span class="booking-subline">
                                                <?php echo adminEscape($reference); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo adminEscape($paymentStatus); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="environment-badge <?php echo $environmentClass; ?>">
                                            <?php echo adminEscape($environmentLabel); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="booking-action-group">
                                            <a
                                                href="admin_booking_view.php?id=<?php echo (int) $booking["id"]; ?>"
                                                class="admin-button admin-button-light booking-view-btn"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                                View
                                            </a>
                                        </div>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                            </tbody>
                        </table>

                    <?php else: ?>

                        <div class="admin-empty">
                            <i
                                class="fa-regular fa-calendar-xmark"
                                style="font-size:30px;display:block;margin-bottom:12px;"
                            ></i>

                            No bookings match your search or filters.
                        </div>

                    <?php endif; ?>

                </div>


                <?php if ($totalPages > 1): ?>

                    <nav class="admin-pagination">

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                            <?php
                                $query = http_build_query([
                                    "page" => $i,
                                    "limit" => $limit,
                                    "search" => $search,
                                    "status" => $statusFilter,
                                    "method" => $methodFilter,
                                    "travel" => $travelFilter
                                ]);
                            ?>

                            <a
                                href="?<?php echo adminEscape($query); ?>"
                                class="<?php echo $i === $page ? "active" : ""; ?>"
                            >
                                <?php echo $i; ?>
                            </a>

                        <?php endfor; ?>

                    </nav>

                <?php endif; ?>

            </section>

        </main>
    </div>

</div>

<script>
const sidebar =
    document.getElementById("adminSidebar");

const mobileToggle =
    document.getElementById("adminMobileToggle");

if (sidebar && mobileToggle) {
    mobileToggle.addEventListener("click", function () {
        sidebar.classList.toggle("open");
    });
}
</script>

</body>
</html>

<?php
if ($stmt) {
    $stmt->close();
}
?>
