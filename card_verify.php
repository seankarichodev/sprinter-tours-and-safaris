<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   PAYSTACK PAYMENT VERIFICATION
========================================================= */


/* =========================================================
   START SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}


/* =========================================================
   LOAD DATABASE + PAYSTACK CONFIG
========================================================= */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/paystack_config.php";


/* =========================================================
   CHECK PAYSTACK CONFIGURATION
========================================================= */

if (
    !defined("PAYSTACK_SECRET_KEY") ||
    empty(PAYSTACK_SECRET_KEY)
) {

    error_log(
        "Sprinter verification error: Paystack secret key missing."
    );

    header(
        "Location: my_bookings.php?payment_error=config"
    );

    exit();

}


/* =========================================================
   REQUIRE LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header(
        "Location: auth.php"
    );

    exit();

}


$user_id =
    (int) $_SESSION["user_id"];


/* =========================================================
   GET PAYSTACK REFERENCE
========================================================= */

$reference =
    trim(
        $_GET["reference"]
        ?? ""
    );


if ($reference === "") {

    header(
        "Location: my_bookings.php?payment_error=reference"
    );

    exit();

}


/* =========================================================
   BASIC REFERENCE VALIDATION
========================================================= */

if (
    strlen($reference) > 150 ||
    !preg_match(
        '/^[A-Za-z0-9._-]+$/',
        $reference
    )
) {

    error_log(
        "Sprinter verification error: Invalid payment reference format."
    );

    header(
        "Location: my_bookings.php?payment_error=reference"
    );

    exit();

}


/* =========================================================
   BUILD PAYSTACK VERIFY URL
========================================================= */

$url =
    "https://api.paystack.co/transaction/verify/"
    . rawurlencode($reference);


/* =========================================================
   INITIALIZE CURL
========================================================= */

$curl =
    curl_init();


