import { Controller } from '@hotwired/stimulus';
import { lockScroll, unlockScroll } from '../js/utils/dom.js';

/**
 * Modal Controller
 *
 * Vanilla JS modal implementation
 *
 * Usage:
 * <button data-action="click->modal#open" data-modal-target-param="searchModal">
 *   Open Modal
 * </button>
 *
 * <div data-controller="modal" data-modal-id-value="searchModal" class="hidden">
 *   <div data-action="click->modal#close" class="backdrop"></div>
 *   <div class="modal-content">
 *     <button data-action="click->modal#close">Close</button>
 *     Content
 *   </div>
 * </div>
 */
export default class extends Controller {
    static values = {
        id: String,
    };

    connect() {
        this.isOpen = false;

        // Listen for open events from other elements
        document.addEventListener(
            'modal:open',
            this.handleOpenEvent.bind(this)
        );

        // Close on Escape key
        this.boundHandleEscape = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.boundHandleEscape);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleEscape);
        if (this.isOpen) {
            this.close();
        }
    }

    open(event) {
        if (event) {
            event.preventDefault();
            const targetId =
                event.params?.target ||
                event.currentTarget.getAttribute('data-modal-target-param');
            if (targetId && targetId !== this.idValue) {
                return;
            }
        }

        this.isOpen = true;
        this.element.classList.remove('hidden');
        this.element.setAttribute('aria-hidden', 'false');

        // Prevent body scroll
        lockScroll();

        // Focus first focusable element
        setTimeout(() => {
            const firstFocusable = this.element.querySelector(
                'input, button, [tabindex]:not([tabindex="-1"])'
            );
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }, 100);
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        this.isOpen = false;
        this.element.classList.add('hidden');
        this.element.setAttribute('aria-hidden', 'true');

        // Restore body scroll
        unlockScroll();
    }

    toggle(event) {
        this.isOpen ? this.close(event) : this.open(event);
    }

    handleEscape(event) {
        if (this.isOpen && event.key === 'Escape') {
            this.close();
        }
    }

    handleOpenEvent(event) {
        if (event.detail.id === this.idValue) {
            this.open();
        }
    }
}

// Helper function to open modal from anywhere
window.openModal = function (id) {
    document.dispatchEvent(new CustomEvent('modal:open', { detail: { id } }));
};
