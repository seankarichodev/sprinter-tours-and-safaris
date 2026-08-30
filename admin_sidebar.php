<?php

$currentPage =
    basename(
        $_SERVER["PHP_SELF"]
    );

?>

<aside
    class="admin-sidebar"
    id="adminSidebar"
>

    <div class="admin-brand">

        <h2 class="admin-brand-title">
            SPRINTER
        </h2>

        <p class="admin-brand-subtitle">
            Admin Portal
        </p>

    </div>


    <div class="admin-nav-section">

        <p class="admin-nav-label">
            Overview
        </p>


        <a
            href="dashboard.php"
            class="admin-nav-link
            <?php
                echo $currentPage === "dashboard.php"
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fa-solid fa-chart-line"></i>

            Dashboard

        </a>

    </div>


    <div class="admin-nav-section">

        <p class="admin-nav-label">
            Management
        </p>


        <a
            href="bookings.php"
            class="admin-nav-link
            <?php
                echo $currentPage === "bookings.php"
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fa-solid fa-calendar-check"></i>

            Bookings

        </a>


        <a
            href="users.php"
            class="admin-nav-link
            <?php
                echo $currentPage === "users.php"
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fa-solid fa-users"></i>

            Customers

        </a>


        <a
            href="messages.php"
            class="admin-nav-link
            <?php
                echo $currentPage === "messages.php"
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fa-solid fa-envelope"></i>

            Messages

        </a>

    </div>


    <div class="admin-nav-section">

        <p class="admin-nav-label">
            Finance
        </p>


        <a
            href="payments.php"
            class="admin-nav-link
            <?php
                echo $currentPage === "payments.php"
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fa-solid fa-credit-card"></i>

            Payments

        </a>


        <a
            href="reports.php"
            class="admin-nav-link
            <?php
                echo $currentPage === "reports.php"
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fa-solid fa-chart-pie"></i>

            Reports

        </a>

    </div>


    <div class="admin-sidebar-bottom">

        <a
            href="index.html"
            class="admin-nav-link"
            target="_blank"
        >

            <i class="fa-solid fa-arrow-up-right-from-square"></i>

            View Website

        </a>


        <a
            href="admin_logout.php"
            class="admin-nav-link"
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            Logout

        </a>

    </div>

</aside>