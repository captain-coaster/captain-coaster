import { Controller } from '@hotwired/stimulus';

const KEY = 'theme';

/**
 * Dark theme switch (DESIGN.md, Dark theme): sets `data-theme="dark"` on <html>, which swaps the color roles in
 * tokens.css, and remembers the choice on this device. The theme applies only where this controller is mounted:
 * a page without it stays light, until every page is ready for the dark roles.
 *
 * Usage:
 *   <button type="button" role="switch" aria-checked="false"
 *           data-controller="theme" data-action="theme#toggle">Dark theme</button>
 */
export default class extends Controller {
    connect() {
        this.apply(this.stored() === 'dark');
    }

    disconnect() {
        delete document.documentElement.dataset.theme;
    }

    toggle() {
        const dark = document.documentElement.dataset.theme !== 'dark';
        this.apply(dark);
        try {
            window.localStorage.setItem(KEY, dark ? 'dark' : 'light');
        } catch {
            // Private browsing or blocked storage: the switch still works for this page view.
        }
    }

    apply(dark) {
        if (dark) {
            document.documentElement.dataset.theme = 'dark';
        } else {
            delete document.documentElement.dataset.theme;
        }
        this.element.setAttribute('aria-checked', String(dark));
    }

    stored() {
        try {
            return window.localStorage.getItem(KEY);
        } catch {
            return null;
        }
    }
}
