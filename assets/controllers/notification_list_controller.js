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

        navigator.sendBeacon(url, this.tokenBody());
    }

    /** The row's own "mark as read" button: marks it read and stays on the page. */
    dismiss(event) {
        const button = event.currentTarget;
        const row = button.closest('li');
        button.disabled = true;

        fetch(button.getAttribute('data-mark-read-url'), {
            method: 'POST',
            body: this.tokenBody(),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                this.showRead(row, button);
            })
            .catch(() => {
                button.disabled = false;
            });
    }

    showRead(row, button) {
        const link = row.querySelector('a');
        row.removeAttribute('data-unread');
        row.querySelector('[data-unread-text]')?.remove();
        link.removeAttribute('data-action');
        link.removeAttribute('data-mark-read-url');
        button.remove();
        link.focus();

        // The unread plate sits in the page header, outside this controller's element.
        const plate = document.getElementById('unread-plate');
        if (!plate) {
            return;
        }
        const count = Number(plate.dataset.count) - 1;
        plate.dataset.count = String(count);
        plate.hidden = count < 1;
        plate.querySelector('[data-unread-count]').textContent = String(count);
        const label = plate.querySelector('[data-unread-label]');
        label.textContent =
            count === 1 ? label.dataset.one : label.dataset.other;
    }

    tokenBody() {
        const body = new FormData();
        body.set('_token', this.markReadTokenValue);

        return body;
    }
}
