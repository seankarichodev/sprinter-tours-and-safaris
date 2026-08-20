<?php

session_start();

require_once "db.php";


/* =========================================================
   REQUIRE LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: auth.php");
    exit();

}


$user_id =
    (int) $_SESSION["user_id"];


/* =========================================================
   VALIDATE BOOKING ID
========================================================= */

$booking_id =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


if (
    !$booking_id ||
    $booking_id < 1
) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   GET PAID BOOKING BELONGING TO THIS CUSTOMER
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
            phone,
            amount,
            payment_status,
            payment_reference,
            mpesa_receipt,
            created_at
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    die(
        "Unable to load receipt."
    );

}


$stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$booking =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   BOOKING NOT FOUND
========================================================= */

if (!$booking) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   RECEIPT ONLY FOR PAID BOOKINGS
========================================================= */

if (
    strtolower(
        trim(
            $booking["payment_status"]
        )
    )
    !== "paid"
) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   PAYMENT REFERENCE
========================================================= */

$reference =
    "N/A";


if (
    $booking["payment"] === "Mpesa" &&
    !empty(
        $booking["mpesa_receipt"]
    )
) {

    $reference =
        $booking["mpesa_receipt"];

}
elseif (
    !empty(
        $booking["payment_reference"]
    )
) {

    $reference =
        $booking["payment_reference"];

}


/* =========================================================
   RECEIPT NUMBER
========================================================= */

$receipt_number =
    "STS-"
    . date("Y")
    . "-"
    . str_pad(
        (string) $booking["id"],
        6,
        "0",
        STR_PAD_LEFT
    );


/* =========================================================
   BUSINESS DETAILS

   Add real public details here later.
========================================================= */

$business_name =
    "Sprinter Tours & Safaris";

$business_phone =
    "";

$business_email =
    "";

$business_address =
    "Kenya";


/* =========================================================
   RECEIPT VERIFICATION URL

   DEVELOPMENT:
   Use your active ngrok address.

   PRODUCTION:
   Replace with your real domain.
========================================================= */

$base_url =
    "https://careless-approval-clerical.ngrok-free.dev/wildlife-tours";


/* =========================================================
   RECEIPT VERIFICATION SIGNATURE
========================================================= */

require_once __DIR__ . "/receipt_config.php";


$verification_signature =
    hash_hmac(
        "sha256",
        $receipt_number,
        RECEIPT_VERIFY_SECRET
    );


$verification_url =
    $base_url
    . "/verify_receipt.php?receipt="
    . urlencode(
        $receipt_number
    )
    . "&sig="
    . urlencode(
        $verification_signature
    );


/* =========================================================
   QR CODE
========================================================= */

$qr_url =
    "https://api.qrserver.com/v1/create-qr-code/"
    . "?size=220x220&data="
    . urlencode(
        $verification_url
    );


/* =========================================================
   DISPLAY VALUES
========================================================= */

$tour_name =
    !empty(
        $booking["tour_name"]
    )
        ? $booking["tour_name"]
        : "Tour Booking";


$payment_date =
    !empty(
        $booking["created_at"]
    )
        ? date(
            "d M Y",
            strtotime(
                $booking["created_at"]
            )
        )
        : date("d M Y");

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
        Receipt <?php
        echo htmlspecialchars(
            $receipt_number
        );
        ?>
    </title>


    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <style>

/* =========================================================
   RESET
========================================================= */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}


body {

    font-family:
        "Poppins",
        sans-serif;

    background:
        #edf3ef;

    color:
        #1c1c1c;

    padding:
        35px 15px;

}


/* =========================================================
   PAGE CONTAINER
========================================================= */

.receipt-wrapper {

    width:
        min(
            900px,
            100%
        );

    margin:
        auto;

}


/* =========================================================
   RECEIPT
========================================================= */

.receipt {

    overflow:
        hidden;

    border-radius:
        18px;

    background:
        white;

    box-shadow:
        0 15px 45px
        rgba(
            0,
            0,
            0,
            0.12
        );

}


/* =========================================================
   HEADER
========================================================= */

.receipt-header {

    padding:
        30px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        25px;

    color:
        white;

    background:

        linear-gradient(
            135deg,
            #08752d,
            #043b18
        );

}


/* =========================================================
   BRAND
========================================================= */

.brand {

    display:
        flex;

    align-items:
        center;

    gap:
        17px;

}


