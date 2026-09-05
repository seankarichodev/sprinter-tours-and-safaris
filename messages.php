
<?php
require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";


/* =========================================================
   CSRF TOKEN
========================================================= */

if (
    empty($_SESSION["csrf_token"])
) {

    $_SESSION["csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}


$csrfToken =
    $_SESSION["csrf_token"];



/* =========================================================
   POST ACTIONS + OWNER AUDIT LOGGING
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken =
        $_POST["csrf_token"]
        ?? "";

    if (
        empty($submittedToken) ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {
        http_response_code(403);
        exit("Invalid security token.");
    }

    $action =
        trim(
            (string) (
                $_POST["action"]
                ?? ""
            )
        );

    $messageId =
        isset($_POST["message_id"])
            ? (int) $_POST["message_id"]
            : 0;

    $allowedActions = [
        "mark_read",
        "mark_unread"
    ];

    if (
        $messageId > 0 &&
        in_array(
            $action,
            $allowedActions,
            true
        )
    ) {

        /* Load the message before changing it so the audit
           record still contains useful information. */

        $messageStmt =
            $conn->prepare(
                "
                SELECT
                    id,
                    name,
                    email,
                    phone,
                    subject,
                    message,
                    status,
                    created_at
                FROM messages
                WHERE id = ?
                LIMIT 1
                "
            );

        if (!$messageStmt) {
            http_response_code(500);
            exit("Unable to prepare message action.");
        }

        $messageStmt->bind_param(
            "i",
            $messageId
        );

        $messageStmt->execute();

        $messageResult =
            $messageStmt->get_result();

        if (
            !$messageResult ||
            $messageResult->num_rows !== 1
        ) {
            $messageStmt->close();
            header("Location: messages.php");
            exit();
        }

        $messageBefore =
            $messageResult->fetch_assoc();

        $messageStmt->close();

        /* Audit actor */

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
            !in_array(
                $auditRole,
                ["admin", "owner"],
                true
            )
        ) {
            http_response_code(403);
            exit("Invalid administrative role.");
        }

        $auditIp =
            $_SERVER["REMOTE_ADDR"]
            ?? null;

        /* Safe message details */

        $senderName =
            trim(
                (string) (
                    $messageBefore["name"]
                    ?? ""
                )
            );

        $senderEmail =
            trim(
                (string) (
                    $messageBefore["email"]
                    ?? ""
                )
            );

        $subject =
            trim(
                (string) (
                    $messageBefore["subject"]
                    ?? ""
                )
            );

        $oldStatus =
            trim(
                (string) (
                    $messageBefore["status"]
                    ?? ""
                )
            );

        $messagePreviewForAudit =
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    (string) (
                        $messageBefore["message"]
                        ?? ""
                    )
                )
            );

        if (
            mb_strlen(
                $messagePreviewForAudit
            ) > 120
        ) {
            $messagePreviewForAudit =
                mb_substr(
                    $messagePreviewForAudit,
                    0,
                    120
                )
                . "…";
        }

        /* =====================================================
           MARK READ
        ===================================================== */

        if (
            $action === "mark_read" &&
            strtolower($oldStatus) !== "read"
        ) {

            $conn->begin_transaction();

            try {

                $actionStmt =
                    $conn->prepare(
                        "
                        UPDATE messages
                        SET status = 'Read'
                        WHERE id = ?
                        LIMIT 1
                        "
                    );

                if (!$actionStmt) {
                    throw new RuntimeException(
                        "Unable to prepare message update."
                    );
                }

                $actionStmt->bind_param(
                    "i",
                    $messageId
                );

                if (!$actionStmt->execute()) {
                    $actionStmt->close();
                    throw new RuntimeException(
                        "Unable to mark message read."
                    );
                }

                $actionStmt->close();

                $auditAction =
                    "Marked message read";

                $auditEntityType =
                    "message";

                $auditEntityId =
                    $messageId;

                $auditDetails =
                    "Marked customer message #"
                    . $messageId
                    . " as Read. Sender: "
                    . ($senderName !== "" ? $senderName : "Unknown")
                    . " | Email: "
                    . ($senderEmail !== "" ? $senderEmail : "—")
                    . " | Subject: "
                    . ($subject !== "" ? $subject : "General enquiry")
                    . " | Previous status: "
                    . ($oldStatus !== "" ? $oldStatus : "Unknown");

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

                if (!$auditStmt) {
                    throw new RuntimeException(
                        "Unable to prepare audit record."
                    );
                }

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
                    $auditStmt->close();
                    throw new RuntimeException(
                        "Unable to save audit record."
                    );
                }

                $auditStmt->close();

                $conn->commit();

            } catch (Throwable $error) {

                $conn->rollback();

                error_log(
                    "Mark-read audit failed for message #"
                    . $messageId
                    . ": "
                    . $error->getMessage()
                );

                http_response_code(500);
                exit("Unable to update this message safely.");
            }
        }

        /* =====================================================
           MARK UNREAD
        ===================================================== */

        elseif (
            $action === "mark_unread" &&
            strtolower($oldStatus) !== "unread"
        ) {

            $conn->begin_transaction();

            try {

                $actionStmt =
                    $conn->prepare(
                        "
                        UPDATE messages
                        SET status = 'Unread'
                        WHERE id = ?
                        LIMIT 1
                        "
                    );

                if (!$actionStmt) {
                    throw new RuntimeException(
                        "Unable to prepare message update."
                    );
                }

                $actionStmt->bind_param(
                    "i",
                    $messageId
                );

                if (!$actionStmt->execute()) {
                    $actionStmt->close();
                    throw new RuntimeException(
                        "Unable to mark message unread."
                    );
                }

                $actionStmt->close();

                $auditAction =
                    "Marked message unread";

                $auditEntityType =
                    "message";

                $auditEntityId =
                    $messageId;

                $auditDetails =
                    "Marked customer message #"
                    . $messageId
                    . " as Unread. Sender: "
                    . ($senderName !== "" ? $senderName : "Unknown")
                    . " | Email: "
                    . ($senderEmail !== "" ? $senderEmail : "—")
                    . " | Subject: "
                    . ($subject !== "" ? $subject : "General enquiry")
                    . " | Previous status: "
                    . ($oldStatus !== "" ? $oldStatus : "Unknown");

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

                if (!$auditStmt) {
                    throw new RuntimeException(
                        "Unable to prepare audit record."
                    );
                }

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
                    $auditStmt->close();
                    throw new RuntimeException(
                        "Unable to save audit record."
                    );
                }

                $auditStmt->close();

                $conn->commit();

            } catch (Throwable $error) {

                $conn->rollback();

                error_log(
                    "Mark-unread audit failed for message #"
                    . $messageId
                    . ": "
                    . $error->getMessage()
                );

                http_response_code(500);
                exit("Unable to update this message safely.");
            }
        }

    }

    /*
     * POST / REDIRECT / GET
     *
     * Prevents refreshing the page from
     * repeating the previous database action.
     */

    $redirectParameters = [];

    if (
        !empty($_POST["return_search"])
    ) {
        $redirectParameters["search"] =
            $_POST["return_search"];
    }

    if (
        !empty($_POST["return_status"])
    ) {
        $redirectParameters["status"] =
            $_POST["return_status"];
    }

    if (
        !empty($_POST["return_limit"])
    ) {
        $redirectParameters["limit"] =
            (int) $_POST["return_limit"];
    }

    if (
        !empty($_POST["return_page"])
    ) {
        $redirectParameters["page"] =
            (int) $_POST["return_page"];
    }

    $redirect =
        "messages.php";

    if (
        !empty($redirectParameters)
    ) {
        $redirect .=
            "?"
            . http_build_query(
                $redirectParameters
            );
    }

    header(
        "Location: "
        . $redirect
    );

    exit();
}



