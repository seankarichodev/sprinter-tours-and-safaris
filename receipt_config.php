<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   RECEIPT VERIFICATION CONFIG
========================================================= */

/*
 * Create your own long random secret locally.
 *
 * DO NOT:
 * - put it in JavaScript
 * - publish it on GitHub
 * - send it in chat
 */

<?php

require_once __DIR__ . '/env_loader.php';

define(
    "RECEIPT_VERIFY_SECRET",
    getenv("RECEIPT_VERIFY_SECRET")
);

?>