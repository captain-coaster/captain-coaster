import { Controller } from '@hotwired/stimulus';
import { lockScroll, unlockScroll } from '../js/utils/dom.js';

/**
 * Filter Sidebar Controller
 *
 * Handles mobile slide-in filter sidebar behavior.
 * Uses translate-x-full for hidden state.
 *
 * Usage:
 * <aside data-controller="filter-sidebar" class="translate-x-full ...">
 *   <button data-action="filter-sidebar#close">Close</button>
 * </aside>
 *
 * Open from anywhere (outside controller scope):
 * <button data-action="click->global#openFilterSidebar">Open Filters</button>
 * Or use: window.openFilterSidebar()
 */
export default class extends Controller {
    connect() {
        this.isOpen = false;

        // Listen for open events from anywhere
        this.boundHandleOpenEvent = this.handleOpenEvent.bind(this);
        document.addEventListener(
            'filter-sidebar:open',
            this.boundHandleOpenEvent
        );

        // Close on Escape key
        this.boundHandleEscape = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.boundHandleEscape);
    }

    disconnect() {
        document.removeEventListener(
            'filter-sidebar:open',
            this.boundHandleOpenEvent
        );
        document.removeEventListener('keydown', this.boundHandleEscape);
        if (this.isOpen) {
            unlockScroll();
        }
    }

    open() {
        this.isOpen = true;
        this.element.classList.remove('translate-x-full');
        lockScroll();
    }

    close() {
        this.isOpen = false;
        this.element.classList.add('translate-x-full');
        unlockScroll();
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    handleEscape(event) {
        if (this.isOpen && event.key === 'Escape') {
            this.close();
        }
    }

    handleOpenEvent() {
        this.open();
    }
}

// Global function to open filter sidebar from anywhere
window.openFilterSidebar = function () {
    document.dispatchEvent(new CustomEvent('filter-sidebar:open'));
};
