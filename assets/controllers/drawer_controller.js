import { Controller } from '@hotwired/stimulus';
import { lockScroll, unlockScroll } from '../js/utils/dom.js';

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Drawer Controller
 *
 * Mobile navigation drawer that appears below the navbar (not covering it).
 * Full-screen height, no backdrop. Closes on Escape and on resize to desktop.
 * Traps focus while open and restores it to the trigger on close.
 *
 * Usage:
 * <nav data-controller="drawer">
 *   <button data-action="click->drawer#toggle">Toggle</button>
 *   <div data-drawer-target="panel" tabindex="-1" class="...">Drawer content</div>
 * </nav>
 */
export default class extends Controller {
    static targets = ['panel', 'openIcon', 'closeIcon'];

    connect() {
        this.isOpen = false;

        this.boundHandleKeydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.boundHandleKeydown);

        this.boundHandleResize = this.handleResize.bind(this);
        window.addEventListener('resize', this.boundHandleResize);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleKeydown);
        window.removeEventListener('resize', this.boundHandleResize);
        unlockScroll();
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        if (this.isOpen) return;

        this.isOpen = true;
        this.previouslyFocused = document.activeElement;
        lockScroll();

        this.panelTarget.classList.remove('opacity-0', 'invisible');
        this.panelTarget.classList.add('opacity-100', 'visible');

        if (this.hasOpenIconTarget && this.hasCloseIconTarget) {
            this.openIconTarget.classList.add('hidden');
            this.closeIconTarget.classList.remove('hidden');
        }

        this.panelTarget.setAttribute('aria-hidden', 'false');

        // Move focus into the drawer
        const focusable = this.focusableElements();
        (focusable[0] || this.panelTarget).focus();
    }

    close() {
        if (!this.isOpen) return;

        this.isOpen = false;
        unlockScroll();

        this.panelTarget.classList.add('opacity-0', 'invisible');
        this.panelTarget.classList.remove('opacity-100', 'visible');

        if (this.hasOpenIconTarget && this.hasCloseIconTarget) {
            this.openIconTarget.classList.remove('hidden');
            this.closeIconTarget.classList.add('hidden');
        }

        this.panelTarget.setAttribute('aria-hidden', 'true');

        // Restore focus to the element that opened the drawer (the hamburger)
        if (this.previouslyFocused?.focus) {
            this.previouslyFocused.focus();
        }
    }

    handleKeydown(event) {
        if (!this.isOpen) return;

        if (event.key === 'Escape') {
            this.close();
        } else if (event.key === 'Tab') {
            this.trapFocus(event);
        }
    }

    trapFocus(event) {
        const focusable = this.focusableElements();
        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        } else if (!this.panelTarget.contains(active)) {
            event.preventDefault();
            first.focus();
        }
    }

    focusableElements() {
        return Array.from(
            this.panelTarget.querySelectorAll(FOCUSABLE_SELECTOR)
        ).filter((el) => el.offsetParent !== null);
    }

    handleResize() {
        // Close drawer when resizing to desktop breakpoint (768px = md)
        if (this.isOpen && window.innerWidth >= 768) {
            this.close();
        }
    }
}
