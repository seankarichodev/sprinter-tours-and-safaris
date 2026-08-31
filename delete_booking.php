<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db.php";


/* =========================================================
   ONLY ALLOW POST
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: bookings.php");
    exit();
}


/* =========================================================
   VERIFY CSRF TOKEN
========================================================= */

$csrfToken =
    $_POST["csrf_token"]
    ?? "";


if (
    empty($_SESSION["csrf_token"]) ||
    empty($csrfToken) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $csrfToken
    )
) {

    http_response_code(403);

    exit(
        "Invalid security token."
    );
}


/* =========================================================
   VALIDATE BOOKING ID
========================================================= */

$bookingId =
    isset($_POST["id"])
        ? (int) $_POST["id"]
        : 0;


if ($bookingId <= 0) {

    header("Location: bookings.php");
    exit();
}


/* =========================================================
   LOAD BOOKING BEFORE DELETION
   We keep a safe snapshot so the audit log can explain
   exactly what record was removed.
========================================================= */

$selectStmt =
    $conn->prepare(
        "
        SELECT
            id,
            name,
            email,
            phone,
            tour_name,
            date,
            time,
            amount,
            payment,
            payment_status

        FROM bookings

        WHERE id = ?

        LIMIT 1
        "
    );


if (!$selectStmt) {

    http_response_code(500);

    exit(
        "Unable to prepare booking deletion."
    );
}


$selectStmt->bind_param(
    "i",
    $bookingId
);


$selectStmt->execute();


$selectResult =
    $selectStmt->get_result();


if (
    !$selectResult ||
    $selectResult->num_rows !== 1
) {

    $selectStmt->close();

    header("Location: bookings.php");
    exit();
}


$booking =
    $selectResult->fetch_assoc();


$selectStmt->close();


/* =========================================================
   AUDIT ACTOR
========================================================= */

$auditAdminId =
    (int) $_SESSION["admin_id"];

$auditUsername =
    trim(
        (string) $_SESSION["admin_username"]
    );

$auditRole =
    strtolower(
        trim(
            (string) $_SESSION["admin_role"]
        )
    );


if (
    !in_array(
        $auditRole,
        [
            "admin",
            "owner"
        ],
        true
    )
) {

    http_response_code(403);

    exit(
        "Invalid administrative role."
    );
}


/* =========================================================
   BUILD AUDIT DETAILS BEFORE RECORD IS REMOVED
========================================================= */

$customerName =
    trim(
        (string) (
            $booking["name"]
            ?? ""
        )
    );

$customerEmail =
    trim(
        (string) (
            $booking["email"]
            ?? ""
        )
    );

$phone =
    trim(
        (string) (
            $booking["phone"]
            ?? ""
        )
    );

$tourName =
    trim(
        (string) (
            $booking["tour_name"]
            ?? ""
        )
    );

$travelDate =
    trim(
        (string) (
            $booking["date"]
            ?? ""
        )
    );

$travelTime =
    trim(
        (string) (
            $booking["time"]
            ?? ""
        )
    );

$paymentMethod =
    trim(
        (string) (
            $booking["payment"]
            ?? ""
        )
    );

$paymentStatus =
    trim(
        (string) (
            $booking["payment_status"]
            ?? ""
        )
    );

$amount =
    (float) (
        $booking["amount"]
        ?? 0
    );


$auditAction =
    "Deleted booking";

$auditEntityType =
    "booking";

$auditEntityId =
    $bookingId;

$auditIp =
    $_SERVER["REMOTE_ADDR"]
    ?? null;


$auditDetails =
    "Deleted booking #"
    . $bookingId
    . ". Customer: "
    . ($customerName !== "" ? $customerName : "Unknown")
    . " | Email: "
    . ($customerEmail !== "" ? $customerEmail : "—")
    . " | Phone: "
    . ($phone !== "" ? $phone : "—")
    . " | Tour: "
    . ($tourName !== "" ? $tourName : "Not specified")
    . " | Travel: "
    . ($travelDate !== "" ? $travelDate : "—")
    . " "
    . ($travelTime !== "" ? $travelTime : "")
    . " | Amount: KES "
    . number_format(
        $amount,
        0,
        ".",
        ""
    )
    . " | Payment: "
    . ($paymentMethod !== "" ? $paymentMethod : "—")
    . " | Status: "
    . ($paymentStatus !== "" ? $paymentStatus : "—");


/* =========================================================
   DELETE + AUDIT AS ONE TRANSACTION

   Important:
   If the audit record cannot be written, the deletion is
   rolled back. That prevents an administrative deletion
   from happening without an owner-visible trail.
========================================================= */

$conn->begin_transaction();


try {

    /* =====================================================
       DELETE BOOKING
    ===================================================== */

    $deleteStmt =
        $conn->prepare(
            "
            DELETE FROM bookings

            WHERE id = ?

            LIMIT 1
            "
        );


    if (!$deleteStmt) {

        throw new RuntimeException(
            "Unable to prepare deletion."
        );
    }


    $deleteStmt->bind_param(
        "i",
        $bookingId
    );


    if (!$deleteStmt->execute()) {

        $deleteStmt->close();

        throw new RuntimeException(
            "Unable to delete booking."
        );
    }


    $deletedRows =
        $deleteStmt->affected_rows;


    $deleteStmt->close();


    if ($deletedRows !== 1) {

        throw new RuntimeException(
            "Booking was not deleted."
        );
    }


    /* =====================================================
       WRITE OWNER AUDIT RECORD
    ===================================================== */

    $auditStmt =
        $conn->prepare(
            "
            INSERT INTO admin_audit_log
            (
                admin_id,
                username,
                role,
                action,
                entity_type,
                entity_id,
                details,
                ip_address
            )

            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            "
        );


    if (!$auditStmt) {

        throw new RuntimeException(
            "Unable to prepare audit record."
        );
    }


    $auditStmt->bind_param(
        "issssiss",
        $auditAdminId,
        $auditUsername,
        $auditRole,
        $auditAction,
        $auditEntityType,
        $auditEntityId,
        $auditDetails,
        $auditIp
    );


    if (!$auditStmt->execute()) {

        $auditStmt->close();

        throw new RuntimeException(
            "Unable to save audit record."
        );
    }


    $auditStmt->close();


    /* =====================================================
       COMMIT BOTH OPERATIONS
    ===================================================== */

    $conn->commit();


} catch (Throwable $error) {

    $conn->rollback();


    error_log(
        "Booking deletion failed for booking #"
        . $bookingId
        . ": "
        . $error->getMessage()
    );


    http_response_code(500);

    exit(
        "Unable to delete this booking safely."
    );
}


/* =========================================================
   REDIRECT
========================================================= */

header(
    "Location: bookings.php"
);

exit();
