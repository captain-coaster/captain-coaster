import { Controller } from '@hotwired/stimulus';
import { hide } from '../js/utils/dom.js';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        slug: String,
        locale: String,
    };

    connect() {
        this.debounceTimeout = null;
        this.loadReviews(false);
    }

    disconnect() {
        // Clean up timeout on disconnect
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }
    }

    loadReviews(shouldScroll = false) {
        fetch(this.buildUrl(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                this.containerTarget.innerHTML = html;
                this.attachPaginationHandlers();
                if (shouldScroll) {
                    this.containerTarget.scrollIntoView({ behavior: 'smooth' });
                }

                this.setupEventListeners();
            })
            .catch((error) => {
                console.error('Error loading reviews:', error);
                hide(this.containerTarget);
            });
    }

    buildUrl() {
        const form = this.element.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams();

        // Only include non-empty values
        for (const [key, value] of formData.entries()) {
            params.set(key, value);
        }

        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate('coaster_reviews_ajax_load', {
                    slug: this.slugValue,
                    _locale: this.localeValue,
                    data: formData,
                });
            } catch (error) {
                console.warn('Routing failed:', error);
            }
        }

        return `${window.location.origin}/${this.localeValue}/coasters/${this.slugValue}/reviews?${params}`;
    }

    attachPaginationHandlers() {
        const paginationLinks =
            this.containerTarget.querySelectorAll('ul.pagination a');
        paginationLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                this.element.querySelector("input[name='page']").value =
                    parseInt(link.dataset.page) || 1;
                this.loadReviews(true);
            });
        });
    }

    setupEventListeners() {
        this.element
            .querySelector('form')
            .addEventListener('change', () => this.debouncedLoadReviews(false));
    }

    debouncedLoadReviews(shouldScroll = false) {
        // Clear existing timeout
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }

        // Set new timeout for 300ms delay
        this.debounceTimeout = setTimeout(() => {
            this.loadReviews(shouldScroll);
        }, 300);
    }
}
