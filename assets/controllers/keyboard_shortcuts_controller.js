import { Controller } from '@hotwired/stimulus';

/**
 * Keyboard Shortcuts Controller
 *
 * Handles global keyboard shortcuts for the application
 */
export default class extends Controller {
    connect() {
        // Update search shortcut display based on platform
        this.updateSearchShortcutDisplay();

        // Set up global keyboard shortcuts
        this.boundHandleKeydown = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.boundHandleKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundHandleKeydown);
    }

    /**
     * Update search shortcut key display based on platform
     */
    updateSearchShortcutDisplay() {
        const shortcutKey = document.getElementById('search-shortcut-key');
        if (shortcutKey && navigator.platform.indexOf('Mac') > -1) {
            shortcutKey.textContent = '⌘';
        }
    }

    /**
     * Handle global keyboard shortcuts
     */
    handleKeydown(event) {
        const isMac = navigator.platform.indexOf('Mac') > -1;
        const isSearchShortcut = isMac
            ? event.metaKey && event.key === 'k'
            : event.ctrlKey && event.key === 'k';

        if (isSearchShortcut) {
            event.preventDefault();
            this.handleSearchShortcut();
        }
    }

    /**
     * Handle search keyboard shortcut (Cmd+K / Ctrl+K)
     */
    handleSearchShortcut() {
        // On mobile, open the search modal
        if (window.innerWidth < 768) {
            const searchButton = document.querySelector(
                '[data-modal-target-param="searchModal"]'
            );
            if (searchButton) {
                searchButton.click();
            }
        } else {
            // On desktop, focus the search input
            const searchInput = document.getElementById('search-coaster');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    }
}
