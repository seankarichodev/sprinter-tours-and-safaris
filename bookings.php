<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db.php";

if (empty($_SESSION["csrf_token"])) {

    $_SESSION["csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}

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


$allowedLimits = [
    5,
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
   VALID PAYMENT STATUS FILTER
========================================================= */

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



/* =========================================================
   COUNT MATCHING BOOKINGS
========================================================= */

$countSql =
    "
    SELECT
        COUNT(*) AS total
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
        OR CAST(b.id AS CHAR) LIKE CONCAT('%', ?, '%')
        OR u.name LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''
        OR LOWER(b.payment_status) =
           LOWER(?)
    )
    ";


$countStmt =
    $conn->prepare(
        $countSql
    );


$totalRecords = 0;


if ($countStmt) {

    $countStmt->bind_param(
        "sssssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $statusFilter,
        $statusFilter
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



$totalPages =
    max(
        1,
        (int) ceil(
            $totalRecords
            / $limit
        )
    );


if (
    $page > $totalPages
) {

    $page =
        $totalPages;
}


$offset =
    ($page - 1)
    * $limit;



/* =========================================================
   FETCH BOOKINGS
========================================================= */

$sql =
    "
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
        OR CAST(b.id AS CHAR) LIKE CONCAT('%', ?, '%')
        OR u.name LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''
        OR LOWER(b.payment_status) =
           LOWER(?)
    )

    ORDER BY b.id DESC

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
        "sssssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $statusFilter,
        $statusFilter
    );


    $stmt->execute();


    $result =
        $stmt->get_result();
}



/* =========================================================
   HELPER
========================================================= */

function adminEscape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function getPaymentStatusClass(
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
        Bookings | Sprinter Admin
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
                        Bookings
                    </h1>

                    <p>
                        Manage customer reservations,
                        travel information and payments.
                    </p>

                </div>


                <div class="admin-toolbar-group">

                    <a
                        href="export_excel.php"
                        class="admin-button admin-button-light"
                    >

                        <i class="fa-solid fa-file-excel"></i>

                        Excel

                    </a>


                    <a
                        href="export_pdf.php"
                        class="admin-button admin-button-light"
                    >

                        <i class="fa-solid fa-file-pdf"></i>

                        PDF

                    </a>

                </div>

            </section>



            <!-- =============================================
                 FILTER TOOLBAR
            ============================================== -->

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
                            placeholder="Search booking, customer, tour..."
                        >

                    </div>


                    <select
                        name="status"
                        class="admin-select"
                    >

                        <option value="">
                            All payment statuses
                        </option>


                        <?php

                        foreach (
                            [
                                "Pending",
                                "Paid",
                                "Failed",
                                "Cancelled",
                                "TimedOut"
                            ]
                            as $status
                        ):

                        ?>

                            <option
                                value="<?php echo adminEscape($status); ?>"
                                <?php
                                    echo $statusFilter === $status
                                        ? "selected"
                                        : "";
                                ?>
                            >

                                <?php
                                    echo adminEscape(
                                        $status
                                    );
                                ?>

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
                        $search !== "" ||
                        $statusFilter !== ""
                    ): ?>

                        <a
                            href="bookings.php"
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
                                <?php
                                    echo $limit === $option
                                        ? "selected"
                                        : "";
                                ?>
                            >

                                <?php echo $option; ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


            </form>



            <!-- =============================================
                 BOOKINGS TABLE
            ============================================== -->

            <section class="admin-panel">


                <div class="admin-panel-header">

                    <h2>
                        All Bookings
                    </h2>


                    <span
                        style="
                            font-size: 12px;
                            color: var(--admin-muted);
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
                        $result &&
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
                                        Travel
                                    </th>

                                    <th>
                                        Amount
                                    </th>

                                    <th>
                                        Payment
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>



                            <tbody>


                            <?php while (
                                $booking =
                                    $result
                                    ->fetch_assoc()
                            ): ?>


                                <?php

                                $paymentStatus =
                                    (string) (
                                        $booking[
                                            "payment_status"
                                        ]
                                        ?? "Unknown"
                                    );


                                $statusClass =
                                    getPaymentStatusClass(
                                        $paymentStatus
                                    );


                                $tourName =
                                    trim(
                                        (string) (
                                            $booking[
                                                "tour_name"
                                            ]
                                            ?? ""
                                        )
                                    );


                                $travelDate =
                                    $booking["date"]
                                    ?? null;


                                $travelTime =
                                    $booking["time"]
                                    ?? null;

                                ?>


                                <tr>


                                    <!-- BOOKING -->

                                    <td>

                                        <span class="booking-reference">

                                            #
                                            <?php
                                                echo (int)
                                                    $booking["id"];
                                            ?>

                                        </span>

                                        <br>

                                        <small
                                            style="
                                                color:
                                                var(--admin-muted);
                                            "
                                        >

                                            <?php

                                            $created =
                                                $booking[
                                                    "created_at"
                                                ]
                                                ?? null;


                                            echo $created
                                                ? date(
                                                    "d M Y",
                                                    strtotime(
                                                        $created
                                                    )
                                                )
                                                : "—";

                                            ?>

                                        </small>

                                    </td>



                                    <!-- CUSTOMER -->

                                    <td class="customer-cell">

                                        <strong>

                                            <?php
                                                echo adminEscape(
                                                    $booking[
                                                        "name"
                                                    ]
                                                    ?? ""
                                                );
                                            ?>

                                        </strong>


                                        <span>

                                            <?php
                                                echo adminEscape(
                                                    $booking[
                                                        "email"
                                                    ]
                                                    ?? ""
                                                );
                                            ?>

                                        </span>


                                        <?php if (
                                            !empty(
                                                $booking["phone"]
                                            )
                                        ): ?>

                                            <span>

                                                <?php
                                                    echo adminEscape(
                                                        $booking[
                                                            "phone"
                                                        ]
                                                    );
                                                ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <!-- TOUR -->

                                    <td>

                                        <span class="booking-tour">

                                            <?php

                                            echo $tourName !== ""
                                                ? adminEscape(
                                                    $tourName
                                                )
                                                : "Not specified";

                                            ?>

                                        </span>

                                    </td>



                                    <!-- TRAVEL -->

                                    <td>

                                        <strong>

                                            <?php

                                            echo $travelDate
                                                ? date(
                                                    "d M Y",
                                                    strtotime(
                                                        $travelDate
                                                    )
                                                )
                                                : "—";

                                            ?>

                                        </strong>


                                        <?php if (
                                            $travelTime
                                        ): ?>

                                            <br>

                                            <small
                                                style="
                                                    color:
                                                    var(--admin-muted);
                                                "
                                            >

                                                <?php

                                                echo date(
                                                    "H:i",
                                                    strtotime(
                                                        $travelTime
                                                    )
                                                );

                                                ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>



                                    <!-- AMOUNT -->

                                    <td class="amount-cell">

                                        KES

                                        <?php
                                            echo number_format(
                                                (float) (
                                                    $booking[
                                                        "amount"
                                                    ]
                                                    ?? 0
                                                ),
                                                0
                                            );
                                        ?>

                                    </td>



                                    <!-- PAYMENT -->

                                    <td>

                                        <strong>

                                            <?php
                                                echo adminEscape(
                                                    $booking[
                                                        "payment"
                                                    ]
                                                    ?? "—"
                                                );
                                            ?>

                                        </strong>


                                        <?php if (
                                            !empty(
                                                $booking[
                                                    "mpesa_receipt"
                                                ]
                                            )
                                        ): ?>

                                            <br>

                                            <small
                                                style="
                                                    color:
                                                    var(--admin-muted);
                                                "
                                            >

                                                <?php
                                                    echo adminEscape(
                                                        $booking[
                                                            "mpesa_receipt"
                                                        ]
                                                    );
                                                ?>

                                            </small>

                                        <?php elseif (
                                            !empty(
                                                $booking[
                                                    "payment_reference"
                                                ]
                                            )
                                        ): ?>

                                            <br>

                                            <small
                                                style="
                                                    color:
                                                    var(--admin-muted);
                                                "
                                            >

                                                <?php
                                                    echo adminEscape(
                                                        $booking[
                                                            "payment_reference"
                                                        ]
                                                    );
                                                ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>



                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="status-badge
                                            <?php
                                                echo $statusClass;
                                            ?>"
                                        >

                                            <?php
                                                echo adminEscape(
                                                    $paymentStatus
                                                );
                                            ?>

                                        </span>

                                    </td>



                                    <!-- ACTIONS -->

                                    <td>

                                        <a
                                            href="edit_booking.php?id=<?php echo (int) $booking["id"]; ?>"
                                            class="admin-action-link admin-action-edit"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                            Edit

                                        </a>


                                       <form
    action="delete_booking.php"
    method="POST"
    style="display:inline;"
    onsubmit="return confirm('Delete this booking?');"
>

    <input
        type="hidden"
        name="id"
        value="<?php echo (int) $booking["id"]; ?>"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?php
            echo htmlspecialchars(
                $_SESSION["csrf_token"],
                ENT_QUOTES,
                "UTF-8"
            );
        ?>"
    >

    <button
        type="submit"
        class="admin-action-link admin-action-delete"
        style="border:0; cursor:pointer;"
    >
        Delete
    </button>

</form>

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
                                    font-size: 30px;
                                    display: block;
                                    margin-bottom: 12px;
                                "
                            ></i>


                            No bookings match your search or filters.

                        </div>


                    <?php endif; ?>


                </div>



                <!-- =========================================
                     PAGINATION
                ========================================== -->

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
                                        "page"
                                            => $i,

                                        "limit"
                                            => $limit,

                                        "search"
                                            => $search,

                                        "status"
                                            => $statusFilter
                                    ]
                                );

                        ?>


                            <a
                                href="?<?php echo adminEscape($query); ?>"
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