import { Controller } from '@hotwired/stimulus';

/**
 * Controls the mobile navbar panel's local state. The panel state is an
 * attribute rather than a framework class so Tailwind utility names cannot
 * affect navigation visibility.
 */
export default class extends Controller {
    static targets = ['panel'];

    toggle() {
        const isOpen = !this.panelTarget.hasAttribute('data-open');
        this.panelTarget.toggleAttribute('data-open', isOpen);
        this._syncTriggers(isOpen);
    }

    hide() {
        this.panelTarget.removeAttribute('data-open');
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
