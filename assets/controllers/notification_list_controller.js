import { Controller } from '@hotwired/stimulus';

/** Appends the next page of notifications instead of navigating to it, so loading more never hides what's already on screen. */
export default class extends Controller {
    static targets = ['list', 'trigger'];

    loadMore(event) {
        event.preventDefault();

        const url = event.currentTarget.getAttribute('href');

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                const hasMore = response.headers.get('X-Notification-Has-More') === '1';
                const nextBefore = response.headers.get('X-Notification-Next-Before');
                const nextBeforeId = response.headers.get('X-Notification-Next-Before-Id');

                return response.text().then((html) => ({ html, hasMore, nextBefore, nextBeforeId }));
            })
            .then(({ html, hasMore, nextBefore, nextBeforeId }) => {
                this.listTarget.insertAdjacentHTML('beforeend', html);

                if (!hasMore) {
                    this.triggerTarget.closest('.panel-footer').remove();
                    return;
                }

                const nextUrl = new URL(url, window.location.origin);
                nextUrl.searchParams.set('before', nextBefore);
                nextUrl.searchParams.set('beforeId', nextBeforeId);
                this.triggerTarget.setAttribute('href', nextUrl.toString());
            })
            .catch((error) => {
                console.error('Error loading older notifications:', error);
            });
    }
}
