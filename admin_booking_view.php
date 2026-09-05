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

$csrfToken = $_SESSION["csrf_token"];


/* =========================================================
   HELPERS
========================================================= */

function bookingViewEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function bookingViewStatusClass(string $status): string
{
    return match (strtolower(trim($status))) {
        "paid" => "status-paid",
        "pending" => "status-pending",
        "failed", "timedout" => "status-failed",
        "cancelled" => "status-cancelled",
        default => "status-default"
    };
}

function adminBookingAudit(
    mysqli $conn,
    int $adminId,
    string $username,
    string $action,
    int $bookingId,
    string $details
): void {

    $auditTable = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");

    if (!$auditTable || $auditTable->num_rows !== 1) {
        return;
    }

    $role = "admin";
    $entityType = "booking";
    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO admin_audit_log
        (
            admin_id,
            username,
            role,
            action,
            entity_type,
            entity_id,
            details,
            ip_address
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "issssiss",
        $adminId,
        $username,
        $role,
        $action,
        $entityType,
        $bookingId,
        $details,
        $ipAddress
    );

    $stmt->execute();
    $stmt->close();
}


/* =========================================================
   BOOKING ID
========================================================= */

$bookingId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($bookingId <= 0) {
    http_response_code(400);
    exit("Invalid booking.");
}


/* =========================================================
   POST ACTIONS
========================================================= */

