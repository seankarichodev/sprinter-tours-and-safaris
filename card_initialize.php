<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   PAYSTACK CARD PAYMENT INITIALIZATION
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
        "Sprinter payment error: Paystack secret key is missing."
    );

    header(
        "Location: my_bookings.php?payment_error=config"
    );

    exit();

}


if (
    !defined("PAYSTACK_CALLBACK_URL") ||
    empty(PAYSTACK_CALLBACK_URL)
) {

    error_log(
        "Sprinter payment error: Paystack callback URL is missing."
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
   REQUIRE POST REQUEST
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"]
    !== "POST"
) {

    header(
        "Location: my_bookings.php"
    );

    exit();

}


/* =========================================================
   VALIDATE BOOKING ID
========================================================= */

$booking_id =
    filter_input(
        INPUT_POST,
        "booking_id",
        FILTER_VALIDATE_INT
    );


if (
    !$booking_id ||
    $booking_id < 1
) {

    header(
        "Location: my_bookings.php?payment_error=booking"
    );

    exit();

}


/* =========================================================
   GET BOOKING FROM DATABASE

   IMPORTANT:
   We retrieve the amount from the database.
   We NEVER trust an amount submitted by the browser.
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            email,
            tour_name,
            amount,
            payment,
            payment_status
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    error_log(
        "Sprinter payment database prepare error: "
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


$result =
    $stmt->get_result();


$booking =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   BOOKING MUST EXIST AND BELONG TO CUSTOMER
========================================================= */

if (!$booking) {

    header(
        "Location: my_bookings.php?payment_error=booking"
    );

    exit();

}


/* =========================================================
   VERIFY PAYMENT METHOD
========================================================= */

if (
    $booking["payment"]
    !== "Card"
) {

    header(
        "Location: my_bookings.php?payment_error=method"
    );

    exit();

}


/* =========================================================
   PREVENT DUPLICATE PAYMENT
========================================================= */

if (
    strtolower(
        trim(
            $booking["payment_status"]
        )
    )
    === "paid"
) {

    header(
        "Location: receipt.php?id="
        . $booking_id
    );

    exit();

}


/* =========================================================
   VALIDATE DATABASE AMOUNT
========================================================= */

$booking_amount =
    (float) $booking["amount"];


if ($booking_amount <= 0) {

    error_log(
        "Sprinter payment error: Invalid booking amount. Booking ID: "
        . $booking_id
    );

    header(
        "Location: my_bookings.php?payment_error=amount"
    );

    exit();

}


/* =========================================================
   PAYSTACK AMOUNT

   Paystack expects the amount in subunits.

   Example:

   KES 25,000
   becomes
   2,500,000
========================================================= */

$amount =
    (int) round(
        $booking_amount * 100
    );


/* =========================================================
   BUILD PAYSTACK REQUEST
========================================================= */

$data = [

    "email" =>
        $booking["email"],

    "amount" =>
        $amount,

    "currency" =>
        "KES",

    "callback_url" =>
        PAYSTACK_CALLBACK_URL,

    "metadata" => [

        "booking_id" =>
            $booking_id,

        "user_id" =>
            $user_id,

        "tour_name" =>
            $booking["tour_name"]

    ]

];


/* =========================================================
   INITIALIZE CURL
========================================================= */

$curl =
    curl_init();


if ($curl === false) {

    error_log(
        "Sprinter payment error: Unable to initialize cURL."
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();

}


/* =========================================================
   PAYSTACK API REQUEST
========================================================= */

curl_setopt_array(
    $curl,
    [

        CURLOPT_URL =>
            "https://api.paystack.co/transaction/initialize",

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_POST =>
            true,

        CURLOPT_POSTFIELDS =>
            json_encode($data),

        CURLOPT_HTTPHEADER => [

            "Authorization: Bearer "
            . PAYSTACK_SECRET_KEY,

            "Content-Type: application/json",

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
        "Sprinter Paystack connection error: "
        . $curl_error
    );

    header(
        "Location: my_bookings.php?payment_error=connection"
    );

    exit();

}


/* =========================================================
   DECODE PAYSTACK RESPONSE
========================================================= */

$paystack_response =
    json_decode(
        $response,
        true
    );


if (
    !is_array(
        $paystack_response
    )
) {

    error_log(
        "Sprinter Paystack invalid JSON response. HTTP "
        . $http_code
    );

    header(
        "Location: my_bookings.php?payment_error=provider"
    );

    exit();

}


/* =========================================================
   SUCCESS

   Paystack returns an authorization_url.
   Redirect the customer there.
========================================================= */

if (
    $http_code >= 200 &&
    $http_code < 300 &&
    isset(
        $paystack_response["status"]
    ) &&
    $paystack_response["status"] === true &&
    !empty(
        $paystack_response["data"]["authorization_url"]
    )
) {

    $authorization_url =
        $paystack_response["data"]["authorization_url"];


    /*
     * Safety check:
     * Only redirect to HTTPS.
     */

    if (
        filter_var(
            $authorization_url,
            FILTER_VALIDATE_URL
        ) &&
        str_starts_with(
            $authorization_url,
            "https://"
        )
    ) {

        header(
            "Location: "
            . $authorization_url
        );

        exit();

    }

}


/* =========================================================
   PAYSTACK ERROR

   Log technical information on the server instead of
   exposing the entire Paystack response to the customer.
========================================================= */

$provider_message =
    $paystack_response["message"]
    ?? "Unknown Paystack initialization error";


error_log(

    "Sprinter Paystack initialization failed. "
    . "Booking ID: "
    . $booking_id
    . " | HTTP: "
    . $http_code
    . " | Message: "
    . $provider_message

);


header(
    "Location: my_bookings.php?payment_error=provider"
);

exit();

?>