.logo-box {

    width:
        82px;

    height:
        82px;

    flex-shrink:
        0;

    padding:
        5px;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    border-radius:
        50%;

    background:
        white;

}


.logo-box img {

    width:
        100%;

    height:
        100%;

    object-fit:
        contain;

    border-radius:
        50%;

}


.brand h1 {

    color:
        white;

    font-size:
        24px;

}


.brand p {

    margin-top:
        5px;

    color:
        #e9f5ec;

    font-size:
        12px;

}


/* =========================================================
   RECEIPT TITLE
========================================================= */

.receipt-title {

    text-align:
        right;

}


.receipt-title h2 {

    color:
        white;

    font-size:
        31px;

}


.receipt-title p {

    margin-top:
        4px;

    color:
        #e5f3e8;

    font-size:
        13px;

}


/* =========================================================
   BODY
========================================================= */

.receipt-body {

    padding:
        32px;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:
        inline-block;

    margin-bottom:
        27px;

    padding:
        8px 16px;

    border-radius:
        25px;

    background:
        #ddf5e4;

    color:
        #08752d;

    font-size:
        13px;

    font-weight:
        700;

}


/* =========================================================
   GRID
========================================================= */

.details-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap:
        32px;

}


/* =========================================================
   SECTIONS
========================================================= */

.receipt-section {

    margin-bottom:
        30px;

}


.receipt-section h3 {

    margin-bottom:
        15px;

    padding-bottom:
        8px;

    border-bottom:
        1px solid #dddddd;

    color:
        #08752d;

    font-size:
        17px;

}


/* =========================================================
   ROWS
========================================================= */

.detail-row {

    margin:
        11px 0;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap:
        25px;

    font-size:
        13px;

}


.detail-label {

    color:
        #444444;

    font-weight:
        600;

}


.detail-value {

    text-align:
        right;

    color:
        #222222;

    overflow-wrap:
        anywhere;

}


/* =========================================================
   PAYMENT TOTAL
========================================================= */

.amount-box {

    margin-top:
        8px;

    padding:
        23px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    border-left:
        5px solid #08752d;

    border-radius:
        11px;

    background:
        #edf8f0;

    color:
        #08752d;

}


.amount-box span:first-child {

    font-size:
        17px;

    font-weight:
        600;

}


.amount-box strong {

    font-size:
        26px;

}


/* =========================================================
   COMPANY
========================================================= */

.company-info {

    margin-top:
        30px;

    padding:
        22px;

    border-radius:
        12px;

    background:
        #f7f7f7;

}


.company-info h3 {

    margin-bottom:
        13px;

    color:
        #08752d;

}


.company-info p {

    margin:
        7px 0;

    font-size:
        13px;

}


/* =========================================================
   QR VERIFICATION
========================================================= */

.qr-section {

    margin-top:
        30px;

    padding-top:
        27px;

    display:
        flex;

    align-items:
        center;

    gap:
        26px;

    border-top:
        1px solid #dddddd;

}


.qr-section img {

    width:
        145px;

    height:
        145px;

    flex-shrink:
        0;

    padding:
        6px;

    border:
        1px solid #dddddd;

    border-radius:
        10px;

    background:
        white;

}


.qr-text {

    flex:
        1;

}


.qr-text > strong {

    color:
        #08752d;

    font-size:
        17px;

}


.qr-text p {

    margin-top:
        8px;

    color:
        #555555;

    font-size:
        12px;

    line-height:
        1.7;

}


.verification-link {

    margin-top:
        10px;

    display:
        inline-block;

    color:
        #08752d;

    font-size:
        11px;

    text-decoration:
        none;

    overflow-wrap:
        anywhere;

}


/* =========================================================
   FOOTER
========================================================= */

.receipt-footer {

    margin-top:
        30px;

    padding-top:
        22px;

    border-top:
        1px solid #dddddd;

    text-align:
        center;

    color:
        #666666;

    font-size:
        12px;

}


.receipt-footer p {

    margin:
        5px 0;

}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    margin-top:
        22px;

    display:
        flex;

    justify-content:
        center;

    gap:
        12px;

    flex-wrap:
        wrap;

}


.actions button,
.actions a {

    padding:
        12px 21px;

    border:
        none;

    border-radius:
        8px;

    background:
        #08752d;

    color:
        white;

    font-family:
        inherit;

    font-size:
        13px;

    font-weight:
        600;

    text-decoration:
        none;

    cursor:
        pointer;

    transition:
        0.25s ease;

}


