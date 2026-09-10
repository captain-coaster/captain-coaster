import { Controller } from '@hotwired/stimulus';

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
        successMessage: { type: String, default: 'Copied!' },
    };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.contentValue);
            this.showNotification(this.successMessageValue, 'success');
        } catch {
            this.showNotification('Copy failed', 'danger');
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
