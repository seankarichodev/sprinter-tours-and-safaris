<?php

require_once __DIR__ . "/admin_auth.php";
requireAdmin();
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
                    WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_revenue,

        SUM(
            CASE
                WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
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
      AND amount > 1
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
   DEVELOPMENT ACTIVITY
========================================================= */

$testLikeCount = 0;

$testLikeStmt =
    $conn->prepare(
        "
        SELECT COUNT(*) AS total
        FROM bookings
        WHERE YEAR(created_at) = ?
          AND amount <= 1
        "
    );

if ($testLikeStmt) {

    $testLikeStmt->bind_param(
        "i",
        $year
    );

    $testLikeStmt->execute();

    $testLikeResult =
        $testLikeStmt->get_result();

    if (
        $testLikeResult
        &&
        ($testLikeRow = $testLikeResult->fetch_assoc())
    ) {

        $testLikeCount =
            (int) (
                $testLikeRow["total"]
                ?? 0
            );
    }

    $testLikeStmt->close();
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
                        WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
                AS revenue

        FROM bookings

        WHERE YEAR(created_at) = ?
          AND amount > 1

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
                        WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
                AS revenue

        FROM bookings

        WHERE YEAR(created_at) = ?
          AND amount > 1

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
                    WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                    THEN 1
                    ELSE 0
                END
            )
                AS paid_transactions,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(COALESCE(payment_status, '')) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
                AS revenue

        FROM bookings

        WHERE YEAR(created_at) = ?
          AND amount > 1

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
                        WHEN LOWER(COALESCE(b.payment_status, '')) = 'paid'
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
            AND b.amount > 1

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
        Reports | Sprinter Admin
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
                        Reports
                    </h1>

                    <p>
                        Review live booking performance, confirmed revenue, customers and payment activity.
                    </p>

                </div>


                <form
                    method="GET"
                    action="reports.php"
                    class="admin-toolbar-group"
                >

                    <select
                        name="year"
                        class="admin-select"
                        onchange="this.form.submit()"
                    >

                        <?php foreach (
                            $availableYears
                            as $reportYear
                        ): ?>

                            <option
                                value="<?php echo $reportYear; ?>"
                                <?php
                                    echo $year === $reportYear
                                        ? "selected"
                                        : "";
                                ?>
                            >

                                <?php echo $reportYear; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

            </section>



            <!-- =============================================
                 SUMMARY
            ============================================== -->

            <section class="admin-stats-grid">


                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Live Bookings
                        </p>

                        <div class="admin-stat-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>

                    </div>

                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $summary["total_bookings"]
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Business bookings above KES 1 in <?php echo $year; ?>
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Live Revenue
                        </p>

                        <div class="admin-stat-icon">
                            <i class="fa-solid fa-wallet"></i>
                        </div>

                    </div>

                    <div class="admin-stat-value">

                        KES
                        <?php
                            echo number_format(
                                $summary["total_revenue"],
                                0
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Confirmed live payments only
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Live Paid Bookings
                        </p>

                        <div class="admin-stat-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>

                    </div>

                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $summary["paid_bookings"]
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Successful live payments
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Pending
                        </p>

                        <div class="admin-stat-icon">
                            <i class="fa-solid fa-clock"></i>
                        </div>

                    </div>

                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $summary["pending_bookings"]
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Awaiting payment confirmation
                    </p>

                </article>


            </section>


            <section
                class="admin-panel"
                style="margin-top:18px;"
            >
                <div class="admin-panel-body">
                    <div
                        style="
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            gap:16px;
                            flex-wrap:wrap;
                        "
                    >
                        <div>
                            <strong style="display:block;margin-bottom:5px;">
                                Reporting Integrity
                            </strong>

                            <span
                                style="
                                    color:var(--admin-muted);
                                    font-size:12px;
                                    line-height:1.5;
                                "
                            >
                                Business analytics exclude development transactions
                                of KES 1 or less. Those records remain available in
                                operational history for traceability.
                            </span>
                        </div>

                        <span
                            class="status-badge status-pending"
                            style="white-space:nowrap;"
                        >
                            <?php echo number_format($testLikeCount); ?>
                            test-like record<?php echo $testLikeCount === 1 ? "" : "s"; ?>
                        </span>
                    </div>
                </div>
            </section>



            <!-- =============================================
                 CHARTS
            ============================================== -->

            <section class="admin-grid-2">


                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Monthly Live Bookings
                        </h2>

                        <span>
                            <?php echo $year; ?>
                        </span>

                    </div>

                    <div class="admin-panel-body">

                        <div class="admin-chart-container">

                            <canvas id="bookingsReportChart"></canvas>

                        </div>

                    </div>

                </article>



                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Monthly Live Revenue
                        </h2>

                        <span>
                            KES
                        </span>

                    </div>

                    <div class="admin-panel-body">

                        <div class="admin-chart-container">

                            <canvas id="revenueReportChart"></canvas>

                        </div>

                    </div>

                </article>


            </section>



            <!-- =============================================
                 TOUR PERFORMANCE
            ============================================== -->

            <section class="admin-panel">

                <div class="admin-panel-header">

                    <h2>
                        Tour Performance
                    </h2>

                </div>


                <div class="admin-table-wrapper">


                    <?php if (
                        count(
                            $tourPerformance
                        ) > 0
                    ): ?>

                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>
                                        Tour
                                    </th>

                                    <th>
                                        Bookings
                                    </th>

                                    <th>
                                        Live Revenue
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $tourPerformance
                                as $tour
                            ): ?>

                                <tr>

                                    <td>

                                        <strong>

                                            <?php
                                                echo reportEscape(
                                                    $tour["tour_name"]
                                                    ?? ""
                                                );
                                            ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?php
                                            echo number_format(
                                                (int) (
                                                    $tour[
                                                        "total_bookings"
                                                    ]
                                                    ?? 0
                                                )
                                            );
                                        ?>

                                    </td>


                                    <td class="amount-cell">

                                        KES

                                        <?php
                                            echo number_format(
                                                (float) (
                                                    $tour["revenue"]
                                                    ?? 0
                                                ),
                                                0
                                            );
                                        ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            </tbody>

                        </table>


                    <?php else: ?>

                        <div class="admin-empty">

                            No tour performance data for
                            <?php echo $year; ?>.

                        </div>

                    <?php endif; ?>


                </div>

            </section>



            <!-- =============================================
                 PAYMENT + CUSTOMER PERFORMANCE
            ============================================== -->

            <section
                class="admin-grid-2"
                style="margin-top:24px;"
            >


                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Payment Performance
                        </h2>

                    </div>


                    <div class="admin-table-wrapper">


                        <?php if (
                            count(
                                $paymentPerformance
                            ) > 0
                        ): ?>


                            <table class="admin-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Method
                                        </th>

                                        <th>
                                            Transactions
                                        </th>

                                        <th>
                                            Paid
                                        </th>

                                        <th>
                                            Revenue
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                <?php foreach (
                                    $paymentPerformance
                                    as $payment
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?php
                                                    echo reportEscape(
                                                        $payment[
                                                            "payment"
                                                        ]
                                                        ?? "Unknown"
                                                    );
                                                ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $payment[
                                                            "total_transactions"
                                                        ]
                                                        ?? 0
                                                    )
                                                );
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $payment[
                                                            "paid_transactions"
                                                        ]
                                                        ?? 0
                                                    )
                                                );
                                            ?>

                                        </td>


                                        <td class="amount-cell">

                                            KES

                                            <?php
                                                echo number_format(
                                                    (float) (
                                                        $payment[
                                                            "revenue"
                                                        ]
                                                        ?? 0
                                                    ),
                                                    0
                                                );
                                            ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>


                                </tbody>

                            </table>


                        <?php else: ?>

                            <div class="admin-empty">
                                No payment data available.
                            </div>

                        <?php endif; ?>


                    </div>

                </article>



                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Customer Booking Activity
                        </h2>

                    </div>


                    <div class="admin-table-wrapper">


                        <?php if (
                            count(
                                $topCustomers
                            ) > 0
                        ): ?>


                            <table class="admin-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Bookings
                                        </th>

                                        <th>
                                            Live Value
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                <?php foreach (
                                    $topCustomers
                                    as $customer
                                ): ?>

                                    <tr>

                                        <td class="customer-cell">

                                            <strong>

                                                <?php
                                                    echo reportEscape(
                                                        $customer[
                                                            "name"
                                                        ]
                                                        ?? ""
                                                    );
                                                ?>

                                            </strong>

                                            <span>

                                                <?php
                                                    echo reportEscape(
                                                        $customer[
                                                            "email"
                                                        ]
                                                        ?? ""
                                                    );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $customer[
                                                            "total_bookings"
                                                        ]
                                                        ?? 0
                                                    )
                                                );
                                            ?>

                                        </td>


                                        <td class="amount-cell">

                                            KES

                                            <?php
                                                echo number_format(
                                                    (float) (
                                                        $customer[
                                                            "total_spent"
                                                        ]
                                                        ?? 0
                                                    ),
                                                    0
                                                );
                                            ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>


                                </tbody>

                            </table>


                        <?php else: ?>

                            <div class="admin-empty">
                                No customer activity available.
                            </div>

                        <?php endif; ?>


                    </div>

                </article>


            </section>


        </main>

    </div>

