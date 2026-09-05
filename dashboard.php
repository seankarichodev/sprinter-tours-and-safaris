<?php

require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";


/* =========================================================
   DASHBOARD STATISTICS
========================================================= */

$totalBookings = 0;
$totalRevenue = 0;
$totalCustomers = 0;
$unreadMessages = 0;


/* TOTAL BOOKINGS */

$result =
    $conn->query(
        "
        SELECT COUNT(*) AS total
        FROM bookings
        "
    );

if ($result) {

    $row =
        $result->fetch_assoc();

    $totalBookings =
        (int) (
            $row["total"]
            ?? 0
        );
}


/* =========================================================
   REVENUE

   Only confirmed paid bookings count as revenue.
========================================================= */

$result =
    $conn->query(
        "
        SELECT
            COALESCE(
                SUM(amount),
                0
            ) AS total
        FROM bookings
        WHERE LOWER(COALESCE(payment_status, '')) = 'paid'
          AND amount > 1
        "
    );

if ($result) {

    $row =
        $result->fetch_assoc();

    $totalRevenue =
        (float) (
            $row["total"]
            ?? 0
        );
}


/* TOTAL CUSTOMERS */

$result =
    $conn->query(
        "
        SELECT COUNT(*) AS total
        FROM users
        "
    );

if ($result) {

    $row =
        $result->fetch_assoc();

    $totalCustomers =
        (int) (
            $row["total"]
            ?? 0
        );
}


/* UNREAD MESSAGES */

$result =
    $conn->query(
        "
        SELECT COUNT(*) AS total
        FROM messages
        WHERE status = 'Unread'
        "
    );

if ($result) {

    $row =
        $result->fetch_assoc();

    $unreadMessages =
        (int) (
            $row["total"]
            ?? 0
        );
}


/* =========================================================
   PAYMENT METHOD BREAKDOWN
========================================================= */

$paymentBreakdown = [];

$result =
    $conn->query(
        "
        SELECT
            payment,
            COUNT(*) AS total
        FROM bookings
        WHERE amount > 1
        GROUP BY payment
        ORDER BY total DESC
        "
    );

if ($result) {

    while (
        $row =
            $result->fetch_assoc()
    ) {

        $paymentBreakdown[] =
            $row;
    }
}


/* =========================================================
   MONTHLY BOOKINGS
========================================================= */

$monthLabels = [];
$monthValues = [];


/*
 * Current year monthly booking data.
 */

$result =
    $conn->query(
        "
        SELECT
            MONTH(date) AS month_number,
            COUNT(*) AS total
        FROM bookings
        WHERE YEAR(date) = YEAR(CURDATE())
          AND amount > 1
        GROUP BY MONTH(date)
        ORDER BY MONTH(date)
        "
    );


$monthlyData =
    array_fill(
        1,
        12,
        0
    );


if ($result) {

    while (
        $row =
            $result->fetch_assoc()
    ) {

        $monthNumber =
            (int) $row["month_number"];

        $monthlyData[$monthNumber] =
            (int) $row["total"];
    }
}


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


foreach (
    $monthNames
    as $monthNumber => $monthName
) {

    $monthLabels[] =
        $monthName;

    $monthValues[] =
        $monthlyData[$monthNumber];
}


/* =========================================================
   RECENT BOOKINGS
========================================================= */

$recentBookings =
    $conn->query(
        "
        SELECT
            id,
            name,
            email,
            date,
            amount,
            payment,
            payment_status
        FROM bookings
        ORDER BY id DESC
        LIMIT 6
        "
    );


/* =========================================================
   RECENT MESSAGES
========================================================= */

