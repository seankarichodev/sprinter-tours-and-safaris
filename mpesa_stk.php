<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   M-PESA STK PUSH
========================================================= */


/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE + M-PESA CONFIG
========================================================= */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mpesa_config.php";


/* =========================================================
   REQUIRED CONFIGURATION
========================================================= */

$requiredConstants = [

    "MPESA_CONSUMER_KEY",
    "MPESA_CONSUMER_SECRET",
    "MPESA_SHORTCODE",
    "MPESA_PASSKEY",
    "MPESA_CALLBACK_URL",
    "MPESA_OAUTH_URL",
    "MPESA_STK_URL"

];


foreach ($requiredConstants as $constant) {

    if (
        !defined($constant) ||
        trim(
            (string) constant($constant)
        ) === ""
    ) {

        error_log(
            "Sprinter M-Pesa configuration missing: "
            . $constant
        );

        header(
            "Location: my_bookings.php?payment_error=config"
        );

        exit();
    }
}


/* =========================================================
   REQUIRE CUSTOMER LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header(
        "Location: auth.php"
    );

    exit();
}


$user_id =
    (int) $_SESSION["user_id"];


/* =========================================================
   REQUIRE POST
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"]
    !== "POST"
) {

    header(
        "Location: my_bookings.php"
    );

    exit();
}


/* =========================================================
   BOOKING ID
========================================================= */

$booking_id =
    filter_input(
        INPUT_POST,
        "booking_id",
        FILTER_VALIDATE_INT
    );


if (
    !$booking_id ||
    $booking_id < 1
) {

    header(
        "Location: my_bookings.php?payment_error=booking"
    );

    exit();
}


/* =========================================================
   PHONE NUMBER
========================================================= */

$phone =
    trim(
        $_POST["phone"]
        ?? ""
    );


$phone =
    preg_replace(
        "/\D/",
        "",
        $phone
    );


/*
 * 0712345678
 * → 254712345678
 */

if (
    str_starts_with(
        $phone,
        "0"
    )
) {

    $phone =
        "254"
        . substr(
            $phone,
            1
        );
}


/*
 * 712345678
 * → 254712345678
 *
 * 112345678
 * → 254112345678
 */

if (
    str_starts_with(
        $phone,
        "7"
    ) ||
    str_starts_with(
        $phone,
        "1"
    )
) {

    $phone =
        "254"
        . $phone;
}


/* =========================================================
   VALIDATE KENYAN MOBILE NUMBER
========================================================= */

if (
    !preg_match(
        "/^254[17][0-9]{8}$/",
        $phone
    )
) {

    header(
        "Location: my_bookings.php?payment_error=phone"
    );

    exit();
}


/* =========================================================
   GET BOOKING

   IMPORTANT:
   Amount is retrieved from the database.
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            tour_name,
            amount,
            payment,
            payment_status
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    error_log(
        "M-Pesa booking query prepare error: "
        . $conn->error
    );

    header(
        "Location: my_bookings.php?payment_error=database"
    );

    exit();
}


$stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);


$stmt->execute();


$booking =
    $stmt
        ->get_result()
        ->fetch_assoc();


$stmt->close();


/* =========================================================
   BOOKING MUST EXIST
========================================================= */

if (!$booking) {

    header(
        "Location: my_bookings.php?payment_error=booking"
    );

    exit();
}


/* =========================================================
   MUST BE M-PESA
========================================================= */

if (
    $booking["payment"]
    !== "Mpesa"
) {

    header(
        "Location: my_bookings.php?payment_error=method"
    );

    exit();
}


/* =========================================================
   CURRENT STATUS
========================================================= */

$currentStatus =
    strtolower(
        trim(
            (string) (
                $booking["payment_status"]
                ?? "Pending"
            )
        )
    );


/* =========================================================
   ALREADY PAID
========================================================= */

if ($currentStatus === "paid") {

    header(
        "Location: receipt.php?id="
        . $booking_id
    );

    exit();
}


