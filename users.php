<?php

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/db.php";


/* =========================================================
   INPUTS
========================================================= */

$search =
    trim(
        $_GET["search"] ?? ""
    );


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
   COUNT CUSTOMERS
========================================================= */

$countSql =
    "
    SELECT
        COUNT(*) AS total
    FROM users u
    WHERE
    (
        ? = ''
        OR u.name LIKE CONCAT('%', ?, '%')
        OR u.email LIKE CONCAT('%', ?, '%')
    )
    ";


$countStmt =
    $conn->prepare(
        $countSql
    );


$totalRecords = 0;


if ($countStmt) {

    $countStmt->bind_param(
        "sss",
        $search,
        $search,
        $search
    );


    $countStmt->execute();


    $countResult =
        $countStmt->get_result();


    if ($countResult) {

        $countRow =
            $countResult->fetch_assoc();


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
            $totalRecords / $limit
        )
    );


if ($page > $totalPages) {
    $page = $totalPages;
}


$offset =
    ($page - 1)
    * $limit;



/* =========================================================
   FETCH CUSTOMERS + BUSINESS INFORMATION
========================================================= */

$sql =
    "
    SELECT

        u.id,
        u.name,
        u.email,
        u.created_at,

        COUNT(b.id)
            AS total_bookings,

        COALESCE(
            SUM(
                CASE

                    WHEN LOWER(
                        COALESCE(
                            b.payment_status,
                            ''
                        )
                    ) = 'paid'

                    THEN b.amount

                    ELSE 0

                END
            ),
            0
        )
            AS total_spent,

        MAX(
            CASE

                WHEN b.phone IS NOT NULL
                     AND b.phone <> ''

                THEN b.phone

                ELSE NULL

            END
        )
            AS phone,

        MAX(b.date)
            AS latest_travel_date

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

    ORDER BY
        u.created_at DESC

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
        "sss",
        $search,
        $search,
        $search
    );


    $stmt->execute();


    $result =
        $stmt->get_result();
}



/* =========================================================
   HELPERS
========================================================= */

function customerEscape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function customerInitials(
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
        Customers | Sprinter Admin
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
                 HEADER
            ============================================== -->

            <section class="admin-page-header">

                <div>

                    <h1>
                        Customers
                    </h1>

                    <p>
                        View registered customers,
                        booking activity and customer value.
                    </p>

                </div>

            </section>



            <!-- =============================================
                 SEARCH
            ============================================== -->

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
                 CUSTOMER TABLE
            ============================================== -->

            <section class="admin-panel">


                <div class="admin-panel-header">

                    <h2>
                        All Customers
                    </h2>


                    <span
                        style="
                            font-size: 12px;
                            color: var(--admin-muted);
                        "
                    >

                        <?php
                            echo number_format(
                                $totalRecords
                            );
                        ?>

                        customer<?php
                            echo $totalRecords === 1
                                ? ""
                                : "s";
                        ?>

                    </span>

                </div>



                <div class="admin-table-wrapper">


                    <?php if (
                        $result &&
                        $result->num_rows > 0
                    ): ?>


                        <table class="admin-table">


                            <thead>

                                <tr>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Contact
                                    </th>

                                    <th>
                                        Bookings
                                    </th>

                                    <th>
                                        Total Spent
                                    </th>

                                    <th>
                                        Latest Travel
                                    </th>

                                    <th>
                                        Joined
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>



                            <tbody>


                            <?php while (
                                $customer =
                                    $result->fetch_assoc()
                            ): ?>


                                <tr>


                                    <!-- CUSTOMER -->

                                    <td>

                                        <div
                                            style="
                                                display:flex;
                                                align-items:center;
                                                gap:11px;
                                            "
                                        >


                                            <div
                                                style="
                                                    width:38px;
                                                    height:38px;
                                                    border-radius:50%;
                                                    display:grid;
                                                    place-items:center;
                                                    background:#e8f5ee;
                                                    color:var(--admin-green);
                                                    font-size:12px;
                                                    font-weight:800;
                                                    flex-shrink:0;
                                                "
                                            >

                                                <?php
                                                    echo customerEscape(
                                                        customerInitials(
                                                            $customer[
                                                                "name"
                                                            ]
                                                            ?? ""
                                                        )
                                                    );
                                                ?>

                                            </div>


                                            <div class="customer-cell">

                                                <strong>

                                                    <?php
                                                        echo customerEscape(
                                                            $customer[
                                                                "name"
                                                            ]
                                                            ?? ""
                                                        );
                                                    ?>

                                                </strong>


                                                <span>

                                                    Customer
                                                    #<?php
                                                        echo (int)
                                                            $customer["id"];
                                                    ?>

                                                </span>

                                            </div>


                                        </div>

                                    </td>



                                    <!-- CONTACT -->

                                    <td class="customer-cell">

                                        <strong>

                                            <?php
                                                echo customerEscape(
                                                    $customer[
                                                        "email"
                                                    ]
                                                    ?? ""
                                                );
                                            ?>

                                        </strong>


                                        <span>

                                            <?php

                                            $phone =
                                                trim(
                                                    (string) (
                                                        $customer[
                                                            "phone"
                                                        ]
                                                        ?? ""
                                                    )
                                                );


                                            echo $phone !== ""
                                                ? customerEscape(
                                                    $phone
                                                )
                                                : "No phone recorded";

                                            ?>

                                        </span>

                                    </td>



                                    <!-- BOOKINGS -->

                                    <td>

                                        <strong>

                                            <?php
                                                echo number_format(
                                                    (int) (
                                                        $customer[
                                                            "total_bookings"
                                                        ]
                                                        ?? 0
                                                    )
                                                );
                                            ?>

                                        </strong>

                                    </td>



                                    <!-- TOTAL SPENT -->

                                    <td class="amount-cell">

                                        KES

                                        <?php
                                            echo number_format(
                                                (float) (
                                                    $customer[
                                                        "total_spent"
                                                    ]
                                                    ?? 0
                                                ),
                                                0
                                            );
                                        ?>

                                    </td>



                                    <!-- LATEST TRAVEL -->

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



                                    <!-- JOINED -->

                                    <td>

                                        <?php

                                        $joined =
                                            $customer[
                                                "created_at"
                                            ]
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



                                    <!-- ACTION -->

                                    <td>

                                        <a
                                            href="customer.php?id=<?php echo (int) $customer["id"]; ?>"
                                            class="admin-action-link admin-action-view"
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
                                            => $search
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

</script>


</body>

</html>

<?php

if ($stmt) {
    $stmt->close();
}

?>