/* =========================================================
   SPRINTER TOURS & SAFARIS
   MAIN JAVASCRIPT
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       1. FAST LOADER
    ===================================================== */

    const loader = document.getElementById("loader");

    if (loader) {

        setTimeout(function () {

            loader.classList.add("loader-hidden");

            setTimeout(function () {
                loader.style.display = "none";
            }, 400);

        }, 500);

    }


    /* =====================================================
       2. DARK MODE
    ===================================================== */

    const darkToggle =
        document.getElementById("darkToggle");


    function updateDarkModeIcon() {

        if (!darkToggle) {
            return;
        }

        if (
            document.body.classList.contains(
                "dark-mode"
            )
        ) {

            darkToggle.textContent = "☀️";

        } else {

            darkToggle.textContent = "🌙";

        }

    }


    if (darkToggle) {

        const savedMode =
            localStorage.getItem("darkMode");

        if (savedMode === "on") {

            document.body.classList.add(
                "dark-mode"
            );

        }

        updateDarkModeIcon();


        darkToggle.addEventListener(
            "click",
            function () {

                document.body.classList.toggle(
                    "dark-mode"
                );


                const darkModeActive =
                    document.body.classList.contains(
                        "dark-mode"
                    );


                localStorage.setItem(
                    "darkMode",
                    darkModeActive
                        ? "on"
                        : "off"
                );


                updateDarkModeIcon();

            }
        );

    }


    /* =====================================================
       3. HERO SLIDER
    ===================================================== */

    const slides =
        document.querySelectorAll(".slide");


    if (slides.length > 1) {

        let slideIndex = 0;


        setInterval(function () {

            slides[slideIndex]
                .classList
                .remove("active");


            slideIndex++;


            if (
                slideIndex >= slides.length
            ) {

                slideIndex = 0;

            }


            slides[slideIndex]
                .classList
                .add("active");

        }, 5000);

    }


    /* =====================================================
       4. DESTINATION SEARCH
    ===================================================== */

    const searchInput =
        document.getElementById("searchInput");

    const destinationCards =
        document.querySelectorAll(".home-card");


    if (
        searchInput &&
        destinationCards.length > 0
    ) {

        searchInput.addEventListener(
            "input",
            function () {

                const searchTerm =
                    searchInput.value
                        .toLowerCase()
                        .trim();


                destinationCards.forEach(
                    function (card) {

                        const heading =
                            card.querySelector("h3");

                        const paragraph =
                            card.querySelector("p");


                        const destinationName =
                            heading
                                ? heading.textContent
                                    .toLowerCase()
                                : "";


                        const description =
                            paragraph
                                ? paragraph.textContent
                                    .toLowerCase()
                                : "";


                        const matches =
                            destinationName.includes(
                                searchTerm
                            ) ||
                            description.includes(
                                searchTerm
                            );


                        if (matches) {

                            card.style.display = "";

                        } else {

                            card.style.display = "none";

                        }

                    }
                );

            }
        );

    }


    /* =====================================================
       5. FAQ ACCORDION
    ===================================================== */

    const faqQuestions =
        document.querySelectorAll(
            ".faq-question"
        );


    faqQuestions.forEach(
        function (question) {

            question.addEventListener(
                "click",
                function () {

                    const answer =
                        question.nextElementSibling;


                    if (!answer) {
                        return;
                    }


                    const isOpen =
                        answer.classList.contains(
                            "faq-open"
                        );


                    /*
                     Close all FAQ answers first
                    */

                    document
                        .querySelectorAll(
                            ".faq-answer"
                        )
                        .forEach(
                            function (item) {

                                item.classList.remove(
                                    "faq-open"
                                );

                            }
                        );


                    if (!isOpen) {

                        answer.classList.add(
                            "faq-open"
                        );

                    }

                }
            );

        }
    );


    /* =====================================================
       6. BACK TO TOP BUTTON
    ===================================================== */

    const topBtn =
        document.getElementById("topBtn");


    if (topBtn) {

        function updateTopButton() {

            if (
                window.scrollY > 350
            ) {

                topBtn.classList.add(
                    "top-visible"
                );

            } else {

                topBtn.classList.remove(
                    "top-visible"
                );

            }

        }


        window.addEventListener(
            "scroll",
            updateTopButton
        );


        updateTopButton();


        topBtn.addEventListener(
            "click",
            function () {

                window.scrollTo({

                    top: 0,

                    behavior: "smooth"

                });

            }
        );

    }


    /* =====================================================
       7. NAVBAR SCROLL EFFECT
    ===================================================== */

    const navbar =
        document.getElementById("navbar");


    if (navbar) {

        function updateNavbar() {

            if (
                window.scrollY > 60
            ) {

                navbar.classList.add(
                    "navbar-scrolled"
                );

            } else {

                navbar.classList.remove(
                    "navbar-scrolled"
                );

            }

        }


        window.addEventListener(
            "scroll",
            updateNavbar
        );


        updateNavbar();

    }


    /* =====================================================
       8. SCROLL REVEAL
    ===================================================== */

    const revealElements =
        document.querySelectorAll(
            `
            .home-card,
            .home-package,
            .why-box,
            .testimonial,
            .contact-box,
            .faq-item,
            .service-box,
            .about-box
            `
        );


    if (
        "IntersectionObserver" in window
    ) {

        const observer =
            new IntersectionObserver(

                function (entries) {

                    entries.forEach(
                        function (entry) {

                            if (
                                entry.isIntersecting
                            ) {

                                entry.target
                                    .classList
                                    .add("show");


                                observer.unobserve(
                                    entry.target
                                );

                            }

                        }
                    );

                },

                {
                    threshold: 0.12
                }

            );


        revealElements.forEach(
            function (element) {

                element.classList.add(
                    "hidden"
                );

                observer.observe(
                    element
                );

            }
        );

    } else {

        revealElements.forEach(
            function (element) {

                element.classList.add(
                    "show"
                );

            }
        );

    }


    /* =====================================================
       9. CURSOR GLOW
       Only runs when the element exists
    ===================================================== */

    const cursorGlow =
        document.querySelector(
            ".cursor-glow"
        );


    if (cursorGlow) {

        document.addEventListener(
            "mousemove",
            function (event) {

                cursorGlow.style.left =
                    event.clientX + "px";

                cursorGlow.style.top =
                    event.clientY + "px";

            }
        );

    }

});