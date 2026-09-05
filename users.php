<?php

require_once __DIR__ . "/admin_auth.php";
requireAdmin();
require_once __DIR__ . "/db.php";


/* =========================================================
   HELPERS
========================================================= */

function customerEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function customerInitials(string $name): string
{
    $name = trim($name);

    if ($name === "") {
        return "?";
    }

    $parts = preg_split('/\s+/', $name);

    if (!$parts) {
        return "?";
    }

    $initials = "";

    foreach (array_slice($parts, 0, 2) as $part) {
        if ($part !== "") {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return $initials !== "" ? $initials : "?";
}


/* =========================================================
   INPUTS
========================================================= */

$search = trim($_GET["search"] ?? "");

$allowedLimits = [5, 10, 25, 50];

$limit = isset($_GET["limit"])
    ? (int) $_GET["limit"]
    : 10;

if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$page = isset($_GET["page"])
    ? max(1, (int) $_GET["page"])
    : 1;


/* =========================================================
   CUSTOMER SUMMARY
========================================================= */

$summary = [
    "registered" => 0,
    "with_bookings" => 0,
    "returning" => 0,
    "live_value" => 0.0
];

$summarySql = "
    SELECT
        COUNT(*) AS registered,

        SUM(
            CASE
                WHEN booking_count > 0
                THEN 1
                ELSE 0
            END
        ) AS with_bookings,

        SUM(
            CASE
                WHEN booking_count > 1
                THEN 1
                ELSE 0
            END
        ) AS returning_customers,

        COALESCE(
            SUM(live_value),
            0
        ) AS live_value

    FROM
    (
        SELECT
            u.id,

            COUNT(b.id) AS booking_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(COALESCE(b.payment_status, '')) = 'paid'
                             AND b.amount > 1
                        THEN b.amount
                        ELSE 0
                    END
                ),
                0
            ) AS live_value

        FROM users u

        LEFT JOIN bookings b
            ON b.user_id = u.id

        GROUP BY u.id
    ) customer_summary
";

$summaryResult = $conn->query($summarySql);

if ($summaryResult && ($summaryRow = $summaryResult->fetch_assoc())) {
    $summary["registered"] = (int) ($summaryRow["registered"] ?? 0);
    $summary["with_bookings"] = (int) ($summaryRow["with_bookings"] ?? 0);
    $summary["returning"] = (int) ($summaryRow["returning_customers"] ?? 0);
    $summary["live_value"] = (float) ($summaryRow["live_value"] ?? 0);
}


/* =========================================================
   COUNT CUSTOMERS
========================================================= */

$countSql = "
    SELECT COUNT(*) AS total
    FROM users u
    WHERE
    (
        ? = ''
        OR u.name LIKE CONCAT('%', ?, '%')
        OR u.email LIKE CONCAT('%', ?, '%')
    )
";

$countStmt = $conn->prepare($countSql);

$totalRecords = 0;

if ($countStmt) {
    $countStmt->bind_param(
        "sss",
        $search,
        $search,
        $search
    );

    $countStmt->execute();
    $countResult = $countStmt->get_result();

    if ($countResult && ($countRow = $countResult->fetch_assoc())) {
        $totalRecords = (int) ($countRow["total"] ?? 0);
    }

    $countStmt->close();
}

$totalPages = max(
    1,
    (int) ceil($totalRecords / $limit)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;


/* =========================================================
   FETCH CUSTOMERS
========================================================= */

$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.created_at,

        COUNT(b.id) AS total_bookings,

        SUM(
            CASE
                WHEN LOWER(COALESCE(b.payment_status, '')) = 'paid'
                THEN 1
                ELSE 0
            END
        ) AS paid_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(b.payment_status, '')) = 'paid'
                         AND b.amount > 1
                    THEN b.amount
                    ELSE 0
                END
            ),
            0
        ) AS live_value,

        MAX(
            CASE
                WHEN b.phone IS NOT NULL
                     AND b.phone <> ''
                THEN b.phone
                ELSE NULL
            END
        ) AS phone,

        MAX(b.date) AS latest_travel_date

    FROM users u

    LEFT JOIN bookings b
        ON b.user_id = u.id

    WHERE
    (
        ? = ''
        OR u.name LIKE CONCAT('%', ?, '%')
        OR u.email LIKE CONCAT('%', ?, '%')
    )

    GROUP BY
        u.id,
        u.name,
        u.email,
        u.created_at

    ORDER BY u.created_at DESC

    LIMIT $limit
    OFFSET $offset
";

$stmt = $conn->prepare($sql);
$result = null;

