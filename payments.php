<?php

require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";


/* =========================================================
   INPUTS
========================================================= */

$search =
    trim(
        $_GET["search"] ?? ""
    );

$statusFilter =
    trim(
        $_GET["status"] ?? ""
    );

$methodFilter =
    trim(
        $_GET["method"] ?? ""
    );


$allowedStatuses = [
    "",
    "Pending",
    "Paid",
    "Failed",
    "Cancelled",
    "TimedOut"
];


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter = "";
}


$allowedMethods = [
    "",
    "Mpesa",
    "Card",
    "PayPal"
];


if (
    !in_array(
        $methodFilter,
        $allowedMethods,
        true
    )
) {

    $methodFilter = "";
}


$allowedLimits = [
    10,
    25,
    50
];


$limit =
    isset($_GET["limit"])
        ? (int) $_GET["limit"]
        : 10;


if (
    !in_array(
        $limit,
        $allowedLimits,
        true
    )
) {

    $limit = 10;
}


$page =
    isset($_GET["page"])
        ? max(
            1,
            (int) $_GET["page"]
        )
        : 1;



/* =========================================================
   PAYMENT STATISTICS
========================================================= */

$statsSql =
    "
    SELECT

        COUNT(*) AS total_transactions,

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
                WHEN amount <= 1
                THEN 1
                ELSE 0
            END
        ) AS test_like_count

    FROM bookings
    ";


$statsResult =
    $conn->query(
        $statsSql
    );


$stats = [
    "total_transactions" => 0,
    "paid_revenue" => 0,
    "paid_count" => 0,
    "pending_count" => 0,
    "failed_count" => 0,
    "test_like_count" => 0
];


if ($statsResult) {

    $statsRow =
        $statsResult
        ->fetch_assoc();


    if ($statsRow) {

        $stats["total_transactions"] =
            (int) (
                $statsRow["total_transactions"]
                ?? 0
            );

        $stats["paid_revenue"] =
            (float) (
                $statsRow["paid_revenue"]
                ?? 0
            );

        $stats["paid_count"] =
            (int) (
                $statsRow["paid_count"]
                ?? 0
            );

        $stats["pending_count"] =
            (int) (
                $statsRow["pending_count"]
                ?? 0
            );

        $stats["failed_count"] =
            (int) (
                $statsRow["failed_count"]
                ?? 0
            );

        $stats["test_like_count"] =
            (int) (
                $statsRow["test_like_count"]
                ?? 0
            );
    }
}



/* =========================================================
   PAYMENT METHOD BREAKDOWN
========================================================= */

$methodBreakdown = [];


$methodResult =
    $conn->query(
        "
        SELECT
            payment,
            COUNT(*) AS total,
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

        ORDER BY total DESC
        "
    );


if ($methodResult) {

    while (
        $row =
            $methodResult
            ->fetch_assoc()
    ) {

        $methodBreakdown[] =
            $row;
    }
}



/* =========================================================
   COUNT FILTERED TRANSACTIONS
========================================================= */

$countSql =
    "
    SELECT
        COUNT(*) AS total

    FROM bookings

    WHERE
    (
        ? = ''

        OR name LIKE CONCAT('%', ?, '%')

        OR email LIKE CONCAT('%', ?, '%')

        OR phone LIKE CONCAT('%', ?, '%')

        OR tour_name LIKE CONCAT('%', ?, '%')

        OR payment_reference LIKE CONCAT('%', ?, '%')

        OR mpesa_receipt LIKE CONCAT('%', ?, '%')

        OR checkout_request_id LIKE CONCAT('%', ?, '%')

        OR merchant_request_id LIKE CONCAT('%', ?, '%')

        OR CAST(id AS CHAR) LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''
        OR LOWER(payment_status) = LOWER(?)
    )

    AND
    (
        ? = ''
        OR LOWER(payment) = LOWER(?)
    )
    ";


$countStmt =
    $conn->prepare(
        $countSql
    );


