<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db.php";


/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION["csrf_token"])) {

    $_SESSION["csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}


$message = "";
$messageType = "error";


/* =========================================================
   GET BOOKING ID
========================================================= */

$bookingId = 0;


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $bookingId =
        isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

} else {

    $bookingId =
        isset($_GET["id"])
            ? (int) $_GET["id"]
            : 0;
}


if ($bookingId <= 0) {

    header("Location: bookings.php");
    exit();
}


/* =========================================================
   UPDATE BOOKING
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update"])
) {

    $csrfToken =
        $_POST["csrf_token"]
        ?? "";


    if (
        empty($csrfToken) ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $csrfToken
        )
    ) {

        http_response_code(403);

        exit(
            "Invalid security token."
        );
    }


    $name =
        trim(
            $_POST["name"]
            ?? ""
        );


    $email =
        trim(
            $_POST["email"]
            ?? ""
        );


    $date =
        trim(
            $_POST["date"]
            ?? ""
        );


    $time =
        trim(
            $_POST["time"]
            ?? ""
        );


    $payment =
        trim(
            $_POST["payment"]
            ?? ""
        );


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        $name === "" ||
        $email === "" ||
        $date === "" ||
        $time === "" ||
        $payment === ""
    ) {

        $message =
            "Please complete all required fields.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

    } elseif (
        !in_array(
            $payment,
            [
                "Mpesa",
                "Card"
            ],
            true
        )
    ) {

        $message =
            "Invalid payment method.";

    } else {


        /* =================================================
           PREPARED UPDATE
        ================================================= */

        $stmt =
            $conn->prepare(
                "
                UPDATE bookings

                SET
                    name = ?,
                    email = ?,
                    date = ?,
                    time = ?,
                    payment = ?

                WHERE id = ?

                LIMIT 1
                "
            );


        if (!$stmt) {

            $message =
                "Unable to prepare the booking update.";

        } else {


            $stmt->bind_param(
                "sssssi",
                $name,
                $email,
                $date,
                $time,
                $payment,
                $bookingId
            );


           if ($stmt->execute()) {

    $stmt->close();


    /* =====================================================
       AUDIT LOG — BOOKING UPDATED
    ===================================================== */

    $auditAdminId =
        isset($_SESSION["admin_id"])
            ? (int) $_SESSION["admin_id"]
            : null;

    $auditUsername =
        isset($_SESSION["admin_username"])
            ? trim((string) $_SESSION["admin_username"])
            : "Unknown";

    $auditRole =
        isset($_SESSION["admin_role"])
            ? strtolower(trim((string) $_SESSION["admin_role"]))
            : "admin";


    /*
     * The audit table only accepts:
     * admin
     * owner
     */
    if (!in_array(
        $auditRole,
        ["admin", "owner"],
        true
    )) {

        $auditRole = "admin";
    }


    $auditAction =
        "Updated booking";

    $auditEntityType =
        "booking";

    $auditEntityId =
        $bookingId;

    $auditDetails =
        "Updated customer/travel details. "
        . "Customer: "
        . $name
        . " | Email: "
        . $email
        . " | Travel date: "
        . $date
        . " | Travel time: "
        . $time
        . " | Payment method: "
        . $payment;


    $auditIp =
        $_SERVER["REMOTE_ADDR"]
        ?? null;


    $auditStmt =
        $conn->prepare(
            "
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
            "
        );


    if ($auditStmt) {

        $auditStmt->bind_param(
            "issssiss",
            $auditAdminId,
            $auditUsername,
            $auditRole,
            $auditAction,
            $auditEntityType,
            $auditEntityId,
            $auditDetails,
            $auditIp
        );

        /*
         * Booking update has already succeeded.
         * Audit failure must not destroy the booking update.
         */
        if (!$auditStmt->execute()) {

            error_log(
                "Audit logging failed for booking #"
                . $bookingId
            );
        }

        $auditStmt->close();

    } else {

        error_log(
            "Unable to prepare audit log for booking #"
            . $bookingId
        );
    }


    header(
        "Location: bookings.php"
    );

    exit();

} else {

    $message =
        "Unable to update this booking.";

    $stmt->close();
}
        }
    }
}


