<?php

require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";


/* =========================================================
   HELPERS
========================================================= */

function customerViewEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function customerBookingStatusClass(string $status): string
{
    return match (strtolower(trim($status))) {
        "paid" => "status-paid",
        "pending" => "status-pending",
        "failed", "timedout", "timed out", "timeout" => "status-failed",
        "cancelled" => "status-cancelled",
        default => "status-default"
    };
}

function customerInitialsFromName(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = "";

    if ($parts) {
        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part !== "") {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
    }

    return $initials !== "" ? $initials : "CU";
}


/* =========================================================
   CUSTOMER ID
========================================================= */

$customerId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($customerId <= 0) {
    header("Location: users.php");
    exit();
}


/* =========================================================
   CUSTOMER
========================================================= */

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

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load customer.");
}

$stmt->bind_param("i", $customerId);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $stmt->close();

    header("Location: users.php");
    exit();
}

$customer = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CUSTOMER SUMMARY
========================================================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_bookings,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                THEN 1
                ELSE 0
            END
        ) AS paid_bookings,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled_bookings,

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
        ) AS live_value,

        MAX(date) AS latest_travel,

        MAX(
            CASE
                WHEN phone IS NOT NULL
                     AND phone <> ''
                THEN phone
                ELSE NULL
            END
        ) AS phone

    FROM bookings
    WHERE user_id = ?
");

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load customer summary.");
}

$stmt->bind_param("i", $customerId);
$stmt->execute();

$summaryResult = $stmt->get_result();
$summary = $summaryResult
    ? $summaryResult->fetch_assoc()
    : [];

$stmt->close();


/* =========================================================
   BOOKING HISTORY
========================================================= */

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

    ORDER BY created_at DESC, id DESC
");

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load booking history.");
}

$stmt->bind_param("i", $customerId);
$stmt->execute();

$bookings = $stmt->get_result();

