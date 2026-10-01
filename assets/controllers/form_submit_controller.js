import { Controller } from '@hotwired/stimulus';

// Disables the submit button while the form posts, so a slow upload or
// sign-in can't be sent twice. Required fields are native (`required`) and
// validated server-side.
export default class extends Controller {
    static values = { loadingText: String };

    submit() {
        const submitButton = this.element.querySelector('button[type=submit]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = this.loadingTextValue;
        }
    }
}
