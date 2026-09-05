<?php
session_start();

require_once __DIR__ . "/db.php";

$message = "";

/* Owner artwork used only inside the red executive brand panel. */
$ownerLionImage = "images/owner-lion.jpg";
$adminCheetahImage = "images/admin-cheetah.jpg";

/* =========================================================
   PORTAL MODE
========================================================= */

$portal = strtolower(
    trim(
        $_POST["portal"]
        ?? $_GET["portal"]
        ?? "admin"
    )
);

if (!in_array($portal, ["admin", "owner"], true)) {
    $portal = "admin";
}


/* =========================================================
   EXISTING / STALE STAFF SESSION

   A staff session is reusable only when it is complete and
   belongs to the portal currently being requested. This
   prevents an old Admin session from blocking the Owner
   portal (and vice versa), and also repairs incomplete
   sessions left behind by older versions of the project.
========================================================= */

$hasAnyStaffSession =
    isset($_SESSION["admin_id"])
    || isset($_SESSION["admin_username"])
    || isset($_SESSION["admin_role"])
    || isset($_SESSION["admin"]);

$hasCompleteStaffSession =
    isset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_role"]
    );

if ($hasCompleteStaffSession) {

    $sessionRole = strtolower(
        trim((string) $_SESSION["admin_role"])
    );

    if ($sessionRole === $portal) {

        if ($sessionRole === "owner") {
            header("Location: owner_dashboard.php");
            exit();
        }

        if ($sessionRole === "admin") {
            header("Location: dashboard.php");
            exit();
        }
    }
}

/*
   If the session is incomplete, invalid, or belongs to the
   other portal, clear only the staff-login keys. Customer
   session data (if any) remains untouched.
*/
if ($hasAnyStaffSession) {
    unset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_role"],
        $_SESSION["admin"]
    );

    session_regenerate_id(true);
}


