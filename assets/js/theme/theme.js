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

    // Heading elements toggle functionality
    $('.panel-footer')
        .has('> .heading-elements:not(.not-collapsible)')
        .prepend(
            '<a class="heading-elements-toggle"><i class="icon-more"></i></a>'
        );
    $('.page-title, .panel-title')
        .parent()
        .has('> .heading-elements:not(.not-collapsible)')
        .children('.page-title, .panel-title')
        .append(
            '<a class="heading-elements-toggle"><i class="icon-more"></i></a>'
        );

    $(
        '.page-title .heading-elements-toggle, .panel-title .heading-elements-toggle'
    ).on('click', function () {
        $(this)
            .parent()
            .parent()
            .toggleClass('has-visible-elements')
            .children('.heading-elements')
            .toggleClass('visible-elements');
    });

    $('.panel-footer .heading-elements-toggle').on('click', function () {
        $(this)
            .parent()
            .toggleClass('has-visible-elements')
            .children('.heading-elements')
            .toggleClass('visible-elements');
    });

    // Breadcrumb elements toggle
    $('.breadcrumb-line')
        .has('.breadcrumb-elements')
        .prepend(
            '<a class="breadcrumb-elements-toggle"><i class="icon-menu-open"></i></a>'
        );
    $('.breadcrumb-elements-toggle').on('click', function () {
        $(this)
            .parent()
            .children('.breadcrumb-elements')
            .toggleClass('visible-elements');
    });

    // Dropdown content click prevention
    $(document).on('click', '.dropdown-content', function (e) {
        e.stopPropagation();
    });

    // Disabled navbar links
    $('.navbar-nav .disabled a').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
    });

    // Dropdown tabs - removed (tab component not used)

    // Panel reload functionality
    $('.panel [data-action=reload]').click(function (e) {
        e.preventDefault();
        var panel = $(this).parent().parent().parent().parent().parent();
        $(panel).block({
            message: '<i class="icon-spinner2 spinner"></i>',
            overlayCSS: {
                backgroundColor: '#fff',
                opacity: 0.8,
                cursor: 'wait',
                'box-shadow': '0 0 0 1px #ddd',
            },
            css: {
                border: 0,
                padding: 0,
                backgroundColor: 'none',
            },
        });
        window.setTimeout(function () {
            $(panel).unblock();
        }, 2000);
    });

    // Category reload functionality
    $('.category-title [data-action=reload]').click(function (e) {
        e.preventDefault();
        var category = $(this).parent().parent().parent().parent();
        $(category).block({
            message: '<i class="icon-spinner2 spinner"></i>',
            overlayCSS: {
                backgroundColor: '#000',
                opacity: 0.5,
                cursor: 'wait',
                'box-shadow': '0 0 0 1px #000',
            },
            css: {
                border: 0,
                padding: 0,
                backgroundColor: 'none',
                color: '#fff',
            },
        });
        window.setTimeout(function () {
            $(category).unblock();
        }, 2000);
    });

    // Sidebar default category reload
    $('.sidebar-default .category-title [data-action=reload]').click(
        function (e) {
            e.preventDefault();
            var category = $(this).parent().parent().parent().parent();
            $(category).block({
                message: '<i class="icon-spinner2 spinner"></i>',
                overlayCSS: {
                    backgroundColor: '#fff',
                    opacity: 0.8,
                    cursor: 'wait',
                    'box-shadow': '0 0 0 1px #ddd',
                },
                css: {
                    border: 0,
                    padding: 0,
                    backgroundColor: 'none',
                },
            });
            window.setTimeout(function () {
                $(category).unblock();
            }, 2000);
        }
    );

    // Initialize collapsed categories
    $('.category-collapsed').children('.category-content').hide();
    $('.category-collapsed')
        .find('[data-action=collapse]')
        .addClass('rotate-180');

    // Category collapse functionality
    $('.category-title [data-action=collapse]').click(function (e) {
        e.preventDefault();
        var content = $(this).parent().parent().parent().nextAll();
        $(this).parents('.category-title').toggleClass('category-collapsed');
        $(this).toggleClass('rotate-180');
        setMinHeight();
        content.slideToggle(150);
    });

    // Initialize collapsed panels
    $('.panel-collapsed').children('.panel-heading').nextAll().hide();
    $('.panel-collapsed').find('[data-action=collapse]').addClass('rotate-180');

    // Panel collapse functionality
    $('.panel [data-action=collapse]').click(function (e) {
        e.preventDefault();
        var content = $(this).parent().parent().parent().parent().nextAll();
        $(this).parents('.panel').toggleClass('panel-collapsed');
        $(this).toggleClass('rotate-180');
        setMinHeight();
        content.slideToggle(150);
    });

    // Panel close functionality
    $('.panel [data-action=close]').click(function (e) {
        e.preventDefault();
        var panel = $(this).parent().parent().parent().parent().parent();
        setMinHeight();
        panel.slideUp(150, function () {
            $(this).remove();
        });
    });

    // Category close functionality
    $('.category-title [data-action=close]').click(function (e) {
        e.preventDefault();
        var category = $(this).parent().parent().parent().parent();
        setMinHeight();
        category.slideUp(150, function () {
            $(this).remove();
        });
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

    // Navigation tooltips - removed (tooltip component not used)

    // Main navigation functionality
    $('.navigation-main')
        .find('li')
        .has('ul')
        .children('a')
        .on('click', function (e) {
            e.preventDefault();
            $(this)
                .parent('li')
                .not('.disabled')
                .not(
                    $('.sidebar-xs')
                        .not('.sidebar-xs-indicator')
                        .find('.navigation-main')
                        .children('li')
                )
                .toggleClass('active')
                .children('ul')
                .slideToggle(250);
            if ($('.navigation-main').hasClass('navigation-accordion')) {
                $(this)
                    .parent('li')
                    .not('.disabled')
                    .not(
                        $('.sidebar-xs')
                            .not('.sidebar-xs-indicator')
                            .find('.navigation-main')
                            .children('li')
                    )
                    .siblings(':has(.has-ul)')
                    .removeClass('active')
                    .children('ul')
                    .slideUp(250);
            }
        });

    // Alternative navigation functionality
    $('.navigation-alt')
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
                .slideToggle(200);
            if ($('.navigation-alt').hasClass('navigation-accordion')) {
                $(this)
                    .parent('li')
                    .not('.disabled')
                    .siblings(':has(.has-ul)')
                    .removeClass('active')
                    .children('ul')
                    .slideUp(200);
            }
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

    // Sidebar hide/show functionality
    $(document).on('click', '.sidebar-main-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-main-hidden');
    });

    $(document).on('click', '.sidebar-secondary-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-secondary-hidden');
    });

    $(document).on('click', '.sidebar-detached-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-detached-hidden');
    });

    $(document).on('click', '.sidebar-all-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-all-hidden');
    });

    // Sidebar opposite functionality
    $(document).on('click', '.sidebar-opposite-toggle', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-opposite-visible');
        if ($('body').hasClass('sidebar-opposite-visible')) {
            $('body').addClass('sidebar-xs');
            $('.navigation-main')
                .children('li')
                .children('ul')
                .css('display', '');
        } else {
            $('body').removeClass('sidebar-xs');
        }
    });

    $(document).on('click', '.sidebar-opposite-main-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-opposite-visible');
        if ($('body').hasClass('sidebar-opposite-visible')) {
            $('body').addClass('sidebar-main-hidden');
        } else {
            $('body').removeClass('sidebar-main-hidden');
        }
    });

    $(document).on('click', '.sidebar-opposite-secondary-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-opposite-visible');
        if ($('body').hasClass('sidebar-opposite-visible')) {
            $('body').addClass('sidebar-secondary-hidden');
        } else {
            $('body').removeClass('sidebar-secondary-hidden');
        }
    });

    $(document).on('click', '.sidebar-opposite-hide', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-all-hidden');
        if ($('body').hasClass('sidebar-all-hidden')) {
            $('body').addClass('sidebar-opposite-visible');
            $('.navigation-main')
                .children('li')
                .children('ul')
                .css('display', '');
        } else {
            $('body').removeClass('sidebar-opposite-visible');
        }
    });

    $(document).on('click', '.sidebar-opposite-fix', function (e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-opposite-visible');
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

    $('.sidebar-mobile-opposite-toggle').on('click', function (e) {
        e.preventDefault();
        $('body')
            .toggleClass('sidebar-mobile-opposite')
            .removeClass(
                'sidebar-mobile-main sidebar-mobile-secondary sidebar-mobile-detached'
            );
    });

    $('.sidebar-mobile-detached-toggle').on('click', function (e) {
        e.preventDefault();
        $('body')
            .toggleClass('sidebar-mobile-detached')
            .removeClass(
                'sidebar-mobile-main sidebar-mobile-secondary sidebar-mobile-opposite'
            );
    });

    // Window resize handling
    $(window)
        .on('resize', function () {
            setTimeout(function () {
                setMinHeight();
                if ($(window).width() <= 768) {
                    $('body').addClass('sidebar-xs-indicator');
                    $('.sidebar-opposite').insertBefore('.content-wrapper');
                    $('.sidebar-detached').insertBefore('.content-wrapper');
                    $('.dropdown-submenu')
                        .on('mouseenter', function () {
                            $(this).children('.dropdown-menu').addClass('show');
                        })
                        .on('mouseleave', function () {
                            $(this)
                                .children('.dropdown-menu')
                                .removeClass('show');
                        });
                } else {
                    $('body').removeClass('sidebar-xs-indicator');
                    $('.sidebar-opposite').insertAfter('.content-wrapper');
                    $('body').removeClass(
                        'sidebar-mobile-main sidebar-mobile-secondary sidebar-mobile-detached sidebar-mobile-opposite'
                    );
                    if ($('body').hasClass('has-detached-left')) {
                        $('.sidebar-detached').insertBefore(
                            '.container-detached'
                        );
                    } else if ($('body').hasClass('has-detached-right')) {
                        $('.sidebar-detached').insertAfter(
                            '.container-detached'
                        );
                    }
                    $(
                        '.page-header-content, .panel-heading, .panel-footer'
                    ).removeClass('has-visible-elements');
                    $('.heading-elements').removeClass('visible-elements');
                    $('.dropdown-submenu')
                        .children('.dropdown-menu')
                        .removeClass('show');
                }
            }, 100);
        })
        .resize();

    // Popovers and tooltips removed - components not used
});
