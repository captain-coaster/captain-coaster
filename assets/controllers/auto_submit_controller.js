import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        loadingText: String,
        requiredError: String,
        debounce: { type: Number, default: 0 },
    };
    static targets = ['requiredCheckbox'];

    connect() {
        this._debounceTimer = null;
    }

    disconnect() {
        if (this._debounceTimer) clearTimeout(this._debounceTimer);
    }

    /** Debounced auto-submit — use with data-action="input->auto-submit#debounce" */
    debounce() {
        if (this._debounceTimer) clearTimeout(this._debounceTimer);
        const delay = this.debounceValue || 400;
        this._debounceTimer = setTimeout(() => {
            this.element.submit();
        }, delay);
    }

    submit(event) {
        if (
            this.hasRequiredCheckboxTarget &&
            !this.requiredCheckboxTarget.checked
        ) {
            event.preventDefault();
            if (!this.element.querySelector('[data-required-error]')) {
                const error = document.createElement('p');
                error.setAttribute('data-required-error', '');
                error.className = 'mt-1 text-sm text-red-600 dark:text-red-400';
                error.textContent = this.requiredErrorValue;
                this.requiredCheckboxTarget.insertAdjacentElement('afterend', error);
            }
            return;
        }

        this.element.querySelector('[data-required-error]')?.remove();

        this.element.setAttribute('aria-busy', 'true');

        const btn = this.element.querySelector('button[type=submit]');
        if (btn) {
            btn.disabled = true;
            if (this.loadingTextValue) btn.textContent = this.loadingTextValue;
        }
    }
}