/* =========================================================
   INPUTS
========================================================= */

$search =
    trim(
        $_GET["search"]
        ?? ""
    );


$statusFilter =
    trim(
        $_GET["status"]
        ?? ""
    );


$allowedStatuses = [
    "",
    "Unread",
    "Read"
];


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter = "";
}



$allowedLimits = [
    5,
    10,
    25,
    50
];


$limit =
    isset($_GET["limit"])
        ? (int) $_GET["limit"]
        : 10;


if (
    !in_array(
        $limit,
        $allowedLimits,
        true
    )
) {

    $limit = 10;
}


$page =
    isset($_GET["page"])
        ? max(
            1,
            (int) $_GET["page"]
        )
        : 1;



/* =========================================================
   MESSAGE STATISTICS
========================================================= */

$statsSql =
    "
    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN LOWER(status) = 'unread'
                THEN 1
                ELSE 0
            END
        ) AS unread,

        SUM(
            CASE
                WHEN LOWER(status) = 'read'
                THEN 1
                ELSE 0
            END
        ) AS message_read

    FROM messages
    ";


$statsResult =
    $conn->query(
        $statsSql
    );


$stats = [
    "total" => 0,
    "unread" => 0,
    "message_read" => 0
];


if ($statsResult) {

    $statsRow =
        $statsResult
        ->fetch_assoc();


    if ($statsRow) {

        $stats["total"] =
            (int) (
                $statsRow["total"]
                ?? 0
            );


        $stats["unread"] =
            (int) (
                $statsRow["unread"]
                ?? 0
            );


        $stats["message_read"] =
            (int) (
                $statsRow[
                    "message_read"
                ]
                ?? 0
            );
    }
}



