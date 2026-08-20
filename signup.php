<?php

require_once "db.php";


/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$message_type = "error";


/* =========================================================
   SIGNUP
========================================================= */

if (isset($_POST["signup"])) {

    $name =
        trim($_POST["name"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $password =
        $_POST["password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message =
            "Please fill in all fields.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

    } elseif (
        strlen($password) < 8
    ) {

        $message =
            "Your password must contain at least 8 characters.";

    } elseif (
        $password !== $confirm_password
    ) {

        $message =
            "The passwords do not match.";

    } else {


        /* =================================================
           CHECK IF EMAIL EXISTS
        ================================================= */

        $check =
            $conn->prepare(
                "
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
                "
            );


        if (!$check) {

            $message =
                "Unable to create your account right now.";

        } else {

            $check->bind_param(
                "s",
                $email
            );


            $check->execute();


            $result =
                $check->get_result();


            if (
                $result->num_rows > 0
            ) {

                $message =
                    "An account with that email already exists.";

            } else {


                /* =========================================
                   HASH PASSWORD
                ========================================= */

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /* =========================================
                   CREATE USER
                ========================================= */

                $stmt =
                    $conn->prepare(
                        "
                        INSERT INTO users
                        (
                            name,
                            email,
                            password
                        )
                        VALUES (?, ?, ?)
                        "
                    );


                if (!$stmt) {

                    $message =
                        "Unable to create your account right now.";

                } else {

                    $stmt->bind_param(
                        "sss",
                        $name,
                        $email,
                        $hashedPassword
                    );


                    if (
                        $stmt->execute()
                    ) {

                        $stmt->close();

                        $check->close();


                        header(
                            "Location: auth.php?registered=1"
                        );

                        exit();

                    } else {

                        $message =
                            "Signup failed. Please try again.";

                    }


                    $stmt->close();

                }

            }


            $check->close();

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
        Create Account | Sprinter Tours & Safaris
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


<body class="auth-page">


<!-- =====================================================
     LOADER
===================================================== -->

<div id="loader">

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
            Preparing your journey...
        </p>

    </div>

</div>



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
            type="button"
            aria-label="Toggle dark mode"
        >
            🌙
        </button>

    </nav>

</header>



<!-- =====================================================
     SIGNUP AREA
===================================================== -->

<main class="auth-main signup-main">


    <!-- LEFT -->

    <section class="auth-showcase signup-showcase">

        <div class="auth-showcase-overlay">

            <div class="auth-showcase-content">


                <p class="auth-kicker">
                    YOUR NEXT ADVENTURE STARTS HERE
                </p>


                <h2>
                    Travel Further.
                </h2>


                <p>

                    Create your Sprinter account
                    and make planning your next
                    journey easier.

                </p>



                <div class="auth-benefits">


                    <div class="auth-benefit">

                        <span>✓</span>

                        <p>
                            Book tours online
                        </p>

                    </div>


                    <div class="auth-benefit">

                        <span>✓</span>

                        <p>
                            Manage your bookings
                        </p>

                    </div>


                    <div class="auth-benefit">

                        <span>✓</span>

                        <p>
                            Track your payment status
                        </p>

                    </div>


                    <div class="auth-benefit">

                        <span>✓</span>

                        <p>
                            Access your travel receipts
                        </p>

                    </div>


                </div>

            </div>

        </div>

    </section>



    <!-- RIGHT -->

    <section class="auth-form-side">

        <div class="auth-card signup-card">


            <div class="auth-card-heading">

                <p class="section-label">
                    JOIN SPRINTER
                </p>

                <h2>
                    Create Account
                </h2>

                <p>
                    Enter your details to get started.
                </p>

            </div>



            <!-- ERROR MESSAGE -->

            <?php if (!empty($message)): ?>

                <div class="auth-message error">

                    <?php
                    echo htmlspecialchars(
                        $message
                    );
                    ?>

                </div>

            <?php endif; ?>



            <!-- SIGNUP FORM -->

            <form
                method="POST"
                action=""
                class="auth-form"
            >


                <!-- NAME -->

                <div class="auth-field">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
                        autocomplete="name"
                        required
                    >

                </div>



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
                        value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                        autocomplete="email"
                        required
                    >

                </div>



                <!-- PASSWORD -->

                <div class="auth-field">

                    <label for="signupPassword">
                        Password
                    </label>


                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="signupPassword"
                            name="password"
                            placeholder="Minimum 8 characters"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="signupPasswordToggle"
                            aria-label="Show password"
                        >
                            Show
                        </button>

                    </div>


                    <div class="password-strength">

                        <div
                            class="password-strength-bar"
                            id="passwordStrengthBar"
                        ></div>

                    </div>


                    <small
                        class="password-hint"
                        id="passwordHint"
                    >
                        Use at least 8 characters.
                    </small>

                </div>



                <!-- CONFIRM PASSWORD -->

                <div class="auth-field">

                    <label for="confirmPassword">
                        Confirm Password
                    </label>


                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="confirmPassword"
                            name="confirm_password"
                            placeholder="Enter your password again"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="confirmPasswordToggle"
                            aria-label="Show confirmed password"
                        >
                            Show
                        </button>

                    </div>


                    <small
                        class="password-match"
                        id="passwordMatch"
                    ></small>

                </div>



                <!-- SUBMIT -->

                <button
                    type="submit"
                    name="signup"
                    class="auth-submit"
                >
                    Create My Account
                </button>


            </form>



            <!-- LOGIN -->

            <div class="auth-divider">

                <span>
                    Already travelling with us?
                </span>

            </div>


            <p class="auth-register">

                Already have an account?

                <a href="auth.php">
                    Sign In
                </a>

            </p>



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



<script src="script.js"></script>


<script>

/* =========================================================
   SIGNUP PASSWORD FEATURES
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const password =
            document.getElementById(
                "signupPassword"
            );


        const confirmPassword =
            document.getElementById(
                "confirmPassword"
            );


        const passwordToggle =
            document.getElementById(
                "signupPasswordToggle"
            );


        const confirmToggle =
            document.getElementById(
                "confirmPasswordToggle"
            );


        const strengthBar =
            document.getElementById(
                "passwordStrengthBar"
            );


        const passwordHint =
            document.getElementById(
                "passwordHint"
            );


        const passwordMatch =
            document.getElementById(
                "passwordMatch"
            );



        /* =============================================
           SHOW / HIDE PASSWORD
        ============================================= */

        function setupPasswordToggle(
            input,
            button
        ) {

            if (
                !input ||
                !button
            ) {

                return;

            }


            button.addEventListener(
                "click",
                function () {

                    const hidden =
                        input.type ===
                        "password";


                    input.type =
                        hidden
                            ? "text"
                            : "password";


                    button.textContent =
                        hidden
                            ? "Hide"
                            : "Show";

                }
            );

        }


        setupPasswordToggle(
            password,
            passwordToggle
        );


        setupPasswordToggle(
            confirmPassword,
            confirmToggle
        );



        /* =============================================
           PASSWORD STRENGTH
        ============================================= */

        function checkStrength() {

            if (
                !password ||
                !strengthBar ||
                !passwordHint
            ) {

                return;

            }


            const value =
                password.value;


            let score = 0;


            if (value.length >= 8) {
                score++;
            }


            if (
                /[A-Z]/.test(value) &&
                /[a-z]/.test(value)
            ) {

                score++;

            }


            if (
                /\d/.test(value)
            ) {

                score++;

            }


            if (
                /[^A-Za-z0-9]/.test(value)
            ) {

                score++;

            }



            strengthBar.className =
                "password-strength-bar";


            if (value.length === 0) {

                passwordHint.textContent =
                    "Use at least 8 characters.";

                return;

            }


            if (score <= 1) {

                strengthBar.classList.add(
                    "strength-weak"
                );

                passwordHint.textContent =
                    "Password strength: Weak";

            } else if (score <= 3) {

                strengthBar.classList.add(
                    "strength-medium"
                );

                passwordHint.textContent =
                    "Password strength: Good";

            } else {

                strengthBar.classList.add(
                    "strength-strong"
                );

                passwordHint.textContent =
                    "Password strength: Strong";

            }

        }



        /* =============================================
           CONFIRM PASSWORD
        ============================================= */

        function checkPasswordMatch() {

            if (
                !password ||
                !confirmPassword ||
                !passwordMatch
            ) {

                return;

            }


            if (
                confirmPassword.value === ""
            ) {

                passwordMatch.textContent =
                    "";

                passwordMatch.className =
                    "password-match";

                return;

            }


            if (
                password.value ===
                confirmPassword.value
            ) {

                passwordMatch.textContent =
                    "Passwords match.";

                passwordMatch.className =
                    "password-match match-success";

            } else {

                passwordMatch.textContent =
                    "Passwords do not match.";

                passwordMatch.className =
                    "password-match match-error";

            }

        }



        if (password) {

            password.addEventListener(
                "input",
                function () {

                    checkStrength();

                    checkPasswordMatch();

                }
            );

        }


        if (confirmPassword) {

            confirmPassword.addEventListener(
                "input",
                checkPasswordMatch
            );

        }


    }
);

</script>


</body>

</html>