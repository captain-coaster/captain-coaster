import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        slug: String,
        locale: String,
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
            })
            .catch((error) => {
                console.error('Error loading AI summary:', error);
                this.containerTarget.style.display = 'none';
            });
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