/* =========================================================
   LOAD BOOKING
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            name,
            email,
            tour_name,
            date,
            time,
            payment,
            amount,
            payment_status,
            payment_reference,
            created_at

        FROM bookings

        WHERE id = ?

        LIMIT 1
        "
    );


if (!$stmt) {

    http_response_code(500);

    exit(
        "Unable to load booking."
    );
}


$stmt->bind_param(
    "i",
    $bookingId
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows !== 1) {

    $stmt->close();


    header(
        "Location: bookings.php"
    );

    exit();
}


$booking =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   PRESERVE SUBMITTED VALUES IF VALIDATION FAILED
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $message !== ""
) {

    $booking["name"] =
        $_POST["name"]
        ?? $booking["name"];


    $booking["email"] =
        $_POST["email"]
        ?? $booking["email"];


    $booking["date"] =
        $_POST["date"]
        ?? $booking["date"];


    $booking["time"] =
        $_POST["time"]
        ?? $booking["time"];


    $booking["payment"] =
        $_POST["payment"]
        ?? $booking["payment"];
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
        Edit Booking | Sprinter Tours & Safaris
    </title>


    <link
        rel="stylesheet"
        href="admin.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        .booking-edit-wrapper {
            max-width: 850px;
        }

        .booking-edit-card {
            margin-top: 24px;

            background: #ffffff;

            border: 1px solid #e1e8e4;
            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 8px 26px
                rgba(15, 40, 31, 0.05);
        }


        .booking-edit-header {
            padding: 24px 27px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            border-bottom:
                1px solid #edf0ee;

            background:
                linear-gradient(
                    135deg,
                    #ffffff,
                    #f7fbf8
                );
        }


        .booking-edit-header h2 {
            margin: 0 0 5px;

            color: #10231c;

            font-size: 18px;
        }


        .booking-edit-header p {
            margin: 0;

            color: #77827e;

            font-size: 11px;
        }


        .booking-number {
            padding: 8px 12px;

            border-radius: 9px;

            background: #eaf7ef;

            color: #087442;

            font-size: 11px;
            font-weight: 800;
        }


        .booking-edit-body {
            padding: 28px;
        }


        .booking-info-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 14px;

            margin-bottom: 28px;
        }


        .booking-info-box {
            padding: 14px;

            border-radius: 11px;

            background: #f8faf9;

            border: 1px solid #edf0ee;
        }


        .booking-info-box span {
            display: block;

            margin-bottom: 4px;

            color: #89928e;

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.4px;
        }


        .booking-info-box strong {
            color: #21342d;

            font-size: 12px;
        }


        .edit-form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }


        .edit-field-full {
            grid-column:
                1 / -1;
        }


        .edit-field label {
            display: block;

            margin-bottom: 7px;

            color: #34443e;

            font-size: 10px;
            font-weight: 800;
        }


        .edit-field input,
        .edit-field select {
            width: 100%;

            height: 48px;

            padding:
                0 14px;

            border:
                1px solid #dce3df;

            border-radius:
                10px;

            outline: none;

            background:
                #ffffff;

            color: #172a23;

            font: inherit;
            font-size: 12px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .edit-field input:focus,
        .edit-field select:focus {
            border-color:
                #0b8248;

            box-shadow:
                0 0 0 4px
                rgba(11, 130, 72, 0.07);
        }


        .booking-edit-message {
            margin-bottom: 20px;

            padding: 12px 14px;

            border:
                1px solid #fecaca;

            border-radius: 10px;

            background: #fff1f2;

            color: #9f1239;

            font-size: 11px;
        }


        .booking-edit-actions {
            margin-top: 28px;

            padding-top: 22px;

            border-top:
                1px solid #edf0ee;

            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }


        .booking-cancel-button,
        .booking-save-button {
            min-height: 43px;

            padding:
                0 18px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            border-radius: 10px;

            text-decoration: none;

            font-size: 11px;
            font-weight: 700;

            cursor: pointer;
        }


        .booking-cancel-button {
            border:
                1px solid #dce3df;

            background:
                #ffffff;

            color:
                #53635d;
        }


        .booking-save-button {
            border: 0;

            background:
                linear-gradient(
                    135deg,
                    #0b8248,
                    #075e36
                );

            color: #ffffff;

            box-shadow:
                0 10px 22px
                rgba(11, 130, 72, 0.16);
        }


        .booking-save-button:hover {
            transform:
                translateY(-1px);
        }


        @media (
            max-width: 760px
        ) {

            .booking-info-grid,
            .edit-form-grid {
                grid-template-columns:
                    1fr;
            }

            .edit-field-full {
                grid-column:
                    auto;
            }

            .booking-edit-header {
                align-items:
                    flex-start;

                flex-direction:
                    column;
            }
        }

    </style>

</head>


<body>


<div class="admin-layout">


    <?php

    $activePage =
        "bookings";


    require
        __DIR__
        . "/admin_sidebar.php";

    ?>


    <main class="admin-main">


        <?php

        require
            __DIR__
            . "/admin_topbar.php";

        ?>


        <section class="admin-content">


            <div class="booking-edit-wrapper">


                <div class="admin-page-header">


                    <div>


                        <a
                            href="bookings.php"
                            class="admin-back-link"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Back to Bookings

                        </a>


                        <h1>
                            Edit Booking
                        </h1>


                        <p>
                            Update customer and travel details
                            for this reservation.
                        </p>


                    </div>


                </div>



                <div class="booking-edit-card">


                    <div class="booking-edit-header">


                        <div>

                            <h2>
                                Reservation Details
                            </h2>

                            <p>
                                Review the booking before
                                saving any changes.
                            </p>

                        </div>


                        <span class="booking-number">

                            Booking
                            #<?php
                            echo (int)
                                $booking["id"];
                            ?>

                        </span>


                    </div>



                    <div class="booking-edit-body">


                        <div class="booking-info-grid">


                            <div class="booking-info-box">

                                <span>
                                    Tour
                                </span>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking["tour_name"]
                                            ?: "Not specified",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="booking-info-box">

                                <span>
                                    Amount
                                </span>

                                <strong>

                                    KES

                                    <?php
                                    echo number_format(
                                        (float)
                                        $booking["amount"],
                                        0
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="booking-info-box">

                                <span>
                                    Status
                                </span>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $booking["payment_status"]
                                            ?: "Pending",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </strong>

                            </div>


                        </div>



                        <?php if (
                            $message !== ""
                        ): ?>


                            <div class="booking-edit-message">

                                <i class="fa-solid fa-circle-exclamation"></i>

                                <?php
                                echo htmlspecialchars(
                                    $message,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </div>


                        <?php endif; ?>



                        <form
                            method="POST"
                            action=""
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



                            <div class="edit-form-grid">


                                <div class="edit-field">

                                    <label for="name">
                                        CUSTOMER NAME
                                    </label>

                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        value="<?php
                                            echo htmlspecialchars(
                                                $booking["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        required
                                    >

                                </div>



                                <div class="edit-field">

                                    <label for="email">
                                        EMAIL ADDRESS
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="<?php
                                            echo htmlspecialchars(
                                                $booking["email"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        required
                                    >

                                </div>



                                <div class="edit-field">

                                    <label for="date">
                                        TRAVEL DATE
                                    </label>

                                    <input
                                        type="date"
                                        id="date"
                                        name="date"
                                        value="<?php
                                            echo htmlspecialchars(
                                                $booking["date"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        required
                                    >

                                </div>



                                <div class="edit-field">

                                    <label for="time">
                                        TRAVEL TIME
                                    </label>

                                    <input
                                        type="time"
                                        id="time"
                                        name="time"
                                        value="<?php
                                            echo htmlspecialchars(
                                                $booking["time"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            );
                                        ?>"
                                        required
                                    >

                                </div>



                                <div class="edit-field edit-field-full">

                                    <label for="payment">
                                        PAYMENT METHOD
                                    </label>

                                    <select
                                        id="payment"
                                        name="payment"
                                        required
                                    >

                                        <option
                                            value="Mpesa"
                                            <?php
                                            echo (
                                                $booking["payment"]
                                                === "Mpesa"
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            M-Pesa
                                        </option>


                                        <option
                                            value="Card"
                                            <?php
                                            echo (
                                                $booking["payment"]
                                                === "Card"
                                            )
                                                ? "selected"
                                                : "";
                                            ?>
                                        >
                                            Card
                                        </option>

                                    </select>

                                </div>


                            </div>



                            <div class="booking-edit-actions">


                                <a
                                    href="bookings.php"
                                    class="booking-cancel-button"
                                >

                                    Cancel

                                </a>


                                <button
                                    type="submit"
                                    name="update"
                                    class="booking-save-button"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Changes

                                </button>


                            </div>


                        </form>


                    </div>


                </div>


            </div>


        </section>


    </main>


</div>


</body>

</html>