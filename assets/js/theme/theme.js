/**
 * Main application theme JavaScript
 * Migrated from public/js/core/app.min.js
 * Sidebar management moved to sidebar_controller.js (Phase 2 Sidebar
 * cluster, captain-coaster/captain-coaster#380) -- what's left here is
 * navbar-dropdown active-state propagation and disabled-link handling.
 */

// Remove transitions on page load
window.addEventListener('load', () => {
    document.body.classList.remove('no-transitions');
});

function init() {
    document.body.classList.add('no-transitions');

    document.querySelectorAll('.cc-navbar__nav .disabled a').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    const ANCESTOR_SELECTOR = '.cc-navbar__nav .cc-menu:not(.language-switch)';

    document
        .querySelectorAll(
            '.cc-menu__list:not(.cc-menu__content), .cc-menu__list:not(.cc-menu__content) .cc-menu__submenu'
        )
        .forEach((menu) => {
            if (!menu.querySelector('li.active')) return;

            menu.classList.add('active');

            for (
                let node = menu.parentElement;
                node;
                node = node.parentElement
            ) {
                if (node.matches(ANCESTOR_SELECTOR)) {
                    node.classList.add('active');
                }
            }
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
