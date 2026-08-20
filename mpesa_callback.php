<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   M-PESA STK CALLBACK
========================================================= */

require_once __DIR__ . "/db.php";


/* =========================================================
   RESPONSE HELPER
========================================================= */

function sendMpesaResponse(
    int $code = 0,
    string $description = "Callback received"
): void {

    header(
        "Content-Type: application/json"
    );

    echo json_encode(
        [
            "ResultCode" => $code,
            "ResultDesc" => $description
        ]
    );

    exit();
}


/* =========================================================
   FAILURE STATUS CLASSIFIER

   We use the Daraja result description as the main signal
   and common result codes as additional hints.

   Final database values:
   - Cancelled
   - Failed
   - TimedOut
========================================================= */

function classifyMpesaFailure(
    ?int $resultCode,
    string $description
): string {

    $description =
        strtolower(
            trim($description)
        );


    /* =====================================================
       CUSTOMER CANCELLED
    ===================================================== */

    if (
        $resultCode === 1032 ||
        str_contains(
            $description,
            "cancel"
        ) ||
        str_contains(
            $description,
            "declin"
        )
    ) {

        return "Cancelled";
    }


    /* =====================================================
       TIMEOUT / NO RESPONSE
    ===================================================== */

    if (
        $resultCode === 1037 ||
        str_contains(
            $description,
            "timeout"
        ) ||
        str_contains(
            $description,
            "timed out"
        ) ||
        str_contains(
            $description,
            "unreachable"
        ) ||
        str_contains(
            $description,
            "no response"
        )
    ) {

        return "TimedOut";
    }


    /* =====================================================
       EVERYTHING ELSE IS A FAILED PAYMENT
    ===================================================== */

    return "Failed";
}


/* =========================================================
   READ CALLBACK BODY
========================================================= */

$rawData =
    file_get_contents(
        "php://input"
    );


if (
    $rawData === false ||
    trim($rawData) === ""
) {

    http_response_code(400);

    sendMpesaResponse(
        1,
        "Empty callback"
    );
}


/* =========================================================
   DECODE JSON
========================================================= */

$data =
    json_decode(
        $rawData,
        true
    );


if (!is_array($data)) {

    error_log(
        "Sprinter M-Pesa callback: Invalid JSON."
    );

    http_response_code(400);

    sendMpesaResponse(
        1,
        "Invalid callback"
    );
}


/* =========================================================
   OPTIONAL DEVELOPMENT LOGGING

   Keep false for normal use.
========================================================= */

$debug_logging = false;


if ($debug_logging) {

    file_put_contents(

        __DIR__
        . "/mpesa_callback_log.txt",

        date(
            "Y-m-d H:i:s"
        )
        . PHP_EOL
        . $rawData
        . PHP_EOL
        . "------------------------"
        . PHP_EOL,

        FILE_APPEND
        | LOCK_EX
    );
}


/* =========================================================
   GET STK CALLBACK
========================================================= */

if (
    !isset(
        $data["Body"]["stkCallback"]
    ) ||
    !is_array(
        $data["Body"]["stkCallback"]
    )
) {

    error_log(
        "Sprinter M-Pesa callback: stkCallback missing."
    );

    http_response_code(400);

    sendMpesaResponse(
        1,
        "Invalid callback structure"
    );
}


$callback =
    $data["Body"]["stkCallback"];


/* =========================================================
   CORE CALLBACK VALUES
========================================================= */

$resultCode =
    isset(
        $callback["ResultCode"]
    )
        ? (int) $callback["ResultCode"]
        : null;


$resultDescription =
    trim(
        (string) (
            $callback["ResultDesc"]
            ?? ""
        )
    );


$merchantRequestId =
    trim(
        (string) (
            $callback["MerchantRequestID"]
            ?? ""
        )
    );


$checkoutRequestId =
    trim(
        (string) (
            $callback["CheckoutRequestID"]
            ?? ""
        )
    );


/* =========================================================
   REQUIRE CHECKOUT REQUEST ID
========================================================= */

if ($checkoutRequestId === "") {

    error_log(
        "Sprinter M-Pesa callback: CheckoutRequestID missing."
    );

    http_response_code(400);

    sendMpesaResponse(
        1,
        "Checkout reference missing"
    );
}


