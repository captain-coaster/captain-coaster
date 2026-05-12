import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle'];

    connect() {
        // Initialize theme from localStorage or system preference
        const savedTheme = localStorage.getItem('theme');
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)')
            .matches
            ? 'dark'
            : 'light';

        this.currentTheme = savedTheme || systemTheme;
        this.applyTheme(this.currentTheme);
        this.updateAllToggleIcons();

        // Listen for system theme changes
        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    this.currentTheme = e.matches ? 'dark' : 'light';
                    this.applyTheme(this.currentTheme);
                    this.updateAllToggleIcons();
                }
            });
    }

    toggle() {
        this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.applyTheme(this.currentTheme);
        this.updateAllToggleIcons();
        localStorage.setItem('theme', this.currentTheme);
    }

    applyTheme(theme) {
        // Set dark class for Tailwind
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    updateAllToggleIcons() {
        // Update all theme toggle buttons on the page
        const allThemeButtons = document.querySelectorAll(
            '[data-controller*="theme"]'
        );

        allThemeButtons.forEach((button) => {
            this.updateToggleIcon(button);
        });
    }

    updateToggleIcon(button = null) {
        const targetButton = button || this.element;
        const icon = targetButton.querySelector('svg');

        if (icon) {
            icon.setAttribute('stroke-width', '1.5');

            if (this.currentTheme === 'dark') {
                // Sun icon for dark theme
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" />`;
            } else {
                // Moon icon for light theme
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />`;
            }
        }
    }
}
