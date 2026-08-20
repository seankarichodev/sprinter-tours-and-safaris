<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   PAYSTACK CONFIGURATION
========================================================= */


/*
 * TEST SECRET KEY
 *
 * Keep this server-side only.
 * Never put this key in HTML or JavaScript.
 * Never upload it to a public GitHub repository.
 */

<?php

require_once __DIR__ . '/env_loader.php';

define(
    "PAYSTACK_SECRET_KEY",
    getenv("PAYSTACK_SECRET_KEY")
);

define(
    "PAYSTACK_CALLBACK_URL",
    getenv("PAYSTACK_CALLBACK_URL")
);

?>
