import { Controller } from '@hotwired/stimulus';

/**
 * Auto-loads more reviews as the "load more" link scrolls into view, on top
 * of the same replace-the-whole-list mechanism as
 * notification_list_controller.js: re-fetch a larger count from the top and
 * swap it in, rather than tracking a cursor client-side. The link stays a
 * real href, so it still works with JS disabled.
 */
export default class extends Controller {
    static targets = ['container', 'trigger'];

    connect() {
        this.observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                this.fetchMore(entries[0].target.querySelector('a').getAttribute('href'));
            }
        }, { rootMargin: '200px' });

        this.observeTrigger();
    }

    disconnect() {
        this.observer.disconnect();
    }

    loadMore(event) {
        event.preventDefault();
        this.fetchMore(event.currentTarget.getAttribute('href'));
    }

    fetchMore(url) {
        this.observer.disconnect();

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                this.containerTarget.innerHTML = html;
                this.observeTrigger();
            })
            .catch((error) => {
                console.error('Error loading more reviews:', error);
            });
    }

    observeTrigger() {
        if (this.hasTriggerTarget) {
            this.observer.observe(this.triggerTarget);
        }
    }
}