if ($curl === false) {

    error_log(
        "Sprinter verification error: Unable to initialize cURL."
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();

}


/* =========================================================
   PAYSTACK VERIFICATION REQUEST
========================================================= */

curl_setopt_array(
    $curl,
    [

        CURLOPT_URL =>
            $url,

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_HTTPHEADER => [

            "Authorization: Bearer "
            . PAYSTACK_SECRET_KEY,

            "Accept: application/json",

            "Cache-Control: no-cache"

        ],

        CURLOPT_CONNECTTIMEOUT =>
            10,

        CURLOPT_TIMEOUT =>
            30

    ]
);


/* =========================================================
   SEND REQUEST
========================================================= */

$response =
    curl_exec($curl);


$curl_error =
    curl_error($curl);


$http_code =
    curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


curl_close($curl);


/* =========================================================
   CONNECTION ERROR
========================================================= */

if ($response === false) {

    error_log(
        "Sprinter Paystack verification connection error: "
        . $curl_error
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();

}


/* =========================================================
   DECODE RESPONSE
========================================================= */

$result =
    json_decode(
        $response,
        true
    );


if (!is_array($result)) {

    error_log(
        "Sprinter verification error: Invalid Paystack JSON response."
    );

    header(
        "Location: my_bookings.php?payment_error=provider"
    );

    exit();

}


/* =========================================================
   VERIFY PAYSTACK RESPONSE
========================================================= */

if (
    $http_code < 200 ||
    $http_code >= 300 ||
    !isset($result["status"]) ||
    $result["status"] !== true ||
    !isset($result["data"]) ||
    !is_array($result["data"])
) {

    $provider_message =
        $result["message"]
        ?? "Unknown verification error";


    error_log(
        "Sprinter Paystack verification failed. "
        . "HTTP: "
        . $http_code
        . " | Message: "
        . $provider_message
    );


    header(
        "Location: my_bookings.php?payment_error=verification"
    );

    exit();

}


$payment =
    $result["data"];


/* =========================================================
   PAYMENT MUST ACTUALLY BE SUCCESSFUL
========================================================= */

if (
    strtolower(
        (string) (
            $payment["status"]
            ?? ""
        )
    )
    !== "success"
) {

    header(
        "Location: my_bookings.php?payment_error=unsuccessful"
    );

    exit();

}


/* =========================================================
   VERIFY RETURNED REFERENCE
========================================================= */

$verified_reference =
    trim(
        (string) (
            $payment["reference"]
            ?? ""
        )
    );


if (
    $verified_reference === "" ||
    !hash_equals(
        $reference,
        $verified_reference
    )
) {

    error_log(
        "Sprinter verification error: Payment reference mismatch."
    );

    header(
        "Location: my_bookings.php?payment_error=reference"
    );

    exit();

}


/* =========================================================
   GET PAYSTACK METADATA
========================================================= */

$metadata =
    $payment["metadata"]
    ?? [];


if (!is_array($metadata)) {

    error_log(
        "Sprinter verification error: Payment metadata missing."
    );

    header(
        "Location: my_bookings.php?payment_error=metadata"
    );

    exit();

}


/* =========================================================
   GET BOOKING ID FROM VERIFIED PAYSTACK METADATA
========================================================= */

$booking_id =
    (int) (
        $metadata["booking_id"]
        ?? 0
    );


$metadata_user_id =
    (int) (
        $metadata["user_id"]
        ?? 0
    );


if ($booking_id < 1) {

    error_log(
        "Sprinter verification error: Invalid booking metadata."
    );

    header(
        "Location: my_bookings.php?payment_error=metadata"
    );

    exit();

}


/* =========================================================
   VERIFY METADATA USER
========================================================= */

if (
    $metadata_user_id < 1 ||
    $metadata_user_id !== $user_id
) {

    error_log(
        "Sprinter verification error: User metadata mismatch."
    );

    header(
        "Location: my_bookings.php?payment_error=ownership"
    );

    exit();

}


/* =========================================================
   GET BOOKING FROM DATABASE

   Booking must belong to the logged-in customer.
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            email,
            amount,
            payment,
            payment_status,
            payment_reference
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    error_log(
        "Sprinter verification database prepare error: "
        . $conn->error
    );

    header(
        "Location: my_bookings.php?payment_error=database"
    );

    exit();

}


$stmt->bind_param(
    "ii",
    $booking_id,
    $user_id
);


$stmt->execute();


$booking =
    $stmt
        ->get_result()
        ->fetch_assoc();


$stmt->close();


if (!$booking) {

    header(
        "Location: my_bookings.php?payment_error=booking"
    );

    exit();

}


/* =========================================================
   VERIFY THIS IS A CARD BOOKING
========================================================= */

if (
    $booking["payment"]
    !== "Card"
) {

    error_log(
        "Sprinter verification error: Booking payment method mismatch."
    );

    header(
        "Location: my_bookings.php?payment_error=method"
    );

    exit();

}


/* =========================================================
   IF ALREADY PAID

   Do not perform another database payment update.
========================================================= */

if (
    strtolower(
        trim(
            $booking["payment_status"]
        )
    )
    === "paid"
) {

    /*
     * If the booking already has a payment reference,
     * ensure this callback matches it.
     */

    $saved_reference =
        trim(
            (string) (
                $booking["payment_reference"]
                ?? ""
            )
        );


    if (
        $saved_reference !== "" &&
        !hash_equals(
            $saved_reference,
            $reference
        )
    ) {

        error_log(
            "Sprinter verification error: Paid booking reference mismatch."
        );

        header(
            "Location: my_bookings.php?payment_error=reference"
        );

        exit();

    }


    header(
        "Location: receipt.php?id="
        . $booking_id
    );

    exit();

}


/* =========================================================
   VERIFY AMOUNT

   Database amount:
   KES 25,000

   Paystack amount:
   2,500,000 subunits
========================================================= */

$expected_amount =
    (int) round(
        (float) $booking["amount"]
        * 100
    );


$paid_amount =
    (int) (
        $payment["amount"]
        ?? 0
    );


if (
    $expected_amount <= 0 ||
    $paid_amount !== $expected_amount
) {

    error_log(
        "Sprinter verification error: Amount mismatch. "
        . "Booking ID: "
        . $booking_id
        . " | Expected: "
        . $expected_amount
        . " | Received: "
        . $paid_amount
    );

    header(
        "Location: my_bookings.php?payment_error=amount"
    );

    exit();

}


/* =========================================================
   VERIFY CURRENCY
========================================================= */

$currency =
    strtoupper(
        trim(
            (string) (
                $payment["currency"]
                ?? ""
            )
        )
    );


if ($currency !== "KES") {

    error_log(
        "Sprinter verification error: Unexpected currency. "
        . "Booking ID: "
        . $booking_id
        . " | Currency: "
        . $currency
    );

    header(
        "Location: my_bookings.php?payment_error=currency"
    );

    exit();

}


/* =========================================================
   VERIFY CUSTOMER EMAIL

   Paystack should report the same customer email used
   when the transaction was initialized.
========================================================= */

$paystack_email =
    strtolower(
        trim(
            (string) (
                $payment["customer"]["email"]
                ?? ""
            )
        )
    );


$booking_email =
    strtolower(
        trim(
            (string) $booking["email"]
        )
    );


if (
    $paystack_email === "" ||
    $paystack_email !== $booking_email
) {

    error_log(
        "Sprinter verification error: Customer email mismatch. "
        . "Booking ID: "
        . $booking_id
    );

    header(
        "Location: my_bookings.php?payment_error=customer"
    );

    exit();

}


/* =========================================================
   MARK BOOKING AS PAID

   The WHERE clause includes Pending so an already-paid
   booking cannot simply be overwritten.
========================================================= */

$update =
    $conn->prepare(
        "
        UPDATE bookings
        SET
            payment_status = 'Paid',
            payment_reference = ?
        WHERE id = ?
        AND user_id = ?
        AND payment = 'Card'
        AND payment_status = 'Pending'
        "
    );


if (!$update) {

    error_log(
        "Sprinter payment update prepare error: "
        . $conn->error
    );

    header(
        "Location: my_bookings.php?payment_error=database"
    );

    exit();

}


$update->bind_param(
    "sii",
    $reference,
    $booking_id,
    $user_id
);


if (!$update->execute()) {

    error_log(
        "Sprinter payment update failed: "
        . $update->error
    );

    $update->close();


    header(
        "Location: my_bookings.php?payment_error=database"
    );

    exit();

}


$affected_rows =
    $update->affected_rows;


$update->close();


/* =========================================================
   CONFIRM DATABASE UPDATE
========================================================= */

if ($affected_rows !== 1) {

    error_log(
        "Sprinter verification warning: Booking was verified "
        . "but payment status update affected "
        . $affected_rows
        . " rows. Booking ID: "
        . $booking_id
    );

    header(
        "Location: my_bookings.php?payment_error=database"
    );

    exit();

}


/* =========================================================
   SUCCESS
========================================================= */

header(
    "Location: my_bookings.php?payment=success"
);

exit();

?>