/* =========================================================
   COUNT FILTERED MESSAGES
========================================================= */

$countSql =
    "
    SELECT
        COUNT(*) AS total

    FROM messages

    WHERE
    (
        ? = ''

        OR name LIKE CONCAT('%', ?, '%')

        OR email LIKE CONCAT('%', ?, '%')

        OR phone LIKE CONCAT('%', ?, '%')

        OR subject LIKE CONCAT('%', ?, '%')

        OR message LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''

        OR LOWER(status)
           = LOWER(?)
    )
    ";


$countStmt =
    $conn->prepare(
        $countSql
    );


$totalRecords = 0;


if ($countStmt) {

    $countStmt->bind_param(
        "ssssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $statusFilter,
        $statusFilter
    );


    $countStmt->execute();


    $countResult =
        $countStmt->get_result();


    if ($countResult) {

        $countRow =
            $countResult
            ->fetch_assoc();


        $totalRecords =
            (int) (
                $countRow["total"]
                ?? 0
            );
    }


    $countStmt->close();
}



$totalPages =
    max(
        1,
        (int) ceil(
            $totalRecords
            / $limit
        )
    );


if (
    $page > $totalPages
) {

    $page =
        $totalPages;
}


$offset =
    ($page - 1)
    * $limit;



/* =========================================================
   FETCH MESSAGES
========================================================= */

$sql =
    "
    SELECT

        id,
        name,
        email,
        phone,
        subject,
        message,
        status,
        created_at

    FROM messages

    WHERE
    (
        ? = ''

        OR name LIKE CONCAT('%', ?, '%')

        OR email LIKE CONCAT('%', ?, '%')

        OR phone LIKE CONCAT('%', ?, '%')

        OR subject LIKE CONCAT('%', ?, '%')

        OR message LIKE CONCAT('%', ?, '%')
    )

    AND
    (
        ? = ''

        OR LOWER(status)
           = LOWER(?)
    )

    ORDER BY
        created_at DESC,
        id DESC

    LIMIT $limit
    OFFSET $offset
    ";


$stmt =
    $conn->prepare(
        $sql
    );


$result = null;


if ($stmt) {

    $stmt->bind_param(
        "ssssssss",
        $search,
        $search,
        $search,
        $search,
        $search,
        $search,
        $statusFilter,
        $statusFilter
    );


    $stmt->execute();


    $result =
        $stmt->get_result();
}



/* =========================================================
   HELPERS
========================================================= */

function messageEscape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function messagePreview(
    string $message,
    int $length = 90
): string {

    $message =
        trim(
            preg_replace(
                '/\s+/',
                ' ',
                $message
            )
        );


    if (
        mb_strlen($message)
        <= $length
    ) {

        return $message;
    }


    return
        mb_substr(
            $message,
            0,
            $length
        )
        . "…";
}


