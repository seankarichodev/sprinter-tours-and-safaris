<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   M-PESA CHECKOUT
========================================================= */

session_start();

require_once __DIR__ . "/db.php";


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
   GET CUSTOMER BOOKING
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            name,
            email,
            tour_name,
            phone,
            amount,
            payment,
            payment_status,
            date,
            time
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    error_log(
        "M-Pesa checkout database error: "
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


$result =
    $stmt->get_result();


$booking =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   BOOKING MUST EXIST
========================================================= */

if (!$booking) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   VERIFY PAYMENT METHOD
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
   ALREADY PAID
========================================================= */

if (
    strtolower(
        trim(
            $booking["payment_status"]
        )
    )
    === "paid"
) {

    header(
        "Location: receipt.php?id="
        . $booking_id
    );

    exit();

}


/* =========================================================
   VALIDATE AMOUNT
========================================================= */

$amount =
    (float) $booking["amount"];


if ($amount <= 0) {

    error_log(
        "Invalid M-Pesa booking amount. Booking ID: "
        . $booking_id
    );

    header(
        "Location: my_bookings.php?payment_error=amount"
    );

    exit();

}


/* =========================================================
   DEFAULT PHONE
========================================================= */

$phone =
    trim(
        (string) (
            $booking["phone"]
            ?? ""
        )
    );


$tour_name =
    !empty(
        $booking["tour_name"]
    )
        ? $booking["tour_name"]
        : "Tour Booking";

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
        M-Pesa Payment | Sprinter Tours & Safaris
    </title>


    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body class="mpesa-checkout-page">


<!-- =====================================================
     NAVIGATION
===================================================== -->

<header id="navbar">

    <h1>
        SPRINTER TOURS & SAFARIS
    </h1>


    <nav>

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

        <a href="logout.php">
            Logout
        </a>


        <button
            id="darkToggle"
            type="button"
            aria-label="Toggle dark mode"
        >
            🌙
        </button>

    </nav>

</header>



<!-- =====================================================
     HERO
===================================================== -->

<section class="mpesa-checkout-hero">


    <div class="mpesa-checkout-hero-content">


        <p class="mpesa-checkout-kicker">
            SECURE MOBILE PAYMENT
        </p>


        <h2>
            Pay with M-Pesa
        </h2>


        <p>

            Confirm your booking and enter the
            Safaricom number that should receive
            the M-Pesa payment request.

        </p>


    </div>


</section>



<!-- =====================================================
     CHECKOUT
===================================================== -->

<main class="mpesa-checkout-main">


    <div class="mpesa-checkout-layout">


        <!-- =============================================
             BOOKING / PAYMENT
        ============================================== -->

        <section class="mpesa-payment-card">


            <div class="mpesa-payment-heading">


                <div class="mpesa-icon">
                    M
                </div>


                <div>

                    <p class="mpesa-section-label">
                        M-PESA PAYMENT
                    </p>

                    <h2>
                        Complete Your Payment
                    </h2>

                </div>


            </div>



            <!-- =========================================
                 BOOKING REFERENCE
            ========================================== -->

            <div class="mpesa-booking-box">


                <div class="mpesa-reference">


                    <span>
                        Booking Reference
                    </span>


                    <strong>

                        #<?php
                        echo (int) $booking["id"];
                        ?>

                    </strong>


                </div>



                <h3>

                    <?php

                    echo htmlspecialchars(
                        $tour_name
                    );

                    ?>

                </h3>



                <div class="mpesa-details-grid">


                    <div>

                        <span>
                            Traveller
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $booking["name"]
                            );

                            ?>

                        </strong>

                    </div>



                    <div>

                        <span>
                            Email
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $booking["email"]
                            );

                            ?>

                        </strong>

                    </div>



                    <div>

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



                    <div>

                        <span>
                            Tour Time
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $booking["time"]
                            );

                            ?>

                        </strong>

                    </div>


                </div>


            </div>



            <!-- =========================================
                 TOTAL
            ========================================== -->

            <div class="mpesa-total">


                <div>

                    <span>
                        Amount to Pay
                    </span>

                    <p>
                        M-Pesa payment
                    </p>

                </div>


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



            <!-- =========================================
                 M-PESA FORM
            ========================================== -->

            <form
                action="mpesa_stk.php"
                method="POST"
                class="mpesa-payment-form"
                id="mpesaPaymentForm"
            >


                <input
                    type="hidden"
                    name="booking_id"
                    value="<?php
                    echo (int) $booking["id"];
                    ?>"
                >



                <div class="mpesa-phone-group">


                    <label for="phone">

                        M-Pesa Phone Number

                    </label>


                    <div class="mpesa-phone-input">


                        <span>
                            +254
                        </span>


                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="<?php
                            echo htmlspecialchars(
                                $phone
                            );
                            ?>"
                            placeholder="2547XXXXXXXX"
                            inputmode="numeric"
                            autocomplete="tel"
                            maxlength="13"
                            required
                        >


                    </div>


                    <small>

                        Enter the Safaricom number
                        that should receive the
                        payment request.

                    </small>


                </div>



                <button
                    type="submit"
                    class="mpesa-pay-button"
                    id="mpesaPayButton"
                >


                    <span>

                        Pay KES

                        <?php

                        echo number_format(
                            $amount,
                            2
                        );

                        ?>

                    </span>


                    <span>
                        →
                    </span>


                </button>


            </form>



            <a
                href="my_bookings.php"
                class="mpesa-cancel"
            >

                ← Return to My Bookings

            </a>


        </section>



        <!-- =============================================
             HOW IT WORKS
        ============================================== -->

        <aside class="mpesa-security-card">


            <div class="mpesa-security-icon">
                🔒
            </div>


            <p class="mpesa-section-label">
                SECURE CHECKOUT
            </p>


            <h2>
                How M-Pesa Payment Works
            </h2>


            <p class="mpesa-security-intro">

                After you continue, an M-Pesa
                payment request will be sent to
                the phone number you entered.

            </p>



            <div class="mpesa-steps">


                <div class="mpesa-step">

                    <span>
                        1
                    </span>

                    <div>

                        <strong>
                            Confirm your number
                        </strong>

                        <p>
                            Enter the Safaricom number
                            you want to use for payment.
                        </p>

                    </div>

                </div>



                <div class="mpesa-step">

                    <span>
                        2
                    </span>

                    <div>

                        <strong>
                            Check your phone
                        </strong>

                        <p>
                            A payment prompt will be
                            sent to your phone.
                        </p>

                    </div>

                </div>



                <div class="mpesa-step">

                    <span>
                        3
                    </span>

                    <div>

                        <strong>
                            Complete payment
                        </strong>

                        <p>
                            Follow the M-Pesa prompt
                            on your phone to authorize
                            the payment.
                        </p>

                    </div>

                </div>



                <div class="mpesa-step">

                    <span>
                        4
                    </span>

                    <div>

                        <strong>
                            Booking confirmation
                        </strong>

                        <p>
                            Your booking should only
                            be marked paid after the
                            payment provider confirms
                            the transaction.
                        </p>

                    </div>

                </div>


            </div>



            <div class="mpesa-security-note">

                <span>
                    🔐
                </span>

                <p>

                    Sprinter should never ask you
                    to enter your M-Pesa PIN directly
                    on this website.

                </p>

            </div>


        </aside>


    </div>


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



<script src="script.js"></script>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const form =
            document.getElementById(
                "mpesaPaymentForm"
            );


        const button =
            document.getElementById(
                "mpesaPayButton"
            );


        const phone =
            document.getElementById(
                "phone"
            );


        if (
            !form ||
            !button ||
            !phone
        ) {

            return;

        }



        /* =============================================
           CLEAN PHONE INPUT
        ============================================== */

        phone.addEventListener(
            "input",
            function () {

                this.value =
                    this.value.replace(
                        /[^0-9+]/g,
                        ""
                    );

            }
        );



        /* =============================================
           PREVENT DOUBLE SUBMISSION
        ============================================== */

        form.addEventListener(
            "submit",
            function () {


                button.disabled =
                    true;


                button.classList.add(
                    "processing"
                );


                button.innerHTML = `

                    <span>
                        Sending M-Pesa request...
                    </span>

                    <span>
                        ⏳
                    </span>

                `;


            }
        );


    }
);

</script>


</body>

</html>