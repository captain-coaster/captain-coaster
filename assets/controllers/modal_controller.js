import { Controller } from '@hotwired/stimulus';

// stimulusFetch: 'lazy' — only used where review items render

/**
 * Modal controller -- built on native <dialog> (showModal()/close()),
 * which gives focus-trap, ESC-to-close, [autofocus] handling, an implicit
 * "dialog" ARIA role, and a ::backdrop for free, none of which the
 * previous hand-rolled implementation had to fake. Public API (show/hide/
 * toggle, modal:* events) is unchanged so callers using the Stimulus
 * outlet (review_actions_controller.js) need no changes.
 *
 * The previous implementation's `backdrop`/`keyboard`/`show` config
 * values are dropped -- unused everywhere in this codebase (this app has
 * exactly one modal, using every default) and easy to reintroduce if a
 * real need for them ever comes up.
 */
export default class extends Controller {
    static targets = ['modal'];

    connect() {
        this._boundHandleDismissClick = this._handleDismissClick.bind(this);
        this._boundHandleBackdropClick = this._handleBackdropClick.bind(this);
        this._boundHandleCancel = this._handleCancel.bind(this);
        this._boundHandleClose = this._handleClose.bind(this);

        // Matches Bootstrap's own `[data-dismiss="modal"]` data-api (close
        // button, cancel button) -- removed along with bootstrap/js/modal.
        this.element.addEventListener('click', this._boundHandleDismissClick);

        if (this.hasModalTarget) {
            this.modalTarget.addEventListener(
                'click',
                this._boundHandleBackdropClick
            );
            // ESC fires 'cancel' before closing -- route it through hide()
            // so 'modal:hide' listeners can veto it like any other
            // dismissal, instead of letting the dialog auto-close.
            this.modalTarget.addEventListener(
                'cancel',
                this._boundHandleCancel
            );
            // Fires whenever close() actually runs (always via hide()
            // below) -- the one place to clean up and dispatch modal:hidden.
            this.modalTarget.addEventListener('close', this._boundHandleClose);
        }
    }

    disconnect() {
        this.element.removeEventListener(
            'click',
            this._boundHandleDismissClick
        );

        if (this.hasModalTarget) {
            this.modalTarget.removeEventListener(
                'click',
                this._boundHandleBackdropClick
            );
            this.modalTarget.removeEventListener(
                'cancel',
                this._boundHandleCancel
            );
            this.modalTarget.removeEventListener(
                'close',
                this._boundHandleClose
            );
        }
    }

    /**
     * Show the modal
     */
    show() {
        if (!this.hasModalTarget || this.isVisible) return;

        const event = this._dispatchCustomEvent('modal:show');
        if (event.defaultPrevented) return;

        document.body.classList.add('modal-open');
        this.modalTarget.showModal();
        // Force reflow so the `.fade`/`.in` transition actually runs.
        void this.modalTarget.offsetHeight;
        this.modalTarget.classList.add('in');

        this._dispatchCustomEvent('modal:shown');
    }

    /**
     * Hide the modal
     */
    hide() {
        if (!this.hasModalTarget || !this.isVisible) return;

        const event = this._dispatchCustomEvent('modal:hide');
        if (event.defaultPrevented) return;

        this.modalTarget.classList.remove('in');
        this.modalTarget.close();
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

    _handleDismissClick(event) {
        if (event.target.closest('[data-dismiss="modal"]')) {
            event.preventDefault();
            this.hide();
        }
    }

    _handleBackdropClick(event) {
        // A click landing on the dialog element itself (not a descendant)
        // means it hit the dialog's own box outside `.modal-dialog`'s
        // content -- same "click outside the content" area Bootstrap's
        // separate backdrop div used to catch.
        if (event.target === this.modalTarget) {
            this.hide();
        }
    }

    _handleCancel(event) {
        event.preventDefault();
        this.hide();
    }

    _handleClose() {
        this.modalTarget.classList.remove('in');
        document.body.classList.remove('modal-open');
        this._dispatchCustomEvent('modal:hidden');
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
        return this.hasModalTarget && this.modalTarget.open;
    }
}
