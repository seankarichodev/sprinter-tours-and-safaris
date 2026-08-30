<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db.php";

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

$stmt = $conn->prepare(
    "
    SELECT
        id,
        name,
        email,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
    "
);

$stmt->bind_param("i", $customerId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    header("Location: users.php");
    exit();
}

$customer = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   CUSTOMER BOOKING SUMMARY
========================================================= */

$stmt = $conn->prepare(
    "
    SELECT

        COUNT(*) AS total_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'Paid'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_spent,

        MAX(date) AS latest_travel

    FROM bookings

    WHERE user_id = ?
    "
);

$stmt->bind_param("i", $customerId);
$stmt->execute();

$summary = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


/* =========================================================
   BOOKING HISTORY
========================================================= */

$stmt = $conn->prepare(
    "
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

    ORDER BY created_at DESC
    "
);

$stmt->bind_param("i", $customerId);
$stmt->execute();

$bookings = $stmt->get_result();

$stmt->close();


/* =========================================================
   CUSTOMER INITIALS
========================================================= */

$nameParts = preg_split(
    "/\s+/",
    trim($customer["name"])
);

$initials = "";

foreach (array_slice($nameParts, 0, 2) as $part) {

    if ($part !== "") {
        $initials .= strtoupper(
            substr($part, 0, 1)
        );
    }
}

