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
            this.fallbackCopy();
        }
    }

    /**
     * Fallback for older browsers or insecure contexts (HTTP).
     */
    fallbackCopy() {
        const textarea = document.createElement('textarea');
        textarea.value = this.contentValue;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            this.showNotification(this.successMessageValue, 'success');
        } catch {
            this.showNotification('Copy failed', 'danger');
        } finally {
            textarea.remove();
        }
    }

    showNotification(message, type) {
        const notificationElement = document.getElementById('notifications');
        if (!notificationElement) {
            return;
        }

        const controller =
            this.application.getControllerForElementAndIdentifier(
                notificationElement,
                'notification'
            );

        if (controller) {
            controller.show(message, type);
        }
    }
}
