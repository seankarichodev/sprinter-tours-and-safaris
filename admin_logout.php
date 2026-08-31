<?php

session_start();

require_once __DIR__ . "/db.php";


/* =========================================================
   AUDIT LOGOUT BEFORE SESSION IS DESTROYED
========================================================= */

if (
    isset(
        $_SESSION["admin_id"],
        $_SESSION["admin_username"],
        $_SESSION["admin_role"]
    )
) {

    $auditAdminId =
        (int) $_SESSION["admin_id"];

    $auditUsername =
        trim(
            (string) $_SESSION["admin_username"]
        );

    $auditRole =
        strtolower(
            trim(
                (string) $_SESSION["admin_role"]
            )
        );


    if (
        in_array(
            $auditRole,
            [
                "admin",
                "owner"
            ],
            true
        )
    ) {

        $auditAction =
            $auditRole === "owner"
                ? "Owner logged out"
                : "Admin logged out";

        $auditEntityType =
            "authentication";

        $auditEntityId =
            $auditAdminId;

        $auditDetails =
            "Successful "
            . $auditRole
            . " portal logout.";

        $auditIp =
            $_SERVER["REMOTE_ADDR"]
            ?? null;


        $auditStmt =
            $conn->prepare(
                "
                INSERT INTO admin_audit_log
                (
                    admin_id,
                    username,
                    role,
                    action,
                    entity_type,
                    entity_id,
                    details,
                    ip_address
                )

                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                "
            );


        if ($auditStmt) {

            $auditStmt->bind_param(
                "issssiss",
                $auditAdminId,
                $auditUsername,
                $auditRole,
                $auditAction,
                $auditEntityType,
                $auditEntityId,
                $auditDetails,
                $auditIp
            );


            if (!$auditStmt->execute()) {

                error_log(
                    "Logout audit failed for "
                    . $auditUsername
                    . ": "
                    . $auditStmt->error
                );
            }


            $auditStmt->close();

        } else {

            error_log(
                "Unable to prepare logout audit record: "
                . $conn->error
            );
        }
    }
}


/* =========================================================
   REMOVE ALL SESSION DATA
========================================================= */

$_SESSION = [];


/* =========================================================
   REMOVE PHP SESSION COOKIE
========================================================= */

if (
    ini_get(
        "session.use_cookies"
    )
) {

    $params =
        session_get_cookie_params();


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


/* =========================================================
   DESTROY SESSION
========================================================= */

session_destroy();


/* =========================================================
   RETURN TO LOGIN
========================================================= */

header(
    "Location: admin_login.php"
);

exit();