/* =========================================================
   LOGIN
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["login"])
) {

    $username =
        trim(
            $_POST["username"] ?? ""
        );

    $password =
        $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $message =
            "Please enter your username and password.";

    } else {

        $stmt =
            $conn->prepare(
                "
                SELECT
                    id,
                    username,
                    password,
                    role
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

            if ($result->num_rows === 1) {

                $admin =
                    $result->fetch_assoc();

                $accountRole =
                    strtolower(
                        trim(
                            (string) (
                                $admin["role"]
                                ?? "admin"
                            )
                        )
                    );

                $roleMatchesPortal =
                    $accountRole === $portal;

                if (
                    $roleMatchesPortal
                    && password_verify(
                        $password,
                        $admin["password"]
                    )
                ) {

                    session_regenerate_id(true);

                    $_SESSION["admin_id"] =
                        (int) $admin["id"];

                    $_SESSION["admin_username"] =
                        (string) $admin["username"];

                    $_SESSION["admin_role"] =
                        $accountRole;

                    /* Compatibility with older admin pages */
                    $_SESSION["admin"] =
                        (string) $admin["username"];


                    /* =====================================================
                       AUDIT SUCCESSFUL LOGIN
                    ===================================================== */

                    $auditAdminId =
                        (int) $admin["id"];

                    $auditUsername =
                        (string) $admin["username"];

                    $auditRole =
                        $accountRole;

                    $auditAction =
                        $accountRole === "owner"
                            ? "Owner logged in"
                            : "Admin logged in";

                    $auditEntityType =
                        "authentication";

                    $auditEntityId =
                        $auditAdminId;

                    $auditDetails =
                        "Successful "
                        . $accountRole
                        . " portal login.";

                    $auditIp =
                        $_SERVER["REMOTE_ADDR"]
                        ?? null;


                    $auditStmt =
                        $conn->prepare(
                            "
                            INSERT INTO admin_audit_log
                            (
                                admin_id,
                                username,
                                role,
                                action,
                                entity_type,
                                entity_id,
                                details,
                                ip_address
                            )

                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            "
                        );


                    if ($auditStmt) {

                        $auditStmt->bind_param(
                            "issssiss",
                            $auditAdminId,
                            $auditUsername,
                            $auditRole,
                            $auditAction,
                            $auditEntityType,
                            $auditEntityId,
                            $auditDetails,
                            $auditIp
                        );


                        if (!$auditStmt->execute()) {

                            error_log(
                                "Login audit failed for "
                                . $auditUsername
                                . ": "
                                . $auditStmt->error
                            );
                        }


                        $auditStmt->close();

                    } else {

                        error_log(
                            "Unable to prepare login audit record: "
                            . $conn->error
                        );
                    }


                    $stmt->close();

                    if ($accountRole === "owner") {

                        header(
                            "Location: owner_dashboard.php"
                        );

                    } else {

                        header(
                            "Location: dashboard.php"
                        );
                    }

                    exit();
                }
            }

            $message =
                $portal === "owner"
                    ? "Incorrect owner username or password."
                    : "Incorrect administrator username or password.";

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


        /* =================================================
           PORTAL SWITCHER + OWNER THEME
        ================================================== */

        .portal-switcher {
            position: relative;
            z-index: 20;
            width: min(720px, calc(100% - 40px));
            margin: 0 auto 22px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .portal-switch {
            display: flex;
            align-items: center;
            gap: 13px;
            min-height: 72px;
            padding: 14px 18px;
            border: 1px solid rgba(10,92,54,0.18);
            border-radius: 15px;
            text-decoration: none;
            background: rgba(255,255,255,0.72);
            backdrop-filter: blur(12px);
            color: #273531;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .portal-switch:hover {
            transform: translateY(-2px);
        }

        .portal-switch i {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 11px;
        }

        .portal-switch span {
            display: grid;
            gap: 3px;
        }

        .portal-switch strong {
            font-size: 14px;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .portal-switch small {
            color: #78817e;
            font-size: 11px;
        }

        .admin-switch.active {
            border-color: #0a5c36;
            box-shadow: 0 10px 28px rgba(10,92,54,.16);
        }

        .admin-switch.active i {
            color: #fff;
            background: #0a5c36;
        }

        .owner-switch.active {
            border-color: #c82d2d;
            box-shadow: 0 10px 30px rgba(155,22,22,.22);
        }

        .owner-switch.active i {
            color: #f5d078;
            background: #761313;
        }

        body.owner-mode {
            background:
                radial-gradient(circle at 14% 14%, rgba(165,22,22,.18), transparent 30%),
                radial-gradient(circle at 86% 80%, rgba(96,0,0,.16), transparent 34%),
                linear-gradient(135deg, #120b0b, #241111 48%, #0c0909);
        }

        body.owner-mode .page {
            background:
                radial-gradient(circle at 10% 10%, rgba(190,30,30,.14), transparent 28%),
                radial-gradient(circle at 88% 82%, rgba(125,13,13,.15), transparent 32%),
                linear-gradient(135deg, #1a0d0d, #0c0b0b);
        }

        body.owner-mode .contours {
            opacity: .2;
            filter: sepia(1) saturate(4) hue-rotate(320deg);
        }

        body.owner-mode .travel-tag {
            background: rgba(16,12,12,.72);
            border-color: rgba(205,72,72,.22);
            color: #eaded8;
        }

        body.owner-mode .portal {
            border: 1px solid rgba(216,69,69,.25);
            box-shadow:
                0 34px 80px rgba(0,0,0,.46),
                0 0 42px rgba(152,15,15,.12);
        }

        body.owner-mode .brand-panel {
            background:
                radial-gradient(circle at 80% 44%, rgba(232,45,45,.16), transparent 36%),
                linear-gradient(145deg, #541010 0%, #270909 52%, #100707 100%);
        }

        body.owner-mode .brand-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(120deg, transparent 0 55%, rgba(255,75,75,.05) 55% 56%, transparent 56%),
                radial-gradient(circle at 88% 45%, rgba(255,55,55,.11), transparent 18%);
            mix-blend-mode: screen;
        }

        body.owner-mode .brand-eyebrow,
        body.owner-mode .brand-content h1 span,
        body.owner-mode .secure-row i,
        body.owner-mode .login-overline {
            color: #ef3b3b;
        }

        body.owner-mode .route-line {
            background: linear-gradient(90deg, rgba(239,59,59,.22), #d59f42, rgba(239,59,59,.22));
        }

        body.owner-mode .route-stop span {
            color: rgba(255,255,255,.62);
        }

        body.owner-mode .login-panel {
            background:
                linear-gradient(145deg, #101010, #090909);
            color: #f5f0eb;
        }

        body.owner-mode .login-box h2 {
            color: #f5f0eb;
        }

        body.owner-mode .login-description,
        body.owner-mode .secure-row,
        body.owner-mode .login-footer,
        body.owner-mode .login-footer a {
            color: #a9a09c;
        }

        body.owner-mode .field label {
            color: #ddd3cd;
        }

        body.owner-mode .field-control {
            background: #111;
            border-color: rgba(213,159,66,.35);
        }

        body.owner-mode .field-control input {
            color: #f6f1ed;
        }

        body.owner-mode .field-control input::placeholder {
            color: #746d69;
        }

        body.owner-mode .field-control:focus-within {
            border-color: #d82f2f;
            box-shadow: 0 0 0 4px rgba(216,47,47,.11);
        }

        body.owner-mode .login-button {
            background:
                linear-gradient(135deg, #7a1111, #d72f2f);
            box-shadow:
                0 12px 26px rgba(190,30,30,.28),
                inset 0 1px 0 rgba(255,255,255,.12);
        }

        body.owner-mode .login-button:hover {
            background:
                linear-gradient(135deg, #901616, #ea3838);
        }

        body.owner-mode .login-icon {
            color: #f2cd73;
            background: rgba(140,20,20,.32);
            border-color: rgba(239,59,59,.24);
        }

        body.owner-mode .login-message {
            background: rgba(132,22,22,.16);
            border-color: rgba(239,59,59,.22);
            color: #ffc0c0;
        }

        body.owner-mode .login-footer {
            border-top-color: rgba(255,255,255,.09);
        }

        @media (max-width: 720px) {
            .portal-switcher {
                grid-template-columns: 1fr;
                margin-bottom: 14px;
            }
        }


        /* =================================================
           RESPONSIVE PORTAL LAYOUT FIX
           Keep switcher above the card instead of beside it.
        ================================================== */

        .page {
            flex-direction: column;
        }

        @media (max-width: 900px) {

            .page {
                justify-content: flex-start;
                padding: 24px 18px 36px;
                overflow: visible;
            }

            .portal-switcher {
                width: min(620px, 100%);
                margin: 0 auto 14px;
            }

            .portal {
                width: min(620px, 100%);
                min-height: 0;
                margin: 0 auto;
                grid-template-columns: 1fr;
            }

            .brand-panel {
                min-height: 300px;
            }
        }

        @media (max-width: 520px) {

            .page {
                padding: 12px 10px 24px;
            }

            .portal-switcher {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 10px;
            }

            .portal-switch {
                min-height: 60px;
                padding: 10px 9px;
                gap: 8px;
                border-radius: 12px;
            }

            .portal-switch i {
                width: 32px;
                height: 32px;
                border-radius: 9px;
                flex: 0 0 32px;
            }

            .portal-switch strong {
                font-size: 10px;
                letter-spacing: .04em;
            }

            .portal-switch small {
                font-size: 8px;
            }

            .portal {
                border-radius: 18px;
            }

            .brand-panel {
                min-height: 245px;
                padding: 22px 20px;
            }

            .brand-header {
                gap: 10px;
            }

            .brand-logo {
                width: 44px;
                height: 44px;
            }

            .brand-name strong {
                font-size: 13px;
            }

            .brand-name span {
                font-size: 8px;
            }

            .brand-content {
                margin-top: 34px;
            }

            .brand-eyebrow {
                font-size: 9px;
            }

            .brand-content h1 {
                max-width: 280px;
                margin-top: 10px;
                font-size: clamp(31px, 10vw, 42px);
                line-height: .98;
            }

            .brand-content p,
            .route {
                display: none;
            }

            .login-panel {
                padding: 28px 20px 24px;
            }

            .login-box {
                max-width: none;
            }

            .login-icon {
                width: 42px;
                height: 42px;
                margin-bottom: 18px;
            }

            .login-overline {
                font-size: 8px;
            }

            .login-box h2 {
                font-size: 31px;
                line-height: 1.05;
            }

            .login-description {
                margin-top: 10px;
                font-size: 12px;
                line-height: 1.55;
            }

            .login-form {
                margin-top: 24px;
            }

            .field {
                margin-bottom: 15px;
            }

            .field-control {
                min-height: 50px;
            }

            .field-control input {
                font-size: 14px;
            }

            .login-button {
                min-height: 52px;
                font-size: 13px;
            }

            .secure-row {
                margin-top: 16px;
                font-size: 9px;
            }

            .login-footer {
                margin-top: 24px;
                padding-top: 17px;
                gap: 10px;
                font-size: 8px;
            }
        }


        /* =================================================
           CINEMATIC ADMIN / OWNER ARTWORK
        ================================================== */

        .page {
            isolation: isolate;
        }

        .portal-switcher,
        .portal {
            position: relative;
            z-index: 5;
        }

        .admin-leaf {
            position: absolute;
            z-index: 1;
            pointer-events: none;
            width: 330px;
            height: 330px;
            opacity: 0;
            filter: blur(.2px);
            background:
                radial-gradient(
                    ellipse at 25% 75%,
                    rgba(33,133,78,.52) 0 8%,
                    transparent 9%
                ),
                radial-gradient(
                    ellipse at 40% 57%,
                    rgba(26,111,67,.44) 0 10%,
                    transparent 11%
                ),
                radial-gradient(
                    ellipse at 56% 40%,
                    rgba(18,94,55,.36) 0 11%,
                    transparent 12%
                ),
                linear-gradient(
                    135deg,
                    transparent 48%,
                    rgba(199,157,71,.20) 49% 50%,
                    transparent 51%
                );
        }

        body.admin-mode .admin-leaf {
            opacity: .72;
        }

        .admin-leaf-one {
            top: -90px;
            left: -80px;
            transform: rotate(-18deg);
        }

        .admin-leaf-two {
            right: -90px;
            bottom: -110px;
            transform: rotate(162deg);
        }

        body.admin-mode .page {
            background:
                radial-gradient(
                    circle at 12% 12%,
                    rgba(34,126,73,.12),
                    transparent 26%
                ),
                radial-gradient(
                    circle at 88% 84%,
                    rgba(12,88,51,.10),
                    transparent 30%
                ),
                linear-gradient(
                    135deg,
                    #f8f4e9,
                    #eee8da
                );
        }

        body.admin-mode .portal {
            box-shadow:
                0 30px 80px rgba(17,67,44,.14),
                0 0 0 1px rgba(12,92,54,.08);
        }

        body.admin-mode .brand-panel {
            background:
                radial-gradient(
                    circle at 78% 18%,
                    rgba(225,193,110,.10),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 20% 85%,
                    rgba(20,117,66,.20),
                    transparent 30%
                ),
                linear-gradient(
                    145deg,
                    #0b633a 0%,
                    #07442c 56%,
                    #04281e 100%
                );
        }

        body.owner-mode .page {
            background:
                radial-gradient(
                    circle at 18% 12%,
                    rgba(182,20,20,.18),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 82% 82%,
                    rgba(114,0,0,.22),
                    transparent 34%
                ),
                linear-gradient(
                    135deg,
                    #120909 0%,
                    #1b0909 48%,
                    #070707 100%
                );
        }

        .owner-lion-art {
            position: absolute;
            z-index: 2;
            inset: 0;
            pointer-events: none;
            background-repeat: no-repeat;
            background-size: cover;
            background-position: 72% center;
            opacity: .34;
            mix-blend-mode: screen;
            filter:
                saturate(1.15)
                contrast(1.08);
        }

        body.owner-mode .brand-panel,
        body.owner-mode .login-panel {
            position: relative;
            z-index: 3;
            background-color: rgba(9, 7, 7, .80);
            backdrop-filter: blur(8px);
        }

        body.owner-mode .brand-panel {
            background:
                linear-gradient(
                    145deg,
                    rgba(87,10,10,.88),
                    rgba(42,4,4,.86) 55%,
                    rgba(12,5,5,.90)
                );
        }

        body.owner-mode .login-panel {
            background:
                linear-gradient(
                    145deg,
                    rgba(18,16,16,.94),
                    rgba(8,8,8,.96)
                );
        }

        body.owner-mode .portal {
            border-color: rgba(244,50,50,.38);
            box-shadow:
                0 30px 90px rgba(0,0,0,.58),
                0 0 48px rgba(201,22,22,.18);
        }

        body.owner-mode .portal::after {
            content: "";
            position: absolute;
            z-index: 7;
            inset: -1px;
            pointer-events: none;
            border-radius: inherit;
            border: 1px solid rgba(239,59,59,.26);
            box-shadow:
                inset 0 0 45px rgba(168,15,15,.08);
        }

        .portal-feature-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 18px;
        }

        .portal-feature {
            min-width: 0;
            padding: 11px 10px;
            display: flex;
            align-items: center;
            gap: 9px;
            border-radius: 12px;
            border: 1px solid rgba(10,92,54,.12);
            background: rgba(248,250,249,.72);
        }

        .portal-feature i {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: rgba(10,92,54,.09);
            color: #0a5c36;
        }

        .portal-feature span {
            display: grid;
            gap: 1px;
            min-width: 0;
        }

        .portal-feature strong {
            font-size: 10px;
            color: #25322f;
            white-space: nowrap;
        }

        .portal-feature small {
            font-size: 8px;
            color: #7c8581;
            white-space: nowrap;
        }

        body.owner-mode .portal-feature {
            border-color: rgba(219,55,55,.20);
            background: rgba(31,13,13,.62);
        }

        body.owner-mode .portal-feature i {
            background: rgba(155,17,17,.22);
            color: #f0c967;
        }

        body.owner-mode .portal-feature strong {
            color: #f2e8e3;
        }

        body.owner-mode .portal-feature small {
            color: #a99690;
        }

        @media (max-width: 900px) {

            .owner-lion-art {
                background-position: 70% 18%;
                background-size: auto 58%;
                opacity: .28;
            }

            body.owner-mode .brand-panel {
                background:
                    linear-gradient(
                        180deg,
                        rgba(82,9,9,.83),
                        rgba(29,5,5,.93)
                    );
            }
        }

        @media (max-width: 520px) {

            .admin-leaf {
                width: 210px;
                height: 210px;
            }

            .admin-leaf-one {
                top: -55px;
                left: -70px;
            }

            .admin-leaf-two {
                right: -65px;
                bottom: -60px;
            }

            .owner-lion-art {
                background-position: 72% 7%;
                background-size: auto 49%;
                opacity: .27;
            }

            .portal-feature-strip {
                gap: 5px;
                margin-top: 15px;
            }

            .portal-feature {
                display: grid;
                justify-items: center;
                text-align: center;
                gap: 5px;
                padding: 8px 5px;
            }

            .portal-feature i {
                width: 27px;
                height: 27px;
                flex-basis: 27px;
            }

            .portal-feature strong {
                font-size: 8px;
            }

            .portal-feature small {
                font-size: 7px;
            }
        }


        /* =================================================
           REAL SAFARI ARTWORK - CHEETAH / LION
        ================================================== */

        .admin-cheetah-art,
        .owner-lion-art {
            position: absolute;
            z-index: 2;
            inset: 0;
            pointer-events: none;
            background-repeat: no-repeat;
            background-size: cover;
            mix-blend-mode: screen;
        }

        .admin-cheetah-art {
            background-position: 15% center;
            opacity: .42;
            filter:
                saturate(1.15)
                contrast(1.08)
                brightness(.88);
        }

        .owner-lion-art {
            background-position: 85% center;
            opacity: .46;
            filter:
                saturate(1.18)
                contrast(1.12)
                brightness(.82);
        }

        body.admin-mode .portal {
            background: rgba(248,251,249,.94);
            border-color: rgba(44,160,93,.26);
            box-shadow:
                0 34px 90px rgba(1,40,23,.20),
                0 0 42px rgba(16,124,66,.10);
        }

        body.admin-mode .brand-panel {
            background:
                linear-gradient(
                    145deg,
                    rgba(5,86,48,.88),
                    rgba(3,54,33,.90) 55%,
                    rgba(2,30,22,.94)
                );
            backdrop-filter: blur(3px);
        }

        body.admin-mode .login-panel {
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.96),
                    rgba(247,250,248,.96)
                );
        }

        body.owner-mode .brand-panel {
            background:
                linear-gradient(
                    145deg,
                    rgba(95,9,9,.88),
                    rgba(45,5,5,.90) 55%,
                    rgba(15,5,5,.94)
                );
            backdrop-filter: blur(3px);
        }

        body.owner-mode .login-panel {
            background:
                linear-gradient(
                    145deg,
                    rgba(17,15,15,.95),
                    rgba(7,7,7,.97)
                );
        }

        body.admin-mode .portal::before,
        body.owner-mode .portal::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            border-radius: inherit;
            background:
                linear-gradient(
                    120deg,
                    rgba(255,255,255,.035),
                    transparent 30%,
                    transparent 70%,
                    rgba(255,255,255,.025)
                );
        }

        .brand-panel,
        .login-panel {
            overflow: hidden;
        }

        body.admin-mode .brand-panel::before {
            content: "";
            position: absolute;
            inset: auto -70px -100px auto;
            width: 330px;
            height: 330px;
            border-radius: 50%;
            background:
                radial-gradient(
                    circle,
                    rgba(40,180,91,.18),
                    transparent 64%
                );
            filter: blur(4px);
        }

        body.owner-mode .brand-panel::before {
            content: "";
            position: absolute;
            inset: auto -80px -90px auto;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            background:
                radial-gradient(
                    circle,
                    rgba(239,46,46,.22),
                    transparent 66%
                );
            filter: blur(4px);
        }

        @media (max-width: 900px) {

            .admin-cheetah-art,
            .owner-lion-art {
                background-size: auto 54%;
                background-repeat: no-repeat;
            }

            .admin-cheetah-art {
                background-position: 18% 5%;
                opacity: .36;
            }

            .owner-lion-art {
                background-position: 78% 4%;
                opacity: .39;
            }
        }

        @media (max-width: 520px) {

            .admin-cheetah-art,
            .owner-lion-art {
                background-size: auto 45%;
            }

            .admin-cheetah-art {
                background-position: 5% 4%;
                opacity: .34;
            }

            .owner-lion-art {
                background-position: 84% 3%;
                opacity: .37;
            }
        }


        /* =================================================
           FINAL ARTWORK OVERRIDES
           Admin stays clean green.
           Owner lion stays ONLY inside the red brand panel.
        ================================================== */

        .admin-leaf,
        .admin-cheetah-art,
        .owner-lion-art {
            display: none !important;
        }

        body.admin-mode .page {
            background:
                radial-gradient(
                    circle at 12% 12%,
                    rgba(34,126,73,.12),
                    transparent 26%
                ),
                radial-gradient(
                    circle at 88% 84%,
                    rgba(12,88,51,.10),
                    transparent 30%
                ),
                linear-gradient(
                    135deg,
                    #f8f4e9,
                    #eee8da
                ) !important;
        }

        body.admin-mode .brand-panel {
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-position: 72% center !important;
            background-blend-mode: normal !important;
        }

        body.owner-mode .page {
            background:
                radial-gradient(
                    circle at 18% 12%,
                    rgba(182,20,20,.16),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 82% 82%,
                    rgba(114,0,0,.18),
                    transparent 34%
                ),
                linear-gradient(
                    135deg,
                    #150909 0%,
                    #1c0a0a 48%,
                    #090707 100%
                ) !important;
        }

        body.owner-mode .brand-panel {
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-position: 68% center !important;
            background-blend-mode: normal !important;
        }

        body.owner-mode .brand-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(
                    90deg,
                    rgba(70,7,7,.24) 0%,
                    rgba(38,3,3,.10) 55%,
                    rgba(10,0,0,.04) 100%
                ),
                radial-gradient(
                    circle at 78% 45%,
                    rgba(255,35,35,.12),
                    transparent 34%
                );
        }

        body.owner-mode .brand-header,
        body.owner-mode .brand-content,
        body.owner-mode .route {
            position: relative;
            z-index: 2;
        }


        body.admin-mode .brand-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(
                    90deg,
                    rgba(2,64,37,.24) 0%,
                    rgba(2,42,27,.10) 55%,
                    rgba(0,15,9,.04) 100%
                ),
                radial-gradient(
                    circle at 78% 45%,
                    rgba(68,190,112,.12),
                    transparent 34%
                );
        }

        body.admin-mode .brand-header,
        body.admin-mode .brand-content,
        body.admin-mode .route {
            position: relative;
            z-index: 2;
        }

        @media (max-width: 900px) {
            body.admin-mode .brand-panel {
                background-position: 74% 30% !important;
                background-size: cover !important;
            }
        }

        @media (max-width: 520px) {
            body.admin-mode .brand-panel {
                background-position: 78% 24% !important;
                background-size: cover !important;
            }
        }

        @media (max-width: 900px) {
            body.owner-mode .brand-panel {
                background-position: 72% 30% !important;
                background-size: cover !important;
            }
        }

        @media (max-width: 520px) {
            body.owner-mode .brand-panel {
                background-position: 76% 24% !important;
                background-size: cover !important;
            }
        }

        /* =================================================
   OWNER LOGIN INPUT VISIBILITY FIX
================================================= */

body.owner-mode .field-control input {
    background: #171313 !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
    border: 1px solid rgba(213, 159, 66, 0.55) !important;
    caret-color: #f2cd73;
}

body.owner-mode .field-control input::placeholder {
    color: #b8aaa4 !important;
    opacity: 1;
}

body.owner-mode .field-control > i {
    color: #d8bd76 !important;
}

body.owner-mode .password-toggle {
    color: #e1d7d2 !important;
}

body.owner-mode .field-control input:focus {
    background: #1b1515 !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
    border-color: #e23b3b !important;
    box-shadow: 0 0 0 4px rgba(226, 59, 59, 0.12) !important;
}

/* Chrome / Brave / Edge autofill */
body.owner-mode .field-control input:-webkit-autofill,
body.owner-mode .field-control input:-webkit-autofill:hover,
body.owner-mode .field-control input:-webkit-autofill:focus,
body.owner-mode .field-control input:-webkit-autofill:active {
    -webkit-text-fill-color: #ffffff !important;
    caret-color: #f2cd73 !important;
    -webkit-box-shadow: 0 0 0 1000px #171313 inset !important;
    box-shadow: 0 0 0 1000px #171313 inset !important;
    border-color: rgba(213, 159, 66, 0.55) !important;
}
    </style>

</head>


<body class="<?php echo $portal === "owner" ? "owner-mode" : "admin-mode"; ?>">


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




    <nav class="portal-switcher" aria-label="Choose login portal">
        <a
            href="?portal=admin"
            class="portal-switch admin-switch <?php echo $portal === "admin" ? "active" : ""; ?>"
        >
            <i class="fa-solid fa-shield-halved"></i>
            <span>
                <strong>Admin Portal</strong>
                <small>Daily operations</small>
            </span>
        </a>

        <a
            href="?portal=owner"
            class="portal-switch owner-switch <?php echo $portal === "owner" ? "active" : ""; ?>"
        >
            <i class="fa-solid fa-crown"></i>
            <span>
                <strong>Owner Portal</strong>
                <small>Executive access</small>
            </span>
        </a>
    </nav>

    <div class="portal">


        <!-- =============================================
             BRAND
        ============================================== -->

        <section
            class="brand-panel"
            style="
                background-image:
                    <?php if ($portal === "owner"): ?>
                        linear-gradient(
                            90deg,
                            rgba(78, 8, 8, 0.96) 0%,
                            rgba(53, 5, 5, 0.84) 43%,
                            rgba(24, 2, 2, 0.40) 100%
                        ),
                        url('<?php echo htmlspecialchars(
                            $ownerLionImage,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>');
                    <?php else: ?>
                        linear-gradient(
                            90deg,
                            rgba(3, 72, 42, 0.96) 0%,
                            rgba(3, 58, 34, 0.84) 43%,
                            rgba(2, 30, 20, 0.40) 100%
                        ),
                        url('<?php echo htmlspecialchars(
                            $adminCheetahImage,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>');
                    <?php endif; ?>
            "
        >


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
                        <?php echo $portal === "owner"
                            ? "Executive Owner Portal"
                            : "Operations Portal"; ?>
                    </span>

                </div>


            </div>



            <div class="brand-content">


                <div class="brand-eyebrow">
                    <?php echo $portal === "owner"
                        ? "Owner Command Center"
                        : "Travel Management"; ?>
                </div>


                <h1>
                    <?php if ($portal === "owner"): ?>
                        Command.
                        Oversee.
                        <span>Grow.</span>
                    <?php else: ?>
                        Behind every
                        unforgettable
                        <span>journey.</span>
                    <?php endif; ?>
                </h1>


                <p>
                    <?php if ($portal === "owner"): ?>
                        Your executive command center for complete
                        visibility, real-time insights and business
                        performance at a glance.
                    <?php else: ?>
                        One secure workspace for reservations,
                        customer enquiries, payments and safari
                        operations.
                    <?php endif; ?>
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
                    <?php echo $portal === "owner" ? "Owner Access" : "Administrator Access"; ?>
                </p>


                <h2>
                    <?php echo $portal === "owner"
                        ? "Welcome Back, Boss"
                        : "Welcome back"; ?>
                </h2>


                <p class="login-description">
                    <?php if ($portal === "owner"): ?>
                        Enter your private owner credentials to access
                        executive oversight and business intelligence.
                    <?php else: ?>
                        Enter your administrator credentials
                        to continue to the Sprinter management
                        dashboard.
                    <?php endif; ?>
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

                    <input
                        type="hidden"
                        name="portal"
                        value="<?php echo htmlspecialchars($portal, ENT_QUOTES, "UTF-8"); ?>"
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
                                placeholder="<?php echo $portal === "owner" ? "Enter owner username" : "Enter admin username"; ?>"
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

                        <?php echo $portal === "owner"
                            ? "Access Owner Dashboard"
                            : "Continue to Dashboard"; ?>

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>



                <div class="portal-feature-strip">

                    <?php if ($portal === "owner"): ?>

                        <div class="portal-feature">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>
                                <strong>Revenue</strong>
                                <small>Live performance</small>
                            </span>
                        </div>

                        <div class="portal-feature">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>
                                <strong>Audit Logs</strong>
                                <small>System activity</small>
                            </span>
                        </div>

                        <div class="portal-feature">
                            <i class="fa-solid fa-eye"></i>
                            <span>
                                <strong>Full View</strong>
                                <small>Complete oversight</small>
                            </span>
                        </div>

                    <?php else: ?>

                        <div class="portal-feature">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span>
                                <strong>Bookings</strong>
                                <small>Reservations</small>
                            </span>
                        </div>

                        <div class="portal-feature">
                            <i class="fa-solid fa-users"></i>
                            <span>
                                <strong>Customers</strong>
                                <small>Client records</small>
                            </span>
                        </div>

                        <div class="portal-feature">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>
                                <strong>Payments</strong>
                                <small>Transactions</small>
                            </span>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="secure-row">

                    <i class="fa-solid fa-shield-halved"></i>

                    <?php echo $portal === "owner" ? "Secure owner authentication" : "Secure administrative access"; ?>

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