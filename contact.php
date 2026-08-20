<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   CONTACT PAGE
========================================================= */

require_once __DIR__ . "/db.php";


/* =========================================================
   INITIAL FORM VALUES
========================================================= */

$success_message = "";
$error_message = "";

$name = "";
$email = "";
$phone = "";
$message = "";


/* =========================================================
   PROCESS CONTACT FORM
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =====================================================
       GET FORM VALUES
    ===================================================== */

    $name =
        isset($_POST["name"]) &&
        is_string($_POST["name"])
            ? trim($_POST["name"])
            : "";


    $email =
        isset($_POST["email"]) &&
        is_string($_POST["email"])
            ? trim($_POST["email"])
            : "";


    $phone =
        isset($_POST["phone"]) &&
        is_string($_POST["phone"])
            ? trim($_POST["phone"])
            : "";


    $message =
        isset($_POST["message"]) &&
        is_string($_POST["message"])
            ? trim($_POST["message"])
            : "";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        $name === "" ||
        $email === "" ||
        $message === ""
    ) {

        $error_message =
            "Please complete all required fields.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error_message =
            "Please enter a valid email address.";

    } elseif (
        strlen($name) > 100
    ) {

        $error_message =
            "Your name is too long.";

    } elseif (
        strlen($email) > 150
    ) {

        $error_message =
            "Your email address is too long.";

    } elseif (
        strlen($phone) > 30
    ) {

        $error_message =
            "Your phone number is too long.";

    } elseif (
        strlen($message) > 3000
    ) {

        $error_message =
            "Your message is too long.";

    } else {


        /* =================================================
           SAVE CUSTOMER MESSAGE
        ================================================= */

        $stmt =
            $conn->prepare(
                "
                INSERT INTO messages
                (
                    name,
                    email,
                    phone,
                    message,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'Unread'
                )
                "
            );


        if (!$stmt) {

            error_log(
                "Contact prepare error: "
                . $conn->error
            );

            $error_message =
                "We could not process your message right now.";

        } else {


            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $phone,
                $message
            );


            if ($stmt->execute()) {


                $success_message =
                    "Thank you! Your message has been sent successfully. Our team will get back to you as soon as possible.";


                /* Clear form */

                $name = "";
                $email = "";
                $phone = "";
                $message = "";


            } else {


                error_log(
                    "Contact insert error: "
                    . $stmt->error
                );


                $error_message =
                    "Your message could not be sent. Please try again.";

            }


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

    <meta
        name="description"
        content="Contact Sprinter Tours & Safaris for safari bookings, holidays, travel enquiries and customer support."
    >

    <title>
        Contact Us | Sprinter Tours & Safaris
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="style.css"
    >


<style>

/* =========================================================
   CONTACT PAGE
========================================================= */

* {
    box-sizing: border-box;
}


html {
    scroll-behavior: smooth;
}


body.contact-page {

    margin: 0;

    background: #f6faf7;

    color: #151515;

    font-family:
        "Poppins",
        sans-serif;
}


/* =========================================================
   HERO
========================================================= */

.contact-hero {

    min-height: 500px;

    padding:
        90px
        25px;

    display: flex;

    justify-content: center;

    align-items: center;

    text-align: center;

    color: white;

    background:

        linear-gradient(
            rgba(0,0,0,.55),
            rgba(0,80,30,.60)
        ),

        url(
            "images/maasai-mara.jpg"
        )

        center / cover
        no-repeat;

}


.contact-hero-content {

    width: 100%;

    max-width: 850px;

}


.contact-label {

    margin-bottom: 12px;

    font-size: 12px;

    font-weight: 700;

    letter-spacing: 4px;

}


.contact-hero h1 {

    margin:
        0
        0
        18px;

    font-size:
        clamp(
            38px,
            6vw,
            68px
        );

    line-height: 1.1;

    color: white;

}


.contact-hero p {

    max-width: 700px;

    margin: auto;

    font-size: 17px;

    line-height: 1.8;

}


/* =========================================================
   MAIN CONTACT AREA
========================================================= */

.contact-main {

    width:
        min(
            1200px,
            calc(100% - 40px)
        );

    margin: auto;

    padding:
        90px
        0;

    display: grid;

    grid-template-columns:
        .9fr
        1.1fr;

    gap: 40px;

}


/* =========================================================
   LEFT CONTACT PANEL
========================================================= */

.contact-information {

    padding: 45px;

    border-radius: 20px;

    color: white;

    background:

        linear-gradient(
            145deg,
            #064b1e,
            #08752d
        );

    box-shadow:
        0
        18px
        45px
        rgba(0,0,0,.12);

}


.small-title {

    margin:
        0
        0
        10px;

    color: #18a748;

    font-size: 12px;

    font-weight: 700;

    letter-spacing: 3px;

}


.contact-information
.small-title {

    color: #d7f5df;

}


.contact-information h2 {

    margin:
        0
        0
        15px;

    color: white;

    font-size: 34px;

}


.contact-information > p {

    margin:
        0
        0
        30px;

    color: #e7f6eb;

    line-height: 1.8;

}


/* =========================================================
   CONTACT ITEM
========================================================= */

.contact-info-item {

    padding:
        20px
        0;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            .18
        );

}


