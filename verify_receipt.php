<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   PUBLIC RECEIPT VERIFICATION

   IMPORTANT:
   THIS FILE NEVER CHANGES PAYMENT STATUS.
========================================================= */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/receipt_config.php";


/* =========================================================
   DEFAULT STATE
========================================================= */

$is_valid = false;

$message =
    "This receipt could not be verified.";

$booking = null;

$receipt_number = "";



/* =========================================================
   GET RECEIPT + SIGNATURE
========================================================= */

$receipt_number =
    trim(
        $_GET["receipt"]
        ?? ""
    );


$provided_signature =
    trim(
        $_GET["sig"]
        ?? ""
    );



/* =========================================================
   BASIC VALIDATION
========================================================= */

if (
    $receipt_number !== "" &&
    $provided_signature !== ""
) {


    /* =====================================================
       CHECK RECEIPT FORMAT

       Expected:
       STS-2026-000001
    ===================================================== */

    if (
        preg_match(
            '/^STS-(\d{4})-(\d{6})$/',
            $receipt_number,
            $matches
        )
    ) {


        $receipt_year =
            (int) $matches[1];


        $booking_id =
            (int) $matches[2];



        /* =================================================
           VERIFY SIGNATURE
        ================================================= */

        $expected_signature =
            hash_hmac(
                "sha256",
                $receipt_number,
                RECEIPT_VERIFY_SECRET
            );


        if (
            hash_equals(
                $expected_signature,
                $provided_signature
            )
        ) {


            /* =============================================
               GET PAID BOOKING

               PUBLIC PAGE:
               Only retrieve information we actually
               need to show publicly.
            ============================================= */

            $stmt =
                $conn->prepare(
                    "
                    SELECT
                        id,
                        tour_name,
                        date,
                        amount,
                        payment,
                        payment_status,
                        created_at
                    FROM bookings
                    WHERE id = ?
                    AND payment_status = 'Paid'
                    LIMIT 1
                    "
                );


            if ($stmt) {


                $stmt->bind_param(
                    "i",
                    $booking_id
                );


                $stmt->execute();


                $result =
                    $stmt->get_result();


                $booking =
                    $result->fetch_assoc();


                $stmt->close();



                /* =========================================
                   VERIFY RECEIPT YEAR
                ========================================= */

                if ($booking) {


                    $booking_year =
                        !empty(
                            $booking["created_at"]
                        )
                            ? (int) date(
                                "Y",
                                strtotime(
                                    $booking["created_at"]
                                )
                            )
                            : $receipt_year;



                    if (
                        $booking_year ===
                        $receipt_year
                    ) {

                        $is_valid =
                            true;


                        $message =
                            "This is a verified Sprinter Tours & Safaris receipt.";

                    }

                }

            }

        }

    }

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
        Receipt Verification | Sprinter Tours & Safaris
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

    min-height: 100vh;

    padding:
        35px 18px;

    display: flex;

    justify-content: center;

    align-items: center;

    font-family:
        "Poppins",
        sans-serif;

    background:

        linear-gradient(
            135deg,
            #043b18,
            #08752d
        );

    color:
        #181818;

}


/* =========================================================
   CARD
========================================================= */

.verify-card {

    width:
        min(
            620px,
            100%
        );

    overflow: hidden;

    border-radius:
        20px;

    background:
        white;

    box-shadow:
        0 20px 60px
        rgba(
            0,
            0,
            0,
            0.25
        );

}


/* =========================================================
   HEADER
========================================================= */

.verify-header {

    padding:
        30px;

    text-align: center;

    color: white;

    background:

        linear-gradient(
            135deg,
            #08752d,
            #043b18
        );

}


.verify-logo {

    width:
        85px;

    height:
        85px;

    margin:
        0 auto 15px;

    padding:
        5px;

    display: flex;

    justify-content: center;

    align-items: center;

    border-radius:
        50%;

    background:
        white;

}


.verify-logo img {

    width: 100%;

    height: 100%;

    object-fit:
        contain;

    border-radius:
        50%;

}


.verify-header h1 {

    font-size:
        22px;

}


.verify-header p {

    margin-top:
        5px;

    color:
        #dcf2e2;

    font-size:
        12px;

}


/* =========================================================
   CONTENT
========================================================= */

.verify-body {

    padding:
        35px;

    text-align:
        center;

}


.verify-icon {

    width:
        78px;

    height:
        78px;

    margin:
        0 auto 18px;

    display:
        flex;

    justify-content: center;

    align-items: center;

    border-radius:
        50%;

    font-size:
        33px;

}


.verify-icon.valid {

    background:
        #dcf5e3;

    color:
        #08752d;

}