/* =========================================================
   ALLOWED RETRY STATES

   Customers can retry after:
   - Pending
   - Cancelled
   - Failed
   - TimedOut
========================================================= */

$allowedStatuses = [

    "pending",
    "cancelled",
    "failed",
    "timedout",
    "timed out"

];


if (
    !in_array(
        $currentStatus,
        $allowedStatuses,
        true
    )
) {

    header(
        "Location: my_bookings.php?payment_error=status"
    );

    exit();
}


/* =========================================================
   AMOUNT

   Never trust browser amount.
========================================================= */

$amount =
    (int) round(
        (float) $booking["amount"]
    );


if ($amount < 1) {

    error_log(
        "Invalid M-Pesa amount for booking "
        . $booking_id
    );

    header(
        "Location: my_bookings.php?payment_error=amount"
    );

    exit();
}


/* =========================================================
   TOUR NAME
========================================================= */

$tour_name =
    !empty(
        $booking["tour_name"]
    )
        ? $booking["tour_name"]
        : "Sprinter Tour";


/* =========================================================
   GET DARAJA OAUTH TOKEN
========================================================= */

$credentials =
    base64_encode(
        MPESA_CONSUMER_KEY
        . ":"
        . MPESA_CONSUMER_SECRET
    );


$curl =
    curl_init();


