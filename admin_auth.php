<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   ADMIN / OWNER AUTHENTICATION GUARD
========================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/* =========================================================
   REQUIRE AUTHENTICATED STAFF ACCOUNT
========================================================= */

if (
    !isset($_SESSION["admin_id"])
    || !isset($_SESSION["admin_username"])
    || !isset($_SESSION["admin_role"])
) {

    header("Location: admin_login.php");
    exit();
}


/* =========================================================
   CURRENT ACCOUNT
========================================================= */

$adminId =
    (int) $_SESSION["admin_id"];

$adminUsername =
    (string) $_SESSION["admin_username"];

$adminRole =
    strtolower(
        trim(
            (string) $_SESSION["admin_role"]
        )
    );


if (
    !in_array(
        $adminRole,
        ["owner", "admin"],
        true
    )
) {

    $_SESSION = [];
    session_destroy();

    header("Location: admin_login.php");
    exit();
}


/* =========================================================
   ROLE HELPERS
========================================================= */

function isOwner(): bool
{
    return isset($_SESSION["admin_role"])
        && $_SESSION["admin_role"] === "owner";
}


function requireOwner(): void
{
    if (!isOwner()) {

        http_response_code(403);

        exit(
            "Owner access required."
        );
    }
}
