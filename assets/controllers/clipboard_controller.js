import { Controller } from '@hotwired/stimulus';
import { trans } from '../translator';

/**
 * Clipboard controller for copying text to clipboard using the modern Clipboard API.
 *
 * Usage:
 *   <button data-controller="clipboard"
 *           data-clipboard-content-value="text to copy"
 *           data-action="clipboard#copy">
 *     Copy
 *   </button>
 */
export default class extends Controller {
    static values = {
        content: String,
        successMessage: String,
        errorMessage: String,
    };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.contentValue);
            this.showNotification(this.successMessageValue || trans('clipboard.copied'), 'success');
        } catch {
            this.showNotification(this.errorMessageValue || trans('clipboard.copy_failed'), 'danger');
        }
    }

    showNotification(message, type) {
        const notificationElement = document.getElementById('toasts');
        if (!notificationElement) {
            return;
        }

        const controller =
            this.application.getControllerForElementAndIdentifier(
                notificationElement,
                'toast'
            );

        if (controller) {
            controller.show(message, type);
        }
    }
}
