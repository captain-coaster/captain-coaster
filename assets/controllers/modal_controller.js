import { Controller } from '@hotwired/stimulus';

// stimulusFetch: 'lazy' — only used where review items render

/**
 * Modal controller -- replaces bootstrap/js/modal (jQuery plugin) with a
 * plain implementation of the same show/hide/backdrop/focus/ESC behavior.
 * Public API (show/hide/toggle, modal:* events) is unchanged so callers
 * using the Stimulus outlet (review_actions_controller.js) need no changes.
 */
export default class extends Controller {
    static targets = ['modal'];
    static values = {
        backdrop: { type: String, default: 'true' },
        keyboard: { type: Boolean, default: true },
        show: { type: Boolean, default: false },
    };

    connect() {
        this._boundHandleKeydown = this._handleKeydown.bind(this);
        this._boundHandleBackdropClick = this._handleBackdropClick.bind(this);
        this._boundHandleDismissClick = this._handleDismissClick.bind(this);
        this._backdropEl = null;

        // Matches Bootstrap's own `[data-dismiss="modal"]` data-api (close
        // button, cancel button) -- removed along with bootstrap/js/modal.
        this.element.addEventListener('click', this._boundHandleDismissClick);

        if (this.hasModalTarget && this.showValue) {
            this.show();
        }
    }

    disconnect() {
        this.element.removeEventListener(
            'click',
            this._boundHandleDismissClick
        );
        this._teardown();
    }

    /**
     * Show the modal
     */
    show() {
        if (!this.hasModalTarget || this.isVisible) return;

        const event = this._dispatchCustomEvent('modal:show');
        if (event.defaultPrevented) return;

        document.body.classList.add('modal-open');

        if (this.backdropValue !== 'false') {
            this._backdropEl = document.createElement('div');
            this._backdropEl.className = 'modal-backdrop fade';
            document.body.appendChild(this._backdropEl);
            // Force reflow so the `.in` transition actually runs.
            void this._backdropEl.offsetHeight;
            this._backdropEl.classList.add('in');
            this._backdropEl.addEventListener(
                'click',
                this._boundHandleBackdropClick
            );
        }

        this.modalTarget.style.display = 'block';
        void this.modalTarget.offsetHeight;
        this.modalTarget.classList.add('in');

        if (this.keyboardValue) {
            document.addEventListener('keydown', this._boundHandleKeydown);
        }

        this._dispatchCustomEvent('modal:shown');

        const autofocusElement = this.modalTarget.querySelector('[autofocus]');
        if (autofocusElement) {
            autofocusElement.focus();
        }
    }

    /**
     * Hide the modal
     */
    hide() {
        if (!this.hasModalTarget || !this.isVisible) return;

        const event = this._dispatchCustomEvent('modal:hide');
        if (event.defaultPrevented) return;

        this.modalTarget.classList.remove('in');
        this.modalTarget.style.display = 'none';

        this._teardown();

        this._dispatchCustomEvent('modal:hidden');
    }

    /**
     * Toggle the modal visibility
     */
    toggle() {
        this.isVisible ? this.hide() : this.show();
    }

    handleShow(event) {
        event.preventDefault();
        this.show();
    }

    handleHide(event) {
        event.preventDefault();
        this.hide();
    }

    handleToggle(event) {
        event.preventDefault();
        this.toggle();
    }

    _handleKeydown(event) {
        if (event.key === 'Escape') {
            this.hide();
        }
    }

    _handleBackdropClick() {
        if (this.backdropValue !== 'static') {
            this.hide();
        }
    }

    _handleDismissClick(event) {
        if (event.target.closest('[data-dismiss="modal"]')) {
            event.preventDefault();
            this.hide();
        }
    }

    _teardown() {
        document.removeEventListener('keydown', this._boundHandleKeydown);
        document.body.classList.remove('modal-open');

        if (this._backdropEl) {
            this._backdropEl.removeEventListener(
                'click',
                this._boundHandleBackdropClick
            );
            this._backdropEl.remove();
            this._backdropEl = null;
        }
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
        return event;
    }

    /**
     * Check if the modal is currently visible
     */
    get isVisible() {
        return this.hasModalTarget && this.modalTarget.classList.contains('in');
    }

    /**
     * Get the modal backdrop element
     */
    get backdrop() {
        return document.querySelector('.modal-backdrop');
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