.verify-icon.invalid {

    background:
        #ffe4e4;

    color:
        #b52a2a;

}


.verify-body h2 {

    margin-bottom:
        9px;

    font-size:
        28px;

}


.verify-message {

    max-width:
        470px;

    margin:
        0 auto 26px;

    color:
        #666666;

    font-size:
        14px;

}


/* =========================================================
   VERIFIED DETAILS
========================================================= */

.verification-details {

    margin-top:
        25px;

    padding:
        22px;

    border-radius:
        12px;

    background:
        #f5f8f5;

    text-align:
        left;

}


.verify-row {

    padding:
        11px 0;

    display:
        flex;

    justify-content:
        space-between;

    gap:
        25px;

    border-bottom:
        1px solid
        #e0e5e1;

}


.verify-row:last-child {

    border-bottom:
        none;

}


.verify-row span {

    color:
        #666666;

    font-size:
        12px;

}


.verify-row strong {

    max-width:
        60%;

    color:
        #222222;

    text-align:
        right;

    font-size:
        13px;

    overflow-wrap:
        anywhere;

}


.verify-paid {

    color:
        #08752d !important;

}


/* =========================================================
   PRIVACY
========================================================= */

.verify-privacy {

    margin-top:
        20px;

    padding:
        13px;

    border-radius:
        8px;

    background:
        #edf7ef;

    color:
        #4d6252;

    font-size:
        11px;

}


/* =========================================================
   BUTTON
========================================================= */

.verify-home {

    display:
        inline-block;

    margin-top:
        27px;

    padding:
        12px 22px;

    border-radius:
        8px;

    background:
        #08752d;

    color:
        white;

    text-decoration:
        none;

    font-size:
        13px;

    font-weight:
        600;

}


.verify-home:hover {

    background:
        #04551e;

}


/* =========================================================
   MOBILE
========================================================= */

@media (
    max-width:
    520px
) {

    body {

        padding:
            15px;

    }


    .verify-body {

        padding:
            28px 20px;

    }


    .verify-row {

        flex-direction:
            column;

        gap:
            3px;

    }


    .verify-row strong {

        max-width:
            100%;

        text-align:
            left;

    }

}

    </style>

</head>


<body>


<div class="verify-card">


    <!-- =================================================
         HEADER
    ================================================== -->

    <header class="verify-header">


        <div class="verify-logo">

            <img
                src="images/Wildlife Sprinter Tours & Safaris.png"
                alt="Sprinter Tours & Safaris Logo"
            >

        </div>


        <h1>
            SPRINTER TOURS & SAFARIS
        </h1>


        <p>
            Official Receipt Verification
        </p>


    </header>



    <!-- =================================================
         VERIFICATION RESULT
    ================================================== -->

    <main class="verify-body">


        <?php if ($is_valid): ?>


            <div class="verify-icon valid">
                ✓
            </div>


            <h2>
                Receipt Verified
            </h2>


            <p class="verify-message">

                <?php

                echo htmlspecialchars(
                    $message
                );

                ?>

            </p>



            <div class="verification-details">


                <div class="verify-row">

                    <span>
                        Receipt Number
                    </span>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $receipt_number
                        );

                        ?>

                    </strong>

                </div>



                <div class="verify-row">

                    <span>
                        Package
                    </span>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $booking["tour_name"]
                            ?: "Tour Booking"
                        );

                        ?>

                    </strong>

                </div>



                <div class="verify-row">

                    <span>
                        Tour Date
                    </span>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $booking["date"]
                        );

                        ?>

                    </strong>

                </div>



                <div class="verify-row">

                    <span>
                        Payment Method
                    </span>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $booking["payment"]
                        );

                        ?>

                    </strong>

                </div>



                <div class="verify-row">

                    <span>
                        Amount Paid
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



                <div class="verify-row">

                    <span>
                        Payment Status
                    </span>

                    <strong class="verify-paid">
                        ✓ Paid
                    </strong>

                </div>


            </div>



            <div class="verify-privacy">

                Customer personal information is
                intentionally hidden on this public
                verification page.

            </div>



        <?php else: ?>


            <div class="verify-icon invalid">
                ✕
            </div>


            <h2>
                Receipt Not Verified
            </h2>


            <p class="verify-message">

                We could not confirm this receipt
                as a valid paid Sprinter Tours &
                Safaris booking.

            </p>


            <div class="verify-privacy">

                Check that the verification link
                or QR code came directly from an
                official Sprinter receipt.

            </div>


        <?php endif; ?>



        <a
            href="index.html"
            class="verify-home"
        >
            Visit Sprinter Tours & Safaris
        </a>


    </main>


</div>


</body>

</html>