$totalRecords = 0;


if ($countStmt) {

    $countStmt->bind_param(
        "ssssssssssssss",
        $search,
        $search,
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


    $countResult =
        $countStmt->get_result();


    if ($countResult) {

        $countRow =
            $countResult
            ->fetch_assoc();


        $totalRecords =
            (int) (
                $countRow["total"]
                ?? 0
            );
    }


    $countStmt->close();
}



/* =========================================================
   PAGINATION
========================================================= */

$totalPages =
    max(
        1,
        (int) ceil(
            $totalRecords
            / $limit
        )
    );


if ($page > $totalPages) {

    $page =
        $totalPages;
}


$offset =
    ($page - 1)
    * $limit;



/* =========================================================
   FETCH TRANSACTIONS
========================================================= */

$sql =
    "
    SELECT

        id,
        user_id,
        name,
        email,
        phone,
        tour_name,
        date,
        amount,
        payment,
        payment_status,
        payment_reference,
        merchant_request_id,
        checkout_request_id,
        mpesa_receipt,
        created_at

    FROM bookings

    WHERE
    (
        ? = ''

        OR name LIKE CONCAT('%', ?, '%')

        OR email LIKE CONCAT('%', ?, '%')

        OR phone LIKE CONCAT('%', ?, '%')

        OR tour_name LIKE CONCAT('%', ?, '%')

        OR payment_reference LIKE CONCAT('%', ?, '%')

        OR mpesa_receipt LIKE CONCAT('%', ?, '%')

        OR checkout_request_id LIKE CONCAT('%', ?, '%')

        OR merchant_request_id LIKE CONCAT('%', ?, '%')

        OR CAST(id AS CHAR) LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''
        OR LOWER(payment_status) = LOWER(?)
    )

    AND
    (
        ? = ''
        OR LOWER(payment) = LOWER(?)
    )

    ORDER BY id DESC

    LIMIT $limit
    OFFSET $offset
    ";


$stmt =
    $conn->prepare(
        $sql
    );


$result = null;


if ($stmt) {

    $stmt->bind_param(
        "ssssssssssssss",
        $search,
        $search,
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


    $result =
        $stmt->get_result();
}



/* =========================================================
   HELPERS
========================================================= */

function paymentEscape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function paymentStatusClass(
    string $status
): string {

    return match (
        strtolower(
            trim($status)
        )
    ) {

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
        Payments | Sprinter Admin
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
                        Payments
                    </h1>

                    <p>
                        Monitor payment activity,
                        transaction status and confirmed revenue.
                    </p>

                </div>

            </section>



            <!-- =============================================
                 PAYMENT STATISTICS
            ============================================== -->

            <section class="admin-stats-grid">


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
                                $stats["paid_revenue"],
                                0
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Paid live-value bookings only
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Live Paid
                        </p>

                        <div class="admin-stat-icon">

                            <i class="fa-solid fa-circle-check"></i>

                        </div>

                    </div>

                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $stats["paid_count"]
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Successful live transactions
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
                                $stats["pending_count"]
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Awaiting confirmation
                    </p>

                </article>



                <article class="admin-stat-card">

                    <div class="admin-stat-top">

                        <p class="admin-stat-label">
                            Failed / Timed Out
                        </p>

                        <div class="admin-stat-icon">

                            <i class="fa-solid fa-triangle-exclamation"></i>

                        </div>

                    </div>

                    <div class="admin-stat-value">

                        <?php
                            echo number_format(
                                $stats["failed_count"]
                            );
                        ?>

                    </div>

                    <p class="admin-stat-note">
                        Require review
                    </p>

                </article>


            </section>



            <!-- =============================================
                 FILTERS
            ============================================== -->

            <form
                method="GET"
                action="payments.php"
                class="admin-toolbar"
            >


                <div class="admin-toolbar-group">


                    <div class="admin-search">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            name="search"
                            value="<?php echo paymentEscape($search); ?>"
                            placeholder="Search customer, reference, receipt..."
                        >

                    </div>



                    <select
                        name="method"
                        class="admin-select"
                    >

                        <option value="">
                            All methods
                        </option>

                        <option
                            value="Mpesa"
                            <?php echo $methodFilter === "Mpesa" ? "selected" : ""; ?>
                        >
                            M-Pesa
                        </option>

                        <option
                            value="Card"
                            <?php echo $methodFilter === "Card" ? "selected" : ""; ?>
                        >
                            Card
                        </option>

                        <option
                            value="PayPal"
                            <?php echo $methodFilter === "PayPal" ? "selected" : ""; ?>
                        >
                            PayPal
                        </option>

                    </select>



                    <select
                        name="status"
                        class="admin-select"
                    >

                        <option value="">
                            All statuses
                        </option>

                        <?php foreach (
                            [
                                "Pending",
                                "Paid",
                                "Failed",
                                "Cancelled",
                                "TimedOut"
                            ]
                            as $status
                        ): ?>

                            <option
                                value="<?php echo paymentEscape($status); ?>"
                                <?php echo $statusFilter === $status ? "selected" : ""; ?>
                            >

                                <?php echo paymentEscape($status); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>



                    <button
                        type="submit"
                        class="admin-button admin-button-primary"
                    >

                        <i class="fa-solid fa-filter"></i>

                        Filter

                    </button>



                    <?php if (
                        $search !== ""
                        ||
                        $statusFilter !== ""
                        ||
                        $methodFilter !== ""
                    ): ?>

                        <a
                            href="payments.php"
                            class="admin-button admin-button-light"
                        >
                            Clear
                        </a>

                    <?php endif; ?>


                </div>



                <div class="admin-toolbar-group">

                    <label for="limit">

                        <small>
                            Show
                        </small>

                    </label>

                    <select
                        name="limit"
                        id="limit"
                        class="admin-select"
                        onchange="this.form.submit()"
                    >

                        <?php foreach (
                            $allowedLimits
                            as $option
                        ): ?>

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



            <!-- =============================================
                 PAYMENT METHOD SUMMARY
            ============================================== -->

            <section
                class="admin-grid-2"
                style="
                    grid-template-columns:
                    repeat(2, minmax(0, 1fr));
                "
            >


                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Payment Methods
                        </h2>

                    </div>


                    <div class="admin-panel-body">


                        <?php if (
                            count(
                                $methodBreakdown
                            ) > 0
                        ): ?>


                            <div class="payment-summary">


                                <?php foreach (
                                    $methodBreakdown
                                    as $method
                                ): ?>


                                    <div class="payment-row">


                                        <div class="payment-label">

                                            <span class="payment-dot"></span>

                                            <?php
                                                echo paymentEscape(
                                                    $method["payment"]
                                                    ?? "Unknown"
                                                );
                                            ?>

                                        </div>


                                        <div>

                                            <strong>

                                                <?php
                                                    echo number_format(
                                                        (int) (
                                                            $method["total"]
                                                            ?? 0
                                                        )
                                                    );
                                                ?>

                                                transaction<?php
                                                    echo ((int) ($method["total"] ?? 0)) === 1
                                                        ? ""
                                                        : "s";
                                                ?>

                                            </strong>

                                            <br>

                                            <small
                                                style="
                                                    color:
                                                    var(--admin-muted);
                                                "
                                            >

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
                                                paid

                                            </small>

                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            </div>


                        <?php else: ?>


                            <div class="admin-empty">

                                No payment method data available.

                            </div>


                        <?php endif; ?>


                    </div>

                </article>



                <article class="admin-panel">

                    <div class="admin-panel-header">

                        <h2>
                            Transaction Overview
                        </h2>

                    </div>


                    <div class="admin-panel-body">


                        <div class="payment-summary">


                            <div class="payment-row">

                                <span>
                                    Total booking transactions
                                </span>

                                <strong>

                                    <?php
                                        echo number_format(
                                            $stats[
                                                "total_transactions"
                                            ]
                                        );
                                    ?>

                                </strong>

                            </div>


                            <div class="payment-row">

                                <span>
                                    Test-like records retained
                                </span>

                                <strong>
                                    <?php echo number_format($stats["test_like_count"]); ?>
                                </strong>

                            </div>


                            <div class="payment-row">

                                <span>
                                    Successful live payments
                                </span>

                                <strong>

                                    <?php
                                        echo number_format(
                                            $stats[
                                                "paid_count"
                                            ]
                                        );
                                    ?>

                                </strong>

                            </div>


                            <div class="payment-row">

                                <span>
                                    Pending payments
                                </span>

                                <strong>

                                    <?php
                                        echo number_format(
                                            $stats[
                                                "pending_count"
                                            ]
                                        );
                                    ?>

                                </strong>

                            </div>


                            <div class="payment-row">

                                <span>
                                    Failed / timed out
                                </span>

                                <strong>

                                    <?php
                                        echo number_format(
                                            $stats[
                                                "failed_count"
                                            ]
                                        );
                                    ?>

                                </strong>

                            </div>


                        </div>


                    </div>

                </article>


            </section>



            <!-- =============================================
                 TRANSACTION TABLE
            ============================================== -->

            <section class="admin-panel">


                <div class="admin-panel-header">

                    <h2>
                        Transactions
                    </h2>

                    <span
                        style="
                            font-size:12px;
                            color:var(--admin-muted);
                        "
                    >

                        <?php
                            echo number_format(
                                $totalRecords
                            );
                        ?>

                        record<?php
                            echo $totalRecords === 1
                                ? ""
                                : "s";
                        ?>

                    </span>

                </div>



                <div class="admin-table-wrapper">


                    <?php if (
                        $result
                        &&
                        $result->num_rows > 0
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
                                        Tour
                                    </th>

                                    <th>
                                        Method
                                    </th>

                                    <th>
                                        Reference
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Environment
                                    </th>

                                    <th>
                                        Created
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>



                            <tbody>


                            <?php while (
                                $paymentRow =
                                    $result
                                    ->fetch_assoc()
                            ): ?>


                                <?php

                                $paymentStatus =
                                    (string) (
                                        $paymentRow[
                                            "payment_status"
                                        ]
                                        ?? "Unknown"
                                    );


                                $statusClass =
                                    paymentStatusClass(
                                        $paymentStatus
                                    );


                                $reference =
                                    trim(
                                        (string) (
                                            $paymentRow[
                                                "mpesa_receipt"
                                            ]
                                            ?? ""
                                        )
                                    );


                                if (
                                    $reference === ""
                                ) {

                                    $reference =
                                        trim(
                                            (string) (
                                                $paymentRow[
                                                    "payment_reference"
                                                ]
                                                ?? ""
                                            )
                                        );
                                }


                                if (
                                    $reference === ""
                                ) {

                                    $reference =
                                        trim(
                                            (string) (
                                                $paymentRow[
                                                    "checkout_request_id"
                                                ]
                                                ?? ""
                                            )
                                        );
                                }

                                ?>


                                <tr>


                                    <td>

                                        <a
                                            href="admin_booking_view.php?id=<?php echo (int) $paymentRow["id"]; ?>"
                                            class="admin-action-link admin-action-view"
                                        >

                                            #
                                            <?php
                                                echo (int)
                                                    $paymentRow["id"];
                                            ?>

                                        </a>

                                    </td>



                                    <td class="customer-cell">

                                        <strong>

                                            <?php
                                                echo paymentEscape(
                                                    $paymentRow[
                                                        "name"
                                                    ]
                                                    ?? ""
                                                );
                                            ?>

                                        </strong>

                                        <span>

                                            <?php
                                                echo paymentEscape(
                                                    $paymentRow[
                                                        "email"
                                                    ]
                                                    ?? ""
                                                );
                                            ?>

                                        </span>

                                    </td>



                                    <td>

                                        <?php
                                            echo paymentEscape(
                                                $paymentRow[
                                                    "tour_name"
                                                ]
                                                ?? "—"
                                            );
                                        ?>

                                    </td>



                                    <td>

                                        <strong>

                                            <?php
                                                echo paymentEscape(
                                                    $paymentRow[
                                                        "payment"
                                                    ]
                                                    ?? "—"
                                                );
                                            ?>

                                        </strong>

                                    </td>



                                    <td>

                                        <?php if (
                                            $reference !== ""
                                        ): ?>

                                            <code
                                                style="
                                                    font-size:11px;
                                                    color:
                                                    var(--admin-muted);
                                                "
                                            >

                                                <?php
                                                    echo paymentEscape(
                                                        $reference
                                                    );
                                                ?>

                                            </code>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>



                                    <td class="amount-cell">

                                        KES

                                        <?php
                                            echo number_format(
                                                (float) (
                                                    $paymentRow[
                                                        "amount"
                                                    ]
                                                    ?? 0
                                                ),
                                                0
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
                                                echo paymentEscape(
                                                    $paymentStatus
                                                );
                                            ?>

                                        </span>

                                    </td>



                                    <td>
                                        <?php if ((float) ($paymentRow["amount"] ?? 0) <= 1): ?>
                                            <span class="status-badge status-pending">TEST-LIKE</span>
                                        <?php else: ?>
                                            <span class="status-badge status-paid">LIVE</span>
                                        <?php endif; ?>
                                    </td>



                                    <td>

                                        <?php

                                        $created =
                                            $paymentRow[
                                                "created_at"
                                            ]
                                            ?? null;


                                        if ($created) {

                                            echo
                                                "<strong>"
                                                . paymentEscape(
                                                    date(
                                                        "d M Y",
                                                        strtotime(
                                                            $created
                                                        )
                                                    )
                                                )
                                                . "</strong>";


                                            echo "<br>";


                                            echo
                                                "<small style='color:var(--admin-muted);'>"
                                                . paymentEscape(
                                                    date(
                                                        "H:i",
                                                        strtotime(
                                                            $created
                                                        )
                                                    )
                                                )
                                                . "</small>";

                                        } else {

                                            echo "—";
                                        }

                                        ?>

                                    </td>

                                    <td>
                                        <a
                                            href="admin_booking_view.php?id=<?php echo (int) $paymentRow["id"]; ?>"
                                            class="admin-action-link admin-action-view"
                                        >
                                            <i class="fa-regular fa-eye"></i> View
                                        </a>
                                    </td>


                                </tr>


                            <?php endwhile; ?>


                            </tbody>


                        </table>


                    <?php else: ?>


                        <div class="admin-empty">

                            <i
                                class="fa-solid fa-credit-card"
                                style="
                                    display:block;
                                    font-size:30px;
                                    margin-bottom:12px;
                                "
                            ></i>

                            No transactions match your filters.

                        </div>


                    <?php endif; ?>


                </div>



                <?php if (
                    $totalPages > 1
                ): ?>


                    <nav class="admin-pagination">


                        <?php

                        for (
                            $i = 1;
                            $i <= $totalPages;
                            $i++
                        ):

                            $query =
                                http_build_query(
                                    [
                                        "page" => $i,
                                        "limit" => $limit,
                                        "search" => $search,
                                        "status" => $statusFilter,
                                        "method" => $methodFilter
                                    ]
                                );

                        ?>


                            <a
                                href="?<?php echo paymentEscape($query); ?>"
                                class="<?php
                                    echo $i === $page
                                        ? "active"
                                        : "";
                                ?>"
                            >

                                <?php
                                    echo $i;
                                ?>

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

</script>


</body>

</html>

<?php

if ($stmt) {
    $stmt->close();
}

?>