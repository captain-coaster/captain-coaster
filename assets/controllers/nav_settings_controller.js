import { Controller } from '@hotwired/stimulus';

/**
 * Nav Settings Controller
 * Handles language dropdown toggle in navigation
 */
export default class extends Controller {
    static targets = ['languageMenu', 'languageIcon'];

    toggleLanguage(event) {
        const button = event.currentTarget;
        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', !isExpanded);

        if (this.hasLanguageMenuTarget) {
            this.languageMenuTarget.classList.toggle('hidden');
        }

        if (this.hasLanguageIconTarget) {
            this.languageIconTarget.classList.toggle('rotate-180');
        }
    }
}