if ($stmt) {
    $stmt->bind_param(
        "sss",
        $search,
        $search,
        $search
    );

    $stmt->execute();
    $result = $stmt->get_result();
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

    <title>Customers | Sprinter Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

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
        .customer-summary-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
            margin-bottom:20px;
        }

        .customer-summary-card{
            position:relative;
            overflow:hidden;
            padding:18px;
            border:1px solid var(--admin-border);
            border-radius:var(--radius-md);
            background:var(--admin-card);
            box-shadow:var(--shadow-sm);
        }

        .customer-summary-card::after{
            content:"";
            position:absolute;
            width:76px;
            height:76px;
            top:-28px;
            right:-28px;
            border-radius:50%;
            background:rgba(23,107,69,.05);
        }

        .customer-summary-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
        }

        .customer-summary-label{
            color:var(--admin-muted);
            font-size:11px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.7px;
        }

        .customer-summary-icon{
            width:36px;
            height:36px;
            display:grid;
            place-items:center;
            border-radius:10px;
            background:linear-gradient(
                135deg,
                var(--admin-green-soft),
                #f8f0dd
            );
            color:var(--admin-green);
        }

        .customer-summary-value{
            margin-top:14px;
            color:var(--admin-text);
            font-size:27px;
            font-weight:800;
        }

        .customer-summary-note{
            margin:5px 0 0;
            color:var(--admin-muted);
            font-size:11px;
        }

        .customer-list-avatar{
            width:40px;
            height:40px;
            flex:0 0 40px;
            display:grid;
            place-items:center;
            border-radius:50%;
            background:linear-gradient(
                135deg,
                #e7f2ea,
                #f4e8ca
            );
            color:var(--admin-green-dark);
            border:1px solid rgba(23,107,69,.10);
            font-size:12px;
            font-weight:800;
        }

        .customer-list-name{
            display:flex;
            align-items:center;
            gap:11px;
        }

        .customer-subline{
            display:block;
            margin-top:3px;
            color:var(--admin-muted);
            font-size:11px;
        }

        .customer-action{
            white-space:nowrap;
        }

        @media(max-width:1100px){
            .customer-summary-grid{
                grid-template-columns:repeat(2,minmax(0,1fr));
            }
        }

        @media(max-width:760px){
            .customer-summary-grid{
                grid-template-columns:1fr;
            }
        }
    </style>

</head>

<body>

