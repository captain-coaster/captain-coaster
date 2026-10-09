import { describe, expect, it, vi } from 'vitest';
import NotificationList from '../../../assets/controllers/notification_list_controller';
import { mount, settle, stubFetch } from '../support/stimulus';

const row = (id) => `
    <li id="n${id}" data-unread>
        <a href="/en/notifications/${id}" data-action="notification-list#markRead" data-mark-read-url="/en/notifications/${id}/read">
            Badge <span data-unread-text>unread</span>
        </a>
        <button data-action="notification-list#dismiss" data-mark-read-url="/en/notifications/${id}/read">Mark as read</button>
    </li>`;

const page = (unread) => `
    <p id="unread-plate" data-count="${unread}">
        <span data-unread-count>${unread}</span> <span data-unread-label data-one="unread one" data-other="unread many">unread many</span>
    </p>
    <div data-controller="notification-list" data-notification-list-mark-read-token-value="tok">
        <ul data-notification-list-target="container">${row(1)}${row(2)}
            <li><a id="more" href="/en/notifications/list?count=40" data-action="notification-list#loadMore">Older</a></li>
        </ul>
    </div>`;

const controllers = { 'notification-list': NotificationList };

describe('notification-list', () => {
    it('marks a row as read in place and counts one less', async () => {
        const fetch = stubFetch({ status: 204 });
        await mount(page(2), controllers);

        document.querySelector('#n1 button').click();
        await settle();

        const [url, request] = fetch.mock.calls[0];
        expect(url).toBe('/en/notifications/1/read');
        expect(request.method).toBe('POST');
        expect(request.body.get('_token')).toBe('tok');

        const read = document.getElementById('n1');
        expect(read.hasAttribute('data-unread')).toBe(false);
        expect(read.querySelector('button')).toBeNull();
        expect(read.querySelector('[data-unread-text]')).toBeNull();
        expect(read.querySelector('a').hasAttribute('data-mark-read-url')).toBe(
            false
        );
        expect(document.activeElement).toBe(read.querySelector('a'));
        expect(document.getElementById('n2').hasAttribute('data-unread')).toBe(
            true
        );

        const plate = document.getElementById('unread-plate');
        expect(plate.textContent.replace(/\s+/g, ' ').trim()).toBe(
            '1 unread one'
        );
        expect(plate.hidden).toBe(false);
    });

    it('hides the unread plate with the last unread notification', async () => {
        stubFetch({ status: 204 });
        await mount(page(1), controllers);

        document.querySelector('#n1 button').click();
        await settle();

        expect(document.getElementById('unread-plate').hidden).toBe(true);
    });

    it('leaves the row unread and the button usable when the request fails', async () => {
        stubFetch({ status: 403 });
        await mount(page(2), controllers);
        const button = document.querySelector('#n1 button');

        button.click();
        expect(button.disabled).toBe(true);
        await settle();

        expect(button.disabled).toBe(false);
        expect(document.getElementById('n1').hasAttribute('data-unread')).toBe(
            true
        );
        expect(document.getElementById('unread-plate').dataset.count).toBe('2');
    });

    it('marks a notification read with a beacon when its link is followed', async () => {
        const sendBeacon = vi.fn();
        Object.defineProperty(navigator, 'sendBeacon', {
            value: sendBeacon,
            configurable: true,
        });
        await mount(page(2), controllers);
        const link = document.querySelector('#n1 a');
        link.addEventListener('click', (event) => event.preventDefault());

        link.click();

        expect(sendBeacon).toHaveBeenCalledOnce();
        expect(sendBeacon.mock.calls[0][0]).toBe('/en/notifications/1/read');
        expect(sendBeacon.mock.calls[0][1].get('_token')).toBe('tok');
    });

    it('replaces the list with the longer one on "load more"', async () => {
        const fetch = stubFetch({ body: '<li id="n3">older</li>' });
        await mount(page(2), controllers);

        document.getElementById('more').click();
        await settle();

        expect(fetch.mock.calls[0][0]).toBe('/en/notifications/list?count=40');
        expect(document.querySelector('ul').innerHTML).toBe(
            '<li id="n3">older</li>'
        );
    });
});
