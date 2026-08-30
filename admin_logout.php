<?php

session_start();

/*
 * Remove all session data.
 */
$_SESSION = [];


/*
 * Remove the PHP session cookie.
 */
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


/*
 * Destroy the session completely.
 */
session_destroy();


/*
 * Return administrator to admin login.
 */
header("Location: admin_login.php");
exit();