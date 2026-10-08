import { Controller } from '@hotwired/stimulus';

/**
 * A form saved as soon as one of its controls changes (a switch): posts it in
 * the background and says the outcome in its status target, a polite live
 * region. A failed save puts the control back. Without JavaScript the form's
 * own submit button posts it.
 */
export default class extends Controller {
    static targets = ['status'];
    static values = { savedText: String, errorText: String };

    save(event) {
        const control = event.target;

        fetch(this.element.action, {
            method: 'POST',
            body: new FormData(this.element),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                this.say(this.savedTextValue, false);
            })
            .catch(() => {
                if (control.type === 'checkbox') {
                    control.checked = !control.checked;
                }
                this.say(this.errorTextValue, true);
            });
    }

    say(text, failed) {
        this.statusTarget.textContent = text;
        this.statusTarget.toggleAttribute('data-failed', failed);
    }
}
