import { Controller } from '@hotwired/stimulus';

/**
 * Keyboard Shortcuts Controller
 *
 * Handles global keyboard shortcuts for the application.
 * ⌘K / Ctrl+K: open mobile search modal, or focus desktop search input.
 */
export default class extends Controller {
    connect() {
        this.updateSearchShortcutDisplay();
        this.boundHandleKeydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.boundHandleKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleKeydown);
    }

    updateSearchShortcutDisplay() {
        const shortcutKey = document.getElementById('search-shortcut-key');
        if (shortcutKey && this.isMac()) {
            shortcutKey.textContent = '⌘';
        }
    }

    handleKeydown(event) {
        const isSearchShortcut = this.isMac()
            ? event.metaKey && event.key === 'k'
            : event.ctrlKey && event.key === 'k';

        if (isSearchShortcut) {
            event.preventDefault();
            this.handleSearchShortcut();
        }
    }

    handleSearchShortcut() {
        if (window.innerWidth < 768) {
            // Mobile: open the search modal via the global controller
            const searchButton = document.querySelector(
                '[data-global-modal-id-param="searchModal"]'
            );
            if (searchButton) searchButton.click();
        } else {
            // Desktop: focus the search input
            const searchInput = document.getElementById('search-coaster');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    }

    isMac() {
        return /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
    }
}
