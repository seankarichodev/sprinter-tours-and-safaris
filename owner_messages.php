<?php
require_once __DIR__ . "/admin_auth.php";
requireOwner();
require_once __DIR__ . "/db.php";

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");

if (!in_array($status, ["", "Unread", "Read"], true)) {
    $status = "";
}

$stats = [
    "total" => 0,
    "unread" => 0,
    "message_read" => 0,
];

$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'unread' THEN 1 ELSE 0 END) AS unread,
        SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'read' THEN 1 ELSE 0 END) AS message_read
    FROM messages
");

if ($statsResult && ($statsRow = $statsResult->fetch_assoc())) {
    $stats["total"] = (int)($statsRow["total"] ?? 0);
    $stats["unread"] = (int)($statsRow["unread"] ?? 0);
    $stats["message_read"] = (int)($statsRow["message_read"] ?? 0);
}

$sql = "
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
        AND (
            ? = ''
            OR LOWER(COALESCE(status, '')) = LOWER(?)
        )
    ORDER BY created_at DESC, id DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to prepare messages query.");
}

$stmt->bind_param(
    "ssssssss",
    $search,
    $search,
    $search,
    $search,
    $search,
    $search,
    $status,
    $status
);

$stmt->execute();
$rows = $stmt->get_result();

function oe($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function messageStatusClass($status): string
{
    return strtolower(trim((string)$status)) === "unread" ? "unread" : "read";
}

function formatMessageDate($value): string
{
    if (!$value) {
        return "—";
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date("d M Y H:i", $timestamp) : "—";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages Oversight | Sprinter Tours & Safaris</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root{
            --bg:#080606;
            --panel:#151010;
            --panel2:#1b1212;
            --red:#e23333;
            --gold:#d6a64d;
            --goldSoft:#edcb7d;
            --text:#f7f1ed;
            --muted:#a69791;
            --border:rgba(255,255,255,.08);
            --green:#52bb7e;
            --amber:#e6b653;
            --danger:#e05b5b;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            min-height:100vh;
            font-family:"DM Sans",sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at 10% 5%,rgba(154,21,21,.18),transparent 28%),
                radial-gradient(circle at 95% 90%,rgba(98,5,5,.16),transparent 32%),
                linear-gradient(135deg,#090707,#120808 48%,#050505);
        }

        body.modal-open{overflow:hidden}
        a{color:inherit}

        .owner-shell{
            min-height:100vh;
            display:grid;
            grid-template-columns:250px minmax(0,1fr);
        }

        .sidebar{
            position:sticky;
            top:0;
            height:100vh;
            padding:26px 18px;
            display:flex;
            flex-direction:column;
            background:linear-gradient(180deg,#260909,#140707 50%,#090606);
            border-right:1px solid rgba(230,55,55,.16);
        }

        .brand{
            display:flex;
            align-items:center;
            gap:12px;
            padding:7px 6px 22px;
            border-bottom:1px solid rgba(255,255,255,.07);
        }

        .brand img{
            width:46px;
            height:46px;
            object-fit:contain;
            background:#fff;
            padding:4px;
            border-radius:12px;
        }

        .brand strong{display:block;font-size:14px}
        .brand span{
            display:block;
            margin-top:3px;
            color:var(--goldSoft);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.5px;
            text-transform:uppercase;
        }

        .nav{
            margin-top:26px;
            display:grid;
            gap:8px;
        }

        .nav-label{
            margin:10px 10px 5px;
            color:#7f706b;
            font-size:8px;
            font-weight:800;
            letter-spacing:1.6px;
            text-transform:uppercase;
        }

        .nav a{
            min-height:44px;
            padding:10px 12px;
            display:flex;
            align-items:center;
            gap:11px;
            border-radius:11px;
            color:#cfc2bc;
            text-decoration:none;
            font-size:11px;
            font-weight:700;
        }

        .nav a i{
            width:28px;
            height:28px;
            display:grid;
            place-items:center;
            border-radius:8px;
            color:var(--gold);
            background:rgba(255,255,255,.04);
        }

        .nav a:hover,
        .nav a.active{
            color:#fff;
            background:linear-gradient(90deg,rgba(180,27,27,.30),rgba(105,10,10,.17));
        }

        .nav a.active{box-shadow:inset 3px 0 0 var(--red)}

        .sidebar-bottom{
            margin-top:auto;
            padding-top:18px;
            border-top:1px solid rgba(255,255,255,.07);
        }

        .profile{
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px 8px;
        }

        .avatar{
            width:36px;
            height:36px;
            display:grid;
            place-items:center;
            border-radius:10px;
            color:var(--goldSoft);
            background:linear-gradient(135deg,#8f1717,#d62e2e);
        }

        .profile strong{display:block;font-size:11px}
        .profile span{
            display:block;
            margin-top:2px;
            color:#857772;
            font-size:8px;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .btn{
            min-height:42px;
            padding:10px 14px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            border:1px solid rgba(255,255,255,.08);
            border-radius:11px;
            background:rgba(255,255,255,.03);
            color:#ddd0ca;
            text-decoration:none;
            font-family:inherit;
            font-size:10px;
            font-weight:700;
            cursor:pointer;
        }

        .btn:hover{
            border-color:rgba(214,166,77,.28);
            background:rgba(214,166,77,.07);
        }

        .btn-primary{
            border-color:rgba(226,51,51,.30);
            background:linear-gradient(135deg,#a51b1b,#df3030);
            color:#fff;
        }

        .main{
            min-width:0;
            padding:30px;
        }

        .topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
            margin-bottom:24px;
        }

        .heading small{
            display:block;
            margin-bottom:7px;
            color:var(--red);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.8px;
            text-transform:uppercase;
        }

        .heading h1{
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:clamp(31px,4vw,45px);
            line-height:1;
        }

        .heading h1 span{color:var(--red)}
        .heading p{
            margin:9px 0 0;
            color:var(--muted);
            font-size:11px;
        }

        .hero{
            position:relative;
            overflow:hidden;
            padding:24px 26px;
            margin-bottom:18px;
            border:1px solid rgba(220,52,52,.22);
            border-radius:18px;
            background:
                linear-gradient(90deg,rgba(83,10,10,.94),rgba(30,7,7,.95)),
                url("images/owner-lion.jpg");
            background-size:cover;
            background-position:78% 42%;
        }

        .hero::after{
            content:"";
            position:absolute;
            inset:0;
            background:linear-gradient(90deg,rgba(75,7,7,.90),rgba(22,5,5,.66),rgba(6,6,6,.15));
        }

        .hero>*{position:relative;z-index:2}
        .hero small{
            color:var(--goldSoft);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.7px;
            text-transform:uppercase;
        }

        .hero h2{
            margin:8px 0 5px;
            font-family:"Playfair Display",serif;
            font-size:clamp(26px,3vw,36px);
        }

        .hero h2 span{color:var(--red)}
        .hero p{
            max-width:760px;
            margin:0;
            color:#cbbcb6;
            font-size:11px;
            line-height:1.6;
        }

        .stats{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:14px;
            margin-bottom:18px;
        }

        .stat{
            padding:18px;
            border:1px solid var(--border);
            border-radius:15px;
            background:linear-gradient(180deg,#1a1212,#110d0d);
        }

        .stat-top{
            display:flex;
            justify-content:space-between;
            gap:10px;
        }

        .stat-label{
            margin:0;
            color:#968782;
            font-size:8px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .stat-icon{
            width:34px;
            height:34px;
            display:grid;
            place-items:center;
            border-radius:9px;
            color:var(--gold);
            background:rgba(157,21,21,.18);
        }

        .stat-value{
            margin-top:15px;
            font-family:"Playfair Display",serif;
            font-size:clamp(23px,3vw,31px);
        }

        .stat-note{
            margin:7px 0 0;
            color:#756965;
            font-size:9px;
        }

        .filters{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-bottom:16px;
        }

        .filters input,
        .filters select{
            min-height:42px;
            padding:0 12px;
            border:1px solid rgba(255,255,255,.09);
            border-radius:10px;
            background:#151010;
            color:#fff;
            outline:none;
            font:inherit;
            font-size:10px;
        }

        .filters input{
            min-width:240px;
            flex:1;
        }

        .filters input:focus,
        .filters select:focus{
            border-color:rgba(214,166,77,.35);
        }

        .panel{
            min-width:0;
            overflow:hidden;
            border:1px solid var(--border);
            border-radius:16px;
            background:linear-gradient(180deg,#191212,#0f0c0c);
            margin-bottom:16px;
        }

        .panel-head{
            min-height:58px;
            padding:15px 18px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            border-bottom:1px solid rgba(255,255,255,.06);
        }

        .panel-head h2{margin:0;font-size:12px}
        .panel-head span{
            color:#887a75;
            font-size:9px;
        }

        .table-wrap{overflow-x:auto}
        .table{
            width:100%;
            min-width:900px;
            border-collapse:collapse;
        }

        .table th{
            padding:12px 14px;
            color:#776a65;
            font-size:8px;
            text-align:left;
            text-transform:uppercase;
            letter-spacing:1px;
            border-bottom:1px solid rgba(255,255,255,.06);
        }

        .table td{
            padding:13px 14px;
            color:#c8bbb5;
            font-size:9px;
            border-bottom:1px solid rgba(255,255,255,.05);
            vertical-align:middle;
        }

        .table tbody tr:hover{background:rgba(255,255,255,.018)}
        .table strong{color:#f2e9e5}
        .table small{color:#776a65}
        .message-preview{
            display:block;
            max-width:360px;
            color:#bcaea8;
            line-height:1.55;
        }

        .pill{
            display:inline-flex;
            min-height:25px;
            padding:5px 8px;
            align-items:center;
            border-radius:999px;
            font-size:8px;
            font-weight:800;
            text-transform:uppercase;
        }

        .pill.unread{
            color:#ebbf63;
            background:rgba(169,118,28,.16);
        }

        .pill.read{
            color:#75d7a1;
            background:rgba(44,135,83,.16);
        }

        .view-btn{
            min-height:32px;
            padding:7px 10px;
            white-space:nowrap;
        }

        .empty{
            padding:28px 18px;
            color:#796c67;
            font-size:9px;
            text-align:center;
        }

        .mobile-menu{display:none}

        .modal-backdrop{
            position:fixed;
            z-index:500;
            inset:0;
            display:none;
            align-items:center;
            justify-content:center;
            padding:24px;
            background:rgba(0,0,0,.78);
            backdrop-filter:blur(5px);
        }

        .modal-backdrop.open{display:flex}

        .message-modal{
            width:min(720px,100%);
            max-height:88vh;
            overflow:auto;
            border:1px solid rgba(226,51,51,.24);
            border-radius:18px;
            background:linear-gradient(180deg,#1d1212,#0e0b0b);
            box-shadow:0 24px 80px rgba(0,0,0,.55);
        }

        .modal-head{
            position:sticky;
            top:0;
            z-index:2;
            padding:18px 20px;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            border-bottom:1px solid rgba(255,255,255,.07);
            background:rgba(20,13,13,.96);
            backdrop-filter:blur(10px);
        }

        .modal-head small{
            display:block;
            margin-bottom:5px;
            color:var(--goldSoft);
            font-size:8px;
            font-weight:800;
            letter-spacing:1.4px;
            text-transform:uppercase;
        }

        .modal-head h3{
            margin:0;
            font-family:"Playfair Display",serif;
            font-size:24px;
        }

        .modal-close{
            width:38px;
            height:38px;
            flex:0 0 38px;
            border:1px solid rgba(255,255,255,.08);
            border-radius:10px;
            background:rgba(255,255,255,.04);
            color:#fff;
            cursor:pointer;
        }

        .modal-body{padding:20px}
        .message-meta{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:10px;
            margin-bottom:18px;
        }

        .meta-card{
            padding:12px 14px;
            border:1px solid rgba(255,255,255,.06);
            border-radius:11px;
            background:rgba(255,255,255,.025);
        }

        .meta-card span{
            display:block;
            margin-bottom:5px;
            color:#786b66;
            font-size:8px;
            font-weight:800;
            letter-spacing:1px;
            text-transform:uppercase;
        }

        .meta-card strong{
            display:block;
            color:#eadfda;
            font-size:10px;
            word-break:break-word;
        }

        .full-message{
            padding:18px;
            border:1px solid rgba(255,255,255,.07);
            border-radius:13px;
            background:#120d0d;
            color:#d6c8c2;
            font-size:11px;
            line-height:1.8;
            white-space:pre-wrap;
            overflow-wrap:anywhere;
        }

        .modal-note{
            margin:14px 0 0;
            color:#776a65;
            font-size:9px;
            line-height:1.6;
        }

        @media(max-width:1100px){
            .owner-shell{grid-template-columns:210px minmax(0,1fr)}
        }

        @media(max-width:860px){
            .owner-shell{display:block}
            .sidebar{
                position:fixed;
                z-index:100;
                left:-260px;
                width:250px;
                transition:left .2s ease;
            }
            .sidebar.open{left:0}
            .main{padding:20px}
            .mobile-menu{display:inline-flex}
        }

        @media(max-width:700px){
            .stats{grid-template-columns:1fr}
            .message-meta{grid-template-columns:1fr}
        }

        @media(max-width:600px){
            .main{padding:15px}
            .topbar{
                align-items:flex-start;
                flex-direction:column;
            }
            .filters{display:grid}
            .filters input{min-width:0;width:100%}
            .filters select,.filters button{width:100%}
            .modal-backdrop{padding:12px}
        }
    </style>

    <link rel="stylesheet" href="owner_readability.css">
</head>
<body>

<div class="owner-shell">

    <aside class="sidebar" id="ownerSidebar">
        <div class="brand">
            <img src="images/Wildlife Sprinter Tours & Safaris.png" alt="Sprinter Tours & Safaris">
            <div>
                <strong>Sprinter Tours & Safaris</strong>
                <span>Owner Command Center</span>
            </div>
        </div>

        <nav class="nav">
            <div class="nav-label">Executive</div>

            <a href="owner_dashboard.php">
                <i class="fa-solid fa-crown"></i>
                Command Center
            </a>

            <a href="owner_reports.php">
                <i class="fa-solid fa-chart-pie"></i>
                Business Reports
            </a>

            <a href="owner_payments.php">
                <i class="fa-solid fa-credit-card"></i>
                Payments
            </a>

            <div class="nav-label">Oversight</div>

            <a href="owner_bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                Bookings
            </a>

            <a href="owner_customers.php">
                <i class="fa-solid fa-users"></i>
                Customers
            </a>

            <a href="owner_messages.php" class="active">
                <i class="fa-solid fa-envelope"></i>
                Messages
            </a>

            <a href="owner_audit.php">
                <i class="fa-solid fa-shield-halved"></i>
                Audit Activity
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="profile">
                <div class="avatar">
                    <i class="fa-solid fa-crown"></i>
                </div>
                <div>
                    <strong><?php echo oe($adminUsername); ?></strong>
                    <span>Owner</span>
                </div>
            </div>

            <a href="admin_logout.php" class="btn" style="width:100%;margin-top:8px;">
                <i class="fa-solid fa-right-from-bracket"></i>
                Sign Out
            </a>
        </div>
    </aside>

    <main class="main">

        <header class="topbar">
            <div class="heading">
                <small>Executive Oversight</small>
                <h1>Messages <span>Oversight.</span></h1>
                <p>Review customer enquiries and office response status without altering the operational inbox.</p>
            </div>

            <button type="button" class="btn mobile-menu" id="ownerMenu">
                <i class="fa-solid fa-bars"></i>
                Menu
            </button>
        </header>

        <section class="hero">
            <small>Customer Communication Oversight</small>
            <h2>See every enquiry. <span>Miss nothing.</span></h2>
            <p>
                Owner-only visibility into customer messages, contact details and current read status.
                Message handling remains an operational Admin responsibility.
            </p>
        </section>

        <section class="stats">
            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Total Messages</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats["total"]); ?></div>
                <p class="stat-note">All customer enquiries</p>
            </article>

            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Unread</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats["unread"]); ?></div>
                <p class="stat-note">Needs operational attention</p>
            </article>

            <article class="stat">
                <div class="stat-top">
                    <p class="stat-label">Read</p>
                    <div class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats["message_read"]); ?></div>
                <p class="stat-note">Already reviewed by the office</p>
            </article>
        </section>

        <form class="filters" method="GET" action="owner_messages.php">
            <input
                type="search"
                name="search"
                value="<?php echo oe($search); ?>"
                placeholder="Search customer, email, phone, subject or message"
                autocomplete="off"
            >

            <select name="status">
                <option value="">All statuses</option>
                <option value="Unread" <?php echo $status === "Unread" ? "selected" : ""; ?>>Unread</option>
                <option value="Read" <?php echo $status === "Read" ? "selected" : ""; ?>>Read</option>
            </select>

            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>

            <?php if ($search !== "" || $status !== ""): ?>
                <a class="btn" href="owner_messages.php">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </a>
            <?php endif; ?>
        </form>

        <section class="panel">
            <div class="panel-head">
                <h2>Customer Inbox</h2>
                <span>Owner read-only oversight · latest 100 messages</span>
            </div>

            <div class="table-wrap">
                <?php if ($rows && $rows->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Received</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <?php
                                $statusClass = messageStatusClass($row["status"] ?? "");
                                $displayStatus = trim((string)($row["status"] ?? "")) !== ""
                                    ? (string)$row["status"]
                                    : "Unknown";

                                $subject = trim((string)($row["subject"] ?? ""));
                                $subject = $subject !== "" ? $subject : "No subject";

                                $message = (string)($row["message"] ?? "");
                                $preview = mb_strimwidth($message, 0, 120, "...");
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo oe($row["name"] ?: "Unknown customer"); ?></strong><br>
                                    <small><?php echo oe($row["email"] ?: "—"); ?></small>
                                </td>

                                <td><?php echo oe($row["phone"] ?: "—"); ?></td>

                                <td><?php echo oe($subject); ?></td>

                                <td>
                                    <span class="message-preview"><?php echo oe($preview ?: "—"); ?></span>
                                </td>

                                <td>
                                    <span class="pill <?php echo oe($statusClass); ?>">
                                        <?php echo oe($displayStatus); ?>
                                    </span>
                                </td>

                                <td><?php echo oe(formatMessageDate($row["created_at"] ?? null)); ?></td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn view-btn js-view-message"
                                        data-id="<?php echo (int)$row["id"]; ?>"
                                        data-name="<?php echo oe($row["name"] ?: "Unknown customer"); ?>"
                                        data-email="<?php echo oe($row["email"] ?: "—"); ?>"
                                        data-phone="<?php echo oe($row["phone"] ?: "—"); ?>"
                                        data-subject="<?php echo oe($subject); ?>"
                                        data-status="<?php echo oe($displayStatus); ?>"
                                        data-received="<?php echo oe(formatMessageDate($row["created_at"] ?? null)); ?>"
                                        data-message="<?php echo oe($message); ?>"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                        View
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">
                        <i class="fa-regular fa-envelope" style="margin-right:6px;"></i>
                        No messages match this filter.
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>
</div>

<div class="modal-backdrop" id="messageModal" aria-hidden="true">
    <section class="message-modal" role="dialog" aria-modal="true" aria-labelledby="modalSubject">
        <div class="modal-head">
            <div>
                <small id="modalReference">Customer Message</small>
                <h3 id="modalSubject">Message details</h3>
            </div>

            <button type="button" class="modal-close" id="modalClose" aria-label="Close message">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="message-meta">
                <div class="meta-card">
                    <span>Customer</span>
                    <strong id="modalName">—</strong>
                </div>

                <div class="meta-card">
                    <span>Email</span>
                    <strong id="modalEmail">—</strong>
                </div>

                <div class="meta-card">
                    <span>Phone</span>
                    <strong id="modalPhone">—</strong>
                </div>

                <div class="meta-card">
                    <span>Received</span>
                    <strong id="modalReceived">—</strong>
                </div>

                <div class="meta-card">
                    <span>Status</span>
                    <strong id="modalStatus">—</strong>
                </div>

                <div class="meta-card">
                    <span>Message ID</span>
                    <strong id="modalId">—</strong>
                </div>
            </div>

            <div class="full-message" id="modalMessage">—</div>

            <p class="modal-note">
                <i class="fa-solid fa-lock"></i>
                Owner oversight is read-only. Opening this window does not change the message status or alter the Admin inbox.
            </p>
        </div>
    </section>
</div>

<script>
    const sidebar = document.getElementById("ownerSidebar");
    const menuButton = document.getElementById("ownerMenu");

    if (sidebar && menuButton) {
        menuButton.addEventListener("click", () => {
            sidebar.classList.toggle("open");
        });
    }

    const modal = document.getElementById("messageModal");
    const modalClose = document.getElementById("modalClose");

    const fields = {
        reference: document.getElementById("modalReference"),
        subject: document.getElementById("modalSubject"),
        name: document.getElementById("modalName"),
        email: document.getElementById("modalEmail"),
        phone: document.getElementById("modalPhone"),
        received: document.getElementById("modalReceived"),
        status: document.getElementById("modalStatus"),
        id: document.getElementById("modalId"),
        message: document.getElementById("modalMessage")
    };

    function openMessage(button) {
        fields.reference.textContent = "Customer Message #" + (button.dataset.id || "—");
        fields.subject.textContent = button.dataset.subject || "No subject";
        fields.name.textContent = button.dataset.name || "—";
        fields.email.textContent = button.dataset.email || "—";
        fields.phone.textContent = button.dataset.phone || "—";
        fields.received.textContent = button.dataset.received || "—";
        fields.status.textContent = button.dataset.status || "—";
        fields.id.textContent = "#" + (button.dataset.id || "—");
        fields.message.textContent = button.dataset.message || "—";

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
    }

    function closeMessage() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
    }

    document.querySelectorAll(".js-view-message").forEach((button) => {
        button.addEventListener("click", () => openMessage(button));
    });

    if (modalClose) {
        modalClose.addEventListener("click", closeMessage);
    }

    if (modal) {
        modal.addEventListener("click", (event) => {
            if (event.target === modal) {
                closeMessage();
            }
        });
    }

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal && modal.classList.contains("open")) {
            closeMessage();
        }
    });
</script>

</body>
</html>