<div class="admin-layout">

    <?php
        $activePage = "customers";
        require __DIR__ . "/admin_sidebar.php";
    ?>

    <div class="admin-main">

        <?php require __DIR__ . "/admin_topbar.php"; ?>

        <main class="admin-content">

            <section class="admin-page-header">

                <div>
                    <h1>Customers</h1>

                    <p>
                        Find customers, review contact details and open their booking history.
                    </p>
                </div>

            </section>


            <section class="customer-summary-grid">

                <article class="customer-summary-card">
                    <div class="customer-summary-top">
                        <span class="customer-summary-label">
                            Registered Customers
                        </span>

                        <div class="customer-summary-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>

                    <div class="customer-summary-value">
                        <?php echo number_format($summary["registered"]); ?>
                    </div>

                    <p class="customer-summary-note">
                        Customer accounts in the system
                    </p>
                </article>


                <article class="customer-summary-card">
                    <div class="customer-summary-top">
                        <span class="customer-summary-label">
                            With Bookings
                        </span>

                        <div class="customer-summary-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="customer-summary-value">
                        <?php echo number_format($summary["with_bookings"]); ?>
                    </div>

                    <p class="customer-summary-note">
                        Customers with at least one booking
                    </p>
                </article>


                <article class="customer-summary-card">
                    <div class="customer-summary-top">
                        <span class="customer-summary-label">
                            Returning Customers
                        </span>

                        <div class="customer-summary-icon">
                            <i class="fa-solid fa-rotate"></i>
                        </div>
                    </div>

                    <div class="customer-summary-value">
                        <?php echo number_format($summary["returning"]); ?>
                    </div>

                    <p class="customer-summary-note">
                        Customers with multiple bookings
                    </p>
                </article>


                <article class="customer-summary-card">
                    <div class="customer-summary-top">
                        <span class="customer-summary-label">
                            Live Customer Value
                        </span>

                        <div class="customer-summary-icon">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>

                    <div class="customer-summary-value">
                        KES <?php echo number_format($summary["live_value"], 0); ?>
                    </div>

                    <p class="customer-summary-note">
                        Paid live-value bookings only
                    </p>
                </article>

            </section>


            <form
                method="GET"
                action="users.php"
                class="admin-toolbar"
            >

                <div class="admin-toolbar-group">

                    <div class="admin-search">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            name="search"
                            value="<?php echo customerEscape($search); ?>"
                            placeholder="Search customer name or email..."
                        >

                    </div>


                    <button
                        type="submit"
                        class="admin-button admin-button-primary"
                    >

                        <i class="fa-solid fa-magnifying-glass"></i>
                        Search

                    </button>


                    <?php if ($search !== ""): ?>

                        <a
                            href="users.php"
                            class="admin-button admin-button-light"
                        >
                            Clear
                        </a>

                    <?php endif; ?>

                </div>


                <div class="admin-toolbar-group">

                    <label for="limit">
                        <small>Show</small>
                    </label>

                    <select
                        name="limit"
                        id="limit"
                        class="admin-select"
                        onchange="this.form.submit()"
                    >

                        <?php foreach ($allowedLimits as $option): ?>

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


            <section class="admin-panel">

                <div class="admin-panel-header">

                    <h2>Customer Directory</h2>

                    <span
                        style="
                            font-size:12px;
                            color:var(--admin-muted);
                        "
                    >

                        <?php echo number_format($totalRecords); ?>

                        customer<?php
                            echo $totalRecords === 1
                                ? ""
                                : "s";
                        ?>

                    </span>

                </div>


                <div class="admin-table-wrapper">

                    <?php if (
                        $result
                        && $result->num_rows > 0
                    ): ?>

                        <table class="admin-table">

                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Contact</th>
                                    <th>Bookings</th>
                                    <th>Paid Bookings</th>
                                    <th>Live Value</th>
                                    <th>Latest Travel</th>
                                    <th>Joined</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php while (
                                $customer = $result->fetch_assoc()
                            ): ?>

                                <tr>

                                    <td>

                                        <div class="customer-list-name">

                                            <div class="customer-list-avatar">
                                                <?php
                                                    echo customerEscape(
                                                        customerInitials(
                                                            $customer["name"]
                                                            ?? ""
                                                        )
                                                    );
                                                ?>
                                            </div>

                                            <div class="customer-cell">

                                                <strong>
                                                    <?php
                                                        echo customerEscape(
                                                            $customer["name"]
                                                            ?? ""
                                                        );
                                                    ?>
                                                </strong>

                                                <span>
                                                    Customer #<?php
                                                        echo (int)
                                                            $customer["id"];
                                                    ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <td class="customer-cell">

                                        <strong>
                                            <?php
                                                echo customerEscape(
                                                    $customer["email"]
                                                    ?? ""
                                                );
                                            ?>
                                        </strong>

                                        <span>

                                            <?php
                                                $phone =
                                                    trim(
                                                        (string) (
                                                            $customer["phone"]
                                                            ?? ""
                                                        )
                                                    );

                                                echo $phone !== ""
                                                    ? customerEscape($phone)
                                                    : "No phone recorded";
                                            ?>

                                        </span>

                                    </td>


                                    <td>
                                        <strong>
                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $customer["total_bookings"]
                                                        ?? 0
                                                    )
                                                );
                                            ?>
                                        </strong>
                                    </td>


                                    <td>
                                        <strong>
                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $customer["paid_bookings"]
                                                        ?? 0
                                                    )
                                                );
                                            ?>
                                        </strong>
                                    </td>


                                    <td class="amount-cell">

                                        KES

                                        <?php
                                            echo number_format(
                                                (float) (
                                                    $customer["live_value"]
                                                    ?? 0
                                                ),
                                                0
                                            );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                            $latestTravel =
                                                $customer[
                                                    "latest_travel_date"
                                                ]
                                                ?? null;

                                            echo $latestTravel
                                                ? date(
                                                    "d M Y",
                                                    strtotime(
                                                        $latestTravel
                                                    )
                                                )
                                                : "—";
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                            $joined =
                                                $customer["created_at"]
                                                ?? null;

                                            echo $joined
                                                ? date(
                                                    "d M Y",
                                                    strtotime(
                                                        $joined
                                                    )
                                                )
                                                : "—";
                                        ?>

                                    </td>


                                    <td>

                                        <a
                                            href="customer.php?id=<?php echo (int) $customer["id"]; ?>"
                                            class="admin-button admin-button-light customer-action"
                                        >

                                            <i class="fa-regular fa-eye"></i>
                                            View

                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="admin-empty">

                            <i
                                class="fa-solid fa-users"
                                style="
                                    display:block;
                                    font-size:30px;
                                    margin-bottom:12px;
                                "
                            ></i>

                            No customers found.

                        </div>

                    <?php endif; ?>

                </div>


                <?php if ($totalPages > 1): ?>

                    <nav class="admin-pagination">

                        <?php for (
                            $i = 1;
                            $i <= $totalPages;
                            $i++
                        ): ?>

                            <?php
                                $query =
                                    http_build_query(
                                        [
                                            "page" => $i,
                                            "limit" => $limit,
                                            "search" => $search
                                        ]
                                    );
                            ?>

                            <a
                                href="?<?php echo customerEscape($query); ?>"
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
    sidebar
    && mobileToggle
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

</script>


</body>
</html>

<?php
if ($stmt) {
    $stmt->close();
}
?>
