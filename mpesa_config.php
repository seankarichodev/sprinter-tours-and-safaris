<?php

require_once __DIR__ . '/env_loader.php';

date_default_timezone_set("Africa/Nairobi");

define(
    "MPESA_CONSUMER_KEY",
    getenv("MPESA_CONSUMER_KEY")
);

define(
    "MPESA_CONSUMER_SECRET",
    getenv("MPESA_CONSUMER_SECRET")
);

define(
    "MPESA_SHORTCODE",
    "174379"
);

define(
    "MPESA_PASSKEY",
    getenv("MPESA_PASSKEY")
);

define(
    "MPESA_CALLBACK_URL",
    getenv("MPESA_CALLBACK_URL")
);

define(
    "MPESA_OAUTH_URL",
    "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials"
);

define(
    "MPESA_STK_URL",
    "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"
);

define(
    "MPESA_STK_QUERY_URL",
    "https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query"
);
