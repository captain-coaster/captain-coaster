/**
 * Sticky sidebar with custom scrollbar functionality
 * Migrated from public/js/core/layout_fixed_custom.js
 * Handles mini sidebar behavior and responsive interactions
 */

$(function () {
    // Mini sidebar functionality
    function miniSidebar() {
        if ($('body').hasClass('sidebar-xs')) {
            $('.sidebar-main.sidebar-fixed .sidebar-content')
                .on('mouseenter', function () {
                    if ($('body').hasClass('sidebar-xs')) {
                        // Expand fixed navbar
                        $('body')
                            .removeClass('sidebar-xs')
                            .addClass('sidebar-fixed-expanded');
                    }
                })
                .on('mouseleave', function () {
                    if ($('body').hasClass('sidebar-fixed-expanded')) {
                        // Collapse fixed navbar
                        $('body')
                            .removeClass('sidebar-fixed-expanded')
                            .addClass('sidebar-xs');
                    }
                });
        }
    }

    // Initialize mini sidebar
    miniSidebar();

    // Toggle mini sidebar
    $('.sidebar-main-toggle').on('click', function (e) {
        // Initialize mini sidebar
        miniSidebar();
    });

    // Note: Custom scrollbar functionality (niceScroll) has been commented out
    // as it's not commonly used in modern applications and can be replaced
    // with CSS-based scrollbars if needed
});
