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
   DELETE BOOKING
========================================================= */

$stmt =
    $conn->prepare(
        "
        DELETE FROM bookings
        WHERE id = ?
        LIMIT 1
        "
    );


if (!$stmt) {

    http_response_code(500);

    exit(
        "Unable to process deletion."
    );
}


$stmt->bind_param(
    "i",
    $bookingId
);


$stmt->execute();


$stmt->close();


/* =========================================================
   REDIRECT
========================================================= */

header(
    "Location: bookings.php"
);

exit();