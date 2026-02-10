import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { loadingText: String, requiredError: String };
    static targets = ['requiredCheckbox'];

    submit(event) {
        if (
            this.hasRequiredCheckboxTarget &&
            !this.requiredCheckboxTarget.checked
        ) {
            event.preventDefault();
            const group = this.requiredCheckboxTarget.closest('.form-group');
            if (group) {
                group.classList.add('has-error');
                if (!group.querySelector('.help-block-error')) {
                    const error = document.createElement('span');
                    error.className = 'help-block help-block-error text-danger';
                    error.textContent = this.requiredErrorValue;
                    group.appendChild(error);
                }
            }
            return;
        }

        const btn = this.element.querySelector('button[type=submit]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = this.loadingTextValue;
        }
    }
}
