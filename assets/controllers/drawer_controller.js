import { Controller } from '@hotwired/stimulus';
import { lockScroll, unlockScroll } from '../js/utils/dom.js';

/**
 * Drawer Controller
 *
 * Mobile navigation drawer that slides in from the left.
 * Positioned below the navbar (not covering it).
 * Full-screen height without backdrop.
 *
 * Usage:
 * <nav data-controller="drawer">
 *   <button data-action="click->drawer#toggle">Toggle</button>
 *   <div data-drawer-target="panel" class="...">Drawer content</div>
 * </nav>
 */
export default class extends Controller {
    static targets = [
        'panel',
        'openIcon',
        'closeIcon',
        'languageMenu',
        'languageIcon',
    ];

    connect() {
        this.isOpen = false;

        // Close drawer on Escape key
        this.boundHandleEscape = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.boundHandleEscape);

        // Close drawer on window resize to desktop
        this.boundHandleResize = this.handleResize.bind(this);
        window.addEventListener('resize', this.boundHandleResize);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleEscape);
        window.removeEventListener('resize', this.boundHandleResize);
        unlockScroll();
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        if (this.isOpen) return;

        this.isOpen = true;
        lockScroll();

        // Show panel instantly with opacity
        this.panelTarget.classList.remove('opacity-0', 'invisible');
        this.panelTarget.classList.add('opacity-100', 'visible');

        // Toggle hamburger/close icons
        if (this.hasOpenIconTarget && this.hasCloseIconTarget) {
            this.openIconTarget.classList.add('hidden');
            this.closeIconTarget.classList.remove('hidden');
        }

        // Set ARIA attributes
        this.panelTarget.setAttribute('aria-hidden', 'false');
    }

    close() {
        if (!this.isOpen) return;

        this.isOpen = false;
        unlockScroll();

        // Hide panel instantly with opacity
        this.panelTarget.classList.add('opacity-0', 'invisible');
        this.panelTarget.classList.remove('opacity-100', 'visible');

        // Toggle hamburger/close icons
        if (this.hasOpenIconTarget && this.hasCloseIconTarget) {
            this.openIconTarget.classList.remove('hidden');
            this.closeIconTarget.classList.add('hidden');
        }

        // Set ARIA attributes
        this.panelTarget.setAttribute('aria-hidden', 'true');

        // Close language menu if open
        this.closeLanguageMenu();
    }

    toggleLanguage(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!this.hasLanguageMenuTarget) return;

        const isHidden = this.languageMenuTarget.classList.contains('hidden');

        if (isHidden) {
            this.languageMenuTarget.classList.remove('hidden');
            if (this.hasLanguageIconTarget) {
                this.languageIconTarget.classList.add('rotate-180');
            }
        } else {
            this.closeLanguageMenu();
        }
    }

    closeLanguageMenu() {
        if (this.hasLanguageMenuTarget) {
            this.languageMenuTarget.classList.add('hidden');
        }
        if (this.hasLanguageIconTarget) {
            this.languageIconTarget.classList.remove('rotate-180');
        }
    }

    handleEscape(event) {
        if (this.isOpen && event.key === 'Escape') {
            this.close();
        }
    }

    handleResize() {
        // Close drawer when resizing to desktop breakpoint (768px = md)
        if (this.isOpen && window.innerWidth >= 768) {
            this.close();
        }
    }
}
