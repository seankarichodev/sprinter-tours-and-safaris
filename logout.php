<?php

session_start();

/* Clear all customer session data */
$_SESSION = [];

/* Remove the session cookie */
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* Destroy the session */
session_destroy();

/* Return customer to customer login */
header("Location: auth.php");
exit();

?>