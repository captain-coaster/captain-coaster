/**
 * Main application theme JavaScript
 * Migrated from public/js/core/app.min.js
 * Handles UI interactions, sidebar management, and theme functionality
 */

// Remove transitions on page load
$(window).on('load', function () {
    $('body').removeClass('no-transitions');
});

$(function () {
    // Calculate and set minimum page height
    function setMinHeight() {
        var minHeight =
            $(window).height() -
            $('.page-container').offset().top -
            $('.navbar-fixed-bottom').outerHeight();
        $('.page-container').attr('style', 'min-height:' + minHeight + 'px');
    }

    // Initialize page
    $('body').addClass('no-transitions');
    setMinHeight();

    // Disabled navbar links
    $('.navbar-nav .disabled a').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
    });

    // Navigation setup
    $('.navigation').find('li.active').parents('li').addClass('active');
    $('.navigation')
        .find('li')
        .not('.active, .category-title')
        .has('ul')
        .children('ul')
        .addClass('hidden-ul');
    $('.navigation').find('li').has('ul').children('a').addClass('has-ul');
    $(
        '.dropdown-menu:not(.dropdown-content), .dropdown-menu:not(.dropdown-content) .dropdown-submenu'
    )
        .has('li.active')
        .addClass('active')
        .parents(
            '.navbar-nav .dropdown:not(.language-switch), .navbar-nav .dropup:not(.language-switch)'
        )
        .addClass('active');

    // Main navigation functionality (simplified - no nested menus in current design)
    $('.navigation-main')
        .find('li')
        .has('ul')
        .children('a')
        .on('click', function (e) {
            e.preventDefault();
            $(this)
                .parent('li')
                .not('.disabled')
                .toggleClass('active')
                .children('ul')
                .slideToggle(250);
        });

    // Sidebar toggle functionality
    $('.sidebar-main-toggle').on('click', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-xs');
    });

    // Navigation disabled links
    $(document).on('click', '.navigation .disabled a', function (e) {
        e.preventDefault();
    });

    // Sidebar control
    $(document).on('click', '.sidebar-control', function (e) {
        setMinHeight();
    });

    // Mobile sidebar toggles
    $('.sidebar-mobile-main-toggle').on('click', function (e) {
        e.preventDefault();
        $('body')
            .toggleClass('sidebar-mobile-main')
            .removeClass(
                'sidebar-mobile-secondary sidebar-mobile-opposite sidebar-mobile-detached'
            );
    });

    $('.sidebar-mobile-secondary-toggle').on('click', function (e) {
        e.preventDefault();
        $('body')
            .toggleClass('sidebar-mobile-secondary')
            .removeClass(
                'sidebar-mobile-main sidebar-mobile-opposite sidebar-mobile-detached'
            );
    });

    // Window resize handling
    $(window)
        .on('resize', function () {
            setTimeout(function () {
                setMinHeight();
                if ($(window).width() <= 768) {
                    $('body').addClass('sidebar-xs-indicator');
                } else {
                    $('body').removeClass('sidebar-xs-indicator');
                    $('body').removeClass(
                        'sidebar-mobile-main sidebar-mobile-secondary'
                    );
                }
            }, 100);
        })
        .resize();
});
