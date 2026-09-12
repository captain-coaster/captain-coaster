import { Controller } from '@hotwired/stimulus';

/**
 * Replaces layout_fixed_custom.js (deleted) and theme.js's sidebar-only
 * jQuery bindings. Attached to <body> since every effect here is a
 * body/page-wide class toggle, not one element's own state.
 *
 * `setMinHeight()` from the old theme.js is intentionally not ported:
 * it read `$('.navbar-fixed-bottom').outerHeight()`, which is always
 * `undefined` (that class matches nothing in this app), making the
 * computed value `NaN` and the resulting `style="min-height:NaNpx"`
 * invalid CSS the browser silently drops -- confirmed live via
 * getComputedStyle() that `.page-container`'s real min-height always
 * came from the plain `100vh` CSS rule, never this inline style. It
 * was already dead code before this migration.
 */
export default class extends Controller {
    connect() {
        this._onResize = this._onResize.bind(this);
        window.addEventListener('resize', this._onResize);
    }

    disconnect() {
        window.removeEventListener('resize', this._onResize);
    }

    toggleMain() {
        document.body.classList.toggle('sidebar-xs');
    }

    expand() {
        if (document.body.classList.contains('sidebar-xs')) {
            document.body.classList.remove('sidebar-xs');
            document.body.classList.add('sidebar-fixed-expanded');
        }
    }

    collapse() {
        if (document.body.classList.contains('sidebar-fixed-expanded')) {
            document.body.classList.remove('sidebar-fixed-expanded');
            document.body.classList.add('sidebar-xs');
        }
    }

    toggleMobileMain() {
        document.getElementById('navbar-mobile')?.removeAttribute('data-open');
        document.body.classList.toggle('sidebar-mobile-main');
        document.body.classList.remove('sidebar-mobile-secondary');
    }

    toggleMobileSecondary() {
        document.getElementById('navbar-mobile')?.removeAttribute('data-open');
        document.body.classList.toggle('sidebar-mobile-secondary');
        document.body.classList.remove('sidebar-mobile-main');
    }

    closeMobile() {
        document.body.classList.remove(
            'sidebar-mobile-main',
            'sidebar-mobile-secondary'
        );
    }

    _onResize() {
        // Keep in sync with --breakpoint-tablet in assets/styles/tokens.css.
        if (window.innerWidth >= 769) {
            document.body.classList.remove(
                'sidebar-mobile-main',
                'sidebar-mobile-secondary'
            );
        }
    }
}
