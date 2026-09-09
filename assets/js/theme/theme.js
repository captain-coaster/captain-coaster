/**
 * Main application theme JavaScript
 * Migrated from public/js/core/app.min.js
 * Sidebar management moved to sidebar_controller.js (Phase 2 Sidebar
 * cluster, captain-coaster/captain-coaster#380) -- what's left here is
 * navbar-dropdown active-state propagation and disabled-link handling.
 */

// Remove transitions on page load
$(window).on('load', function () {
    $('body').removeClass('no-transitions');
});

$(function () {
    // Initialize page
    $('body').addClass('no-transitions');

    // Disabled navbar links
    $('.navbar-nav .disabled a').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
    });

    $(
        '.dropdown-menu:not(.dropdown-content), .dropdown-menu:not(.dropdown-content) .dropdown-submenu'
    )
        .has('li.active')
        .addClass('active')
        .parents(
            '.navbar-nav .dropdown:not(.language-switch), .navbar-nav .dropup:not(.language-switch)'
        )
        .addClass('active');
});
