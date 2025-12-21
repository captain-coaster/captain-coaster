import { Controller } from '@hotwired/stimulus';

/**
 * Offcanvas Controller
 *
 * Vanilla JS offcanvas/drawer implementation
 *
 * Usage:
 * <button data-action="click->offcanvas#open" data-offcanvas-target-param="sidebarMenu">
 *   Open Menu
 * </button>
 *
 * <aside data-controller="offcanvas" data-offcanvas-id-value="sidebarMenu" class="fixed inset-y-0 right-0 w-64 transform translate-x-full transition-transform duration-300">
 *   <button data-action="click->offcanvas#close">Close</button>
 *   Content
 * </aside>
 */
export default class extends Controller {
    static values = {
        id: String,
        placement: { type: String, default: 'end' }, // 'start' or 'end'
    };

    connect() {
        this.isOpen = false;

        // Listen for open events from other elements
        document.addEventListener(
            'offcanvas:open',
            this.handleOpenEvent.bind(this)
        );

        // Close on Escape key
        this.boundHandleEscape = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.boundHandleEscape);

        // Close when clicking backdrop
        this.boundHandleBackdropClick = this.handleBackdropClick.bind(this);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleEscape);
        this.removeBackdrop();
    }

    open(event) {
        if (event) {
            event.preventDefault();
            const targetId =
                event.params?.target ||
                event.currentTarget.getAttribute('data-offcanvas-target-param');
            if (targetId && targetId !== this.idValue) {
                return;
            }
        }

        this.isOpen = true;
        this.element.classList.remove('translate-x-full', '-translate-x-full');
        this.element.classList.add('translate-x-0');
        this.element.setAttribute('aria-hidden', 'false');

        // Add backdrop
        this.createBackdrop();

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        this.isOpen = false;

        if (this.placementValue === 'end') {
            this.element.classList.remove('translate-x-0');
            this.element.classList.add('translate-x-full');
        } else {
            this.element.classList.remove('translate-x-0');
            this.element.classList.add('-translate-x-full');
        }

        this.element.setAttribute('aria-hidden', 'true');

        // Remove backdrop
        this.removeBackdrop();

        // Restore body scroll
        document.body.style.overflow = '';
    }

    toggle(event) {
        this.isOpen ? this.close(event) : this.open(event);
    }

    createBackdrop() {
        if (this.backdrop) return;

        this.backdrop = document.createElement('div');
        this.backdrop.className =
            'fixed inset-0 bg-neutral-900/50 backdrop-blur-sm z-40 transition-opacity duration-300';
        this.backdrop.addEventListener('click', this.boundHandleBackdropClick);
        document.body.appendChild(this.backdrop);

        // Trigger animation
        setTimeout(() => {
            this.backdrop.style.opacity = '1';
        }, 10);
    }

    removeBackdrop() {
        if (!this.backdrop) return;

        this.backdrop.style.opacity = '0';
        setTimeout(() => {
            if (this.backdrop) {
                this.backdrop.remove();
                this.backdrop = null;
            }
        }, 300);
    }

    handleBackdropClick() {
        this.close();
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

// Helper function to open offcanvas from anywhere
window.openOffcanvas = function (id) {
    document.dispatchEvent(
        new CustomEvent('offcanvas:open', { detail: { id } })
    );
};
