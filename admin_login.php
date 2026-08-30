<?php

session_start();

require_once __DIR__ . "/db.php";


$message = "";


/* =========================================================
   ALREADY LOGGED IN
========================================================= */

if (isset($_SESSION["admin_id"])) {

    header("Location: dashboard.php");
    exit();
}


/* =========================================================
   ADMIN LOGIN
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["login"])
) {

    $username =
        trim(
            $_POST["username"] ?? ""
        );


    $password =
        $_POST["password"] ?? "";


    if (
        $username === "" ||
        $password === ""
    ) {

        $message =
            "Please enter your username and password.";

    } else {


        $stmt =
            $conn->prepare(
                "
                SELECT
                    id,
                    username,
                    password
                FROM admin
                WHERE username = ?
                LIMIT 1
                "
            );


        if (!$stmt) {

            $message =
                "Unable to process login right now.";

        } else {


            $stmt->bind_param(
                "s",
                $username
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            if (
                $result->num_rows === 1
            ) {

                $admin =
                    $result->fetch_assoc();


                if (
                    password_verify(
                        $password,
                        $admin["password"]
                    )
                ) {

                    session_regenerate_id(true);


                    $_SESSION["admin_id"] =
                        (int) $admin["id"];


                    $_SESSION["admin_username"] =
                        $admin["username"];


                    /*
                     * Temporary compatibility with
                     * older admin pages.
                     */

                    $_SESSION["admin"] =
                        $admin["username"];


                    $stmt->close();


                    header(
                        "Location: dashboard.php"
                    );


                    exit();
                }
            }


            $message =
                "Incorrect username or password.";


            $stmt->close();
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
        Admin Login | Sprinter Tours & Safaris
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
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        :root {

            --forest:
                #0a5c36;

            --forest-deep:
                #073b28;

            --forest-black:
                #062b20;

            --gold:
                #c99a43;

            --gold-light:
                #e4c57b;

            --ivory:
                #f5f1e8;

            --sand:
                #e8dec9;

            --white:
                #ffffff;

            --ink:
                #1b2724;

            --muted:
                #747c78;

            --border:
                rgba(23, 47, 40, 0.13);
        }


        * {
            box-sizing: border-box;
        }


        html {
            min-height: 100%;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                "DM Sans",
                sans-serif;

            color:
                var(--ink);

            background:
                var(--ivory);

            overflow-x: hidden;
        }



        /* =================================================
           PAGE BACKGROUND
        ================================================== */

        .page {

            min-height: 100vh;

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            padding:
                48px 24px;

            overflow: hidden;

            background:

                radial-gradient(
                    circle at 10% 10%,
                    rgba(201,154,67,0.11),
                    transparent 27%
                ),

                radial-gradient(
                    circle at 87% 82%,
                    rgba(10,92,54,0.10),
                    transparent 30%
                ),

                linear-gradient(
                    135deg,
                    #f8f5ed,
                    #efe9dc
                );
        }



        /* =================================================
           CONTOUR MAP DECORATION
        ================================================== */

        .contours {

            position: absolute;

            inset: 0;

            pointer-events: none;

            opacity:
                0.27;

            background-image:

                repeating-radial-gradient(
                    ellipse at 16% 32%,
                    transparent 0,
                    transparent 24px,
                    rgba(10,92,54,0.16) 25px,
                    transparent 27px
                ),

                repeating-radial-gradient(
                    ellipse at 82% 70%,
                    transparent 0,
                    transparent 32px,
                    rgba(201,154,67,0.16) 33px,
                    transparent 35px
                );
        }



        /* =================================================
           FLOATING TRAVEL LABELS
        ================================================== */

        .travel-tag {

            position: absolute;

            display: flex;

            align-items: center;

            gap: 8px;

            padding:
                9px 13px;

            border:
                1px solid
                rgba(10,92,54,0.12);

            border-radius:
                999px;

            background:
                rgba(255,255,255,0.56);

            backdrop-filter:
                blur(8px);

            color:
                #53635e;

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                0.4px;

            box-shadow:
                0 10px 30px
                rgba(31,45,40,0.05);
        }


        .travel-tag i {

            color:
                var(--forest);
        }


        .tag-one {

            left: 6%;
            top: 16%;
        }


        .tag-two {

            right: 7%;
            top: 20%;
        }


        .tag-three {

            left: 8%;
            bottom: 15%;
        }


        .tag-four {

            right: 8%;
            bottom: 17%;
        }



        /* =================================================
           MAIN FRAME
        ================================================== */

        .portal {

            position: relative;

            z-index: 5;

            width:
                min(
                    1120px,
                    100%
                );

            min-height:
                650px;

            display: grid;

            grid-template-columns:
                0.92fr
                1.08fr;

            background:
                rgba(255,255,255,0.74);

            border:
                1px solid
                rgba(255,255,255,0.8);

            border-radius:
                30px;

            overflow: hidden;

            box-shadow:

                0 35px 90px
                rgba(34,48,43,0.13);
        }



        /* =================================================
           BRAND PANEL
        ================================================== */

        .brand-panel {

            position: relative;

            overflow: hidden;

            padding:
                44px;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            background:

                radial-gradient(
                    circle at 75% 22%,
                    rgba(228,197,123,0.16),
                    transparent 25%
                ),

                linear-gradient(
                    150deg,
                    var(--forest) 0%,
                    var(--forest-deep) 54%,
                    var(--forest-black) 100%
                );

            color:
                white;
        }



        .brand-panel::after {

            content: "";

            position: absolute;

            width: 420px;
            height: 420px;

            border:
                1px solid
                rgba(255,255,255,0.07);

            border-radius:
                50%;

            right:
                -215px;

            bottom:
                -170px;

            box-shadow:

                0 0 0 45px
                rgba(255,255,255,0.025),

                0 0 0 90px
                rgba(255,255,255,0.018);
        }



        /* LOGO */

        .brand-header {

            position: relative;

            z-index: 2;

            display: flex;

            align-items: center;

            gap:
                14px;
        }


        .brand-logo {

            width:
                58px;

            height:
                58px;

            border-radius:
                15px;

            object-fit:
                contain;

            padding:
                5px;

            background:
                white;

            box-shadow:
                0 14px 30px
                rgba(0,0,0,0.16);
        }


        .brand-title strong {

            display: block;

            font-size:
                16px;

            line-height:
                1.3;
        }


        .brand-title span {

            display: block;

            margin-top:
                5px;

            color:
                rgba(255,255,255,0.58);

            font-size:
                9px;

            font-weight:
                700;

            letter-spacing:
                1.7px;

            text-transform:
                uppercase;
        }



        /* BRAND CONTENT */

        .brand-content {

            position: relative;

            z-index: 2;
        }


        .brand-eyebrow {

            display: flex;

            align-items: center;

            gap:
                11px;

            margin-bottom:
                20px;

            color:
                var(--gold-light);

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                1.7px;

            text-transform:
                uppercase;
        }


        .brand-eyebrow::before {

            content: "";

            width:
                32px;

            height:
                1px;

            background:
                var(--gold-light);
        }


        .brand-content h1 {

            margin:
                0;

            max-width:
                450px;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                clamp(
                    42px,
                    4vw,
                    62px
                );

            font-weight:
                600;

            line-height:
                1.04;

            letter-spacing:
                -1.2px;
        }


        .brand-content h1 span {

            color:
                var(--gold-light);
        }


        .brand-content p {

            max-width:
                410px;

            margin:
                22px 0 0;

            color:
                rgba(255,255,255,0.67);

            font-size:
                13px;

            line-height:
                1.8;
        }



        /* MINI ROUTE */

        .route {

            position: relative;

            z-index: 2;

            margin-top:
                34px;

            padding-top:
                24px;

            border-top:
                1px solid
                rgba(255,255,255,0.12);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap:
                12px;
        }


        .route-stop {

            display: flex;

            flex-direction: column;

            gap:
                4px;
        }


        .route-stop strong {

            font-size:
                11px;
        }


        .route-stop span {

            color:
                rgba(255,255,255,0.45);

            font-size:
                9px;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        .route-line {

            flex: 1;

            height:
                1px;

            position: relative;

            margin:
                0 8px;

            background:
                rgba(255,255,255,0.18);
        }


        .route-line::before,
        .route-line::after {

            content: "";

            position: absolute;

            top:
                -3px;

            width:
                7px;

            height:
                7px;

            border-radius:
                50%;

            background:
                var(--gold-light);
        }


        .route-line::before {
            left: 0;
        }


        .route-line::after {
            right: 0;
        }



        /* =================================================
           LOGIN PANEL
        ================================================== */

        .login-panel {

            padding:
                58px 64px;

            display: flex;

            align-items: center;

            justify-content: center;

            position: relative;

            background:

                linear-gradient(
                    180deg,
                    rgba(255,255,255,0.94),
                    rgba(250,249,246,0.94)
                );
        }


        .login-box {

            width:
                100%;

            max-width:
                410px;
        }



        /* HEADER */

        .login-icon {

            width:
                48px;

            height:
                48px;

            display: grid;

            place-items: center;

            margin-bottom:
                22px;

            border-radius:
                14px;

            background:
                rgba(10,92,54,0.09);

            color:
                var(--forest);

            font-size:
                18px;
        }


        .login-overline {

            margin:
                0 0 8px;

            color:
                var(--forest);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.7px;

            text-transform:
                uppercase;
        }


        .login-box h2 {

            margin:
                0;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                38px;

            font-weight:
                600;

            letter-spacing:
                -0.8px;
        }


        .login-description {

            margin:
                12px 0 32px;

            color:
                var(--muted);

            font-size:
                12px;

            line-height:
                1.7;
        }



        /* ERROR MESSAGE */

        .login-message {

            display: flex;

            align-items: flex-start;

            gap:
                9px;

            padding:
                12px 14px;

            margin-bottom:
                20px;

            border:
                1px solid
                #fecaca;

            border-radius:
                10px;

            background:
                #fff1f2;

            color:
                #9f1239;

            font-size:
                12px;

            line-height:
                1.5;
        }



        /* FORM */

        .login-form {

            display: grid;

            gap:
                20px;
        }


        .field label {

            display: block;

            margin-bottom:
                7px;

            color:
                #34413d;

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                0.3px;
        }


        .field-control {

            position: relative;
        }


        .field-control > i {

            position: absolute;

            left:
                15px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #99a09e;

            font-size:
                13px;

            pointer-events:
                none;
        }


        .field-control input {

            width:
                100%;

            height:
                51px;

            padding:
                0 45px;

            border:
                1px solid
                #dce1de;

            border-radius:
                12px;

            outline:
                none;

            background:
                #ffffff;

            color:
                var(--ink);

            font-family:
                inherit;

            font-size:
                13px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .field-control input::placeholder {

            color:
                #adb3b1;
        }


        .field-control input:focus {

            border-color:
                var(--forest);

            box-shadow:
                0 0 0 4px
                rgba(10,92,54,0.07);
        }



        /* PASSWORD */

        .password-toggle {

            position: absolute;

            top:
                50%;

            right:
                9px;

            transform:
                translateY(-50%);

            width:
                34px;

            height:
                34px;

            border:
                0;

            border-radius:
                8px;

            background:
                transparent;

            color:
                #8f9794;

            cursor:
                pointer;
        }


        .password-toggle:hover {

            background:
                #f3f6f4;

            color:
                var(--forest);
        }



        /* BUTTON */

        .login-button {

            width:
                100%;

            height:
                52px;

            margin-top:
                3px;

            border:
                0;

            border-radius:
                12px;

            display:
                flex;

            align-items: center;

            justify-content: center;

            gap:
                9px;

            background:

                linear-gradient(
                    135deg,
                    var(--forest),
                    var(--forest-deep)
                );

            color:
                white;

            font-family:
                inherit;

            font-size:
                12px;

            font-weight:
                700;

            letter-spacing:
                0.2px;

            cursor:
                pointer;

            box-shadow:
                0 14px 28px
                rgba(10,92,54,0.18);

            transition:
                0.2s ease;
        }


        .login-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 18px 34px
                rgba(10,92,54,0.23);
        }



        /* FOOTER INFO */

        .secure-row {

            display: flex;

            align-items: center;

            justify-content: center;

            gap:
                7px;

            margin-top:
                22px;

            color:
                #929996;

            font-size:
                9px;
        }


        .secure-row i {

            color:
                var(--forest);
        }


        .login-footer {

            margin-top:
                34px;

            padding-top:
                18px;

            border-top:
                1px solid
                #e8ebe9;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap:
                12px;

            color:
                #9da3a1;

            font-size:
                9px;
        }


        .login-footer a {

            color:
                var(--forest);

            text-decoration:
                none;

            font-weight:
                700;
        }



        /* =================================================
           RESPONSIVE
        ================================================== */

        @media (
            max-width: 900px
        ) {

            .portal {

                grid-template-columns:
                    1fr;

                max-width:
                    620px;
            }


            .brand-panel {

                min-height:
                    360px;

                padding:
                    34px;
            }


            .brand-content {

                margin-top:
                    80px;
            }


            .brand-content h1 {

                font-size:
                    42px;
            }


            .route {

                display:
                    none;
            }


            .login-panel {

                padding:
                    46px 32px;
            }


            .travel-tag {

                display:
                    none;
            }

        }


        @media (
            max-width: 520px
        ) {

            .page {

                padding:
                    16px;
            }


            .portal {

                border-radius:
                    22px;
            }


            .brand-panel {

                min-height:
                    280px;

                padding:
                    26px;
            }


            .brand-logo {

                width:
                    48px;

                height:
                    48px;
            }


            .brand-content {

                margin-top:
                    52px;
            }


            .brand-content h1 {

                font-size:
                    34px;
            }


            .brand-content p {

                display:
                    none;
            }


            .login-panel {

                padding:
                    36px 24px;
            }


            .login-box h2 {

                font-size:
                    32px;
            }

        }

    </style>

</head>


<body>


<main class="page">


    <div class="contours"></div>


    <!-- TRAVEL DETAILS -->

    <div class="travel-tag tag-one">

        <i class="fa-solid fa-location-dot"></i>

        Nairobi • Kenya

    </div>


    <div class="travel-tag tag-two">

        <i class="fa-solid fa-compass"></i>

        Safari Operations

    </div>


    <div class="travel-tag tag-three">

        <i class="fa-solid fa-binoculars"></i>

        Explore • Experience

    </div>


    <div class="travel-tag tag-four">

        <i class="fa-solid fa-plane-departure"></i>

        Journey Management

    </div>



    <div class="portal">


        <!-- =============================================
             BRAND
        ============================================== -->

        <section class="brand-panel">


            <div class="brand-header">


                <img
                    src="images/Wildlife Sprinter Tours & Safaris.png"
                    alt="Sprinter Tours & Safaris"
                    class="brand-logo"
                >


                <div class="brand-title">

                    <strong>
                        Sprinter Tours & Safaris
                    </strong>

                    <span>
                        Operations Portal
                    </span>

                </div>


            </div>



            <div class="brand-content">


                <div class="brand-eyebrow">
                    Travel Management
                </div>


                <h1>

                    Behind every
                    unforgettable
                    <span>
                        journey.
                    </span>

                </h1>


                <p>

                    One secure workspace for reservations,
                    customer enquiries, payments and safari
                    operations.

                </p>


            </div>



            <div class="route">


                <div class="route-stop">

                    <strong>
                        Nairobi
                    </strong>

                    <span>
                        Start
                    </span>

                </div>


                <div class="route-line"></div>


                <div class="route-stop">

                    <strong>
                        Safari
                    </strong>

                    <span>
                        Experience
                    </span>

                </div>


                <div class="route-line"></div>


                <div class="route-stop">

                    <strong>
                        Memories
                    </strong>

                    <span>
                        Destination
                    </span>

                </div>


            </div>


        </section>



        <!-- =============================================
             LOGIN
        ============================================== -->

        <section class="login-panel">


            <div class="login-box">


                <div class="login-icon">

                    <i class="fa-solid fa-key"></i>

                </div>


                <p class="login-overline">
                    Administrator Access
                </p>


                <h2>
                    Welcome back
                </h2>


                <p class="login-description">

                    Enter your administrator credentials
                    to continue to the Sprinter management
                    dashboard.

                </p>



                <?php if (
                    $message !== ""
                ): ?>


                    <div
                        class="login-message"
                        role="alert"
                    >

                        <i class="fa-solid fa-circle-exclamation"></i>


                        <span>

                            <?php
                                echo htmlspecialchars(
                                    $message,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>

                        </span>


                    </div>


                <?php endif; ?>



                <form
                    method="POST"
                    action=""
                    class="login-form"
                >


                    <!-- USERNAME -->

                    <div class="field">


                        <label for="username">
                            USERNAME
                        </label>


                        <div class="field-control">


                            <i class="fa-regular fa-user"></i>


                            <input
                                type="text"
                                id="username"
                                name="username"
                                autocomplete="username"
                                placeholder="Enter admin username"
                                value="<?php
                                    echo htmlspecialchars(
                                        $_POST["username"]
                                        ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                required
                            >


                        </div>


                    </div>



                    <!-- PASSWORD -->

                    <div class="field">


                        <label for="password">
                            PASSWORD
                        </label>


                        <div class="field-control">


                            <i class="fa-solid fa-lock"></i>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                id="passwordToggle"
                                aria-label="Show password"
                            >

                                <i
                                    class="fa-regular fa-eye"
                                    id="passwordIcon"
                                ></i>

                            </button>


                        </div>


                    </div>



                    <!-- SIGN IN -->

                    <button
                        type="submit"
                        name="login"
                        class="login-button"
                    >

                        Continue to Dashboard

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>



                <div class="secure-row">

                    <i class="fa-solid fa-shield-halved"></i>

                    Secure administrative access

                </div>



                <div class="login-footer">


                    <span>

                        © 2026 Sprinter Tours & Safaris

                    </span>


                    <a href="index.html">

                        View website

                    </a>


                </div>


            </div>


        </section>


    </div>


</main>



<script>

const password =
    document.getElementById(
        "password"
    );


const passwordToggle =
    document.getElementById(
        "passwordToggle"
    );


const passwordIcon =
    document.getElementById(
        "passwordIcon"
    );


if (
    password &&
    passwordToggle &&
    passwordIcon
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


            passwordIcon.className =
                hidden
                    ? "fa-regular fa-eye-slash"
                    : "fa-regular fa-eye";


            passwordToggle.setAttribute(
                "aria-label",
                hidden
                    ? "Hide password"
                    : "Show password"
            );

        }
    );

}

</script>


</body>

</html>