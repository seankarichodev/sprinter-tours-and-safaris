<?php

require_once "env_loader.php";

$paystackSecretKey = getenv("PAYSTACK_SECRET_KEY");

if ($paystackSecretKey) {
    echo "PAYSTACK KEY LOADED";
} else {
    echo "PAYSTACK KEY MISSING";
}

?>