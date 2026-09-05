<?php
require_once __DIR__ . "/admin_auth.php";
requireAdmin();

/*
 * Legacy hard-delete endpoint retired.
 *
 * Bookings are business/payment records and must not be physically deleted
 * from this endpoint. Operational cancellation is handled through
 * admin_booking_view.php. Paid bookings remain protected until a proper
 * refund workflow exists.
 */
$bookingId = 0;

if (isset($_POST["id"])) {
    $bookingId = (int) $_POST["id"];
} elseif (isset($_GET["id"])) {
    $bookingId = (int) $_GET["id"];
}

if ($bookingId > 0) {
    header("Location: admin_booking_view.php?id=" . $bookingId . "&legacy_delete=disabled");
    exit();
}

header("Location: bookings.php");
exit();
