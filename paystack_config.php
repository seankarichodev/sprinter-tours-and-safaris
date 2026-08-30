<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   PAYSTACK CONFIGURATION
========================================================= */

require_once __DIR__ . "/env_loader.php";


/* =========================================================
   PAYSTACK ENVIRONMENT CONFIGURATION
========================================================= */

define(
    "PAYSTACK_SECRET_KEY",
    getenv("PAYSTACK_SECRET_KEY")
);

define(
    "PAYSTACK_CALLBACK_URL",
    getenv("PAYSTACK_CALLBACK_URL")
);
