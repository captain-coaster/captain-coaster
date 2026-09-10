import { Controller } from '@hotwired/stimulus';

/**
 * Replaces bootstrap/js/collapse for the mobile navbar toggle
 * (`#navbar-mobile`). Bootstrap's real collapse.js animates height via a
 * `.collapsing` intermediate step, but Limitless's own navbar.less already
 * sets `.navbar-collapse.collapsing { transition-duration: 0.00000001s }`
 * (the animation is disabled on purpose -- "buggy on mobile" per its own
 * comment), so there is nothing to animate: toggling `.in` directly, the
 * class `.collapse`/`.collapse.in` (from bootstrap's component-animations.less,
 * untouched) already key off of, produces the same instant result.
 *
 * Named distinctly from the generic `collapse` controller (used elsewhere
 * for collapsible help sections, content/icon targets) -- this one is
 * navbar-#navbar-mobile-specific, not a drop-in replacement for it.
 */
export default class extends Controller {
    static targets = ['panel'];

    toggle() {
        const isOpen = this.panelTarget.classList.toggle('in');
        this._syncTriggers(isOpen);
    }

    hide() {
        this.panelTarget.classList.remove('in');
        this._syncTriggers(false);
    }

    _syncTriggers(isOpen) {
        this.element
            .querySelectorAll('[data-action*="navbar-collapse#toggle"]')
            .forEach((trigger) => {
                trigger.setAttribute('aria-expanded', String(isOpen));
            });
    }
}