function senderInitials(
    string $name
): string {

    $name =
        trim($name);


    if ($name === "") {
        return "?";
    }


    $parts =
        preg_split(
            '/\s+/',
            $name
        );


    if (!$parts) {
        return "?";
    }


    $initials = "";


    foreach (
        array_slice(
            $parts,
            0,
            2
        )
        as $part
    ) {

        if ($part !== "") {

            $initials .=
                strtoupper(
                    substr(
                        $part,
                        0,
                        1
                    )
                );
        }
    }


    return $initials !== ""
        ? $initials
        : "?";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Messages | Sprinter Admin
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >


    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <link
        rel="stylesheet"
        href="admin.css"
    >


    <style>
        body.message-modal-open{
            overflow:hidden;
        }

        .admin-message-view-btn{
            border:0;
            background:none;
            padding:0;
            cursor:pointer;
        }

        .admin-message-modal-backdrop{
            position:fixed;
            z-index:9999;
            inset:0;
            display:none;
            align-items:center;
            justify-content:center;
            padding:24px;
            background:rgba(8,24,18,.58);
            backdrop-filter:blur(5px);
        }

        .admin-message-modal-backdrop.open{
            display:flex;
        }

        .admin-message-modal{
            width:min(760px,100%);
            max-height:88vh;
            overflow:auto;
            border:1px solid var(--admin-border);
            border-radius:18px;
            background:#fff;
            box-shadow:0 28px 90px rgba(14,42,31,.24);
        }

        .admin-message-modal-head{
            position:sticky;
            top:0;
            z-index:2;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            padding:20px 22px;
            border-bottom:1px solid var(--admin-border);
            background:rgba(255,255,255,.97);
            backdrop-filter:blur(10px);
        }

        .admin-message-modal-head small{
            display:block;
            margin-bottom:5px;
            color:var(--admin-gold);
            font-size:9px;
            font-weight:800;
            letter-spacing:1.2px;
            text-transform:uppercase;
        }

        .admin-message-modal-head h3{
            margin:0;
            color:var(--admin-text);
            font-size:23px;
        }

        .admin-message-modal-close{
            width:38px;
            height:38px;
            flex:0 0 38px;
            border:1px solid var(--admin-border);
            border-radius:10px;
            background:#fbfaf7;
            color:var(--admin-text);
            cursor:pointer;
        }

        .admin-message-modal-body{
            padding:22px;
        }

        .admin-message-meta{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:12px;
            margin-bottom:18px;
        }

        .admin-message-meta-card{
            padding:14px;
            border:1px solid var(--admin-border);
            border-radius:12px;
            background:#fbfaf7;
        }

        .admin-message-meta-card span{
            display:block;
            margin-bottom:6px;
            color:var(--admin-muted);
            font-size:9px;
            font-weight:800;
            letter-spacing:1px;
            text-transform:uppercase;
        }

        .admin-message-meta-card strong{
            display:block;
            color:var(--admin-text);
            font-size:12px;
            word-break:break-word;
        }

        .admin-message-full{
            padding:18px;
            border:1px solid var(--admin-border);
            border-radius:13px;
            background:#fcfbf8;
            color:var(--admin-text);
            font-size:13px;
            line-height:1.75;
            white-space:pre-wrap;
            overflow-wrap:anywhere;
        }

        .admin-message-modal-note{
            margin:14px 0 0;
            padding:12px 14px;
            border-radius:11px;
            background:var(--admin-green-soft);
            color:var(--admin-green-dark);
            font-size:11px;
            line-height:1.55;
        }

        @media(max-width:700px){
            .admin-message-meta{
                grid-template-columns:1fr;
            }

            .admin-message-modal-backdrop{
                padding:12px;
            }
        }
    </style>

</head>


<body>


<div class="admin-layout">


    <?php
        require __DIR__
            . "/admin_sidebar.php";
    ?>


    <div class="admin-main">


        <?php
            require __DIR__
                . "/admin_topbar.php";
        ?>


        <main class="admin-content">


            <!-- =============================================
                 PAGE HEADER
            ============================================== -->

            <section class="admin-page-header">

                <div>

                    <h1>
                        Messages
                    </h1>

                    <p>
                        Review customer enquiries and
                        manage communication follow-up.
                    </p>

                </div>

            </section>



            <!-- =============================================
                 MESSAGE STATISTICS
            ============================================== -->

            <section
                class="admin-stats-grid"
                style="
                    grid-template-columns:
                    repeat(3, minmax(0, 1fr));
                "
            >


                <div class="admin-stat-card">

                    <div>

                        <span class="admin-stat-label">
                            TOTAL MESSAGES
                        </span>


                        <div class="admin-stat-value">

                            <?php
                                echo number_format(
                                    $stats["total"]
                                );
                            ?>

                        </div>


                        <span class="admin-stat-description">
                            Customer enquiries
                        </span>

                    </div>


                    <div class="admin-stat-icon">

                        <i class="fa-solid fa-envelope"></i>

                    </div>

                </div>



                <div class="admin-stat-card">

                    <div>

                        <span class="admin-stat-label">
                            UNREAD
                        </span>


                        <div class="admin-stat-value">

                            <?php
                                echo number_format(
                                    $stats["unread"]
                                );
                            ?>

                        </div>


                        <span class="admin-stat-description">
                            Require attention
                        </span>

                    </div>


                    <div class="admin-stat-icon">

                        <i class="fa-solid fa-envelope-open-text"></i>

                    </div>

                </div>



                <div class="admin-stat-card">

                    <div>

                        <span class="admin-stat-label">
                            READ
                        </span>


                        <div class="admin-stat-value">

                            <?php
                                echo number_format(
                                    $stats["message_read"]
                                );
                            ?>

                        </div>


                        <span class="admin-stat-description">
                            Reviewed enquiries
                        </span>

                    </div>


                    <div class="admin-stat-icon">

                        <i class="fa-solid fa-check"></i>

                    </div>

                </div>


            </section>



            <!-- =============================================
                 FILTERS
            ============================================== -->

            <form
                method="GET"
                action="messages.php"
                class="admin-toolbar"
            >


                <div class="admin-toolbar-group">


                    <div class="admin-search">

                        <i class="fa-solid fa-magnifying-glass"></i>


                        <input
                            type="search"
                            name="search"
                            value="<?php echo messageEscape($search); ?>"
                            placeholder="Search messages, sender, subject..."
                        >

                    </div>



                    <select
                        name="status"
                        class="admin-select"
                    >

                        <option value="">
                            All messages
                        </option>


                        <option
                            value="Unread"
                            <?php
                                echo $statusFilter === "Unread"
                                    ? "selected"
                                    : "";
                            ?>
                        >
                            Unread
                        </option>


                        <option
                            value="Read"
                            <?php
                                echo $statusFilter === "Read"
                                    ? "selected"
                                    : "";
                            ?>
                        >
                            Read
                        </option>

                    </select>



                    <button
                        type="submit"
                        class="admin-button admin-button-primary"
                    >

                        <i class="fa-solid fa-filter"></i>

                        Filter

                    </button>



                    <?php if (
                        $search !== ""
                        ||
                        $statusFilter !== ""
                    ): ?>


                        <a
                            href="messages.php"
                            class="admin-button admin-button-light"
                        >

                            Clear

                        </a>


                    <?php endif; ?>


                </div>



                <div class="admin-toolbar-group">

                    <label for="limit">

                        <small>
                            Show
                        </small>

                    </label>


                    <select
                        name="limit"
                        id="limit"
                        class="admin-select"
                        onchange="this.form.submit()"
                    >


                        <?php foreach (
                            $allowedLimits
                            as $option
                        ): ?>


                            <option
                                value="<?php echo $option; ?>"
                                <?php
                                    echo $limit === $option
                                        ? "selected"
                                        : "";
                                ?>
                            >

                                <?php echo $option; ?>

                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>


            </form>



            <!-- =============================================
                 MESSAGES
            ============================================== -->

            <section class="admin-panel">


                <div class="admin-panel-header">

                    <h2>
                        Customer Enquiries
                    </h2>


                    <span
                        style="
                            color:var(--admin-muted);
                            font-size:12px;
                        "
                    >

                        <?php
                            echo number_format(
                                $totalRecords
                            );
                        ?>

                        message<?php
                            echo $totalRecords === 1
                                ? ""
                                : "s";
                        ?>

                    </span>

                </div>



                <div class="admin-table-wrapper">


                    <?php if (
                        $result
                        &&
                        $result->num_rows > 0
                    ): ?>


                        <table class="admin-table">


                            <thead>

                                <tr>

                                    <th>
                                        Sender
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Message
                                    </th>

                                    <th>
                                        Received
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>



                            <tbody>


                            <?php while (
                                $messageRow =
                                    $result->fetch_assoc()
                            ): ?>


                                <?php

                                $isUnread =
                                    strtolower(
                                        trim(
                                            (string) (
                                                $messageRow[
                                                    "status"
                                                ]
                                                ?? ""
                                            )
                                        )
                                    )
                                    === "unread";


                                $senderName =
                                    (string) (
                                        $messageRow["name"]
                                        ?? ""
                                    );


                                $subject =
                                    trim(
                                        (string) (
                                            $messageRow[
                                                "subject"
                                            ]
                                            ?? ""
                                        )
                                    );


                                $fullMessage =
                                    (string) (
                                        $messageRow[
                                            "message"
                                        ]
                                        ?? ""
                                    );

                                ?>


                                <tr
                                    <?php if ($isUnread): ?>

                                        style="
                                            background:
                                            rgba(
                                                11,
                                                122,
                                                59,
                                                0.035
                                            );
                                        "

                                    <?php endif; ?>
                                >


                                    <!-- SENDER -->

                                    <td>

                                        <div
                                            style="
                                                display:flex;
                                                gap:10px;
                                                align-items:flex-start;
                                            "
                                        >


                                            <div
                                                style="
                                                    width:36px;
                                                    height:36px;
                                                    border-radius:50%;
                                                    display:grid;
                                                    place-items:center;
                                                    background:#e8f5ee;
                                                    color:var(--admin-green);
                                                    font-size:11px;
                                                    font-weight:800;
                                                    flex-shrink:0;
                                                "
                                            >

                                                <?php
                                                    echo messageEscape(
                                                        senderInitials(
                                                            $senderName
                                                        )
                                                    );
                                                ?>

                                            </div>


                                            <div class="customer-cell">

                                                <strong>

                                                    <?php
                                                        echo messageEscape(
                                                            $senderName
                                                        );
                                                    ?>

                                                </strong>


                                                <span>

                                                    <?php
                                                        echo messageEscape(
                                                            $messageRow[
                                                                "email"
                                                            ]
                                                            ?? ""
                                                        );
                                                    ?>

                                                </span>


                                                <?php if (
                                                    !empty(
                                                        $messageRow[
                                                            "phone"
                                                        ]
                                                    )
                                                ): ?>

                                                    <span>

                                                        <?php
                                                            echo messageEscape(
                                                                $messageRow[
                                                                    "phone"
                                                                ]
                                                            );
                                                        ?>

                                                    </span>

                                                <?php endif; ?>

                                            </div>


                                        </div>

                                    </td>



                                    <!-- SUBJECT -->

                                    <td>

                                        <strong>

                                            <?php

                                            echo $subject !== ""
                                                ? messageEscape(
                                                    $subject
                                                )
                                                : "General enquiry";

                                            ?>

                                        </strong>

                                    </td>



                                    <!-- MESSAGE -->

                                    <td
                                        style="
                                            max-width:340px;
                                            line-height:1.5;
                                        "
                                    >

                                        <?php
                                            echo messageEscape(
                                                messagePreview(
                                                    $fullMessage
                                                )
                                            );
                                        ?>

                                    </td>



                                    <!-- RECEIVED -->

                                    <td>

                                        <?php

                                        $received =
                                            $messageRow[
                                                "created_at"
                                            ]
                                            ?? null;


                                        if ($received) {

                                            echo "<strong>"
                                                . messageEscape(
                                                    date(
                                                        "d M Y",
                                                        strtotime(
                                                            $received
                                                        )
                                                    )
                                                )
                                                . "</strong>";


                                            echo "<br>";


                                            echo
                                                "<small style='color:var(--admin-muted);'>"
                                                . messageEscape(
                                                    date(
                                                        "H:i",
                                                        strtotime(
                                                            $received
                                                        )
                                                    )
                                                )
                                                . "</small>";

                                        } else {

                                            echo "—";
                                        }

                                        ?>

                                    </td>



                                    <!-- STATUS -->

                                    <td>

                                        <?php if (
                                            $isUnread
                                        ): ?>

                                            <span
                                                class="status-badge status-pending"
                                            >
                                                Unread
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="status-badge status-paid"
                                            >
                                                Read
                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <!-- ACTIONS -->

                                    <td>

                                        <div
                                            style="
                                                display:flex;
                                                gap:12px;
                                                align-items:center;
                                                flex-wrap:wrap;
                                            "
                                        >

                                            <button
                                                type="button"
                                                class="admin-action-link admin-action-view admin-message-view-btn js-view-message"
                                                data-id="<?php echo (int) $messageRow["id"]; ?>"
                                                data-name="<?php echo messageEscape($senderName !== "" ? $senderName : "Unknown customer"); ?>"
                                                data-email="<?php echo messageEscape($messageRow["email"] ?? "—"); ?>"
                                                data-phone="<?php echo messageEscape($messageRow["phone"] ?? "—"); ?>"
                                                data-subject="<?php echo messageEscape($subject !== "" ? $subject : "General enquiry"); ?>"
                                                data-status="<?php echo messageEscape($isUnread ? "Unread" : "Read"); ?>"
                                                data-received="<?php echo messageEscape($received ? date("d M Y H:i", strtotime($received)) : "—"); ?>"
                                                data-message="<?php echo messageEscape($fullMessage); ?>"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                                View
                                            </button>


                                            <?php if ($isUnread): ?>

                                                <form
                                                    method="POST"
                                                    action="messages.php"
                                                    style="margin:0;"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php echo messageEscape($csrfToken); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="mark_read"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="message_id"
                                                        value="<?php echo (int) $messageRow["id"]; ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_search"
                                                        value="<?php echo messageEscape($search); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_status"
                                                        value="<?php echo messageEscape($statusFilter); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_limit"
                                                        value="<?php echo $limit; ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_page"
                                                        value="<?php echo $page; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="admin-action-link admin-action-edit"
                                                        style="
                                                            border:0;
                                                            background:none;
                                                            padding:0;
                                                            cursor:pointer;
                                                        "
                                                    >
                                                        <i class="fa-solid fa-check"></i>
                                                        Mark read
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <form
                                                    method="POST"
                                                    action="messages.php"
                                                    style="margin:0;"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php echo messageEscape($csrfToken); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="mark_unread"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="message_id"
                                                        value="<?php echo (int) $messageRow["id"]; ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_search"
                                                        value="<?php echo messageEscape($search); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_status"
                                                        value="<?php echo messageEscape($statusFilter); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_limit"
                                                        value="<?php echo $limit; ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="return_page"
                                                        value="<?php echo $page; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="admin-action-link admin-action-view"
                                                        style="
                                                            border:0;
                                                            background:none;
                                                            padding:0;
                                                            cursor:pointer;
                                                        "
                                                    >
                                                        <i class="fa-regular fa-envelope"></i>
                                                        Mark unread
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>

                                    </td>


                                </tr>


                            <?php endwhile; ?>


                            </tbody>


                        </table>


                    <?php else: ?>


                        <div class="admin-empty">

                            <i
                                class="fa-regular fa-envelope"
                                style="
                                    display:block;
                                    font-size:30px;
                                    margin-bottom:12px;
                                "
                            ></i>


                            No messages match your filters.

                        </div>


                    <?php endif; ?>


                </div>



                <!-- =========================================
                     PAGINATION
                ========================================== -->

                <?php if (
                    $totalPages > 1
                ): ?>


                    <nav class="admin-pagination">


                        <?php

                        for (
                            $i = 1;
                            $i <= $totalPages;
                            $i++
                        ):

                            $query =
                                http_build_query(
                                    [
                                        "page"
                                            => $i,

                                        "limit"
                                            => $limit,

                                        "search"
                                            => $search,

                                        "status"
                                            => $statusFilter
                                    ]
                                );

                        ?>


                            <a
                                href="?<?php echo messageEscape($query); ?>"
                                class="<?php
                                    echo $i === $page
                                        ? "active"
                                        : "";
                                ?>"
                            >

                                <?php echo $i; ?>

                            </a>


                        <?php endfor; ?>


                    </nav>


                <?php endif; ?>


            </section>


        </main>

    </div>

</div>




<div
    class="admin-message-modal-backdrop"
    id="adminMessageModal"
    aria-hidden="true"
>
    <section
        class="admin-message-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="adminMessageModalSubject"
    >

        <div class="admin-message-modal-head">

            <div>
                <small id="adminMessageModalReference">
                    Customer Message
                </small>

                <h3 id="adminMessageModalSubject">
                    Message details
                </h3>
            </div>

            <button
                type="button"
                class="admin-message-modal-close"
                id="adminMessageModalClose"
                aria-label="Close message"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>

        <div class="admin-message-modal-body">

            <div class="admin-message-meta">

                <div class="admin-message-meta-card">
                    <span>Customer</span>
                    <strong id="adminMessageModalName">—</strong>
                </div>

                <div class="admin-message-meta-card">
                    <span>Email</span>
                    <strong id="adminMessageModalEmail">—</strong>
                </div>

                <div class="admin-message-meta-card">
                    <span>Phone</span>
                    <strong id="adminMessageModalPhone">—</strong>
                </div>

                <div class="admin-message-meta-card">
                    <span>Received</span>
                    <strong id="adminMessageModalReceived">—</strong>
                </div>

                <div class="admin-message-meta-card">
                    <span>Status</span>
                    <strong id="adminMessageModalStatus">—</strong>
                </div>

                <div class="admin-message-meta-card">
                    <span>Message ID</span>
                    <strong id="adminMessageModalId">—</strong>
                </div>

            </div>

            <div
                class="admin-message-full"
                id="adminMessageModalBody"
            >—</div>

            <p class="admin-message-modal-note">
                <i class="fa-solid fa-clipboard-check"></i>
                Opening a message is view-only. Use the separate
                Mark read / Mark unread controls to change its operational status.
            </p>

        </div>

    </section>
</div>


<script>

const sidebar =
    document.getElementById(
        "adminSidebar"
    );


const mobileToggle =
    document.getElementById(
        "adminMobileToggle"
    );


if (
    sidebar &&
    mobileToggle
) {

    mobileToggle.addEventListener(
        "click",
        function () {

            sidebar.classList.toggle(
                "open"
            );

        }
    );

}


const messageModal =
    document.getElementById(
        "adminMessageModal"
    );

const messageModalClose =
    document.getElementById(
        "adminMessageModalClose"
    );

const messageModalFields = {
    reference: document.getElementById("adminMessageModalReference"),
    subject: document.getElementById("adminMessageModalSubject"),
    name: document.getElementById("adminMessageModalName"),
    email: document.getElementById("adminMessageModalEmail"),
    phone: document.getElementById("adminMessageModalPhone"),
    received: document.getElementById("adminMessageModalReceived"),
    status: document.getElementById("adminMessageModalStatus"),
    id: document.getElementById("adminMessageModalId"),
    message: document.getElementById("adminMessageModalBody")
};

function openAdminMessage(button) {

    messageModalFields.reference.textContent =
        "Customer Message #"
        + (
            button.dataset.id
            || "—"
        );

    messageModalFields.subject.textContent =
        button.dataset.subject
        || "General enquiry";

    messageModalFields.name.textContent =
        button.dataset.name
        || "—";

    messageModalFields.email.textContent =
        button.dataset.email
        || "—";

    messageModalFields.phone.textContent =
        button.dataset.phone
        || "—";

    messageModalFields.received.textContent =
        button.dataset.received
        || "—";

    messageModalFields.status.textContent =
        button.dataset.status
        || "—";

    messageModalFields.id.textContent =
        "#"
        + (
            button.dataset.id
            || "—"
        );

    messageModalFields.message.textContent =
        button.dataset.message
        || "—";

    messageModal.classList.add(
        "open"
    );

    messageModal.setAttribute(
        "aria-hidden",
        "false"
    );

    document.body.classList.add(
        "message-modal-open"
    );
}

function closeAdminMessage() {

    if (!messageModal) {
        return;
    }

    messageModal.classList.remove(
        "open"
    );

    messageModal.setAttribute(
        "aria-hidden",
        "true"
    );

    document.body.classList.remove(
        "message-modal-open"
    );
}

document
    .querySelectorAll(
        ".js-view-message"
    )
    .forEach(
        function (button) {

            button.addEventListener(
                "click",
                function () {
                    openAdminMessage(
                        button
                    );
                }
            );
        }
    );

if (messageModalClose) {

    messageModalClose.addEventListener(
        "click",
        closeAdminMessage
    );
}

if (messageModal) {

    messageModal.addEventListener(
        "click",
        function (event) {

            if (
                event.target
                === messageModal
            ) {
                closeAdminMessage();
            }
        }
    );
}

document.addEventListener(
    "keydown",
    function (event) {

        if (
            event.key === "Escape"
            && messageModal
            && messageModal.classList.contains(
                "open"
            )
        ) {
            closeAdminMessage();
        }
    }
);

</script>


</body>

</html>

<?php

if ($stmt) {
    $stmt->close();
}

?>