import { Controller } from '@hotwired/stimulus';

/**
 * Image Upload Controller
 * Handles form submission state for image uploads
 */
export default class extends Controller {
    static targets = ['submitButton'];

    connect() {
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
    }

    handleSubmit(event) {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
            this.submitButtonTarget.textContent =
                this.element.dataset.uploadingText || 'Uploading...';
        }
    }
}
