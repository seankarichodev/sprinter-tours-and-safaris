<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   CUSTOMER AUTHENTICATION
========================================================= */

session_start();

require_once __DIR__ . "/db.php";


/* =========================================================
   INITIAL VALUES
========================================================= */

$message = "";
$message_type = "error";


/*
 * If the customer came here while trying
 * to book a particular package, preserve it.
 */

$package = trim(
    $_GET["package"]
    ?? $_POST["package"]
    ?? ""
);


/* =========================================================
   ALREADY LOGGED IN
========================================================= */

if (isset($_SESSION["user_id"])) {

    if ($package !== "") {

        header(
            "Location: booking.php?package="
            . urlencode($package)
        );

    } else {

        header(
            "Location: my_bookings.php"
        );
    }

    exit();
}


/* =========================================================
   LOGIN
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["login"])
) {

    $email = trim(
        $_POST["email"] ?? ""
    );

    $password =
        $_POST["password"] ?? "";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        $email === "" ||
        $password === ""
    ) {

        $message =
            "Please enter your email and password.";

        $message_type =
            "error";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

        $message_type =
            "error";

    } else {


        /* =================================================
           FIND CUSTOMER
        ================================================= */

        $stmt = $conn->prepare(
            "
            SELECT
                id,
                name,
                email,
                password
            FROM users
            WHERE email = ?
            LIMIT 1
            "
        );


        if (!$stmt) {

            $message =
                "Unable to process login right now.";

            $message_type =
                "error";

        } else {


            $stmt->bind_param(
                "s",
                $email
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            if (
                $result->num_rows === 1
            ) {

                $user =
                    $result->fetch_assoc();


                if (
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {

                    /*
                     * Prevent session fixation.
                     */

                    session_regenerate_id(true);


                    $_SESSION["user_id"] =
                        (int) $user["id"];


                    $_SESSION["user_name"] =
                        $user["name"];


                    $_SESSION["user_email"] =
                        $user["email"];


                    $stmt->close();


                    /*
                     * If the customer originally
                     * clicked a package, send them
                     * back to that package.
                     */

                    if ($package !== "") {

                        header(
                            "Location: booking.php?package="
                            . urlencode($package)
                        );

                    } else {

                        header(
                            "Location: my_bookings.php"
                        );
                    }


                    exit();
                }

            }


            /*
             * Keep one generic error message.
             * This avoids revealing whether a
             * particular email exists.
             */

            $message =
                "Incorrect email or password.";

            $message_type =
                "error";


            $stmt->close();

        }

    }

}


/* =========================================================
   REGISTRATION SUCCESS
========================================================= */

