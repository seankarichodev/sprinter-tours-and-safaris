<?php
require_once __DIR__ . "/admin_auth.php";
requireAdmin();

/*
 * Legacy endpoint retired.
 * All Admin booking changes must go through admin_booking_view.php,
 * where payment integrity and paid-booking protection are enforced.
 */
$bookingId = 0;

if (isset($_GET["id"])) {
    $bookingId = (int) $_GET["id"];
} elseif (isset($_POST["id"])) {
    $bookingId = (int) $_POST["id"];
}

if ($bookingId > 0) {
    header("Location: admin_booking_view.php?id=" . $bookingId);
    exit();
}

header("Location: bookings.php");
exit();
