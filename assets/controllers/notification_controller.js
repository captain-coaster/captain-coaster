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
        // Icon mapping for different notification types
        const icons = {
            success: '✓',
            info: 'ℹ',
            warning: '⚠',
            danger: '✕',
        };

        const icon = icons[type] || icons.info;

        // Create notification with CSS classes
        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;

        // Clean HTML structure using CSS classes
        notification.innerHTML = `
            <div class="notification__content">
                <div class="notification__icon">${icon}</div>
                <div class="notification__message">${message}</div>
                <button type="button" class="notification__close">×</button>
            </div>
        `;

        // Append to body
        document.body.appendChild(notification);

        // Set up close button
        const closeButton = notification.querySelector('.notification__close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                this.hideNotification(notification);
            });
        }

        // Animate in using CSS classes
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });

        // Auto-remove after timeout
        setTimeout(() => {
            this.hideNotification(notification);
        }, timeout);
    }

    hideNotification(notification) {
        notification.classList.remove('show');
        notification.classList.add('hide');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
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
