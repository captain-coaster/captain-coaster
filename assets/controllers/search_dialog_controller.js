import { Controller } from '@hotwired/stimulus';

/**
 * Full-screen mobile search (Nav:SearchDialog). Attached to <body> so the
 * Search tab and the dialog can live in different components.
 */
export default class extends Controller {
    static targets = ['dialog'];

    open() {
        window.dispatchEvent(new CustomEvent('recent-searches:refresh'));
        this.dialogTarget.showModal();
        this.dialogTarget.querySelector('input[type="search"]')?.focus();
    }

    closed() {
        const input = this.dialogTarget.querySelector('input[type="search"]');
        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}
