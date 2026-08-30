<header class="admin-topbar">

    <div class="admin-topbar-left">

        <button
            type="button"
            class="admin-mobile-toggle"
            id="adminMobileToggle"
            aria-label="Open admin navigation"
        >

            <i class="fa-solid fa-bars"></i>

        </button>


        <h2 class="admin-topbar-title">
            Sprinter Tours & Safaris
        </h2>

    </div>


    <div class="admin-topbar-right">

        <div class="admin-user">

            <div class="admin-avatar">

                <?php
                    echo strtoupper(
                        substr(
                            $adminUsername,
                            0,
                            1
                        )
                    );
                ?>

            </div>


            <div class="admin-user-text">

                <strong>

                    <?php
                        echo htmlspecialchars(
                            $adminUsername,
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>

                </strong>

                <span>
                    Administrator
                </span>

            </div>

        </div>

    </div>

</header>