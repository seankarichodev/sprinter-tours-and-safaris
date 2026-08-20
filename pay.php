<?php

session_start();

require_once "db.php";


/* =========================================================
   REQUIRE LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: auth.php");
    exit();

}


$user_id =
    (int) $_SESSION["user_id"];


/* =========================================================
   VALIDATE BOOKING ID
========================================================= */

$booking_id =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


if (
    !$booking_id ||
    $booking_id < 1
) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   GET BOOKING

   Security:
   The booking must belong to the logged-in customer.
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            payment,
            payment_status
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    die(
        "Unable to process payment request."
    );

}


$stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$booking =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   BOOKING NOT FOUND
========================================================= */

if (!$booking) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   ALREADY PAID
========================================================= */

if (
    strtolower(
        $booking["payment_status"]
    ) === "paid"
) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   ROUTE TO PAYMENT PROVIDER
========================================================= */

switch ($booking["payment"]) {


    /* =====================================================
       M-PESA
    ===================================================== */

    case "Mpesa":

        header(
            "Location: mpesa_checkout.php?id="
            . $booking_id
        );

        exit();



    /* =====================================================
       CARD
    ===================================================== */

    case "Card":

        header(
            "Location: card_checkout.php?id="
            . $booking_id
        );

        exit();



    /* =====================================================
       PAYPAL
    ===================================================== */

    case "PayPal":

        header(
            "Location: paypal_checkout.php?id="
            . $booking_id
        );

        exit();



    /* =====================================================
       UNKNOWN METHOD
    ===================================================== */

    default:

        header(
            "Location: my_bookings.php?payment_error=1"
        );

        exit();

}
?>