/* =========================================================
   GET MATCHING BOOKING

   CheckoutRequestID was stored by mpesa_stk.php.
========================================================= */

$stmt =
    $conn->prepare(
        "
        SELECT
            id,
            user_id,
            phone,
            amount,
            payment,
            payment_status,
            checkout_request_id,
            merchant_request_id,
            mpesa_receipt
        FROM bookings
        WHERE checkout_request_id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    error_log(
        "Sprinter M-Pesa callback DB prepare error: "
        . $conn->error
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


$stmt->bind_param(
    "s",
    $checkoutRequestId
);


$stmt->execute();


$booking =
    $stmt
        ->get_result()
        ->fetch_assoc();


$stmt->close();


/* =========================================================
   BOOKING MUST EXIST
========================================================= */

if (!$booking) {

    error_log(
        "Sprinter M-Pesa callback: "
        . "No booking found for CheckoutRequestID "
        . $checkoutRequestId
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   VERIFY PAYMENT METHOD
========================================================= */

if (
    $booking["payment"]
    !== "Mpesa"
) {

    error_log(
        "Sprinter M-Pesa callback: "
        . "Payment method mismatch for booking "
        . $booking["id"]
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   VERIFY MERCHANT REQUEST ID
========================================================= */

$savedMerchantRequestId =
    trim(
        (string) (
            $booking["merchant_request_id"]
            ?? ""
        )
    );


if (
    $savedMerchantRequestId !== "" &&
    $merchantRequestId !== "" &&
    !hash_equals(
        $savedMerchantRequestId,
        $merchantRequestId
    )
) {

    error_log(
        "Sprinter M-Pesa callback: "
        . "MerchantRequestID mismatch for booking "
        . $booking["id"]
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   UNSUCCESSFUL PAYMENT

   This is the part your old callback was missing.

   Pending can now become:
   - Cancelled
   - Failed
   - TimedOut
========================================================= */

if ($resultCode !== 0) {

    $failureStatus =
        classifyMpesaFailure(
            $resultCode,
            $resultDescription
        );


    error_log(

        "Sprinter M-Pesa payment not completed. "
        . "Booking: "
        . $booking["id"]
        . " | CheckoutRequestID: "
        . $checkoutRequestId
        . " | ResultCode: "
        . (string) $resultCode
        . " | Status: "
        . $failureStatus
        . " | Description: "
        . $resultDescription
    );


    /*
     * Only update the currently pending request.
     *
     * This also protects a Paid booking from being
     * changed back to Failed by a later callback.
     */

    $update =
        $conn->prepare(
            "
            UPDATE bookings
            SET payment_status = ?
            WHERE id = ?
            AND checkout_request_id = ?
            AND payment = 'Mpesa'
            AND payment_status = 'Pending'
            "
        );


    if (!$update) {

        error_log(
            "Sprinter M-Pesa failure update prepare error: "
            . $conn->error
        );

        sendMpesaResponse(
            0,
            "Callback received"
        );
    }


    $bookingId =
        (int) $booking["id"];


    $update->bind_param(
        "sis",
        $failureStatus,
        $bookingId,
        $checkoutRequestId
    );


    if (!$update->execute()) {

        error_log(
            "Sprinter M-Pesa failure update error: "
            . $update->error
        );
    }


    $update->close();


    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   SUCCESS CALLBACK METADATA
========================================================= */

$metadataItems =
    $callback["CallbackMetadata"]["Item"]
    ?? [];


if (!is_array($metadataItems)) {

    error_log(
        "Sprinter M-Pesa callback: Metadata missing."
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   CONVERT METADATA ITEMS TO KEY/VALUE ARRAY
========================================================= */

$metadata = [];


foreach (
    $metadataItems
    as $item
) {

    if (
        !is_array($item) ||
        !isset($item["Name"])
    ) {

        continue;
    }


    $name =
        trim(
            (string) $item["Name"]
        );


    $value =
        $item["Value"]
        ?? null;


    $metadata[$name] =
        $value;
}


/* =========================================================
   REQUIRED SUCCESS VALUES
========================================================= */

$mpesaReceipt =
    trim(
        (string) (
            $metadata["MpesaReceiptNumber"]
            ?? ""
        )
    );


$paidAmount =
    isset(
        $metadata["Amount"]
    )
        ? (float) $metadata["Amount"]
        : 0;


$paidPhone =
    preg_replace(
        "/\D/",
        "",
        (string) (
            $metadata["PhoneNumber"]
            ?? ""
        )
    );


$transactionDate =
    trim(
        (string) (
            $metadata["TransactionDate"]
            ?? ""
        )
    );


/* =========================================================
   REQUIRE RECEIPT
========================================================= */

if ($mpesaReceipt === "") {

    error_log(
        "Sprinter M-Pesa callback: "
        . "Successful callback without receipt. "
        . "CheckoutRequestID: "
        . $checkoutRequestId
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   VERIFY AMOUNT
========================================================= */

$expectedAmount =
    (float) (
        (int) round(
            (float) $booking["amount"]
        )
    );


if (
    $paidAmount <= 0 ||
    abs(
        $paidAmount
        - $expectedAmount
    ) > 0.001
) {

    error_log(
        "Sprinter M-Pesa callback: "
        . "Amount mismatch for booking "
        . $booking["id"]
        . ". Expected "
        . $expectedAmount
        . ", received "
        . $paidAmount
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   VERIFY PHONE
========================================================= */

$savedPhone =
    preg_replace(
        "/\D/",
        "",
        (string) (
            $booking["phone"]
            ?? ""
        )
    );


if (
    $paidPhone === "" ||
    $savedPhone === "" ||
    !hash_equals(
        $savedPhone,
        $paidPhone
    )
) {

    error_log(
        "Sprinter M-Pesa callback: "
        . "Phone mismatch for booking "
        . $booking["id"]
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   IDEMPOTENCY / ALREADY PAID

   Safaricom may retry callbacks.
========================================================= */

if (
    strtolower(
        trim(
            (string) $booking["payment_status"]
        )
    )
    === "paid"
) {

    $savedReceipt =
        trim(
            (string) (
                $booking["mpesa_receipt"]
                ?? ""
            )
        );


    if (
        $savedReceipt !== "" &&
        !hash_equals(
            $savedReceipt,
            $mpesaReceipt
        )
    ) {

        error_log(
            "Sprinter M-Pesa callback: "
            . "Paid booking receipt mismatch. Booking "
            . $booking["id"]
        );
    }


    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   MARK BOOKING PAID

   Only the current Pending request may become Paid.
========================================================= */

$update =
    $conn->prepare(
        "
        UPDATE bookings
        SET
            payment_status = 'Paid',
            mpesa_receipt = ?
        WHERE id = ?
        AND checkout_request_id = ?
        AND payment = 'Mpesa'
        AND payment_status = 'Pending'
        "
    );


if (!$update) {

    error_log(
        "Sprinter M-Pesa callback update prepare error: "
        . $conn->error
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


$bookingId =
    (int) $booking["id"];


$update->bind_param(
    "sis",
    $mpesaReceipt,
    $bookingId,
    $checkoutRequestId
);


if (!$update->execute()) {

    error_log(
        "Sprinter M-Pesa callback update error: "
        . $update->error
    );

    $update->close();

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


$affectedRows =
    $update->affected_rows;


$update->close();


/* =========================================================
   CONFIRM SUCCESSFUL UPDATE
========================================================= */

if ($affectedRows !== 1) {

    error_log(
        "Sprinter M-Pesa callback: "
        . "Successful payment verified but update affected "
        . $affectedRows
        . " rows. Booking "
        . $bookingId
    );

    sendMpesaResponse(
        0,
        "Callback received"
    );
}


/* =========================================================
   SUCCESS LOG
========================================================= */

error_log(

    "Sprinter M-Pesa payment confirmed. "
    . "Booking: "
    . $bookingId
    . " | Receipt: "
    . $mpesaReceipt
    . (
        $transactionDate !== ""
            ? " | TransactionDate: "
              . $transactionDate
            : ""
    )

);


/* =========================================================
   RESPOND TO SAFARICOM
========================================================= */

sendMpesaResponse(
    0,
    "Callback received"
);

?>