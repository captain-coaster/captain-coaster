import { Controller } from '@hotwired/stimulus';

/**
 * Theme Selector Controller
 *
 * Provides a three-option theme selector: System, Light, Dark
 * Manages theme state and visual feedback for active selection
 */
export default class extends Controller {
    static targets = ['button', 'indicator'];

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
        this.buttonTargets.forEach((button, index) => {
            const isActive = button.dataset.theme === this.currentTheme;

            if (isActive) {
                button.setAttribute('data-active', '');
                this.moveIndicator(index);
            } else {
                button.removeAttribute('data-active');
            }
        });
    }

    moveIndicator(index) {
        if (this.hasIndicatorTarget) {
            this.indicatorTarget.style.transform = `translateX(${index * 100}%)`;
        }
    }

    updateOtherThemeControllers() {
        // Keep every theme-selector instance on the page (sidebar + drawer) in sync
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
    }

    handleSystemChange(event) {
        if (this.currentTheme === 'system') {
            this.applyTheme('system');
        }
    }
}
