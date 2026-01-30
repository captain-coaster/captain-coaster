import { Controller } from '@hotwired/stimulus';

/**
 * Dropdown Controller
 *
 * Vanilla JS dropdown implementation
 *
 * Usage:
 * <div data-controller="dropdown">
 *   <button data-action="click->dropdown#toggle" data-dropdown-target="button">
 *     Toggle
 *   </button>
 *   <div data-dropdown-target="menu" class="hidden">
 *     Dropdown content
 *   </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['button', 'menu'];

    connect() {
        this.isOpen = false;

        // Close dropdown when clicking outside
        this.boundHandleClickOutside = this.handleClickOutside.bind(this);
        document.addEventListener('click', this.boundHandleClickOutside);

        // Close dropdown on Escape key
        this.boundHandleEscape = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.boundHandleEscape);
    }

    disconnect() {
        document.removeEventListener('click', this.boundHandleClickOutside);
        document.removeEventListener('keydown', this.boundHandleEscape);
    }

    toggle(event) {
        event.stopPropagation();
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.menuTarget.classList.remove('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.isOpen = false;
        this.menuTarget.classList.add('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }

    handleClickOutside(event) {
        if (this.isOpen && !this.element.contains(event.target)) {
            this.close();
        }
    }

    handleEscape(event) {
        if (this.isOpen && event.key === 'Escape') {
            this.close();
        }
    }
}
