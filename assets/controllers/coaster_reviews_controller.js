import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        slug: String,
        locale: String,
    };

    connect() {
        this.loadReviews(1, false);
    }

    loadReviews(page = 1, shouldScroll = true) {
        fetch(this.buildUrl(page), {
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
            })
            .catch((error) => {
                console.error('Error loading reviews:', error);
                this.containerTarget.style.display = 'none';
            });
    }

    buildUrl(page) {
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate('coaster_reviews_ajax_load', {
                    slug: this.slugValue,
                    page: page,
                    _locale: this.localeValue,
                });
            } catch (error) {
                console.warn('Routing failed:', error);
            }
        }

        return `${window.location.origin}/${this.localeValue}/coasters/${this.slugValue}/reviews/ajax/${page}`;
    }

    attachPaginationHandlers() {
        const paginationLinks =
            this.containerTarget.querySelectorAll('ul.pagination a');
        paginationLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const page = parseInt(link.dataset.page) || 1;
                this.loadReviews(page, true);
            });
        });
    }
}
