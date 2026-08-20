<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   MY BOOKINGS
========================================================= */

session_start();

require_once __DIR__ . "/db.php";


/* =========================================================
   REQUIRE LOGIN
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
   GET CUSTOMER BOOKINGS
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            tour_name,
            date,
            time,
            payment,
            phone,
            amount,
            payment_status
        FROM bookings
        WHERE user_id = ?
        ORDER BY id DESC
        "
    );


if (!$stmt) {

    die(
        "Unable to load your bookings."
    );
}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();

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
        content="View your Sprinter Tours & Safaris bookings, payment status and travel receipts."
    >


    <title>
        My Bookings | Sprinter Tours & Safaris
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


<body class="bookings-page">


<!-- =====================================================
     LOADER
===================================================== -->

<div
    id="loader"
    aria-hidden="true"
>

    <div class="loader-content">


        <img
            src="images/Wildlife Sprinter Tours & Safaris.png"
            class="loader-logo"
            alt="Sprinter Tours & Safaris Logo"
        >


        <h2>
            SPRINTER TOURS & SAFARIS
        </h2>


        <div class="spinner"></div>


        <p>
            Loading your bookings...
        </p>


    </div>

</div>



<!-- =====================================================
     RESPONSIVE NAVIGATION
===================================================== -->

<header id="navbar">


    <!-- BRAND -->

    <a
        href="index.html"
        class="site-brand"
        aria-label="Sprinter Tours and Safaris home"
    >

        <span>
            SPRINTER TOURS & SAFARIS
        </span>

    </a>



    <!-- MOBILE HAMBURGER -->

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



    <!-- NAVIGATION -->

    <nav
        id="mainNav"
        class="main-nav"
    >

        <a href="index.html">
            Home
        </a>

        <a href="about.html">
            About
        </a>

        <a href="destinations.html">
            Destinations
        </a>

        <a href="packages.html">
            Packages
        </a>

        <a href="gallery.html">
            Gallery
        </a>

        <a href="booking.php">
            Booking
        </a>

        <a
            href="my_bookings.php"
            class="active"
        >
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
     HERO
===================================================== -->

<section class="bookings-hero">


    <div class="bookings-hero-content">


        <p class="bookings-kicker">
            YOUR TRAVEL DASHBOARD
        </p>


        <h1>
            My Bookings
        </h1>


        <p>

            View your upcoming tours,
            track payment status and access
            receipts for completed payments.

        </p>


        <a
            href="packages.html"
            class="hero-button"
        >
            Book Another Tour
        </a>


    </div>


</section>



<!-- =====================================================
     BOOKING DASHBOARD
===================================================== -->

<main class="bookings-main">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="bookings-header">


        <div>


            <p class="section-label">
                YOUR JOURNEYS
            </p>


            <h2>
                Booking History
            </h2>


        </div>



        <a
            href="packages.html"
            class="bookings-new-btn"
        >
            + New Booking
        </a>


    </div>



    <!-- =================================================
         BOOKING CREATED MESSAGE
    ================================================== -->

    <?php if (isset($_GET["created"])): ?>


        <div
            class="bookings-message success"
            role="status"
        >

            ✓ Your booking was created successfully.
            You can now complete payment below.

        </div>


    <?php endif; ?>



    <!-- =================================================
         PAYMENT SUCCESS MESSAGE
    ================================================== -->

    <?php if (isset($_GET["payment"]) && $_GET["payment"] === "success"): ?>


        <div
            class="bookings-message success"
            role="status"
        >

            ✓ Payment completed successfully.
            Your booking status has been updated.

        </div>


    <?php endif; ?>



    <!-- =================================================
         BOOKINGS
    ================================================== -->

    <?php if ($result->num_rows > 0): ?>


        <div class="bookings-grid">


            <?php while ($row = $result->fetch_assoc()): ?>


                <?php

                $status =
                    $row["payment_status"]
                    ?? "Pending";


                $is_paid =
                    strtolower($status)
                    === "paid";

                ?>


                <article class="booking-dashboard-card">


                    <!-- =====================================
                         TOP
                    ====================================== -->

                    <div class="booking-card-top">


                        <div>


                            <span class="booking-reference">

                                Booking #

                                <?php

                                echo (int) $row["id"];

                                ?>

                            </span>


                            <h3>

                                <?php

                                echo htmlspecialchars(
                                    $row["tour_name"]
                                    ?: "Tour Booking"
                                );

                                ?>

                            </h3>


                        </div>



                        <span
                            class="
                                booking-status
                                <?php

                                echo $is_paid
                                    ? "status-paid"
                                    : "status-pending";

                                ?>
                            "
                        >

                            <?php

                            echo $is_paid
                                ? "Paid"
                                : "Pending";

                            ?>

                        </span>


                    </div>



                    <!-- =====================================
                         DETAILS
                    ====================================== -->

                    <div class="booking-card-details">


                        <!-- DATE -->

                        <div class="booking-detail">


                            <span>
                                Tour Date
                            </span>


                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $row["date"]
                                );

                                ?>

                            </strong>


                        </div>



                        <!-- TIME -->

                        <div class="booking-detail">


                            <span>
                                Tour Time
                            </span>


                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $row["time"]
                                );

                                ?>

                            </strong>


                        </div>



                        <!-- PAYMENT METHOD -->

                        <div class="booking-detail">


                            <span>
                                Payment Method
                            </span>


                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $row["payment"]
                                );

                                ?>

                            </strong>


                        </div>



                        <!-- AMOUNT -->

                        <div class="booking-detail">


                            <span>
                                Amount
                            </span>


                            <strong>

                                KES

                                <?php

                                echo number_format(
                                    (float) $row["amount"],
                                    0
                                );

                                ?>

                            </strong>


                        </div>


                    </div>



                    <!-- =====================================
                         PAYMENT SUMMARY
                    ====================================== -->

                    <div class="booking-payment-summary">


                        <div>


                            <span>
                                Payment Status
                            </span>


                            <strong
                                class="<?php

                                echo $is_paid
                                    ? "payment-paid-text"
                                    : "payment-pending-text";

                                ?>"
                            >

                                <?php

                                echo $is_paid
                                    ? "Payment Complete"
                                    : "Payment Required";

                                ?>

                            </strong>


                        </div>



                        <div class="booking-payment-total">


                            <span>
                                Total
                            </span>


                            <strong>

                                KES

                                <?php

                                echo number_format(
                                    (float) $row["amount"],
                                    0
                                );

                                ?>

                            </strong>


                        </div>


                    </div>



                    <!-- =====================================
                         ACTIONS
                    ====================================== -->

                    <div class="booking-card-actions">


                        <?php if (!$is_paid): ?>


                            <a
                                href="pay.php?id=<?php

                                echo (int) $row["id"];

                                ?>"
                                class="booking-pay-btn"
                            >
                                Pay Now
                            </a>


                        <?php else: ?>


                            <a
                                href="receipt.php?id=<?php

                                echo (int) $row["id"];

                                ?>"
                                class="booking-receipt-btn"
                            >
                                View Receipt
                            </a>


                        <?php endif; ?>



                        <a
                            href="contact.php"
                            class="booking-help-btn"
                        >
                            Need Help?
                        </a>


                    </div>


                </article>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <!-- =================================================
             EMPTY STATE
        ================================================== -->

        <section class="bookings-empty">


            <div class="bookings-empty-icon">
                🧭
            </div>


            <h2>
                No Bookings Yet
            </h2>


            <p>

                Your future journeys will appear
                here once you choose and book
                a tour package.

            </p>


            <a
                href="packages.html"
                class="book-btn"
            >
                Explore Packages
            </a>


        </section>


    <?php endif; ?>


</main>



<!-- =====================================================
     CTA
===================================================== -->

<section class="bookings-cta">


    <p class="section-label">
        WHERE NEXT?
    </p>


    <h2>
        Your Next Adventure Is Waiting.
    </h2>


    <p>

        Browse more destinations
        and discover another journey
        worth remembering.

    </p>


    <a
        href="packages.html"
        class="about-primary-btn"
    >
        Explore Packages
    </a>


</section>



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
     BACK TO TOP
===================================================== -->

<button
    id="topBtn"
    type="button"
    aria-label="Back to top"
>
    ↑
</button>



<!-- =====================================================
     MAIN WEBSITE SCRIPT
===================================================== -->

<script src="script.js"></script>



<!-- =====================================================
     MOBILE NAVIGATION SCRIPT
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



        /* =================================================
           OPEN / CLOSE MOBILE MENU
        ================================================= */

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



        /* =================================================
           CLOSE AFTER LINK CLICK
        ================================================= */

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



        /* =================================================
           RESET ON DESKTOP
        ================================================= */

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
);

</script>


</body>

</html>

<?php

$stmt->close();

?>

?>