.actions button:hover,
.actions a:hover {

    background:
        #04551e;

    transform:
        translateY(-2px);

}


.actions .secondary-action {

    border:
        1px solid #08752d;

    background:
        white;

    color:
        #08752d;

}


.actions .secondary-action:hover {

    background:
        #edf7ef;

    color:
        #04551e;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width:
    700px
) {

    body {

        padding:
            15px 10px;

    }


    .receipt-header {

        flex-direction:
            column;

        text-align:
            center;

    }


    .brand {

        flex-direction:
            column;

        text-align:
            center;

    }


    .receipt-title {

        text-align:
            center;

    }


    .receipt-body {

        padding:
            24px 18px;

    }


    .details-grid {

        grid-template-columns:
            1fr;

        gap:
            0;

    }


    .detail-row {

        flex-direction:
            column;

        gap:
            3px;

    }


    .detail-value {

        text-align:
            left;

    }


    .amount-box {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .qr-section {

        flex-direction:
            column;

        text-align:
            center;

    }

}


/* =========================================================
   PRINT / SAVE PDF
========================================================= */

@media print {

    @page {

        margin:
            12mm;

    }


    body {

        padding:
            0;

        background:
            white;

    }


    .receipt-wrapper {

        width:
            100%;

        max-width:
            none;

    }


    .receipt {

        border-radius:
            0;

        box-shadow:
            none;

    }


    .actions {

        display:
            none;

    }


    .verification-link {

        color:
            #222222;

    }

}

    </style>

</head>


<body>


<div class="receipt-wrapper">


    <!-- =================================================
         RECEIPT
    ================================================== -->

    <article class="receipt">


        <!-- =============================================
             HEADER
        ============================================== -->

        <header class="receipt-header">


            <div class="brand">


                <div class="logo-box">

                    <img
                        src="images/Wildlife Sprinter Tours & Safaris.png"
                        alt="Sprinter Tours & Safaris Logo"
                    >

                </div>


                <div>

                    <h1>
                        SPRINTER TOURS & SAFARIS
                    </h1>

                    <p>
                        Safaris • Tours • Travel Experiences
                    </p>

                </div>


            </div>



            <div class="receipt-title">

                <h2>
                    RECEIPT
                </h2>

                <p>

                    <?php

                    echo htmlspecialchars(
                        $receipt_number
                    );

                    ?>

                </p>

            </div>


        </header>



        <!-- =============================================
             RECEIPT BODY
        ============================================== -->

        <div class="receipt-body">


            <span class="status">
                ✓ PAYMENT CONFIRMED
            </span>



            <!-- =========================================
                 CUSTOMER + BOOKING
            ========================================== -->

            <div class="details-grid">


                <!-- CUSTOMER -->

                <section class="receipt-section">


                    <h3>
                        Customer Details
                    </h3>


                    <div class="detail-row">

                        <span class="detail-label">
                            Name
                        </span>

                        <span class="detail-value">

                            <?php

                            echo htmlspecialchars(
                                $booking["name"]
                            );

                            ?>

                        </span>

                    </div>



                    <div class="detail-row">

                        <span class="detail-label">
                            Email
                        </span>

                        <span class="detail-value">

                            <?php

                            echo htmlspecialchars(
                                $booking["email"]
                            );

                            ?>

                        </span>

                    </div>



                    <?php
                    if (
                        !empty(
                            $booking["phone"]
                        )
                    ):
                    ?>

                        <div class="detail-row">

                            <span class="detail-label">
                                Phone
                            </span>

                            <span class="detail-value">

                                <?php

                                echo htmlspecialchars(
                                    $booking["phone"]
                                );

                                ?>

                            </span>

                        </div>

                    <?php endif; ?>


                </section>



                <!-- BOOKING -->

                <section class="receipt-section">


                    <h3>
                        Booking Details
                    </h3>


                    <div class="detail-row">

                        <span class="detail-label">
                            Booking Number
                        </span>

                        <span class="detail-value">

                            #<?php
                            echo (int) $booking["id"];
                            ?>

                        </span>

                    </div>



                    <div class="detail-row">

                        <span class="detail-label">
                            Tour / Package
                        </span>

                        <span class="detail-value">

                            <?php

                            echo htmlspecialchars(
                                $tour_name
                            );

                            ?>

                        </span>

                    </div>



                    <div class="detail-row">

                        <span class="detail-label">
                            Tour Date
                        </span>

                        <span class="detail-value">

                            <?php

                            echo htmlspecialchars(
                                $booking["date"]
                            );

                            ?>

                        </span>

                    </div>



                    <div class="detail-row">

                        <span class="detail-label">
                            Tour Time
                        </span>

                        <span class="detail-value">

                            <?php

                            echo htmlspecialchars(
                                $booking["time"]
                            );

                            ?>

                        </span>

                    </div>


                </section>


            </div>



            <!-- =========================================
                 PAYMENT DETAILS
            ========================================== -->

            <section class="receipt-section">


                <h3>
                    Payment Details
                </h3>


                <div class="detail-row">

                    <span class="detail-label">
                        Payment Method
                    </span>

                    <span class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $booking["payment"]
                        );

                        ?>

                    </span>

                </div>



                <div class="detail-row">

                    <span class="detail-label">
                        Transaction Reference
                    </span>

                    <span class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $reference
                        );

                        ?>

                    </span>

                </div>



                <div class="detail-row">

                    <span class="detail-label">
                        Payment Status
                    </span>

                    <span class="detail-value">
                        Paid
                    </span>

                </div>



                <div class="detail-row">

                    <span class="detail-label">
                        Receipt Number
                    </span>

                    <span class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $receipt_number
                        );

                        ?>

                    </span>

                </div>



                <div class="detail-row">

                    <span class="detail-label">
                        Receipt Date
                    </span>

                    <span class="detail-value">

                        <?php

                        echo htmlspecialchars(
                            $payment_date
                        );

                        ?>

                    </span>

                </div>


            </section>



            <!-- =========================================
                 TOTAL
            ========================================== -->

            <div class="amount-box">

                <span>
                    Total Paid
                </span>

                <strong>

                    KES

                    <?php

                    echo number_format(
                        (float) $booking["amount"],
                        2
                    );

                    ?>

                </strong>

            </div>



            <!-- =========================================
                 COMPANY DETAILS
            ========================================== -->

            <section class="company-info">


                <h3>
                    Company Details
                </h3>


                <p>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $business_name
                        );

                        ?>

                    </strong>

                </p>



                <?php if (!empty($business_phone)): ?>

                    <p>

                        <strong>
                            Phone / WhatsApp:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $business_phone
                        );

                        ?>

                    </p>

                <?php endif; ?>



                <?php if (!empty($business_email)): ?>

                    <p>

                        <strong>
                            Email:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $business_email
                        );

                        ?>

                    </p>

                <?php endif; ?>



                <?php if (!empty($business_address)): ?>

                    <p>

                        <strong>
                            Location:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $business_address
                        );

                        ?>

                    </p>

                <?php endif; ?>


            </section>



            <!-- =========================================
                 QR VERIFICATION
            ========================================== -->

            <section class="qr-section">


                <img
                    src="<?php
                        echo htmlspecialchars(
                            $qr_url
                        );
                    ?>"
                    alt="Receipt Verification QR Code"
                >


                <div class="qr-text">


                    <strong>
                        Verify This Receipt
                    </strong>


                    <p>

                        Scan this QR code with a phone
                        to confirm that this receipt
                        belongs to a genuine paid booking
                        in the Sprinter Tours & Safaris
                        system.

                    </p>


                    <p>

                        Receipt:

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $receipt_number
                            );

                            ?>

                        </strong>

                    </p>


                    <a
                        href="<?php
                            echo htmlspecialchars(
                                $verification_url
                            );
                        ?>"
                        class="verification-link"
                        target="_blank"
                        rel="noopener noreferrer"
                    >

                        Verify receipt online

                    </a>


                </div>


            </section>



            <!-- =========================================
                 FOOTER
            ========================================== -->

            <footer class="receipt-footer">


                <p>

                    Thank you for booking with
                    Sprinter Tours & Safaris.

                </p>


                <p>

                    Please keep this receipt as proof
                    of payment and booking confirmation.

                </p>


                <p>

                    This receipt is computer generated.

                </p>


            </footer>


        </div>


    </article>



    <!-- =================================================
         ACTIONS
    ================================================== -->

    <div class="actions">


        <button
            type="button"
            onclick="window.print()"
        >

            Print / Save PDF

        </button>


        <a
            href="my_bookings.php"
            class="secondary-action"
        >

            Back to My Bookings

        </a>


    </div>


</div>


</body>

</html>