if (
    isset($_GET["registered"]) &&
    $message === ""
) {

    $message =
        "Account created successfully. You can now log in.";

    $message_type =
        "success";
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
        content="Sign in to your Sprinter Tours & Safaris customer account to manage bookings, payments and receipts."
    >


    <title>
        Customer Login | Sprinter Tours & Safaris
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


<body class="auth-page">


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
            Preparing your account...
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



    <!-- NAVIGATION LINKS -->

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

        <a href="contact.php">
            Contact
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
     CUSTOMER LOGIN AREA
===================================================== -->

<main class="auth-main">


    <!-- =================================================
         LEFT SIDE
    ================================================== -->

    <section class="auth-showcase">


        <div class="auth-showcase-overlay">


            <div class="auth-showcase-content">


                <p class="auth-kicker">
                    YOUR JOURNEY CONTINUES
                </p>


                <h1>
                    Welcome Back.
                </h1>


                <p>
                    Sign in to manage your bookings,
                    continue payments and access
                    your travel information.
                </p>



                <div class="auth-benefits">


                    <div class="auth-benefit">

                        <span>
                            ✓
                        </span>

                        <p>
                            Manage your tour bookings
                        </p>

                    </div>



                    <div class="auth-benefit">

                        <span>
                            ✓
                        </span>

                        <p>
                            Track payment status
                        </p>

                    </div>



                    <div class="auth-benefit">

                        <span>
                            ✓
                        </span>

                        <p>
                            Access your receipts
                        </p>

                    </div>



                    <div class="auth-benefit">

                        <span>
                            ✓
                        </span>

                        <p>
                            Book future adventures faster
                        </p>

                    </div>


                </div>


            </div>


        </div>


    </section>



    <!-- =================================================
         LOGIN FORM SIDE
    ================================================== -->

    <section class="auth-form-side">


        <div class="auth-card">


            <div class="auth-card-heading">


                <p class="section-label">
                    CUSTOMER ACCOUNT
                </p>


                <h2>
                    Sign In
                </h2>


                <p>
                    Enter your details to continue.
                </p>


            </div>



            <!-- =================================================
                 MESSAGE
            ================================================== -->

            <?php if ($message !== ""): ?>


                <div
                    class="auth-message <?php
                        echo htmlspecialchars(
                            $message_type
                        );
                    ?>"
                    role="alert"
                >

                    <?php

                    echo htmlspecialchars(
                        $message
                    );

                    ?>

                </div>


            <?php endif; ?>



            <!-- =================================================
                 LOGIN FORM
            ================================================== -->

            <form
                method="POST"
                action=""
                class="auth-form"
            >


                <!-- PRESERVE PACKAGE -->

                <?php if ($package !== ""): ?>

                    <input
                        type="hidden"
                        name="package"
                        value="<?php
                            echo htmlspecialchars(
                                $package
                            );
                        ?>"
                    >

                <?php endif; ?>



                <!-- EMAIL -->

                <div class="auth-field">


                    <label for="email">
                        Email Address
                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"

                        placeholder="you@example.com"

                        value="<?php
                            echo htmlspecialchars(
                                $_POST["email"]
                                ?? ""
                            );
                        ?>"

                        autocomplete="email"

                        maxlength="150"

                        required
                    >


                </div>



                <!-- PASSWORD -->

                <div class="auth-field">


                    <label for="password">
                        Password
                    </label>


                    <div class="password-wrapper">


                        <input
                            type="password"
                            id="password"
                            name="password"

                            placeholder="Enter your password"

                            autocomplete="current-password"

                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >
                            Show
                        </button>


                    </div>


                </div>



                <!-- SIGN IN -->

                <button
                    type="submit"
                    name="login"
                    class="auth-submit"
                >
                    Sign In
                </button>


            </form>



            <!-- =================================================
                 REGISTER
            ================================================== -->

            <div class="auth-divider">

                <span>
                    New to Sprinter?
                </span>

            </div>



            <p class="auth-register">

                Don't have an account?


                <?php if ($package !== ""): ?>

                    <a
                        href="signup.php?package=<?php
                            echo urlencode(
                                $package
                            );
                        ?>"
                    >
                        Create Account
                    </a>

                <?php else: ?>

                    <a href="signup.php">
                        Create Account
                    </a>

                <?php endif; ?>


            </p>



            <!-- BACK -->

            <div class="auth-back">

                <a href="packages.html">
                    ← Back to Tour Packages
                </a>

            </div>


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
     MAIN WEBSITE JAVASCRIPT
===================================================== -->

<script src="script.js"></script>



<!-- =====================================================
     AUTH PAGE JAVASCRIPT
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           PASSWORD SHOW / HIDE
        ================================================= */

        const password =
            document.getElementById(
                "password"
            );


        const passwordToggle =
            document.getElementById(
                "passwordToggle"
            );


        if (
            password &&
            passwordToggle
        ) {


            passwordToggle.addEventListener(
                "click",
                function () {


                    const hidden =
                        password.type ===
                        "password";


                    password.type =
                        hidden
                            ? "text"
                            : "password";


                    passwordToggle.textContent =
                        hidden
                            ? "Hide"
                            : "Show";


                    passwordToggle.setAttribute(
                        "aria-label",
                        hidden
                            ? "Hide password"
                            : "Show password"
                    );


                }
            );


        }



        /* =================================================
           MOBILE NAVIGATION
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


            /* OPEN / CLOSE */

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



            /* CLOSE AFTER CLICKING LINK */

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



            /* RESET WHEN RETURNING TO DESKTOP */

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