$recentMessages =
    $conn->query(
        "
        SELECT
            id,
            name,
            email,
            message,
            status,
            created_at
        FROM messages
        ORDER BY id DESC
        LIMIT 4
        "
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
        Admin Dashboard | Sprinter Tours & Safaris
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


    <script
        src="https://cdn.jsdelivr.net/npm/chart.js"
    ></script>

</head>


<body>

<div class="admin-layout">


    <?php
        require __DIR__
            . "/admin_sidebar.php";
    ?>


    <div class="admin-main">


        <?php
            require __DIR__
                . "/admin_topbar.php";
        ?>


        <main class="admin-content">


            <!-- =============================================
                 PAGE HEADER
            ============================================== -->

            <section class="admin-page-header">

                <div>

                    <h1>
                        Dashboard
                    </h1>

                    <p>
                        Overview of bookings, customers,
                        payments and enquiries.
                    </p>

                </div>


                <a
                    href="bookings.php"
                    class="admin-button admin-button-primary"
                >

                    <i class="fa-solid fa-calendar-check"></i>

                    Manage Bookings

                </a>

            </section>



            <!-- =============================================
                 STATISTICS
            ============================================== -->

            <section class="admin-stats-grid">


                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Total Bookings
                        </p>

                        <div class="admin-stat-icon">

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>

                    </div>


                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $totalBookings
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        All customer bookings
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Paid Revenue
                        </p>

                        <div class="admin-stat-icon">

                            <i class="fa-solid fa-wallet"></i>

                        </div>

                    </div>


                    <div class="admin-stat-value">

                        KES
                        <?php
                            echo number_format(
                                $totalRevenue,
                                0
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Confirmed paid bookings
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Customers
                        </p>

                        <div class="admin-stat-icon">

                            <i class="fa-solid fa-users"></i>

                        </div>

                    </div>


                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $totalCustomers
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Registered accounts
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Unread Messages
                        </p>

                        <div class="admin-stat-icon">

                            <i class="fa-solid fa-envelope"></i>

                        </div>

                    </div>


                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $unreadMessages
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Require attention
                    </p>

                </article>


            </section>



            <!-- =============================================
                 CHART + PAYMENTS
            ============================================== -->

            <section class="admin-grid-2">


                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Booking Activity
                        </h2>

                        <span>
                            <?php
                                echo date("Y");
                            ?>
                        </span>

                    </div>


                    <div class="admin-panel-body">

                        <div class="admin-chart-container">

                            <canvas
                                id="bookingChart"
                            ></canvas>

                        </div>

                    </div>

                </article>



                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Payment Methods
                        </h2>

                    </div>


                    <div class="admin-panel-body">

                        <div class="payment-summary">


                            <?php if (
                                count(
                                    $paymentBreakdown
                                ) > 0
                            ): ?>


                                <?php foreach (
                                    $paymentBreakdown
                                    as $payment
                                ): ?>

                                    <div class="payment-row">

                                        <div class="payment-label">

                                            <span class="payment-dot"></span>

                                            <?php
                                                echo htmlspecialchars(
                                                    $payment["payment"]
                                                    ?? "Unknown",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </div>


                                        <div class="payment-count">

                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $payment["total"]
                                                        ?? 0
                                                    )
                                                );
                                            ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>


                            <?php else: ?>

                                <div class="admin-empty">

                                    No payment data available.

                                </div>

                            <?php endif; ?>


                        </div>

                    </div>

                </article>


            </section>



            <!-- =============================================
                 RECENT BOOKINGS
            ============================================== -->

            <section class="admin-panel">

                <div class="admin-panel-header">

                    <h2>
                        Recent Bookings
                    </h2>

                    <a href="bookings.php">
                        View all
                    </a>

                </div>


                <div class="admin-table-wrapper">


                    <?php if (
                        $recentBookings &&
                        $recentBookings->num_rows > 0
                    ): ?>


                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>
                                        Booking
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Travel Date
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Method
                                    </th>

                                    <th>
                                        Payment Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $booking =
                                    $recentBookings
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
                                            => "status-paid",

                                        "pending"
                                            => "status-pending",

                                        "failed",
                                        "timedout"
                                            => "status-failed",

                                        "cancelled"
                                            => "status-cancelled",

                                        default
                                            => "status-default"
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
                                                echo htmlspecialchars(
                                                    $booking["name"]
                                                    ?? "",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </strong>

                                        <br>

                                        <small>

                                            <?php
                                                echo htmlspecialchars(
                                                    $booking["email"]
                                                    ?? "",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?php

                                        $travelDate =
                                            $booking["date"]
                                            ?? null;


                                        echo $travelDate
                                            ? date(
                                                "d M Y",
                                                strtotime(
                                                    $travelDate
                                                )
                                            )
                                            : "—";

                                        ?>

                                    </td>


                                    <td>

                                        KES
                                        <?php
                                            echo number_format(
                                                (float) (
                                                    $booking["amount"]
                                                    ?? 0
                                                )
                                            );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                            echo htmlspecialchars(
                                                $booking["payment"]
                                                ?? "—",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>

                                    </td>


                                    <td>

                                        <span
                                            class="status-badge
                                            <?php
                                                echo $statusClass;
                                            ?>"
                                        >

                                            <?php
                                                echo htmlspecialchars(
                                                    $booking[
                                                        "payment_status"
                                                    ]
                                                    ?? "Unknown",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </span>

                                    </td>

                                </tr>


                            <?php endwhile; ?>


                            </tbody>

                        </table>


                    <?php else: ?>


                        <div class="admin-empty">

                            No bookings available yet.

                        </div>


                    <?php endif; ?>


                </div>

            </section>



            <!-- =============================================
                 RECENT MESSAGES
            ============================================== -->

            <section
                class="admin-panel"
                style="margin-top: 24px;"
            >

                <div class="admin-panel-header">

                    <h2>
                        Recent Messages
                    </h2>

                    <a href="messages.php">
                        View inbox
                    </a>

                </div>


                <div class="admin-table-wrapper">


                    <?php if (
                        $recentMessages &&
                        $recentMessages->num_rows > 0
                    ): ?>


                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Message
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Received
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php while (
                                $message =
                                    $recentMessages
                                    ->fetch_assoc()
                            ): ?>


                                <tr>

                                    <td>

                                        <strong>

                                            <?php
                                                echo htmlspecialchars(
                                                    $message["name"]
                                                    ?? "",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </strong>

                                        <br>

                                        <small>

                                            <?php
                                                echo htmlspecialchars(
                                                    $message["email"]
                                                    ?? "",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?php

                                        $messageText =
                                            (string) (
                                                $message["message"]
                                                ?? ""
                                            );


                                        if (
                                            strlen(
                                                $messageText
                                            ) > 80
                                        ) {

                                            $messageText =
                                                substr(
                                                    $messageText,
                                                    0,
                                                    80
                                                )
                                                . "...";
                                        }


                                        echo htmlspecialchars(
                                            $messageText,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        $messageStatus =
                                            $message["status"]
                                            ?? "Unread";

                                        ?>


                                        <span
                                            class="status-badge
                                            <?php
                                                echo $messageStatus
                                                    === "Unread"
                                                    ? "status-pending"
                                                    : "status-paid";
                                            ?>"
                                        >

                                            <?php
                                                echo htmlspecialchars(
                                                    $messageStatus,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php

                                        $created =
                                            $message["created_at"]
                                            ?? null;


                                        echo $created
                                            ? date(
                                                "d M Y H:i",
                                                strtotime(
                                                    $created
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


                        <div class="admin-empty">

                            No customer messages yet.

                        </div>


                    <?php endif; ?>


                </div>

            </section>


        </main>

    </div>

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
    sidebar &&
    mobileToggle
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


/* =========================================================
   REAL DATABASE BOOKING CHART
========================================================= */

const bookingChart =
    document.getElementById(
        "bookingChart"
    );


if (bookingChart) {

    new Chart(
        bookingChart,
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
                            "Bookings",

                        data:
                            <?php
                                echo json_encode(
                                    $monthValues
                                );
                            ?>,

                        borderColor:
                            "#0b7a3b",

                        backgroundColor:
                            "rgba(11, 122, 59, 0.10)",

                        borderWidth:
                            3,

                        tension:
                            0.35,

                        fill:
                            true,

                        pointRadius:
                            4,

                        pointHoverRadius:
                            6

                    }

                ]

            },


            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                plugins: {

                    legend: {

                        display:
                            false

                    }

                },

                scales: {

                    y: {

                        beginAtZero:
                            true,

                        ticks: {

                            precision:
                                0

                        },

                        grid: {

                            color:
                                "rgba(0,0,0,0.05)"

                        }

                    },


                    x: {

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