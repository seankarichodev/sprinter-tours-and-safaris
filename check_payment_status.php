<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   M-PESA PAYMENT STATUS CHECKER
========================================================= */

session_start();

header(
    "Content-Type: application/json"
);

require_once __DIR__ . "/db.php";


/* =========================================================
   RESPONSE HELPER
========================================================= */

function sendJson(
    array $payload,
    int $httpCode = 200
): void {

    http_response_code(
        $httpCode
    );

    echo json_encode(
        $payload
    );

    exit();
}


/* =========================================================
   REQUIRE CUSTOMER LOGIN
========================================================= */

if (!isset($_SESSION["user_id"])) {

    sendJson(
        [
            "success" => false,
            "message" => "Not logged in"
        ],
        401
    );
}


$user_id =
    (int) $_SESSION["user_id"];


/* =========================================================
   GET BOOKING ID
========================================================= */

$booking_id =
    filter_input(
        INPUT_GET,
        "booking_id",
        FILTER_VALIDATE_INT
    );


if (
    !$booking_id ||
    $booking_id < 1
) {

    sendJson(
        [
            "success" => false,
            "message" => "Invalid booking"
        ],
        400
    );
}


/* =========================================================
   GET CUSTOMER M-PESA BOOKING

   IMPORTANT:
   user_id prevents one customer from checking
   another customer's payment status.
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            payment_status,
            mpesa_receipt
        FROM bookings
        WHERE id = ?
        AND user_id = ?
        AND payment = 'Mpesa'
        LIMIT 1
        "
    );


if (!$stmt) {

    sendJson(
        [
            "success" => false,
            "message" => "Database error"
        ],
        500
    );
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


/* =========================================================
   BOOKING NOT FOUND
========================================================= */

if (!$booking) {

    sendJson(
        [
            "success" => false,
            "message" => "Booking not found"
        ],
        404
    );
}


/* =========================================================
   NORMALIZE STATUS
========================================================= */

$rawStatus =
    trim(
        (string) (
            $booking["payment_status"]
            ?? "Pending"
        )
    );


$status =
    strtolower(
        $rawStatus
    );


/* =========================================================
   PAID
========================================================= */

if ($status === "paid") {

    sendJson(
        [
            "success" => true,
            "status" => "Paid",
            "paid" => true,
            "finished" => true,
            "message" =>
                "Payment confirmed successfully.",
            "receipt" =>
                $booking["mpesa_receipt"]
                ?? null
        ]
    );
}


/* =========================================================
   CANCELLED
========================================================= */

if ($status === "cancelled") {

    sendJson(
        [
            "success" => true,
            "status" => "Cancelled",
            "paid" => false,
            "finished" => true,
            "message" =>
                "The M-Pesa payment was cancelled."
        ]
    );
}


/* =========================================================
   FAILED
========================================================= */

if ($status === "failed") {

    sendJson(
        [
            "success" => true,
            "status" => "Failed",
            "paid" => false,
            "finished" => true,
            "message" =>
                "The M-Pesa payment could not be completed."
        ]
    );
}


/* =========================================================
   TIMED OUT
========================================================= */

if (
    $status === "timedout" ||
    $status === "timed out"
) {

    sendJson(
        [
            "success" => true,
            "status" => "TimedOut",
            "paid" => false,
            "finished" => true,
            "message" =>
                "The M-Pesa payment request timed out."
        ]
    );
}


/* =========================================================
   PENDING / PROCESSING
========================================================= */

sendJson(
    [
        "success" => true,
        "status" => "Pending",
        "paid" => false,
        "finished" => false,
        "message" =>
            "Waiting for M-Pesa confirmation."
    ]
);

?>