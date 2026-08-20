<?php

require_once __DIR__ . '/env_loader.php';
<?php

/* =========================================================
   SPRINTER TOURS & SAFARIS
   M-PESA CONFIGURATION
========================================================= */


/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set(
    "Africa/Nairobi"
);


/* =========================================================
   DARAJA SANDBOX CREDENTIALS

   Put your NEW credentials here locally.
   Do not send them in chat.
========================================================= */

define(
    "MPESA_CONSUMER_KEY",
    getenv("MPESA_CONSUMER_KEY")
);


define(
    "MPESA_CONSUMER_SECRET",
    getenv("MPESA_CONSUMER_SECRET")
);



/* =========================================================
   SANDBOX SHORTCODE
========================================================= */

define(
    "MPESA_SHORTCODE",
    "174379"
);


/* =========================================================
   LIPA NA M-PESA PASSKEY

   Put the sandbox passkey supplied for your Daraja setup.
========================================================= */

define(
    "MPESA_PASSKEY",
    getenv("MPESA_PASSKEY")
);

/* =========================================================
   CALLBACK URL

   ngrok is currently forwarding:
   https://careless-approval-clerical.ngrok-free.dev
   → localhost:80

   Safaricom will therefore reach:
   localhost/wildlife-tours/mpesa_callback.php
========================================================= */

define(
    "MPESA_CALLBACK_URL",
    getenv("MPESA_CALLBACK_URL")
);


/* =========================================================
   DARAJA SANDBOX OAUTH
========================================================= */

define(
    "MPESA_OAUTH_URL",
    "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials"
);


/* =========================================================
   DARAJA SANDBOX STK PUSH
========================================================= */

define(
    "MPESA_STK_URL",
    "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"
);

?>