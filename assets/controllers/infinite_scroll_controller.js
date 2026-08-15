import { Controller } from '@hotwired/stimulus';

/**
 * Infinite scroll — fetches the next page of server-rendered cards when the
 * sentinel scrolls into view and appends the response to the items container.
 *
 * Usage:
 *   <div data-controller="infinite-scroll"
 *        data-infinite-scroll-url-value="/tops"
 *        data-infinite-scroll-next-page-value="2"
 *        data-infinite-scroll-total-pages-value="7">
 *     <div data-infinite-scroll-target="items"> ...current page... </div>
 *     <div data-infinite-scroll-target="sentinel"></div>
 *   </div>
 */
export default class extends Controller {
    static targets = ['items', 'sentinel'];
    static values = {
        url: String,
        nextPage: Number,
        totalPages: Number,
    };

    connect() {
        if (
            !this.hasSentinelTarget ||
            !this.hasItemsTarget ||
            this.nextPageValue > this.totalPagesValue
        ) {
            return;
        }

        this.loading = false;
        this.observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    this.loadNext();
                }
            },
            { rootMargin: '200px' }
        );
        this.observer.observe(this.sentinelTarget);
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    async loadNext() {
        if (this.loading || this.nextPageValue > this.totalPagesValue) {
            return;
        }
        this.loading = true;

        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('page', this.nextPageValue);

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const html = await response.text();
            this.itemsTarget.insertAdjacentHTML('beforeend', html);
            this.nextPageValue += 1;

            if (this.nextPageValue > this.totalPagesValue) {
                this.observer.disconnect();
                this.sentinelTarget.innerHTML = '';
            }
        } catch (error) {
            console.error('Infinite scroll error:', error);
        } finally {
            this.loading = false;
        }
    }
}
