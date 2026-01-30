import { Controller } from '@hotwired/stimulus';

/**
 * Base Stimulus Controller
 * Provides shared functionality for all feature controllers
 */
export default class extends Controller {
    static outlets = ['csrf-protection'];

    /**
     * Get CSRF token from outlet or meta tag
     * @returns {string|null} The CSRF token or null if not found
     */
    getCsrfToken() {
        // Try to get token from CSRF protection outlet first
        if (this.hasCsrfProtectionOutlet) {
            return this.csrfProtectionOutlet.getToken();
        }

        // Fallback to meta tag
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : null;
    }

    /**
     * Add CSRF token to request body
     * @param {string|Object|URLSearchParams} body - The request body
     * @returns {string|Object} The body with CSRF token added
     */
    addCsrfToBody(body) {
        const token = this.getCsrfToken();
        if (!token) return body;

        // Handle string body (URL-encoded)
        if (typeof body === 'string') {
            return `${body}&_token=${token}`;
        }

        // Handle URLSearchParams
        if (body instanceof URLSearchParams) {
            body.append('_token', token);
            return body.toString();
        }

        // Handle plain object
        if (typeof body === 'object' && body !== null) {
            return { ...body, _token: token };
        }

        return body;
    }

    /**
     * Show error notification
     * @param {string} message - The error message to display
     */
    showError(message) {
        this.#showNotification(message, 'danger');
    }

    /**
     * Show success notification
     * @param {string} message - The success message to display
     */
    showSuccess(message) {
        this.#showNotification(message, 'success');
    }

    /**
     * Show info notification
     * @param {string} message - The info message to display
     */
    showInfo(message) {
        this.#showNotification(message, 'info');
    }

    /**
     * Show warning notification
     * @param {string} message - The warning message to display
     */
    showWarning(message) {
        this.#showNotification(message, 'warning');
    }

    /**
     * Show loading state on element
     * @param {HTMLElement} element - The element to show loading state on
     */
    showLoading(element) {
        element.classList.add('opacity-50', 'pointer-events-none');
        element.dataset.loading = 'true';
    }

    /**
     * Hide loading state on element
     * @param {HTMLElement} element - The element to hide loading state from
     */
    hideLoading(element) {
        element.classList.remove('opacity-50', 'pointer-events-none');
        delete element.dataset.loading;
    }

    /**
     * Check if element is in loading state
     * @param {HTMLElement} element - The element to check
     * @returns {boolean} True if element is in loading state
     */
    isLoading(element) {
        return element.dataset.loading === 'true';
    }

    /**
     * Show a notification using the notification controller
     * @private
     * @param {string} message - The message to display
     * @param {string} type - The notification type (success, info, warning, danger)
     */
    #showNotification(message, type = 'info') {
        // Try to find notification controller
        const notificationElement = document.getElementById('notifications');
        if (!notificationElement) {
            console.warn('Notification element not found');
            return;
        }

        const notificationController =
            this.application.getControllerForElementAndIdentifier(
                notificationElement,
                'notification'
            );

        if (notificationController) {
            switch (type) {
                case 'success':
                    notificationController.showSuccess(message);
                    break;
                case 'warning':
                    notificationController.showWarning(message);
                    break;
                case 'danger':
                    notificationController.showDanger(message);
                    break;
                default:
                    notificationController.showInfo(message);
            }
        } else {
            console.warn('Notification controller not found');
        }
    }
}
