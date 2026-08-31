<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   CUSTOMER BOOKING PAGE
========================================================= */

session_start();

require_once __DIR__ . "/db.php";


/* =========================================================
   REQUIRE CUSTOMER LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    $package = trim(
        $_GET["package"] ?? ""
    );

    if ($package !== "") {

        header(
            "Location: auth.php?package="
            . urlencode($package)
        );

    } else {

        header(
            "Location: auth.php"
        );
    }

    exit();
}


$user_id = (int) $_SESSION["user_id"];

$message = "";


/* =========================================================
   GET LOGGED-IN CUSTOMER
========================================================= */

$stmt = $conn->prepare(
    "
    SELECT
        id,
        name,
        email
    FROM users
    WHERE id = ?
    LIMIT 1
    "
);


if (!$stmt) {

    die(
        "Unable to load your account."
    );
}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {

    session_destroy();

    header(
        "Location: auth.php"
    );

    exit();
}


/* =========================================================
   AVAILABLE PACKAGES

   IMPORTANT:
   Prices are defined on the SERVER.
========================================================= */

$packages = [


    "maasai-mara" => [

        "name" =>
            "Maasai Mara Safari",

        "description" =>
            "3 Days Safari Experience",

        "price" =>
            25000.00,

        "image" =>
            "images/maasai mara.jpg"

    ],


    "diani-beach" => [

        "name" =>
            "Diani Beach Tour",

        "description" =>
            "2 Days Beach Vacation",

        "price" =>
            18000.00,

        "image" =>
            "images/diani.jpg"

    ],


    "amboseli" => [

        "name" =>
            "Amboseli Safari",

        "description" =>
            "Mt. Kilimanjaro View Safari",

        "price" =>
            22000.00,

        "image" =>
            "images/amboseli-national-park.jpg"

    ],


    "naivasha" => [

        "name" =>
            "Naivasha Tour",

        "description" =>
            "Lake + Boat Ride",

        "price" =>
            15000.00,

        "image" =>
            "images/naivasha-waterbuck-750x563.jpg"

    ],


    "samburu" => [

        "name" =>
            "Samburu Safari",

        "description" =>
            "Wildlife Adventure",

        "price" =>
            27000.00,

        "image" =>
            "images/samburu.jpg"

    ],


    "tsavo-east" => [

        "name" =>
            "Tsavo East Tour",

        "description" =>
            "Red Elephants Safari",

        "price" =>
            24000.00,

        "image" =>
            "images/TSAVO.jpg"

    ]

];


/* =========================================================
   GET PACKAGE
========================================================= */

$package_code = trim(

    $_GET["package"]
    ?? $_POST["package_code"]
    ?? ""

);


$selected_package = null;


if (
    $package_code !== "" &&
    isset($packages[$package_code])
) {

    $selected_package =
        $packages[$package_code];
}