$initials =
    customerInitialsFromName(
        (string) ($customer["name"] ?? "")
    );

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
        <?php echo customerViewEscape($customer["name"] ?? "Customer"); ?>
        | Sprinter Admin
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="admin.css"
    >

    <style>
        .customer-profile-hero{
            position:relative;
            overflow:hidden;
            display:flex;
            align-items:center;
            gap:22px;
            margin:18px 0 20px;
            padding:24px;
            border:1px solid var(--admin-border);
            border-radius:var(--radius-md);
            background:
                radial-gradient(circle at 92% 20%,rgba(200,155,60,.10),transparent 24%),
                radial-gradient(circle at 8% 90%,rgba(23,107,69,.07),transparent 24%),
                linear-gradient(135deg,#fff,#fbfaf7);
        }

        .customer-profile-hero-avatar{
            width:76px;
            height:76px;
            flex:0 0 76px;
            display:grid;
            place-items:center;
            border-radius:20px;
            background:linear-gradient(
                135deg,
                #1f8a55,
                #12372a
            );
            color:#fff;
            font-size:23px;
            font-weight:800;
            box-shadow:0 12px 24px rgba(18,55,42,.16);
        }

        .customer-profile-hero h1{
            margin:0;
            color:var(--admin-text);
            font-size:30px;
        }

        .customer-profile-hero p{
            margin:6px 0 0;
            color:var(--admin-muted);
            font-size:13px;
        }

        .customer-id-pill{
            display:inline-flex;
            align-items:center;
            gap:6px;
            margin-top:10px;
            padding:6px 10px;
            border-radius:999px;
            background:var(--admin-gold-soft);
            color:#7f6123;
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
        }

        .profile-summary-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
            margin-bottom:20px;
        }

        .profile-summary-card{
            padding:18px;
            border:1px solid var(--admin-border);
            border-radius:var(--radius-md);
            background:#fff;
            box-shadow:var(--shadow-sm);
        }

        .profile-summary-label{
            display:block;
            margin-bottom:9px;
            color:var(--admin-muted);
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .profile-summary-value{
            color:var(--admin-text);
            font-size:23px;
            font-weight:800;
        }

        .customer-info-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:12px;
        }

        .customer-info-item{
            padding:16px;
            border:1px solid var(--admin-border);
            border-radius:12px;
            background:#fbfaf7;
        }

        .customer-info-item span{
            display:block;
            margin-bottom:6px;
            color:var(--admin-muted);
            font-size:10px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .customer-info-item strong{
            display:block;
            color:var(--admin-text);
            font-size:13px;
            overflow-wrap:anywhere;
        }

        .environment-badge{
            display:inline-flex;
            align-items:center;
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

        .customer-history-subline{
            display:block;
            margin-top:3px;
            color:var(--admin-muted);
            font-size:11px;
        }

        .customer-booking-action{
            white-space:nowrap;
        }

        @media(max-width:1100px){
            .profile-summary-grid{
                grid-template-columns:repeat(2,minmax(0,1fr));
            }
        }

        @media(max-width:760px){
            .profile-summary-grid{
                grid-template-columns:1fr;
            }

            .customer-profile-hero{
                align-items:flex-start;
                flex-direction:column;
            }

            .customer-info-grid{
                grid-template-columns:1fr;
            }
        }
    </style>

</head>

<body>

<div class="admin-layout">

    <?php
        $activePage = "customers";
        require __DIR__ . "/admin_sidebar.php";
    ?>

    <main class="admin-main">

        <?php require __DIR__ . "/admin_topbar.php"; ?>

        <section class="admin-content">

            <a
                href="users.php"
                class="admin-back-link"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Customers
            </a>


            <section class="customer-profile-hero">

                <div class="customer-profile-hero-avatar">
                    <?php echo customerViewEscape($initials); ?>
                </div>

                <div>

                    <h1>
                        <?php
                            echo customerViewEscape(
                                $customer["name"]
                                ?? "Customer"
                            );
                        ?>
                    </h1>

                    <p>
                        <?php
                            echo customerViewEscape(
                                $customer["email"]
                                ?? ""
                            );
                        ?>
                    </p>

                    <span class="customer-id-pill">
                        <i class="fa-solid fa-id-card"></i>
                        Customer #<?php echo (int) $customerId; ?>
                    </span>

                </div>

            </section>


            <section class="profile-summary-grid">

                <article class="profile-summary-card">
                    <span class="profile-summary-label">
                        Total Bookings
                    </span>

                    <div class="profile-summary-value">
                        <?php
                            echo number_format(
                                (int) (
                                    $summary["total_bookings"]
                                    ?? 0
                                )
                            );
                        ?>
                    </div>
                </article>


                <article class="profile-summary-card">
                    <span class="profile-summary-label">
                        Paid Bookings
                    </span>

                    <div class="profile-summary-value">
                        <?php
                            echo number_format(
                                (int) (
                                    $summary["paid_bookings"]
                                    ?? 0
                                )
                            );
                        ?>
                    </div>
                </article>


                <article class="profile-summary-card">
                    <span class="profile-summary-label">
                        Live Paid Value
                    </span>

                    <div class="profile-summary-value">
                        KES
                        <?php
                            echo number_format(
                                (float) (
                                    $summary["live_value"]
                                    ?? 0
                                ),
                                0
                            );
                        ?>
                    </div>
                </article>


                <article class="profile-summary-card">
                    <span class="profile-summary-label">
                        Latest Travel
                    </span>

                    <div class="profile-summary-value">

                        <?php
                            if (!empty($summary["latest_travel"])) {

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $summary["latest_travel"]
                                    )
                                );

                            } else {
                                echo "No travel";
                            }
                        ?>

                    </div>
                </article>

            </section>


            <section class="admin-panel">

                <div class="admin-panel-header">
                    <h2>Customer Information</h2>
                </div>

                <div class="admin-panel-body">

                    <div class="customer-info-grid">

                        <div class="customer-info-item">
                            <span>Email</span>

                            <strong>
                                <?php
                                    echo customerViewEscape(
                                        $customer["email"]
                                        ?? "—"
                                    );
                                ?>
                            </strong>
                        </div>


                        <div class="customer-info-item">
                            <span>Phone</span>

                            <strong>
                                <?php
                                    echo customerViewEscape(
                                        $summary["phone"]
                                        ?: "No phone recorded"
                                    );
                                ?>
                            </strong>
                        </div>


                        <div class="customer-info-item">
                            <span>Account ID</span>

                            <strong>
                                #<?php echo (int) $customerId; ?>
                            </strong>
                        </div>


                        <div class="customer-info-item">
                            <span>Customer Since</span>

                            <strong>
                                <?php
                                    echo !empty($customer["created_at"])
                                        ? date(
                                            "d M Y",
                                            strtotime(
                                                $customer["created_at"]
                                            )
                                        )
                                        : "—";
                                ?>
                            </strong>
                        </div>


                        <div class="customer-info-item">
                            <span>Cancelled Bookings</span>

                            <strong>
                                <?php
                                    echo number_format(
                                        (int) (
                                            $summary["cancelled_bookings"]
                                            ?? 0
                                        )
                                    );
                                ?>
                            </strong>
                        </div>


                        <div class="customer-info-item">
                            <span>Operational Role</span>

                            <strong>
                                Registered Customer
                            </strong>
                        </div>

                    </div>

                </div>

            </section>


            <section
                class="admin-panel"
                style="margin-top:20px;"
            >

                <div class="admin-panel-header">

                    <div>
                        <h2>Booking History</h2>
                    </div>

                    <span
                        style="
                            font-size:12px;
                            color:var(--admin-muted);
                        "
                    >
                        <?php
                            echo number_format(
                                (int) (
                                    $summary["total_bookings"]
                                    ?? 0
                                )
                            );
                        ?>
                        records
                    </span>

                </div>


                <div class="admin-table-wrapper">

                    <?php if (
                        $bookings
                        && $bookings->num_rows > 0
                    ): ?>

                        <table class="admin-table">

                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Tour / Travel</th>
                                    <th>Payment</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Environment</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php while (
                                $booking = $bookings->fetch_assoc()
                            ): ?>

                                <?php
                                    $status =
                                        (string) (
                                            $booking["payment_status"]
                                            ?? "Unknown"
                                        );

                                    $statusClass =
                                        customerBookingStatusClass(
                                            $status
                                        );

                                    $amount =
                                        (float) (
                                            $booking["amount"]
                                            ?? 0
                                        );

                                    $isTestLike =
                                        $amount <= 1;

                                    $reference =
                                        trim(
                                            (string) (
                                                $booking["mpesa_receipt"]
                                                ?? ""
                                            )
                                        );

                                    if ($reference === "") {
                                        $reference =
                                            trim(
                                                (string) (
                                                    $booking["payment_reference"]
                                                    ?? ""
                                                )
                                            );
                                    }
                                ?>

                                <tr>

                                    <td>
                                        <strong>
                                            #<?php echo (int) $booking["id"]; ?>
                                        </strong>
                                    </td>


                                    <td>

                                        <strong>
                                            <?php
                                                echo customerViewEscape(
                                                    $booking["tour_name"]
                                                    ?: "Not specified"
                                                );
                                            ?>
                                        </strong>

                                        <span class="customer-history-subline">

                                            <?php
                                                echo !empty(
                                                    $booking["date"]
                                                )
                                                    ? date(
                                                        "d M Y",
                                                        strtotime(
                                                            $booking["date"]
                                                        )
                                                    )
                                                    : "—";
                                            ?>

                                            <?php if (
                                                !empty(
                                                    $booking["time"]
                                                )
                                            ): ?>
                                                ·
                                                <?php
                                                    echo date(
                                                        "H:i",
                                                        strtotime(
                                                            $booking["time"]
                                                        )
                                                    );
                                                ?>
                                            <?php endif; ?>

                                        </span>

                                    </td>


                                    <td>

                                        <strong>
                                            <?php
                                                echo customerViewEscape(
                                                    ucfirst(
                                                        $booking["payment"]
                                                        ?: "Unknown"
                                                    )
                                                );
                                            ?>
                                        </strong>

                                        <?php if ($reference !== ""): ?>
                                            <span class="customer-history-subline">
                                                <?php
                                                    echo customerViewEscape(
                                                        $reference
                                                    );
                                                ?>
                                            </span>
                                        <?php endif; ?>

                                    </td>


                                    <td class="amount-cell">
                                        KES
                                        <?php
                                            echo number_format(
                                                $amount,
                                                0
                                            );
                                        ?>
                                    </td>


                                    <td>

                                        <span
                                            class="status-badge <?php echo $statusClass; ?>"
                                        >
                                            <?php
                                                echo customerViewEscape(
                                                    $status
                                                );
                                            ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span
                                            class="environment-badge <?php echo $isTestLike ? "environment-test" : "environment-live"; ?>"
                                        >
                                            <?php
                                                echo $isTestLike
                                                    ? "Test-like"
                                                    : "Live";
                                            ?>
                                        </span>

                                    </td>


                                    <td>
                                        <?php
                                            echo !empty(
                                                $booking["created_at"]
                                            )
                                                ? date(
                                                    "d M Y H:i",
                                                    strtotime(
                                                        $booking["created_at"]
                                                    )
                                                )
                                                : "—";
                                        ?>
                                    </td>


                                    <td>

                                        <a
                                            href="admin_booking_view.php?id=<?php echo (int) $booking["id"]; ?>"
                                            class="admin-button admin-button-light customer-booking-action"
                                        >
                                            <i class="fa-regular fa-eye"></i>
                                            View Booking
                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="admin-empty">

                            <i
                                class="fa-regular fa-calendar-xmark"
                                style="
                                    display:block;
                                    font-size:30px;
                                    margin-bottom:12px;
                                "
                            ></i>

                            This customer has not made any bookings yet.

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </section>

    </main>

</div>


<script>

const sidebar =
    document.getElementById(
        "adminSidebar"
    );

const mobileToggle =
    document.getElementById(
        "adminMobileToggle"
    );

if (
    sidebar
    && mobileToggle
) {

    mobileToggle.addEventListener(
        "click",
        function () {
            sidebar.classList.toggle(
                "open"
            );
        }
    );

}

</script>


</body>
</html>

<?php
$stmt->close();
?>
