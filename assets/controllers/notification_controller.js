import { Controller } from '@hotwired/stimulus';

/**
 * Notification controller for displaying toast-style notifications
 */
export default class extends Controller {
    static targets = ['container'];

    connect() {}

    /**
     * Show a notification message
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, info, warning, danger)
     * @param {number} timeout - Time in milliseconds before the notification disappears
     */
    show(message, type = 'info', timeout = 3000) {
        // Create the notification element with fixed positioning
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-styled-left alert-arrow-left alert-bordered`;

        // Add fixed positioning styles to make it always visible
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.maxWidth = '400px';
        notification.style.zIndex = '9999'; // Ensure it's above modals
        notification.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';

        notification.innerHTML = `
            <button type="button" class="close" data-dismiss="alert"><span>×</span><span class="sr-only">Close</span></button>
            ${message}
        `;

        // Always append to body to ensure it's visible above modals
        document.body.appendChild(notification);

        // Set up the close button
        const closeButton = notification.querySelector('.close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                notification.remove();
            });
        }

        // Add a fade-in effect
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s ease-in-out';
        setTimeout(() => {
            notification.style.opacity = '1';
        }, 10);

        // Auto-remove after timeout with fade-out effect
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, timeout);
    }

    /**
     * Show a success notification
     * @param {string} message - The message to display
     */
    showSuccess(message) {
        this.show(message, 'success');
    }

    /**
     * Show an info notification
     * @param {string} message - The message to display
     */
    showInfo(message) {
        this.show(message, 'info');
    }

    /**
     * Show a warning notification
     * @param {string} message - The message to display
     */
    showWarning(message) {
        this.show(message, 'warning');
    }

    /**
     * Show a danger notification
     * @param {string} message - The message to display
     */
    showDanger(message) {
        this.show(message, 'danger');
    }
}
