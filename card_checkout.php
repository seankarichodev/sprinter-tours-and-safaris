<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   SECURE CARD CHECKOUT
========================================================= */

session_start();

require_once __DIR__ . "/db.php";


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
            date,
            time,
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

    die(
        "Unable to load payment information."
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
   VERIFY PAYMENT METHOD
========================================================= */

if (
    $booking["payment"]
    !== "Card"
) {

    header(
        "Location: my_bookings.php"
    );

    exit();
}


/* =========================================================
   ALREADY PAID
========================================================= */

if (
    strtolower(
        trim(
            (string) $booking["payment_status"]
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
        content="Secure Visa and Mastercard payment for Sprinter Tours & Safaris bookings."
    >


    <title>
        Secure Card Payment | Sprinter Tours & Safaris
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


<body class="checkout-page">


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
     CHECKOUT HERO
===================================================== -->

<section class="checkout-hero">


    <div class="checkout-hero-content">


        <p class="checkout-kicker">
            SECURE CHECKOUT
        </p>


        <h1>
            Complete Your Payment
        </h1>


        <p>

            Review your booking before continuing
            to our secure card payment provider.

        </p>


    </div>


</section>



<!-- =====================================================
     CHECKOUT
===================================================== -->

<main class="checkout-main">


    <div class="checkout-layout">


        <!-- =================================================
             LEFT SIDE
        ================================================== -->

        <section class="checkout-card">


            <div class="checkout-heading">


                <div class="checkout-card-icon">
                    💳
                </div>


                <div>


                    <p class="section-label">
                        CARD PAYMENT
                    </p>


                    <h2>
                        Visa / Mastercard
                    </h2>


                </div>


            </div>



            <!-- =================================================
                 BOOKING INFORMATION
            ================================================== -->

            <div class="checkout-booking">


                <div class="checkout-reference">


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
                        $booking["tour_name"]
                        ?: "Tour Booking"
                    );

                    ?>

                </h3>



                <div class="checkout-details-grid">


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



            <!-- =================================================
                 TOTAL
            ================================================== -->

            <div class="checkout-total">


                <div>


                    <span>
                        Amount to Pay
                    </span>


                    <p>
                        Secure card payment
                    </p>


                </div>



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



            <!-- =================================================
                 PAYSTACK PAYMENT FORM
            ================================================== -->

            <form
                action="card_initialize.php"
                method="POST"
                class="checkout-payment-form"
                id="cardPaymentForm"
            >


                <input
                    type="hidden"
                    name="booking_id"
                    value="<?php
                    echo (int) $booking["id"];
                    ?>"
                >


                <button
                    type="submit"
                    class="checkout-pay-button"
                    id="cardPayButton"
                >


                    <span>
                        Continue to Secure Payment
                    </span>


                    <span>
                        →
                    </span>


                </button>


            </form>



            <a
                href="my_bookings.php"
                class="checkout-cancel"
            >
                ← Return to My Bookings
            </a>


        </section>



        <!-- =================================================
             SECURITY PANEL
        ================================================== -->

        <aside class="checkout-security">


            <div class="checkout-security-icon">
                🔒
            </div>


            <p class="section-label">
                SECURE PAYMENT
            </p>


            <h2>
                Your Payment Is Protected.
            </h2>


            <p>

                You will continue to the secure
                payment provider to enter your
                card information.

            </p>



            <div class="checkout-security-list">


                <div>


                    <span>
                        ✓
                    </span>


                    <p>
                        Secure hosted checkout
                    </p>


                </div>



                <div>


                    <span>
                        ✓
                    </span>


                    <p>

                        Your card details are not
                        entered on the Sprinter website.

                    </p>


                </div>



                <div>


                    <span>
                        ✓
                    </span>


                    <p>

                        Booking amount is verified
                        securely by the server.

                    </p>


                </div>



                <div>


                    <span>
                        ✓
                    </span>


                    <p>

                        Receipt available after
                        successful payment.

                    </p>


                </div>


            </div>



            <div class="checkout-provider">


                <span>
                    PAYMENT METHOD
                </span>


                <strong>
                    Visa • Mastercard
                </strong>


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



<!-- =====================================================
     MAIN WEBSITE SCRIPT
===================================================== -->

<script src="script.js"></script>



<!-- =====================================================
     CHECKOUT SCRIPT
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           PAYMENT FORM ELEMENTS
        ================================================= */

        const form =
            document.getElementById(
                "cardPaymentForm"
            );


        const button =
            document.getElementById(
                "cardPayButton"
            );



        /* =================================================
           RESET PAYMENT BUTTON

           This is important when Paystack returns through
           browser history/back-forward cache after cancel.
        ================================================= */

        function resetPaymentButton() {


            if (!button) {
                return;
            }


            button.disabled =
                false;


            button.classList.remove(
                "processing"
            );


            button.innerHTML = `

                <span>
                    Continue to Secure Payment
                </span>

                <span>
                    →
                </span>

            `;

        }



        /*
         * Normal initial page load.
         */

        resetPaymentButton();



        /*
         * Also reset when Brave restores this page after
         * leaving/cancelling Paystack.
         */

        window.addEventListener(
            "pageshow",
            function () {

                resetPaymentButton();

            }
        );



        /* =================================================
           PREVENT DOUBLE SUBMISSION
        ================================================= */

        if (
            form &&
            button
        ) {


            form.addEventListener(
                "submit",
                function () {


                    if (button.disabled) {
                        return;
                    }


                    button.disabled =
                        true;


                    button.classList.add(
                        "processing"
                    );


                    button.innerHTML = `

                        <span>
                            Connecting securely...
                        </span>

                        <span>
                            ⏳
                        </span>

                    `;


                }
            );


        }



        /* =================================================
           RESPONSIVE NAVIGATION
        ================================================= */

        const menuToggle =
            document.getElementById(
                "menuToggle"
            );


        const mainNav =
            document.getElementById(
                "mainNav"
            );


        if (
            menuToggle &&
            mainNav
        ) {


            /* =============================================
               OPEN / CLOSE
            ============================================= */

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



            /* =============================================
               CLOSE AFTER LINK CLICK
            ============================================= */

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



            /* =============================================
               RESET MENU WHEN RETURNING TO DESKTOP
            ============================================= */

            window.addEventListener(
                "resize",
                function () {


                    if (
                        window.innerWidth > 900
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


    }
);

</script>


</body>

</html>