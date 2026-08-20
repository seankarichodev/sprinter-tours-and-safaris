<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   RECEIPT CONFIGURATION
========================================================= */


/* =========================================================
   LOAD ENVIRONMENT VARIABLES
========================================================= */

require_once __DIR__ . "/env_loader.php";


/* =========================================================
   RECEIPT VERIFICATION SECRET
========================================================= */

$receiptVerifySecret = getenv("RECEIPT_VERIFY_SECRET");

if (!$receiptVerifySecret) {
    die("Receipt verification secret is missing.");
}

define(
    "RECEIPT_VERIFY_SECRET",
    $receiptVerifySecret
);

?>