import { Controller } from '@hotwired/stimulus';
import { hide } from '../js/utils/dom.js';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        slug: String,
        locale: String,
        csrfToken: String,
    };

    connect() {
        this.loadSummary();
    }

    loadSummary() {
        fetch(this.buildUrl(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                this.containerTarget.innerHTML = html;

                // CSRF TOKEN FIX: Inject the token from main template into AJAX-loaded content
                // The token was generated during main page load (reliable session save)
                this.injectCsrfToken();
            })
            .catch((error) => {
                console.error('Error loading AI summary:', error);
                hide(this.containerTarget);
            });
    }

    injectCsrfToken() {
        // Find the summary feedback controller in the AJAX-loaded content
        const feedbackController = this.containerTarget.querySelector(
            '[data-controller*="summary-feedback"]'
        );
        if (feedbackController && this.csrfTokenValue) {
            // Inject the CSRF token from main template into the feedback controller
            feedbackController.setAttribute(
                'data-summary-feedback-csrf-token-value',
                this.csrfTokenValue
            );
        }
    }

    buildUrl() {
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate('coaster_summary_ajax_load', {
                    slug: this.slugValue,
                    _locale: this.localeValue,
                });
            } catch (error) {
                console.warn('Routing failed:', error);
            }
        }

        return `${window.location.origin}/${this.localeValue}/coasters/${this.slugValue}/summary/ajax`;
    }
}
