<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   ADMIN AUTHENTICATION GUARD
========================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/* =========================================================
   REQUIRE AUTHENTICATED ADMIN
========================================================= */

if (
    !isset($_SESSION["admin_id"]) ||
    !isset($_SESSION["admin_username"])
) {

    header("Location: admin_login.php");
    exit();
}


/* =========================================================
   CURRENT ADMIN
========================================================= */

$adminId =
    (int) $_SESSION["admin_id"];

$adminUsername =
    (string) $_SESSION["admin_username"];

?>