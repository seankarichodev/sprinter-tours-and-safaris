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

$adminId = (int) $_SESSION["admin_id"];
$adminUsername = (string) $_SESSION["admin_username"];
$adminRole = strtolower(trim((string) $_SESSION["admin_role"]));


/* =========================================================
   VALIDATE ROLE
========================================================= */

if (!in_array($adminRole, ["owner", "admin"], true)) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

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
        && strtolower(trim((string) $_SESSION["admin_role"])) === "owner";
}

function isAdmin(): bool
{
    return isset($_SESSION["admin_role"])
        && strtolower(trim((string) $_SESSION["admin_role"])) === "admin";
}


/* =========================================================
   OWNER-ONLY GUARD
========================================================= */

function requireOwner(): void
{
    if (!isOwner()) {
        http_response_code(403);
        exit("Owner access required.");
    }
}


/* =========================================================
   ADMIN-ONLY GUARD
========================================================= */

function requireAdmin(): void
{
    if (isAdmin()) {
        return;
    }

    if (isOwner()) {
        header("Location: owner_dashboard.php");
        exit();
    }

    http_response_code(403);
    exit("Administrator access required.");
}


/* =========================================================
   SHARED STAFF GUARD
========================================================= */

function requireStaff(): void
{
    if (!isAdmin() && !isOwner()) {
        http_response_code(403);
        exit("Staff access required.");
    }
}
