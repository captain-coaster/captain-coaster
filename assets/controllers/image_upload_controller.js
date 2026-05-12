import BaseController from './base_controller.js';

/**
 * Image Upload Controller
 * Handles form submission state for image uploads
 */
export default class extends BaseController {
    static targets = ['submitButton'];

    connect() {
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
    }

    handleSubmit(event) {
        if (this.hasSubmitButtonTarget) {
            // Use base controller's loading state
            this.showLoading(this.submitButtonTarget);
            this.submitButtonTarget.textContent =
                this.element.dataset.uploadingText || 'Uploading...';
        }
    }
}