.contact-info-item:last-child {

    border-bottom: none;

}


.contact-info-item h3 {

    margin:
        0
        0
        8px;

    color: white;

    font-size: 17px;

}


.contact-info-item p {

    margin: 0;

    line-height: 1.8;

}


.contact-link {

    display: inline-block;

    margin-bottom: 5px;

    color: #ffffff;

    font-weight: 500;

    text-decoration: none;

    transition: .2s;

}


.contact-link:hover {

    color: #c9f5d6;

    text-decoration: underline;

}


/* =========================================================
   WHATSAPP BUTTON
========================================================= */

.whatsapp-contact {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-top: 5px;

    padding:
        11px
        16px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .25
        );

    border-radius: 9px;

    color: white;

    text-decoration: none;

    font-weight: 600;

    background:
        rgba(
            255,
            255,
            255,
            .10
        );

    transition: .25s;

}


.whatsapp-contact:hover {

    background:
        rgba(
            255,
            255,
            255,
            .18
        );

    transform:
        translateY(-1px);

}


/* =========================================================
   CONTACT FORM
========================================================= */

.contact-form-box {

    padding: 45px;

    border-radius: 20px;

    background: white;

    box-shadow:
        0
        18px
        45px
        rgba(0,0,0,.10);

}


.contact-form-box h2 {

    margin:
        0
        0
        10px;

    font-size: 34px;

}


.contact-form-intro {

    margin:
        0
        0
        30px;

    color: #666;

    line-height: 1.8;

}


.contact-form-group {

    margin-bottom: 20px;

}


.contact-form-group label {

    display: block;

    margin-bottom: 8px;

    color: #333;

    font-weight: 600;

}


.contact-form-group input,
.contact-form-group textarea {

    width: 100%;

    padding:
        14px
        15px;

    border:
        1px solid
        #d3d8d4;

    border-radius: 10px;

    background: #fafafa;

    color: #222;

    font:
        inherit;

    outline: none;

    transition: .2s;

}


.contact-form-group input:focus,
.contact-form-group textarea:focus {

    border-color: #08752d;

    background: white;

    box-shadow:
        0
        0
        0
        3px
        rgba(
            8,
            117,
            45,
            .10
        );

}


.contact-form-group textarea {

    min-height: 165px;

    resize: vertical;

}


.contact-submit {

    width: 100%;

    padding:
        15px
        20px;

    border: none;

    border-radius: 10px;

    background: #08752d;

    color: white;

    font:
        inherit;

    font-weight: 700;

    cursor: pointer;

    transition: .25s;

}


.contact-submit:hover {

    background: #056321;

    transform:
        translateY(-2px);

}


/* =========================================================
   SUCCESS / ERROR
========================================================= */