$successMessage = "";
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        $submittedToken === ""
        || !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        exit("Invalid security token.");
    }

    $action = trim((string) ($_POST["action"] ?? ""));

    if ($action === "update_travel") {

        $travelDate = trim((string) ($_POST["date"] ?? ""));
        $travelTime = trim((string) ($_POST["time"] ?? ""));

        if ($travelDate === "" || $travelTime === "") {
            $errorMessage = "Travel date and time are required.";
        } else {

            $updateStmt = $conn->prepare("
                UPDATE bookings
                SET date = ?, time = ?
                WHERE id = ?
                LIMIT 1
            ");

            if (!$updateStmt) {
                $errorMessage = "Unable to prepare the travel update.";
            } else {

                $updateStmt->bind_param(
                    "ssi",
                    $travelDate,
                    $travelTime,
                    $bookingId
                );

                if ($updateStmt->execute()) {

                    adminBookingAudit(
                        $conn,
                        $adminId,
                        $adminUsername,
                        "Updated booking travel details",
                        $bookingId,
                        "Admin updated travel date/time for booking #"
                        . $bookingId
                        . " to "
                        . $travelDate
                        . " "
                        . $travelTime
                        . "."
                    );

                    $successMessage = "Travel details updated successfully.";

                } else {
                    $errorMessage = "Unable to update travel details.";
                }

                $updateStmt->close();
            }
        }
    }

    elseif ($action === "cancel_booking") {

        $reason = trim((string) ($_POST["reason"] ?? ""));

        $statusStmt = $conn->prepare("
            SELECT payment_status
            FROM bookings
            WHERE id = ?
            LIMIT 1
        ");

        if (!$statusStmt) {
            $errorMessage = "Unable to verify booking status.";
        } else {

            $statusStmt->bind_param("i", $bookingId);
            $statusStmt->execute();

            $statusResult = $statusStmt->get_result();
            $statusRow = $statusResult ? $statusResult->fetch_assoc() : null;
            $statusStmt->close();

            $currentStatus =
                strtolower(trim((string) ($statusRow["payment_status"] ?? "")));

            if ($currentStatus === "paid") {
                $errorMessage =
                    "Paid bookings cannot be cancelled directly. "
                    . "A refund workflow is required.";

            } elseif ($currentStatus === "cancelled") {
                $errorMessage = "This booking is already cancelled.";

            } elseif ($reason === "") {
                $errorMessage = "Please enter a cancellation reason.";

            } else {

                $cancelStmt = $conn->prepare("
                    UPDATE bookings
                    SET payment_status = 'Cancelled'
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$cancelStmt) {
                    $errorMessage = "Unable to prepare cancellation.";
                } else {

                    $cancelStmt->bind_param("i", $bookingId);

                    if ($cancelStmt->execute()) {

                        adminBookingAudit(
                            $conn,
                            $adminId,
                            $adminUsername,
                            "Cancelled booking",
                            $bookingId,
                            "Admin cancelled unpaid booking #"
                            . $bookingId
                            . ". Reason: "
                            . $reason
                        );

                        $successMessage = "Booking cancelled successfully.";

                    } else {
                        $errorMessage = "Unable to cancel this booking.";
                    }

                    $cancelStmt->close();
                }
            }
        }
    }
}


/* =========================================================
   LOAD BOOKING
========================================================= */

$stmt = $conn->prepare("
    SELECT
        b.id,
        b.user_id,
        b.name,
        b.email,
        b.phone,
        b.tour_name,
        b.date,
        b.time,
        b.payment,
        b.amount,
        b.payment_status,
        b.payment_reference,
        b.merchant_request_id,
        b.checkout_request_id,
        b.mpesa_receipt,
        b.created_at,
        u.name AS account_name,
        u.email AS account_email
    FROM bookings b
    LEFT JOIN users u
        ON b.user_id = u.id
    WHERE b.id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load booking.");
}

$stmt->bind_param("i", $bookingId);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result ? $result->fetch_assoc() : null;

$stmt->close();

if (!$booking) {
    http_response_code(404);
    exit("Booking not found.");
}

$status = trim((string) ($booking["payment_status"] ?? "Unknown"));
$statusLower = strtolower($status);
$statusClass = bookingViewStatusClass($status);

$amount = (float) ($booking["amount"] ?? 0);
$isTestLike = $amount <= 1;
$isPaid = $statusLower === "paid";
$isCancelled = $statusLower === "cancelled";

$tourName = trim((string) ($booking["tour_name"] ?? ""));
$tourName = $tourName !== "" ? $tourName : "Not specified";

$paymentReference =
    trim((string) ($booking["mpesa_receipt"] ?? ""));

if ($paymentReference === "") {
    $paymentReference =
        trim((string) ($booking["payment_reference"] ?? ""));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Booking #<?php echo (int) $bookingId; ?> | Sprinter Admin</title>

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
        .booking-detail-grid{
            display:grid;
            grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);
            gap:20px;
            margin-bottom:20px;
        }

        .booking-info-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:12px;
        }

        .booking-info-card{
            padding:16px;
            border:1px solid var(--admin-border);
            border-radius:12px;
            background:#fbfaf7;
        }

        .booking-info-card span{
            display:block;
            margin-bottom:6px;
            color:var(--admin-muted);
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .booking-info-card strong{
            display:block;
            color:var(--admin-text);
            font-size:13px;
            overflow-wrap:anywhere;
        }

        .booking-hero{
            position:relative;
            overflow:hidden;
            padding:24px;
            margin-bottom:20px;
            border:1px solid var(--admin-border);
            border-radius:var(--radius-md);
            background:
                radial-gradient(circle at 90% 18%,rgba(200,155,60,.12),transparent 25%),
                linear-gradient(135deg,#ffffff,#f8f6ef);
        }

        .booking-hero small{
            color:var(--admin-green);
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .booking-hero h1{
            margin:7px 0 5px;
            color:var(--admin-text);
            font-size:30px;
        }

        .booking-hero p{
            margin:0;
            color:var(--admin-muted);
            font-size:13px;
        }

        .integrity-note{
            padding:15px;
            border:1px solid rgba(23,107,69,.16);
            border-radius:12px;
            background:var(--admin-green-soft);
            color:#355b48;
            font-size:12px;
            line-height:1.7;
        }

        .integrity-note strong{
            color:var(--admin-green-dark);
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
        }

        .environment-live{
            color:#145c3b;
            background:#e5f4ea;
        }

        .environment-test{
            color:#7f6123;
            background:#f8efd9;
        }

        .admin-form-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:12px;
        }

        .admin-field{
            display:grid;
            gap:6px;
        }

        .admin-field label{
            color:var(--admin-muted);
            font-size:11px;
            font-weight:700;
        }

        .admin-field input,
        .admin-field textarea{
            width:100%;
            min-height:42px;
            padding:10px 12px;
            border:1px solid var(--admin-border);
            border-radius:9px;
            background:#fff;
            color:var(--admin-text);
            font:inherit;
            outline:none;
        }

        .admin-field textarea{
            min-height:100px;
            resize:vertical;
        }

        .admin-field input:focus,
        .admin-field textarea:focus{
            border-color:var(--admin-green);
            box-shadow:0 0 0 3px rgba(23,107,69,.09);
        }

        .alert{
            padding:13px 15px;
            margin-bottom:16px;
            border-radius:10px;
            font-size:12px;
            font-weight:600;
        }

        .alert-success{
            color:#145c3b;
            background:#e5f4ea;
            border:1px solid rgba(20,92,59,.12);
        }

        .alert-error{
            color:#8e3030;
            background:#fce7e7;
            border:1px solid rgba(142,48,48,.12);
        }

        .danger-panel{
            border-color:rgba(201,74,74,.20);
        }

        .danger-note{
            margin:0 0 14px;
            color:var(--admin-muted);
            font-size:12px;
            line-height:1.65;
        }

        @media(max-width:960px){
            .booking-detail-grid{
                grid-template-columns:1fr;
            }
        }

        @media(max-width:650px){
            .booking-info-grid,
            .admin-form-grid{
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

            <a href="bookings.php" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Bookings
            </a>

            <?php if ($successMessage !== ""): ?>
                <div class="alert alert-success">
                    <?php echo bookingViewEscape($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ""): ?>
                <div class="alert alert-error">
                    <?php echo bookingViewEscape($errorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="booking-hero">
                <small>Booking Operations</small>

                <h1>
                    Booking #<?php echo (int) $booking["id"]; ?>
                    · <?php echo bookingViewEscape($tourName); ?>
                </h1>

                <p>
                    Review reservation, travel and payment information without manually overriding payment success.
                </p>
            </section>


            <section class="booking-detail-grid">

                <article class="admin-panel">

                    <div class="admin-panel-header">
                        <h2>Reservation Information</h2>

                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo bookingViewEscape($status); ?>
                        </span>
                    </div>

                    <div class="admin-panel-body">

                        <div class="booking-info-grid">

                            <div class="booking-info-card">
                                <span>Customer</span>
                                <strong>
                                    <?php echo bookingViewEscape($booking["name"] ?? "—"); ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Email</span>
                                <strong>
                                    <?php echo bookingViewEscape($booking["email"] ?? "—"); ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Phone</span>
                                <strong>
                                    <?php echo bookingViewEscape($booking["phone"] ?: "—"); ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Customer Account</span>
                                <strong>
                                    <?php
                                        echo !empty($booking["user_id"])
                                            ? "#" . (int) $booking["user_id"]
                                            : "No linked account";
                                    ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Tour</span>
                                <strong><?php echo bookingViewEscape($tourName); ?></strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Travel</span>
                                <strong>
                                    <?php
                                        echo !empty($booking["date"])
                                            ? date("d M Y", strtotime($booking["date"]))
                                            : "—";
                                    ?>

                                    <?php if (!empty($booking["time"])): ?>
                                        · <?php echo date("H:i", strtotime($booking["time"])); ?>
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Created</span>
                                <strong>
                                    <?php
                                        echo !empty($booking["created_at"])
                                            ? date("d M Y H:i", strtotime($booking["created_at"]))
                                            : "—";
                                    ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Environment</span>
                                <strong>
                                    <span class="environment-badge <?php echo $isTestLike ? "environment-test" : "environment-live"; ?>">
                                        <?php echo $isTestLike ? "Test-like" : "Live"; ?>
                                    </span>
                                </strong>
                            </div>

                        </div>

                    </div>

                </article>


                <article class="admin-panel">

                    <div class="admin-panel-header">
                        <h2>Payment Information</h2>
                    </div>

                    <div class="admin-panel-body">

                        <div class="booking-info-grid" style="grid-template-columns:1fr;">

                            <div class="booking-info-card">
                                <span>Amount</span>
                                <strong>
                                    KES <?php echo number_format($amount, 0); ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Method</span>
                                <strong>
                                    <?php echo bookingViewEscape($booking["payment"] ?? "—"); ?>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Payment Status</span>
                                <strong>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo bookingViewEscape($status); ?>
                                    </span>
                                </strong>
                            </div>

                            <div class="booking-info-card">
                                <span>Reference / Receipt</span>
                                <strong>
                                    <?php echo bookingViewEscape($paymentReference !== "" ? $paymentReference : "—"); ?>
                                </strong>
                            </div>

                        </div>

                        <div class="integrity-note" style="margin-top:14px;">
                            <strong>Payment integrity:</strong>
                            Admin cannot manually mark a booking as Paid.
                            Successful payment status must come from the actual Card or M-Pesa payment workflow.
                        </div>

                    </div>

                </article>

            </section>


            <section class="booking-detail-grid">

                <article class="admin-panel">

                    <div class="admin-panel-header">
                        <h2>Update Travel Details</h2>
                    </div>

                    <div class="admin-panel-body">

                        <?php if ($isCancelled): ?>

                            <div class="integrity-note">
                                This booking is cancelled. Travel details are retained as historical information.
                            </div>

                        <?php else: ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo bookingViewEscape($csrfToken); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_travel"
                                >

                                <div class="admin-form-grid">

                                    <div class="admin-field">
                                        <label for="date">Travel Date</label>
                                        <input
                                            type="date"
                                            id="date"
                                            name="date"
                                            value="<?php echo bookingViewEscape($booking["date"] ?? ""); ?>"
                                            required
                                        >
                                    </div>

                                    <div class="admin-field">
                                        <label for="time">Travel Time</label>
                                        <input
                                            type="time"
                                            id="time"
                                            name="time"
                                            value="<?php echo bookingViewEscape($booking["time"] ?? ""); ?>"
                                            required
                                        >
                                    </div>

                                </div>

                                <button
                                    type="submit"
                                    class="admin-button admin-button-primary"
                                    style="margin-top:14px;"
                                >
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Travel Details
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </article>


                <article class="admin-panel danger-panel">

                    <div class="admin-panel-header">
                        <h2>Booking Control</h2>
                    </div>

                    <div class="admin-panel-body">

                        <?php if ($isPaid): ?>

                            <div class="integrity-note">
                                <strong>Paid booking protected.</strong><br>
                                Direct cancellation is locked because this booking contains a successful payment.
                                A proper refund workflow is required before a paid booking can be cancelled.
                            </div>

                        <?php elseif ($isCancelled): ?>

                            <div class="integrity-note">
                                This booking is already cancelled. Its record remains in the system for history and audit purposes.
                            </div>

                        <?php else: ?>

                            <p class="danger-note">
                                Cancelling keeps the booking record and audit history. It does not delete the transaction.
                            </p>

                            <form
                                method="POST"
                                onsubmit="return confirm('Cancel this unpaid booking?');"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo bookingViewEscape($csrfToken); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="cancel_booking"
                                >

                                <div class="admin-field">
                                    <label for="reason">Cancellation Reason</label>

                                    <textarea
                                        id="reason"
                                        name="reason"
                                        placeholder="Enter the operational reason for cancellation..."
                                        required
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="admin-button"
                                    style="
                                        margin-top:14px;
                                        background:#c94a4a;
                                        color:#fff;
                                    "
                                >
                                    <i class="fa-solid fa-ban"></i>
                                    Cancel Booking
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </article>

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
