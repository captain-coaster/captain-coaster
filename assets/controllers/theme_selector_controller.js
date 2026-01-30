import { Controller } from '@hotwired/stimulus';

/**
 * Theme Selector Controller
 *
 * Provides a three-option theme selector: System, Light, Dark
 * Manages theme state and visual feedback for active selection
 */
export default class extends Controller {
    static targets = ['button'];

    connect() {
        // Initialize theme from localStorage or default to system
        this.currentTheme = localStorage.getItem('theme') || 'system';
        this.applyTheme(this.currentTheme);
        this.updateButtonStates();

        // Listen for system theme changes
        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.boundHandleSystemChange = this.handleSystemChange.bind(this);
        this.mediaQuery.addEventListener(
            'change',
            this.boundHandleSystemChange
        );
    }

    disconnect() {
        if (this.mediaQuery) {
            this.mediaQuery.removeEventListener(
                'change',
                this.boundHandleSystemChange
            );
        }
    }

    setTheme(event) {
        const theme = event.currentTarget.dataset.theme;

        if (theme === this.currentTheme) return;

        this.currentTheme = theme;
        this.applyTheme(theme);
        this.updateButtonStates();

        // Save to localStorage (or remove if system)
        if (theme === 'system') {
            localStorage.removeItem('theme');
        } else {
            localStorage.setItem('theme', theme);
        }

        // Update other theme controllers on the page
        this.updateOtherThemeControllers();
    }

    applyTheme(theme) {
        let shouldBeDark = false;

        if (theme === 'dark') {
            shouldBeDark = true;
        } else if (theme === 'light') {
            shouldBeDark = false;
        } else {
            // system
            shouldBeDark = this.mediaQuery?.matches || false;
        }

        // Apply dark class for Tailwind
        if (shouldBeDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    updateButtonStates() {
        this.buttonTargets.forEach((button) => {
            const buttonTheme = button.dataset.theme;
            const isActive = buttonTheme === this.currentTheme;

            if (isActive) {
                button.setAttribute('data-active', '');
            } else {
                button.removeAttribute('data-active');
            }
        });
    }

    updateOtherThemeControllers() {
        // Update all other theme-selector controllers on the page
        document
            .querySelectorAll('[data-controller="theme-selector"]')
            .forEach((element) => {
                if (element === this.element) return;

                const controller =
                    this.application.getControllerForElementAndIdentifier(
                        element,
                        'theme-selector'
                    );
                if (controller) {
                    controller.currentTheme = this.currentTheme;
                    controller.updateButtonStates();
                }
            });

        // Update legacy theme toggle controllers
        const otherThemeButtons = document.querySelectorAll(
            '[data-controller*="theme"]:not([data-controller*="theme-selector"])'
        );

        otherThemeButtons.forEach((button) => {
            const controller =
                this.application.getControllerForElementAndIdentifier(
                    button,
                    'theme'
                );
            if (controller && controller.updateToggleIcon) {
                // Update the current theme property and icon
                const effectiveTheme =
                    this.currentTheme === 'system'
                        ? this.mediaQuery?.matches
                            ? 'dark'
                            : 'light'
                        : this.currentTheme;
                controller.currentTheme = effectiveTheme;
                controller.updateToggleIcon();
            }
        });
    }

    handleSystemChange(event) {
        if (this.currentTheme === 'system') {
            this.applyTheme('system');
        }
    }
}