</div>



<script>

/* =========================================================
   MOBILE SIDEBAR
========================================================= */

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
   BOOKINGS CHART
========================================================= */

const bookingsCanvas =
    document.getElementById(
        "bookingsReportChart"
    );


if (bookingsCanvas) {

    new Chart(
        bookingsCanvas,
        {

            type: "bar",

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
                            "Live Bookings",

                        data:
                            <?php
                                echo json_encode(
                                    $bookingChartData
                                );
                            ?>,

                        backgroundColor:
                            "rgba(11,122,59,0.20)",

                        borderColor:
                            "#0b7a3b",

                        borderWidth:
                            2,

                        borderRadius:
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
                        display: false
                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {
                            precision: 0
                        }

                    }

                }

            }

        }
    );

}



/* =========================================================
   REVENUE CHART
========================================================= */

const revenueCanvas =
    document.getElementById(
        "revenueReportChart"
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
                            "Live Revenue",

                        data:
                            <?php
                                echo json_encode(
                                    $revenueChartData
                                );
                            ?>,

                        borderColor:
                            "#0b7a3b",

                        backgroundColor:
                            "rgba(11,122,59,0.10)",

                        borderWidth:
                            3,

                        tension:
                            0.35,

                        fill:
                            true,

                        pointRadius:
                            4

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
                        display: false
                    }

                },

                scales: {

                    y: {

                        beginAtZero: true

                    }

                }

            }

        }
    );

}

</script>


</body>

</html>