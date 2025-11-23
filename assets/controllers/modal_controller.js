import { Controller } from '@hotwired/stimulus';

/**
 * Modal controller for Bootstrap 3.x modal management
 * Provides a modern Stimulus interface for Bootstrap modals while maintaining jQuery compatibility
 */
export default class extends Controller {
    static targets = ['modal'];
    static values = {
        backdrop: { type: String, default: 'true' },
        keyboard: { type: Boolean, default: true },
        show: { type: Boolean, default: false },
        remote: String,
    };

    connect() {
        // Check if jQuery is available
        if (typeof $ === 'undefined') {
            console.error(
                'Modal controller requires jQuery for Bootstrap 3.x compatibility'
            );
            return;
        }

        // Initialize the modal with Bootstrap 3.x options
        this._initializeModal();

        // Set up event listeners for Bootstrap modal events
        this._setupEventListeners();
    }

    disconnect() {
        // Clean up event listeners and modal instance
        if (this.hasModalTarget) {
            $(this.modalTarget).off('.modal-controller');
            $(this.modalTarget).modal('hide');
        }
    }

    /**
     * Show the modal
     */
    show() {
        if (this.hasModalTarget) {
            $(this.modalTarget).modal('show');
        }
    }

    /**
     * Hide the modal
     */
    hide() {
        if (this.hasModalTarget) {
            $(this.modalTarget).modal('hide');
        }
    }

    /**
     * Toggle the modal visibility
     */
    toggle() {
        if (this.hasModalTarget) {
            $(this.modalTarget).modal('toggle');
        }
    }

    /**
     * Handle show action from data-action
     */
    handleShow(event) {
        event.preventDefault();
        this.show();
    }

    /**
     * Handle hide action from data-action
     */
    handleHide(event) {
        event.preventDefault();
        this.hide();
    }

    /**
     * Handle toggle action from data-action
     */
    handleToggle(event) {
        event.preventDefault();
        this.toggle();
    }

    /**
     * Initialize the modal with Bootstrap 3.x configuration
     * @private
     */
    _initializeModal() {
        if (!this.hasModalTarget) return;

        const options = {
            backdrop:
                this.backdropValue === 'false' ? false : this.backdropValue,
            keyboard: this.keyboardValue,
            show: this.showValue,
        };

        // Add remote option if specified
        if (this.hasRemoteValue) {
            options.remote = this.remoteValue;
        }

        // Initialize the Bootstrap modal with options
        $(this.modalTarget).modal(options);
    }

    /**
     * Set up event listeners for Bootstrap modal events
     * @private
     */
    _setupEventListeners() {
        if (!this.hasModalTarget) return;

        const $modal = $(this.modalTarget);

        // Bootstrap 3.x modal events
        $modal.on('show.bs.modal.modal-controller', (event) => {
            this._dispatchCustomEvent('modal:show', { originalEvent: event });
        });

        $modal.on('shown.bs.modal.modal-controller', (event) => {
            this._dispatchCustomEvent('modal:shown', { originalEvent: event });

            // Focus on autofocus elements when modal is shown
            const autofocusElement =
                this.modalTarget.querySelector('[autofocus]');
            if (autofocusElement) {
                autofocusElement.focus();
            }
        });

        $modal.on('hide.bs.modal.modal-controller', (event) => {
            this._dispatchCustomEvent('modal:hide', { originalEvent: event });
        });

        $modal.on('hidden.bs.modal.modal-controller', (event) => {
            this._dispatchCustomEvent('modal:hidden', { originalEvent: event });
        });

        // Handle form submissions within the modal
        $modal.on('submit.modal-controller', 'form', (event) => {
            this._dispatchCustomEvent('modal:form-submit', {
                originalEvent: event,
                form: event.target,
            });
        });
    }

    /**
     * Dispatch custom events for other controllers to listen to
     * @private
     */
    _dispatchCustomEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, {
            detail: {
                controller: this,
                modal: this.modalTarget,
                ...detail,
            },
            bubbles: true,
            cancelable: true,
        });

        this.modalTarget.dispatchEvent(event);
    }

    /**
     * Check if the modal is currently visible
     */
    get isVisible() {
        return this.hasModalTarget && $(this.modalTarget).hasClass('in');
    }

    /**
     * Get the modal backdrop element
     */
    get backdrop() {
        return document.querySelector('.modal-backdrop');
    }

    /**
     * Static method to create modal instances programmatically
     * Useful for dynamic modal creation while maintaining Bootstrap 3.x compatibility
     */
    static createModal(element, options = {}) {
        // Ensure jQuery is available for Bootstrap 3.x
        if (typeof $ === 'undefined') {
            console.error(
                'jQuery is required for Bootstrap 3.x modal functionality'
            );
            return null;
        }

        // Initialize with Bootstrap 3.x modal
        const $modal = $(element);
        $modal.modal(options);

        return $modal;
    }

    /**
     * Utility method to handle form submissions within modals
     * Provides consistent AJAX handling across all modals
     */
    handleFormSubmission(form, options = {}) {
        const formData = new FormData(form);
        const url = form.action || options.url;

        if (!url) {
            console.error('No URL provided for form submission');
            return Promise.reject(new Error('No URL provided'));
        }

        return fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers,
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch((error) => {
                console.error('Form submission error:', error);
                throw error;
            });
    }
}