/* =========================================================
   CREATE BOOKING
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["book"])
) {


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


    $phone =
        trim(
            $_POST["phone"]
            ?? ""
        );


    /* =====================================================
       VALID PACKAGE
    ===================================================== */

    if (!$selected_package) {

        $message =
            "Please select a valid tour package.";

    } else {


        $tour_name =
            $selected_package["name"];


        $amount =
            (float) $selected_package["price"];


        $payment_status =
            "Pending";


        /* =================================================
           PAYMENT METHODS
        ================================================= */

        $allowedPayments = [

            "Mpesa",
            "Card"

        ];


        /* =================================================
           REQUIRED FIELDS
        ================================================= */

        if (
            $date === "" ||
            $time === "" ||
            $payment === ""
        ) {

            $message =
                "Please complete all booking details.";
        }


        /* =================================================
           PAYMENT VALIDATION
        ================================================= */

        elseif (
            !in_array(
                $payment,
                $allowedPayments,
                true
            )
        ) {

            $message =
                "Invalid payment method selected.";
        }


        /* =================================================
           DATE VALIDATION
        ================================================= */

        elseif (
            $date < date("Y-m-d")
        ) {

            $message =
                "Please select today or a future tour date.";
        }


        /* =================================================
           M-PESA PHONE REQUIRED
        ================================================= */

        elseif (
            $payment === "Mpesa" &&
            $phone === ""
        ) {

            $message =
                "Please enter your M-Pesa phone number.";
        }


        /* =================================================
           FORMAT M-PESA PHONE
        ================================================= */

        if (
            $message === "" &&
            $payment === "Mpesa"
        ) {


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


            if (
                !preg_match(
                    "/^254[17][0-9]{8}$/",
                    $phone
                )
            ) {

                $message =
                    "Please enter a valid Kenyan M-Pesa number.";
            }

        }


        /* =================================================
           NON M-PESA DOES NOT NEED PHONE
        ================================================= */

        if (
            $payment !== "Mpesa"
        ) {

            $phone = "";
        }


        /* =================================================
           SAVE BOOKING
        ================================================= */

        if (
            $message === ""
        ) {


            $insert =
                $conn->prepare(
                    "
                    INSERT INTO bookings
                    (
                        user_id,
                        name,
                        email,
                        tour_name,
                        date,
                        time,
                        payment,
                        phone,
                        amount,
                        payment_status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                    "
                );


            if (!$insert) {

                $message =
                    "Booking could not be created. Please try again.";

            } else {


                $insert->bind_param(
                    "isssssssds",

                    $user_id,
                    $user["name"],
                    $user["email"],
                    $tour_name,
                    $date,
                    $time,
                    $payment,
                    $phone,
                    $amount,
                    $payment_status
                );


                if (
                    $insert->execute()
                ) {


                    $insert->close();


                    header(
                        "Location: my_bookings.php?created=1"
                    );

                    exit();


                } else {


                    $message =
                        "Booking could not be created. Please try again.";


                    $insert->close();

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


    <meta
        name="description"
        content="Book your Sprinter Tours & Safaris travel experience securely online."
    >


    <title>
        Book Your Tour | Sprinter Tours & Safaris
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


<body class="booking-page">


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
            Preparing your booking...
        </p>

    </div>

</div>



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


    <!-- MOBILE MENU -->

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


    <!-- NAV -->

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

        <a
            href="booking.php"
            class="active"
        >
            Booking
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
     HERO
===================================================== -->

<section class="booking-hero">

    <div class="booking-hero-content">


        <p class="booking-kicker">
            YOUR JOURNEY STARTS HERE
        </p>


        <h1>
            Book Your Experience.
        </h1>


        <p>
            Choose your travel date,
            preferred time and payment method.
            We'll take care of the rest.
        </p>


    </div>

</section>



<!-- =====================================================
     BOOKING
===================================================== -->

<main class="booking-main">


<?php if (!$selected_package): ?>


    <!-- =================================================
         NO PACKAGE SELECTED
    ================================================== -->

    <section class="booking-no-package">


        <div class="booking-empty-icon">
            🌍
        </div>


        <p class="section-label">
            SELECT YOUR JOURNEY
        </p>


        <h2>
            No Package Selected
        </h2>


        <p>
            Choose one of our tour packages
            before creating your booking.
        </p>


        <a
            href="packages.html"
            class="book-btn"
        >
            Explore Tour Packages
        </a>


    </section>


<?php else: ?>


    <div class="booking-layout">


        <!-- =================================================
             PACKAGE SUMMARY
        ================================================== -->

        <aside class="booking-summary">


            <div class="booking-summary-image">


                <img
                    src="<?php
                        echo htmlspecialchars(
                            $selected_package["image"]
                        );
                    ?>"
                    alt="<?php
                        echo htmlspecialchars(
                            $selected_package["name"]
                        );
                    ?>"
                >


                <span class="booking-selected-badge">
                    SELECTED TOUR
                </span>


            </div>



            <div class="booking-summary-content">


                <p class="section-label">
                    YOUR PACKAGE
                </p>


                <h2>

                    <?php

                    echo htmlspecialchars(
                        $selected_package["name"]
                    );

                    ?>

                </h2>


                <p class="booking-package-description">

                    <?php

                    echo htmlspecialchars(
                        $selected_package["description"]
                    );

                    ?>

                </p>



                <div class="booking-price">


                    <span>
                        Package Price
                    </span>


                    <strong>

                        KES

                        <?php

                        echo number_format(
                            $selected_package["price"],
                            0
                        );

                        ?>

                    </strong>


                </div>



                <div class="booking-customer">


                    <div>

                        <span>
                            Traveller
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $user["name"]
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
                                $user["email"]
                            );

                            ?>

                        </strong>

                    </div>


                </div>



                <a
                    href="packages.html"
                    class="change-package-link"
                >
                    ← Choose Another Package
                </a>


            </div>


        </aside>



        <!-- =================================================
             BOOKING FORM
        ================================================== -->

        <section class="booking-form-card">


            <div class="booking-form-heading">


                <p class="section-label">
                    COMPLETE YOUR BOOKING
                </p>


                <h2>
                    Travel Details
                </h2>


                <p>
                    Confirm your date,
                    time and payment method.
                </p>


            </div>



            <!-- ERROR MESSAGE -->

            <?php if ($message !== ""): ?>


                <div
                    class="booking-message error"
                    role="alert"
                >

                    <?php

                    echo htmlspecialchars(
                        $message
                    );

                    ?>

                </div>


            <?php endif; ?>



            <form
                method="POST"
                class="booking-form"
            >


                <!-- PACKAGE -->

                <input
                    type="hidden"
                    name="package_code"
                    value="<?php
                        echo htmlspecialchars(
                            $package_code
                        );
                    ?>"
                >



                <!-- CUSTOMER -->

                <div class="booking-form-row">


                    <div class="booking-field">


                        <label for="bookingName">
                            Full Name
                        </label>


                        <input
                            type="text"
                            id="bookingName"
                            value="<?php
                                echo htmlspecialchars(
                                    $user["name"]
                                );
                            ?>"
                            disabled
                        >


                    </div>



                    <div class="booking-field">


                        <label for="bookingEmail">
                            Email Address
                        </label>


                        <input
                            type="email"
                            id="bookingEmail"
                            value="<?php
                                echo htmlspecialchars(
                                    $user["email"]
                                );
                            ?>"
                            disabled
                        >


                    </div>


                </div>



                <!-- DATE / TIME -->

                <div class="booking-form-row">


                    <div class="booking-field">


                        <label for="date">
                            Tour Date
                        </label>


                        <input
                            type="date"
                            id="date"
                            name="date"

                            min="<?php
                                echo date(
                                    "Y-m-d"
                                );
                            ?>"

                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["date"]
                                    ?? ""
                                );
                            ?>"

                            required
                        >


                    </div>



                    <div class="booking-field">


                        <label for="time">
                            Tour Time
                        </label>


                        <input
                            type="time"
                            id="time"
                            name="time"

                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["time"]
                                    ?? ""
                                );
                            ?>"

                            required
                        >


                    </div>


                </div>



                <!-- PAYMENT -->

                <div class="booking-field">


                    <label for="payment">
                        Payment Method
                    </label>


                    <select
                        id="payment"
                        name="payment"
                        required
                    >


                        <option value="">
                            Select Payment Method
                        </option>


                        <option
                            value="Mpesa"

                            <?php

                            if (
                                ($_POST["payment"] ?? "")
                                === "Mpesa"
                            ) {

                                echo "selected";
                            }

                            ?>
                        >
                            M-Pesa
                        </option>


                        <option
                            value="Card"

                            <?php

                            if (
                                ($_POST["payment"] ?? "")
                                === "Card"
                            ) {

                                echo "selected";
                            }

                            ?>
                        >
                            Visa / Mastercard
                        </option>


                        <option
                            value=""
                            disabled
                        >
                            PayPal — Coming Soon
                        </option>


                    </select>


                </div>



                <!-- M-PESA PHONE -->

                <div
                    class="booking-field mpesa-field"
                    id="phoneField"
                >


                    <label for="phone">
                        M-Pesa Phone Number
                    </label>


                    <input
                        type="tel"
                        id="phone"
                        name="phone"

                        value="<?php
                            echo htmlspecialchars(
                                $_POST["phone"]
                                ?? ""
                            );
                        ?>"

                        placeholder="254712345678"

                        inputmode="numeric"

                        autocomplete="tel"
                    >


                    <small>

                        Enter the Safaricom number
                        that should receive the
                        M-Pesa payment request.

                    </small>


                </div>



                <!-- PAYMENT DESCRIPTION -->

                <div
                    class="payment-method-info"
                    id="paymentInfo"
                >


                    <div class="payment-info-icon">
                        💳
                    </div>


                    <div>

                        <strong>
                            Choose a payment method
                        </strong>

                        <p>
                            Select M-Pesa or Visa / Mastercard
                            to continue.
                        </p>

                    </div>


                </div>



                <!-- TOTAL -->

                <div class="booking-total">


                    <div>

                        <span>
                            Tour Package
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $selected_package["name"]
                            );

                            ?>

                        </strong>

                    </div>



                    <div class="booking-total-price">

                        <span>
                            Total
                        </span>

                        <strong>

                            KES

                            <?php

                            echo number_format(
                                $selected_package["price"],
                                0
                            );

                            ?>

                        </strong>

                    </div>


                </div>



                <!-- SUBMIT -->

                <button
                    type="submit"
                    name="book"
                    class="booking-submit"
                >
                    Create Booking
                </button>



                <p class="booking-security-note">

                    🔒 Your package price is verified
                    securely by our booking system.

                </p>


            </form>


        </section>


    </div>


<?php endif; ?>


</main>



<!-- =====================================================
     HELP
===================================================== -->

<section class="booking-help">


    <p class="section-label">
        NEED ASSISTANCE?
    </p>


    <h2>
        Need Help With Your Booking?
    </h2>


    <p>
        Contact our team if you have questions
        about your package, travel date or
        payment options.
    </p>


    <a
        href="contact.php"
        class="about-secondary-btn"
    >
        Contact Us
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
     WEBSITE SCRIPT
===================================================== -->

<script src="script.js"></script>



<!-- =====================================================
     BOOKING PAGE JAVASCRIPT
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           ELEMENTS
        ================================================= */

        const paymentSelect =
            document.getElementById(
                "payment"
            );


        const phoneField =
            document.getElementById(
                "phoneField"
            );


        const phoneInput =
            document.getElementById(
                "phone"
            );


        const paymentInfo =
            document.getElementById(
                "paymentInfo"
            );


        const menuToggle =
            document.getElementById(
                "menuToggle"
            );


        const mainNav =
            document.getElementById(
                "mainNav"
            );



        /* =================================================
           PAYMENT UI
        ================================================= */

        function updatePaymentUI() {


            if (!paymentSelect) {
                return;
            }


            const method =
                paymentSelect.value;



            /* M-PESA PHONE */

            if (
                phoneField &&
                phoneInput
            ) {


                if (
                    method === "Mpesa"
                ) {

                    phoneField.style.display =
                        "block";

                    phoneInput.required =
                        true;


                } else {

                    phoneField.style.display =
                        "none";

                    phoneInput.required =
                        false;

                }

            }



            if (!paymentInfo) {
                return;
            }



            /* M-PESA */

            if (
                method === "Mpesa"
            ) {

                paymentInfo.innerHTML = `

                    <div class="payment-info-icon">
                        📱
                    </div>

                    <div>

                        <strong>
                            M-Pesa
                        </strong>

                        <p>
                            Create your booking first,
                            then continue from My Bookings
                            to complete your M-Pesa payment.
                        </p>

                    </div>

                `;

            }


            /* CARD */

            else if (
                method === "Card"
            ) {

                paymentInfo.innerHTML = `

                    <div class="payment-info-icon">
                        💳
                    </div>

                    <div>

                        <strong>
                            Visa / Mastercard
                        </strong>

                        <p>
                            Create your booking first,
                            then continue to secure
                            card payment.
                        </p>

                    </div>

                `;

            }


            /* =================================================
               PAYPAL
               Standby only. The option is disabled in the form
               until the business PayPal account is configured.
            ================================================= */


            /* NONE */

            else {

                paymentInfo.innerHTML = `

                    <div class="payment-info-icon">
                        💳
                    </div>

                    <div>

                        <strong>
                            Choose a payment method
                        </strong>

                        <p>
                            Select M-Pesa or Visa / Mastercard
                            to continue.
                        </p>

                    </div>

                `;

            }

        }



        if (paymentSelect) {

            paymentSelect.addEventListener(
                "change",
                updatePaymentUI
            );

            updatePaymentUI();

        }



        /* =================================================
           MOBILE NAVIGATION
        ================================================= */

        if (
            menuToggle &&
            mainNav
        ) {


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