if ($initials === "") {
    $initials = "CU";
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
        Customer Details | Sprinter Tours & Safaris
    </title>

    <link
        rel="stylesheet"
        href="admin.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>


<body>


<div class="admin-layout">


    <?php
    $activePage = "customers";
    require __DIR__ . "/admin_sidebar.php";
    ?>


    <main class="admin-main">


        <?php
        require __DIR__ . "/admin_topbar.php";
        ?>


        <section class="admin-content">


            <!-- =================================================
                 PAGE HEADER
            ================================================== -->

            <div class="admin-page-header">


                <div>

                    <a
                        href="users.php"
                        class="admin-back-link"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Customers
                    </a>


                    <h1>
                        Customer Profile
                    </h1>


                    <p>
                        Review customer details,
                        booking history and account activity.
                    </p>

                </div>


            </div>



            <!-- =================================================
                 CUSTOMER PROFILE
            ================================================== -->

            <div class="customer-profile-card">


                <div class="customer-profile-avatar">

                    <?php
                    echo htmlspecialchars(
                        $initials,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>


                <div class="customer-profile-details">


                    <h2>

                        <?php
                        echo htmlspecialchars(
                            $customer["name"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>

                    </h2>


                    <p>

                        <i class="fa-regular fa-envelope"></i>

                        <?php
                        echo htmlspecialchars(
                            $customer["email"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>

                    </p>


                    <span>

                        Customer since

                        <?php
                        echo date(
                            "d M Y",
                            strtotime(
                                $customer["created_at"]
                            )
                        );
                        ?>

                    </span>


                </div>


            </div>



            <!-- =================================================
                 SUMMARY CARDS
            ================================================== -->

            <div class="admin-stats-grid">


                <div class="admin-stat-card">


                    <div class="admin-stat-icon">

                        <i class="fa-solid fa-calendar-check"></i>

                    </div>


                    <div>

                        <span>
                            Total Bookings
                        </span>

                        <strong>

                            <?php
                            echo (int)
                                $summary["total_bookings"];
                            ?>

                        </strong>

                    </div>


                </div>



                <div class="admin-stat-card">


                    <div class="admin-stat-icon">

                        <i class="fa-solid fa-wallet"></i>

                    </div>


                    <div>

                        <span>
                            Paid Value
                        </span>

                        <strong>

                            KES

                            <?php
                            echo number_format(
                                (float)
                                $summary["total_spent"],
                                0
                            );
                            ?>

                        </strong>

                    </div>


                </div>



                <div class="admin-stat-card">


                    <div class="admin-stat-icon">

                        <i class="fa-solid fa-plane-departure"></i>

                    </div>


                    <div>

                        <span>
                            Latest Travel
                        </span>

                        <strong>

                            <?php

                            if (
                                !empty(
                                    $summary["latest_travel"]
                                )
                            ) {

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $summary["latest_travel"]
                                    )
                                );

                            } else {

                                echo "No travel yet";
                            }

                            ?>

                        </strong>

                    </div>


                </div>


            </div>



            <!-- =================================================
                 BOOKINGS
            ================================================== -->

            <div class="admin-card">


                <div class="admin-card-header">


                    <div>

                        <h2>
                            Booking History
                        </h2>

                        <p>
                            All bookings associated
                            with this customer.
                        </p>

                    </div>


                </div>



                <div class="admin-table-wrapper">


                    <table class="admin-table">


                        <thead>

                            <tr>

                                <th>
                                    Booking
                                </th>

                                <th>
                                    Tour
                                </th>

                                <th>
                                    Travel
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Created
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (
                            $bookings->num_rows > 0
                        ): ?>


                            <?php while (
                                $booking =
                                    $bookings->fetch_assoc()
                            ): ?>


                                <tr>


                                    <td>

                                        <strong>

                                            #<?php
                                            echo (int)
                                                $booking["id"];
                                            ?>

                                        </strong>

                                    </td>



                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking["tour_name"]
                                                ?: "Not specified",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </td>



                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $booking["date"]
                                            )
                                        ) {

                                            echo date(
                                                "d M Y",
                                                strtotime(
                                                    $booking["date"]
                                                )
                                            );

                                        } else {

                                            echo "—";
                                        }

                                        ?>

                                        <?php if (
                                            !empty(
                                                $booking["time"]
                                            )
                                        ): ?>

                                            <small>

                                                <?php
                                                echo date(
                                                    "g:i A",
                                                    strtotime(
                                                        $booking["time"]
                                                    )
                                                );
                                                ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>



                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                ucfirst(
                                                    $booking["payment"]
                                                        ?: "Unknown"
                                                ),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                            ?>

                                        </strong>


                                        <?php if (
                                            !empty(
                                                $booking["payment_reference"]
                                            )
                                        ): ?>

                                            <small>

                                                <?php
                                                echo htmlspecialchars(
                                                    $booking[
                                                        "payment_reference"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                ?>

                                            </small>

                                        <?php elseif (
                                            !empty(
                                                $booking["mpesa_receipt"]
                                            )
                                        ): ?>

                                            <small>

                                                <?php
                                                echo htmlspecialchars(
                                                    $booking[
                                                        "mpesa_receipt"
                                                    ],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>



                                    <td>

                                        KES

                                        <?php
                                        echo number_format(
                                            (float)
                                            $booking["amount"],
                                            0
                                        );
                                        ?>

                                    </td>



                                    <td>

                                        <?php

                                        $status =
                                            strtolower(
                                                $booking[
                                                    "payment_status"
                                                ]
                                                ?? "pending"
                                            );

                                        $statusClass =
                                            "status-pending";

                                        if (
                                            $status === "paid"
                                        ) {

                                            $statusClass =
                                                "status-paid";

                                        } elseif (
                                            in_array(
                                                $status,
                                                [
                                                    "failed",
                                                    "cancelled",
                                                    "timed out",
                                                    "timeout"
                                                ],
                                                true
                                            )
                                        ) {

                                            $statusClass =
                                                "status-failed";
                                        }

                                        ?>


                                        <span
                                            class="admin-status <?php echo $statusClass; ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                ucfirst($status),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                            ?>

                                        </span>

                                    </td>



                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $booking["created_at"]
                                            )
                                        );
                                        ?>

                                    </td>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="7"
                                    class="admin-empty-state"
                                >

                                    <i class="fa-regular fa-calendar-xmark"></i>

                                    <strong>
                                        No bookings found
                                    </strong>

                                    <span>
                                        This customer has not made
                                        any bookings yet.
                                    </span>

                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>


        </section>


    </main>


</div>


</body>

</html>