if ($curl === false) {

    error_log(
        "Unable to initialize cURL for M-Pesa OAuth."
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();
}


curl_setopt_array(
    $curl,
    [

        CURLOPT_URL =>
            MPESA_OAUTH_URL,

        CURLOPT_HTTPHEADER => [

            "Authorization: Basic "
            . $credentials,

            "Accept: application/json"

        ],

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_CONNECTTIMEOUT =>
            10,

        CURLOPT_TIMEOUT =>
            30

    ]
);


$oauthResponse =
    curl_exec($curl);


$oauthError =
    curl_error($curl);


$oauthHttpCode =
    curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


curl_close($curl);


/* =========================================================
   OAUTH CONNECTION ERROR
========================================================= */

if ($oauthResponse === false) {

    error_log(
        "M-Pesa OAuth connection error: "
        . $oauthError
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();
}


/* =========================================================
   DECODE OAUTH RESPONSE
========================================================= */

$oauthData =
    json_decode(
        $oauthResponse,
        true
    );


if (
    !is_array($oauthData) ||
    $oauthHttpCode < 200 ||
    $oauthHttpCode >= 300 ||
    empty(
        $oauthData["access_token"]
    )
) {

    error_log(
        "M-Pesa OAuth failed. HTTP: "
        . $oauthHttpCode
    );

    header(
        "Location: my_bookings.php?payment_error=oauth"
    );

    exit();
}


$accessToken =
    $oauthData["access_token"];


/* =========================================================
   CREATE STK PASSWORD
========================================================= */

$timestamp =
    date(
        "YmdHis"
    );


$password =
    base64_encode(
        MPESA_SHORTCODE
        . MPESA_PASSKEY
        . $timestamp
    );


/* =========================================================
   STK PAYLOAD
========================================================= */

$payload = [

    "BusinessShortCode" =>
        MPESA_SHORTCODE,

    "Password" =>
        $password,

    "Timestamp" =>
        $timestamp,

    "TransactionType" =>
        "CustomerPayBillOnline",

    "Amount" =>
        $amount,

    "PartyA" =>
        $phone,

    "PartyB" =>
        MPESA_SHORTCODE,

    "PhoneNumber" =>
        $phone,

    "CallBackURL" =>
        MPESA_CALLBACK_URL,

    "AccountReference" =>
        "BOOKING-" . $booking_id,

    "TransactionDesc" =>
        "Sprinter Tours Booking"

];


/* =========================================================
   SEND STK PUSH
========================================================= */

$curl =
    curl_init();


if ($curl === false) {

    error_log(
        "Unable to initialize cURL for M-Pesa STK."
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();
}


curl_setopt_array(
    $curl,
    [

        CURLOPT_URL =>
            MPESA_STK_URL,

        CURLOPT_HTTPHEADER => [

            "Authorization: Bearer "
            . $accessToken,

            "Content-Type: application/json",

            "Accept: application/json"

        ],

        CURLOPT_POST =>
            true,

        CURLOPT_POSTFIELDS =>
            json_encode(
                $payload
            ),

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_CONNECTTIMEOUT =>
            10,

        CURLOPT_TIMEOUT =>
            30

    ]
);


$stkResponse =
    curl_exec($curl);


$stkError =
    curl_error($curl);


$stkHttpCode =
    curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


curl_close($curl);


/* =========================================================
   CONNECTION ERROR
========================================================= */

if ($stkResponse === false) {

    error_log(
        "M-Pesa STK connection error: "
        . $stkError
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();
}


/* =========================================================
   DECODE STK RESPONSE
========================================================= */

$stkData =
    json_decode(
        $stkResponse,
        true
    );


if (!is_array($stkData)) {

    error_log(
        "Invalid M-Pesa STK JSON response."
    );

    header(
        "Location: my_bookings.php?payment_error=provider"
    );

    exit();
}


/* =========================================================
   STK REQUEST ACCEPTED
========================================================= */

if (
    $stkHttpCode >= 200 &&
    $stkHttpCode < 300 &&
    !empty(
        $stkData["MerchantRequestID"]
    ) &&
    !empty(
        $stkData["CheckoutRequestID"]
    )
) {

    $merchantRequestId =
        trim(
            (string)
            $stkData["MerchantRequestID"]
        );


    $checkoutRequestId =
        trim(
            (string)
            $stkData["CheckoutRequestID"]
        );


    /* =====================================================
       SAVE NEW PAYMENT ATTEMPT

       IMPORTANT:
       A Cancelled / Failed / TimedOut booking may retry.

       The new accepted STK request becomes Pending again.
    ===================================================== */

    $update =
        $conn->prepare(
            "
            UPDATE bookings
            SET
                phone = ?,
                merchant_request_id = ?,
                checkout_request_id = ?,
                payment_status = 'Pending',
                mpesa_receipt = NULL
            WHERE id = ?
            AND user_id = ?
            AND payment = 'Mpesa'
            AND payment_status <> 'Paid'
            "
        );


    if (!$update) {

        error_log(
            "Unable to save M-Pesa request identifiers: "
            . $conn->error
        );

        header(
            "Location: my_bookings.php?payment_error=database"
        );

        exit();
    }


    $update->bind_param(
        "sssii",
        $phone,
        $merchantRequestId,
        $checkoutRequestId,
        $booking_id,
        $user_id
    );


    if (!$update->execute()) {

        error_log(
            "Unable to update booking after STK request: "
            . $update->error
        );

        $update->close();

        header(
            "Location: my_bookings.php?payment_error=database"
        );

        exit();
    }


    if (
        $update->affected_rows !== 1
    ) {

        error_log(
            "M-Pesa STK identifiers were not stored for booking "
            . $booking_id
        );

        $update->close();

        header(
            "Location: my_bookings.php?payment_error=database"
        );

        exit();
    }


    $update->close();

    ?>

<!DOCTYPE html>

<html lang="en">


<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Complete your Sprinter Tours & Safaris booking securely using M-Pesa."
    >

    <title>
        M-Pesa Payment | Sprinter Tours & Safaris
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body class="mpesa-status-page">


<!-- =====================================================
     RESPONSIVE NAVIGATION
===================================================== -->

<header id="navbar">


    <a
        href="index.html"
        class="site-brand"
        aria-label="Sprinter Tours and Safaris home"
    >

        <span>
            SPRINTER TOURS & SAFARIS
        </span>

    </a>


    <button
        id="menuToggle"
        class="menu-toggle"
        type="button"
        aria-label="Open navigation menu"
        aria-expanded="false"
        aria-controls="mainNav"
    >

        <span></span>
        <span></span>
        <span></span>

    </button>


    <nav
        id="mainNav"
        class="main-nav"
    >

        <a href="index.html">
            Home
        </a>

        <a href="packages.html">
            Packages
        </a>

        <a href="my_bookings.php">
            My Bookings
        </a>

        <a href="contact.php">
            Contact
        </a>

        <a
            href="logout.php"
            class="logout-link"
        >
            Logout
        </a>

        <button
            id="darkToggle"
            class="theme-toggle"
            type="button"
            aria-label="Toggle dark mode"
        >
            🌙
        </button>

    </nav>


</header>



<!-- =====================================================
     PAYMENT STATUS
===================================================== -->

<main class="mpesa-status-main">


    <section
        class="mpesa-status-card"
        id="mpesaStatusCard"
    >


        <!-- ICON -->

        <div
            class="mpesa-status-icon"
            id="mpesaStatusIcon"
        >
            📱
        </div>


        <!-- LABEL -->

        <p
            class="section-label"
            id="mpesaStatusLabel"
        >
            PAYMENT REQUEST SENT
        </p>


        <!-- HEADING -->

        <h1 id="mpesaStatusHeading">
            Check Your Phone
        </h1>


        <!-- MESSAGE -->

        <p
            class="mpesa-status-intro"
            id="mpesaStatusMessage"
        >

            An M-Pesa payment request
            has been sent to:

        </p>


        <!-- PHONE -->

        <strong
            class="mpesa-status-phone"
            id="mpesaStatusPhone"
        >

            <?php

            echo htmlspecialchars(
                $phone
            );

            ?>

        </strong>



        <!-- =================================================
             PAYMENT DETAILS
        ================================================== -->

        <div class="mpesa-status-details">


            <div>

                <span>
                    Booking
                </span>

                <strong>

                    #<?php

                    echo (int) $booking_id;

                    ?>

                </strong>

            </div>



            <div>

                <span>
                    Package
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $tour_name
                    );

                    ?>

                </strong>

            </div>



            <div>

                <span>
                    Amount
                </span>

                <strong>

                    KES

                    <?php

                    echo number_format(
                        $amount,
                        2
                    );

                    ?>

                </strong>

            </div>



            <div>

                <span>
                    Status
                </span>

                <strong
                    class="mpesa-waiting"
                    id="mpesaPaymentStatus"
                >
                    Awaiting Confirmation
                </strong>

            </div>


        </div>



        <!-- =================================================
             NOTE
        ================================================== -->

        <div
            class="mpesa-status-note"
            id="mpesaStatusNote"
        >

            <strong id="mpesaStatusNoteTitle">
                Important
            </strong>

            <p id="mpesaStatusNoteText">

                Your booking remains Pending
                until M-Pesa confirms a successful
                transaction.

            </p>

        </div>



        <!-- =================================================
             LIVE CHECK
        ================================================== -->

        <div
            class="mpesa-live-check"
            id="mpesaLiveCheck"
        >

            <span class="mpesa-live-dot"></span>

            <span>
                Checking payment status automatically...
            </span>

        </div>



        <!-- =================================================
             NORMAL ACTIONS
        ================================================== -->

        <div
            class="mpesa-status-actions"
            id="mpesaNormalActions"
        >

            <a
                href="my_bookings.php"
                class="mpesa-status-primary"
            >
                Check My Booking
            </a>

            <a
                href="contact.php"
                class="mpesa-status-secondary"
            >
                Need Help?
            </a>

        </div>



        <!-- =================================================
             RETRY ACTIONS
        ================================================== -->

        <div
            class="mpesa-status-actions"
            id="mpesaRetryActions"
            style="display:none;"
        >


            <form
                method="POST"
                action="mpesa_stk.php"
                style="margin:0;"
            >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?php
                    echo (int) $booking_id;
                    ?>"
                >

                <input
                    type="hidden"
                    name="phone"
                    value="<?php
                    echo htmlspecialchars(
                        $phone
                    );
                    ?>"
                >

                <button
                    type="submit"
                    class="mpesa-status-primary"
                >
                    Try Again
                </button>

            </form>


            <a
                href="my_bookings.php"
                class="mpesa-status-secondary"
            >
                My Bookings
            </a>


        </div>


    </section>


</main>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <p>
        © 2026 Sprinter Tours & Safaris.
        All Rights Reserved.
    </p>

</footer>



<!-- =====================================================
     PAYMENT STATUS JAVASCRIPT
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           ELEMENTS
        ================================================= */

        const paymentStatus =
            document.getElementById(
                "mpesaPaymentStatus"
            );


        const statusIcon =
            document.getElementById(
                "mpesaStatusIcon"
            );


        const statusLabel =
            document.getElementById(
                "mpesaStatusLabel"
            );


        const statusHeading =
            document.getElementById(
                "mpesaStatusHeading"
            );


        const statusMessage =
            document.getElementById(
                "mpesaStatusMessage"
            );


        const statusPhone =
            document.getElementById(
                "mpesaStatusPhone"
            );


        const statusNote =
            document.getElementById(
                "mpesaStatusNote"
            );


        const statusNoteTitle =
            document.getElementById(
                "mpesaStatusNoteTitle"
            );


        const statusNoteText =
            document.getElementById(
                "mpesaStatusNoteText"
            );


        const liveCheck =
            document.getElementById(
                "mpesaLiveCheck"
            );


        const normalActions =
            document.getElementById(
                "mpesaNormalActions"
            );


        const retryActions =
            document.getElementById(
                "mpesaRetryActions"
            );


        if (!paymentStatus) {
            return;
        }



        /* =================================================
           BOOKING
        ================================================= */

        const bookingId =
            <?php
            echo (int) $booking_id;
            ?>;



        /* =================================================
           POLLING
        ================================================= */

        let paymentChecker =
            null;


        let checking =
            false;


        let attempts =
            0;


        const maxAttempts =
            60;



        /* =================================================
           STOP CHECKER
        ================================================= */

        function stopChecker() {

            if (paymentChecker) {

                clearInterval(
                    paymentChecker
                );

                paymentChecker =
                    null;
            }
        }



        /* =================================================
           PAYMENT SUCCESS
        ================================================= */

        function showPaymentConfirmed() {


            stopChecker();


            paymentStatus.textContent =
                "Payment Confirmed ✓";


            paymentStatus.classList.remove(
                "mpesa-waiting"
            );


            paymentStatus.classList.add(
                "mpesa-paid"
            );


            if (statusIcon) {

                statusIcon.textContent =
                    "✓";

                statusIcon.classList.add(
                    "mpesa-success-icon"
                );
            }


            if (statusLabel) {

                statusLabel.textContent =
                    "PAYMENT CONFIRMED";
            }


            if (statusHeading) {

                statusHeading.textContent =
                    "Payment Successful";
            }


            if (statusMessage) {

                statusMessage.textContent =
                    "Your M-Pesa payment has been confirmed successfully.";
            }


            if (statusPhone) {

                statusPhone.style.display =
                    "none";
            }


            if (statusNoteTitle) {

                statusNoteTitle.textContent =
                    "Confirmed";
            }


            if (statusNote) {

                statusNote.classList.add(
                    "mpesa-confirmed-note"
                );
            }


            if (statusNoteText) {

                statusNoteText.textContent =
                    "Your booking is confirmed. Your receipt is being prepared.";
            }


            if (liveCheck) {

                liveCheck.innerHTML = `

                    <span>✓</span>

                    <span>
                        Payment verified successfully.
                    </span>

                `;

                liveCheck.classList.add(
                    "mpesa-live-success"
                );
            }


            if (retryActions) {

                retryActions.style.display =
                    "none";
            }


            /*
             * Briefly show success,
             * then open the receipt.
             */

            setTimeout(
                function () {

                    window.location.href =
                        "receipt.php?id="
                        + encodeURIComponent(
                            bookingId
                        );

                },
                2200
            );

        }



        /* =================================================
           FINAL FAILURE UI
        ================================================= */

        function showPaymentFailure(
            status,
            message
        ) {


            stopChecker();


            const normalized =
                String(status)
                    .toLowerCase();


            paymentStatus.classList.remove(
                "mpesa-waiting"
            );


            if (statusPhone) {

                statusPhone.style.display =
                    "none";
            }


            if (normalActions) {

                normalActions.style.display =
                    "none";
            }


            if (retryActions) {

                retryActions.style.display =
                    "flex";
            }



            /* =============================================
               CANCELLED
            ============================================= */

            if (
                normalized ===
                "cancelled"
            ) {

                paymentStatus.textContent =
                    "Payment Cancelled";

                if (statusIcon) {

                    statusIcon.textContent =
                        "✕";
                }

                if (statusLabel) {

                    statusLabel.textContent =
                        "PAYMENT CANCELLED";
                }

                if (statusHeading) {

                    statusHeading.textContent =
                        "Payment Cancelled";
                }

                if (statusMessage) {

                    statusMessage.textContent =
                        message ||
                        "The M-Pesa payment request was cancelled.";
                }

                if (statusNoteTitle) {

                    statusNoteTitle.textContent =
                        "No Payment Received";
                }

                if (statusNoteText) {

                    statusNoteText.textContent =
                        "Your booking was not marked as paid. You may safely try again.";
                }

            }


            /* =============================================
               TIMEOUT
            ============================================= */

            else if (
                normalized ===
                    "timedout" ||
                normalized ===
                    "timed out"
            ) {

                paymentStatus.textContent =
                    "Payment Timed Out";

                if (statusIcon) {

                    statusIcon.textContent =
                        "⌛";
                }

                if (statusLabel) {

                    statusLabel.textContent =
                        "PAYMENT TIMED OUT";
                }

                if (statusHeading) {

                    statusHeading.textContent =
                        "Request Timed Out";
                }

                if (statusMessage) {

                    statusMessage.textContent =
                        message ||
                        "The M-Pesa payment request expired before payment was completed.";
                }

                if (statusNoteTitle) {

                    statusNoteTitle.textContent =
                        "Try Again";
                }

                if (statusNoteText) {

                    statusNoteText.textContent =
                        "No successful payment was confirmed. You may start another M-Pesa request.";
                }

            }


            /* =============================================
               FAILED
            ============================================= */

            else {

                paymentStatus.textContent =
                    "Payment Failed";

                if (statusIcon) {

                    statusIcon.textContent =
                        "!";
                }

                if (statusLabel) {

                    statusLabel.textContent =
                        "PAYMENT FAILED";
                }

                if (statusHeading) {

                    statusHeading.textContent =
                        "Payment Could Not Be Completed";
                }

                if (statusMessage) {

                    statusMessage.textContent =
                        message ||
                        "The M-Pesa payment could not be completed.";
                }

                if (statusNoteTitle) {

                    statusNoteTitle.textContent =
                        "Payment Not Completed";
                }

                if (statusNoteText) {

                    statusNoteText.textContent =
                        "Your booking remains unpaid. You can safely try again.";
                }

            }


            if (liveCheck) {

                liveCheck.innerHTML = `

                    <span>ℹ️</span>

                    <span>
                        Automatic payment checking has stopped.
                    </span>

                `;
            }

        }



        /* =================================================
           CHECK DATABASE STATUS
        ================================================= */

        async function checkPaymentStatus() {


            if (checking) {
                return;
            }


            checking =
                true;


            attempts++;


            try {


                const response =
                    await fetch(

                        "check_payment_status.php?booking_id="
                        + encodeURIComponent(
                            bookingId
                        ),

                        {

                            method:
                                "GET",

                            cache:
                                "no-store",

                            credentials:
                                "same-origin",

                            headers: {

                                "Accept":
                                    "application/json"

                            }

                        }

                    );


                if (!response.ok) {

                    console.error(
                        "Payment status HTTP error:",
                        response.status
                    );

                    return;
                }


                const data =
                    await response.json();



                /* =========================================
                   PAID
                ========================================= */

                if (
                    data.success === true &&
                    data.paid === true
                ) {

                    showPaymentConfirmed();

                    return;
                }



                /* =========================================
                   FINAL NON-PAID STATUS
                ========================================= */

                if (
                    data.success === true &&
                    data.finished === true
                ) {

                    showPaymentFailure(
                        data.status,
                        data.message
                    );

                    return;
                }



                /* =========================================
                   LOCAL POLLING LIMIT
                ========================================= */

                if (
                    attempts >=
                    maxAttempts
                ) {


                    stopChecker();


                    paymentStatus.textContent =
                        "Confirmation Taking Longer";


                    if (statusHeading) {

                        statusHeading.textContent =
                            "Still Waiting for Confirmation";
                    }


                    if (statusMessage) {

                        statusMessage.textContent =
                            "M-Pesa has not returned a final payment result yet.";
                    }


                    if (liveCheck) {

                        liveCheck.innerHTML = `

                            <span>ℹ️</span>

                            <span>
                                You can safely check
                                My Bookings for the
                                latest payment status.
                            </span>

                        `;
                    }

                }


            } catch (error) {


                console.error(
                    "Payment status check failed:",
                    error
                );


            } finally {


                checking =
                    false;

            }

        }



        /* =================================================
           START CHECKING
        ================================================= */

        checkPaymentStatus();


        paymentChecker =
            setInterval(
                checkPaymentStatus,
                3000
            );



        /* =================================================
           STOP IF CUSTOMER LEAVES
        ================================================= */

        window.addEventListener(
            "beforeunload",
            function () {

                stopChecker();

            }
        );


    }
);

</script>



<!-- =====================================================
     MAIN WEBSITE SCRIPT
===================================================== -->

<script src="script.js"></script>



<!-- =====================================================
     MOBILE NAVIGATION
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const menuToggle =
            document.getElementById(
                "menuToggle"
            );


        const mainNav =
            document.getElementById(
                "mainNav"
            );


        if (
            !menuToggle ||
            !mainNav
        ) {

            return;

        }


        menuToggle.addEventListener(
            "click",
            function () {


                const open =
                    mainNav.classList.toggle(
                        "nav-open"
                    );


                menuToggle.classList.toggle(
                    "menu-open",
                    open
                );


                menuToggle.setAttribute(
                    "aria-expanded",
                    open
                        ? "true"
                        : "false"
                );


                document.body.classList.toggle(
                    "mobile-menu-open",
                    open
                );


            }
        );


        mainNav
            .querySelectorAll("a")
            .forEach(
                function (link) {

                    link.addEventListener(
                        "click",
                        function () {

                            mainNav.classList.remove(
                                "nav-open"
                            );

                            menuToggle.classList.remove(
                                "menu-open"
                            );

                            menuToggle.setAttribute(
                                "aria-expanded",
                                "false"
                            );

                            document.body.classList.remove(
                                "mobile-menu-open"
                            );

                        }
                    );

                }
            );


        window.addEventListener(
            "resize",
            function () {


                if (
                    window.innerWidth >
                    900
                ) {

                    mainNav.classList.remove(
                        "nav-open"
                    );

                    menuToggle.classList.remove(
                        "menu-open"
                    );

                    menuToggle.setAttribute(
                        "aria-expanded",
                        "false"
                    );

                    document.body.classList.remove(
                        "mobile-menu-open"
                    );

                }

            }
        );


    }
);

</script>


</body>

</html>

<?php

    exit();
}


/* =========================================================
   STK REQUEST FAILED BEFORE PHONE PROMPT
========================================================= */

$provider_message =
    $stkData["errorMessage"]
    ?? $stkData["ResponseDescription"]
    ?? "Unknown M-Pesa STK error";


error_log(

    "M-Pesa STK request failed. "
    . "Booking ID: "
    . $booking_id
    . " | HTTP: "
    . $stkHttpCode
    . " | Message: "
    . $provider_message

);


header(
    "Location: my_bookings.php?payment_error=provider"
);

exit();

?>