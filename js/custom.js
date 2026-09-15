/**
 * X-Sieben Theme JS
 * Unified & Consolidated: Tooltips, Hamburger Fix, Search Toggle & Accessibility
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("🚀 X-Sieben JS erfolgreich geladen");

    // ==========================================================
    //  CSS INJECTION (Touch Targets & Visibility)
    // ==========================================================
    const styleSheet = document.createElement("style");
    styleSheet.innerText = `
        /* --- EXTRA MENU BASE (Desktop & Tablet) --- */
        #x-sieben-desktop-extra-menu {
            position: fixed !important;
            top: 0 !important;
            right: -100% !important;
            width: 320px !important;
            max-width: 85% !important;
            height: 100vh !important;
            background: #ffffff !important;
            z-index: 999999 !important;
            transition: right 0.3s ease-in-out !important;
            display: block !important;
            visibility: hidden;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        }

        /* Sichtbarkeit bei aktiver Klasse */
        .x-sieben-menu-open {
            right: 0 !important;
            visibility: visible !important;
        }

        /* --- MOBILE HIDE (Extra Menu & Hamburger) --- */
        @media (max-width: 767px) {
            #x-sieben-desktop-extra-menu, 
            .x-sieben-hamburger-btn {
                display: none !important;
            }
        }

        /* --- TOUCH TARGETS (48x48px Regel für Lighthouse) --- */
        .x-sieben-hamburger-btn,
        .x7-search-btn,
        #x-sieben-desktop-extra-menu a {
            min-height: 48px !important;
            display: flex !important;
            align-items: center !important;
            box-sizing: border-box !important;
        }

        /* Hamburger Icon Styling */
        .x-sieben-hamburger-btn i {
            font-size: 24px;
            display: block;
        }

        /* --- SEARCH FORM --- */
        #x7-search-form.active {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* BRAND COLORS HOVER (Footer Socials) */
        .x-sieben-color-facebook:hover  { color: #3b5998 !important; }
        .x-sieben-color-instagram:hover { color: #e1306c !important; }
        .x-sieben-color-linkedin:hover  { color: #0077b5 !important; }
        .x-sieben-color-youtube:hover   { color: #ff0000 !important; }
    `;
    document.head.appendChild(styleSheet);

    // ==========================================================
    //  TOOLTIP FUNCTIONALITY
    // ==========================================================
    document.querySelectorAll(".custom-tooltip").forEach(link => {
        let tooltipBox;
        link.addEventListener("mouseenter", () => {
            if (!tooltipBox) {
                tooltipBox = document.createElement("div");
                tooltipBox.className = "tooltip-box";
                try {
                    const tooltipHtml = JSON.parse(link.getAttribute("data-tooltip"));
                    tooltipBox.innerHTML = tooltipHtml;
                    document.body.appendChild(tooltipBox);
                } catch(e) { console.error("Tooltip JSON Error"); }
            }
            const rect = link.getBoundingClientRect();
            tooltipBox.style.top = window.scrollY + rect.bottom + 8 + "px";
            tooltipBox.style.left = window.scrollX + rect.left + rect.width / 2 + "px";
            tooltipBox.style.display = "block";
        });
        link.addEventListener("mouseleave", () => {
            if (tooltipBox) tooltipBox.style.display = "none";
        });
    });

    // ==========================================================
    //  HEADER SCROLL BEHAVIOR
    // ==========================================================
    const header = document.getElementById("x7-top");
    if (header) {
        let lastScroll = window.scrollY;
        window.addEventListener("scroll", () => {
            const current = window.scrollY;
            if (current > 200) {
                header.classList.toggle("x7-hidden", current > lastScroll);
            } else {
                header.classList.remove("x7-hidden");
            }
            lastScroll = current;
        }, { passive: true });
    }
});

// ==========================================================
//  JQUERY FIXES (HAMBURGER, SEARCH & ACCESSIBILITY)
// ==========================================================
jQuery(document).ready(function($) {
    console.log("✅ X-Sieben jQuery Initialisierung");

    var $menu = $('#x-sieben-desktop-extra-menu');
    var $search = $('#x7-search-form');
    var $hamburger = $('.x-sieben-hamburger-btn');
    var $searchBtn = $('.x7-search-btn');

    function closeAll() {
        $menu.removeClass('x-sieben-menu-open').css({'right': '-100%', 'visibility': 'hidden'});
        $search.removeClass('active');
        $hamburger.attr('aria-expanded', 'false');
        $searchBtn.attr('aria-expanded', 'false');
    }

    // HAMBURGER CLICK (Delegated Event)
    $(document).on('click', '.x-sieben-hamburger-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var isOpen = $menu.hasClass('x-sieben-menu-open');
        closeAll();
        if (!isOpen) {
            $menu.addClass('x-sieben-menu-open').css({'right': '0', 'visibility': 'visible'});
            $(this).attr('aria-expanded', 'true');
        }
    });

    // SEARCH TOGGLE
    $(document).on('click', '.x7-search-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var isSearchOpen = $search.hasClass('active');
        closeAll();
        if (!isSearchOpen) {
            $search.addClass('active');
            $(this).attr('aria-expanded', 'true');
            setTimeout(() => $search.find('input').focus(), 150);
        }
    });

    // Schließen bei Klick außerhalb oder ESC
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#x-sieben-desktop-extra-menu, #x7-search-form, .x-sieben-hamburger-btn, .x7-search-btn').length) {
            closeAll();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === "Escape") closeAll();
    });

    // ARIA Cleanup & Accordion Sync
    $('a[role="menuitem"]').each(function() {
        if ($(this).parents('[role="menu"], [role="menubar"]').length === 0) $(this).removeAttr('role');
    });

    const $accordion = $('#courses-accordion');
    if ($accordion.length) {
        const updateAccordion = () => {
            $accordion.find('[role="tab"]').each(function() {
                const target = $($(this).attr('href') || $(this).attr('data-target'));
                const isOpen = target.hasClass('in') || target.hasClass('show');
                $(this).attr({'aria-expanded': isOpen, 'aria-selected': isOpen});
            });
        };
        updateAccordion();
        $accordion.on('shown.bs.collapse hidden.bs.collapse', updateAccordion);
    }
});