.contact-success,
.contact-error {

    margin-bottom: 25px;

    padding:
        15px
        18px;

    border-radius: 10px;

    line-height: 1.6;

}


.contact-success {

    border:
        1px solid
        #a9deb8;

    background: #e2f6e7;

    color: #076b27;

}


.contact-error {

    border:
        1px solid
        #efb0b0;

    background: #ffe8e8;

    color: #9b1111;

}


/* =========================================================
   FINAL CTA
========================================================= */

.contact-cta {

    padding:
        90px
        25px;

    text-align: center;

    background: #edf6ef;

}


.contact-cta-content {

    width: 100%;

    max-width: 760px;

    margin: auto;

}


.contact-cta h2 {

    margin:
        0
        0
        15px;

    font-size:
        clamp(
            30px,
            5vw,
            42px
        );

}


.contact-cta p {

    margin:
        0
        0
        25px;

    color: #666;

    line-height: 1.8;

}


.contact-cta a {

    display: inline-block;

    padding:
        14px
        28px;

    border-radius: 9px;

    background: #08752d;

    color: white;

    text-decoration: none;

    font-weight: 700;

}


/* =========================================================
   MOBILE
========================================================= */

@media (
    max-width: 900px
) {

    .contact-main {

        grid-template-columns:
            1fr;

    }

}


@media (
    max-width: 600px
) {

    .contact-hero {

        min-height: 430px;

        padding:
            65px
            20px;

    }


    .contact-hero p {

        font-size: 15px;

    }


    .contact-main {

        width:
            calc(
                100% - 28px
            );

        padding:
            55px
            0;

        gap: 25px;

    }


    .contact-information,
    .contact-form-box {

        padding:
            28px
            20px;

        border-radius: 15px;

    }


    .contact-information h2,
    .contact-form-box h2 {

        font-size: 27px;

    }


    .contact-cta {

        padding:
            65px
            20px;

    }

}

</style>

</head>


<body class="contact-page">


<!-- =====================================================
     NAVIGATION
===================================================== -->

<header id="navbar">


    <a
        href="index.html"
        class="site-brand"
    >

        SPRINTER TOURS & SAFARIS

    </a>


    <button
        id="menuToggle"
        class="menu-toggle"
        type="button"
        aria-label="Open navigation"
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
            href="contact.php"
            class="active"
        >
            Contact
        </a>


        <button
            id="darkToggle"
            class="theme-toggle"
            type="button"
        >
            🌙
        </button>

    </nav>


</header>



<!-- =====================================================
     HERO
===================================================== -->

<section class="contact-hero">


    <div class="contact-hero-content">


        <p class="contact-label">
            GET IN TOUCH
        </p>


        <h1>
            Your Next Journey Starts Here.
        </h1>


        <p>

            Planning a safari,
            beach escape,
            corporate trip
            or international adventure?

            Talk to Sprinter Tours & Safaris
            and let us help you plan
            your journey.

        </p>


    </div>


</section>



<!-- =====================================================
     CONTACT
===================================================== -->

