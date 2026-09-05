import { Controller } from '@hotwired/stimulus';

/**
 * Replaces the whole list on "load more", same mechanism as the coaster
 * page's own "load more photos" (coaster_images_controller.js): re-fetch a
 * larger count from the top and swap it in, rather than tracking a cursor
 * client-side. The response already carries its own next-page trigger (or
 * none, once there's nothing left), so there's nothing else to update here.
 */
export default class extends Controller {
    static targets = ['container'];
    static values = { markReadToken: String };

    loadMore(event) {
        event.preventDefault();

        fetch(event.currentTarget.getAttribute('href'), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                this.containerTarget.innerHTML = html;
            })
            .catch((error) => {
                console.error('Error loading older notifications:', error);
            });
    }

    /**
     * Fires on a real click only (never on the same link's GET, which stays
     * side-effect-free — see NotificationController::readAction()). Uses
     * sendBeacon rather than a blocking fetch so it never delays the
     * navigation the click already started.
     */
    markRead(event) {
        const url = event.currentTarget.getAttribute('data-mark-read-url');
        if (!url) {
            return;
        }

        const body = new FormData();
        body.set('_token', this.markReadTokenValue);

        navigator.sendBeacon(url, body);
    }
}