<main class="contact-main">


    <!-- CONTACT DETAILS -->

    <section class="contact-information">


        <p class="small-title">
            CONTACT SPRINTER
        </p>


        <h2>
            Let's Talk Travel.
        </h2>


        <p>

            Questions about destinations,
            packages, bookings or payments?

            Our team is ready to assist you.

        </p>



        <!-- PHONE -->

        <div class="contact-info-item">


            <h3>
                📞 Phone
            </h3>


            <p>

                <a
                    class="contact-link"
                    href="tel:+254771770469"
                >
                    +254 771 770 469
                </a>

                <br>


                <a
                    class="contact-link"
                    href="tel:+254725240949"
                >
                    +254 725 240 949
                </a>

                <br>


                <a
                    class="contact-link"
                    href="tel:+254729378078"
                >
                    +254 729 378 078
                </a>

            </p>


        </div>



        <!-- WHATSAPP -->

        <div class="contact-info-item">


            <h3>
                💬 WhatsApp
            </h3>


            <p>

                <a
                    class="whatsapp-contact"
                    href="https://wa.me/254771770469"
                    target="_blank"
                    rel="noopener noreferrer"
                >

                    💬 Chat on WhatsApp

                </a>

            </p>


        </div>



        <!-- EMAIL -->

        <div class="contact-info-item">


            <h3>
                ✉️ Email
            </h3>


            <p>

                <a
                    class="contact-link"
                    href="mailto:sprintertoursandsafari254@gmail.com"
                >

                    sprintertoursandsafari254@gmail.com

                </a>

            </p>


        </div>



        <!-- LOCATION -->

        <div class="contact-info-item">


            <h3>
                📍 Location
            </h3>


            <p>
                Nakuru, Kenya
            </p>


        </div>



        <!-- SUPPORT -->

        <div class="contact-info-item">


            <h3>
                🕐 Customer Support
            </h3>


            <p>

                Available for travel enquiries,
                booking assistance and customer support.

            </p>


        </div>


    </section>



    <!-- =================================================
         FORM
    ================================================== -->

    <section class="contact-form-box">


        <p class="small-title">
            SEND A MESSAGE
        </p>


        <h2>
            How Can We Help?
        </h2>


        <p class="contact-form-intro">

            Tell us about your travel plans
            or ask us any question.

        </p>



        <?php if ($success_message !== ""): ?>


            <div
                class="contact-success"
                role="status"
            >

                <?php

                echo htmlspecialchars(
                    $success_message
                );

                ?>

            </div>


        <?php endif; ?>



        <?php if ($error_message !== ""): ?>


            <div
                class="contact-error"
                role="alert"
            >

                <?php

                echo htmlspecialchars(
                    $error_message
                );

                ?>

            </div>


        <?php endif; ?>



        <form
            method="POST"
            action="contact.php"
        >


            <div class="contact-form-group">


                <label for="name">
                    Full Name *
                </label>


                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?php
                    echo htmlspecialchars(
                        $name
                    );
                    ?>"
                    maxlength="100"
                    required
                >


            </div>



            <div class="contact-form-group">


                <label for="email">
                    Email Address *
                </label>


                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php
                    echo htmlspecialchars(
                        $email
                    );
                    ?>"
                    maxlength="150"
                    required
                >


            </div>



            <div class="contact-form-group">


                <label for="phone">
                    Phone Number
                </label>


                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?php
                    echo htmlspecialchars(
                        $phone
                    );
                    ?>"
                    maxlength="30"
                    placeholder="e.g. 2547XXXXXXXX"
                >


            </div>



            <div class="contact-form-group">


                <label for="message">
                    Message *
                </label>


                <textarea
                    id="message"
                    name="message"
                    maxlength="3000"
                    required
                ><?php
echo htmlspecialchars(
    $message
);
?></textarea>


            </div>



            <button
                type="submit"
                class="contact-submit"
            >

                Send Message

            </button>


        </form>


    </section>


</main>



<!-- =====================================================
     CTA
===================================================== -->

<section class="contact-cta">


    <div class="contact-cta-content">


        <p class="small-title">
            READY TO EXPLORE?
        </p>


        <h2>
            From Savannah to Skyline.
        </h2>


        <p>

            From unforgettable Kenyan safaris
            to journeys around the world,
            Sprinter Tours & Safaris
            is ready to help you plan.

        </p>


        <a href="packages.html">
            Explore Our Packages
        </a>


    </div>


</section>



<footer>

    <p>

        © 2026 Sprinter Tours & Safaris.
        All Rights Reserved.

    </p>

</footer>



<button
    id="topBtn"
    type="button"
    aria-label="Back to top"
>
    ↑
</button>



<script src="script.js"></script>


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


        }


        const topBtn =
            document.getElementById(
                "topBtn"
            );


        if (topBtn) {


            topBtn.addEventListener(
                "click",
                function () {


                    window.scrollTo(
                        {
                            top: 0,
                            behavior: "smooth"
                        }
                    );


                }
            );


        }


    }
);

</script>


</body>

</html>