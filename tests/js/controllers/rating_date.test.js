import { beforeEach, describe, expect, it, vi } from 'vitest';
import CsrfProtection from '../../../assets/controllers/csrf_protection_controller';
import RatingDate from '../../../assets/controllers/rating_date_controller';
import Toast from '../../../assets/controllers/toast_controller';
import { mount, settle, stubFetch, stubViewport } from '../support/stimulus';

const page = ({ ratingId = 77, min = '2016-06-30', max = '' } = {}) => `
    <div id="csrf" data-controller="csrf-protection" data-csrf-protection-token-value="tok"></div>
    <div id="toasts" data-controller="toast"></div>
    <div data-controller="rating-date" data-rating-date-update-url-value="/en/ratings/coasters/42/edit"
         data-rating-date-rating-id-value="${ratingId}" data-rating-date-min-date-value="${min}" data-rating-date-max-date-value="${max}"
         data-rating-date-before-opening-message-value="Before opening" data-rating-date-after-closing-message-value="After closing"
         data-rating-date-future-message-value="In the future" data-rating-date-csrf-protection-outlet="#csrf">
        <button id="calendar" data-rating-date-target="calendarButton" data-action="rating-date#toggleDatePicker">Date</button>
        <div id="picker" data-rating-date-target="dateContainer">
            <input type="date" data-rating-date-target="dateInput" data-action="change->rating-date#dateChanged">
            <button class="today-btn" data-action="rating-date#setToday">Today</button>
            <button id="clear" data-action="rating-date#clearDate">Clear</button>
        </div>
    </div>`;

const controllers = {
    'rating-date': RatingDate,
    'csrf-protection': CsrfProtection,
    toast: Toast,
};
const calendar = () => document.getElementById('calendar');
const input = () => document.querySelector('input');
const open = () => document.getElementById('picker').classList.contains('show');
const dot = () => calendar().querySelector('.date-indicator');
const toast = () =>
    document.querySelector('.notification--danger .notification__message')
        ?.textContent;

async function pick(date) {
    input().value = date;
    input().dispatchEvent(new Event('change', { bubbles: true }));
    await settle();
}

describe('rating-date', () => {
    beforeEach(() => {
        vi.useFakeTimers({
            toFake: ['Date'],
            now: new Date('2026-10-09T10:00:00Z'),
        });
    });

    it('offers the ride date only once the coaster is rated', async () => {
        await mount(page({ ratingId: '' }), controllers);
        expect(calendar().style.display).toBe('none');

        document.dispatchEvent(
            new CustomEvent('rating:created', { detail: { ratingId: 77 } })
        );
        await settle();
        expect(calendar().style.display).toBe('inline-flex');

        input().value = '2026-05-01';
        document.dispatchEvent(new CustomEvent('rating:deleted'));
        await settle();
        expect(calendar().style.display).toBe('none');
        expect(input().value).toBe('');
    });

    it('stops following ratings once it has left the page', async () => {
        await mount(page({ ratingId: '' }), controllers);
        const widget = document.querySelector(
            '[data-controller="rating-date"]'
        );
        widget.remove();
        await settle();

        document.dispatchEvent(
            new CustomEvent('rating:created', { detail: { ratingId: 77 } })
        );

        expect(widget.dataset.ratingDateRatingIdValue).toBe('');
    });

    it('bounds the date field to the coaster opening and closing dates', async () => {
        await mount(page({ max: '2020-01-05' }), controllers);

        expect([input().min, input().max]).toEqual([
            '2016-06-30',
            '2020-01-05',
        ]);
    });

    it('saves today with the CSRF token and marks the button', async () => {
        const fetch = stubFetch({ body: { state: 'success' } });
        await mount(page(), controllers);
        calendar().click();

        document.querySelector('.today-btn').click();
        await settle();

        expect(fetch.mock.calls[0]).toEqual([
            '/en/ratings/coasters/42/edit',
            expect.objectContaining({
                method: 'POST',
                body: 'riddenAt=2026-10-09&_token=tok',
            }),
        ]);
        expect(input().value).toBe('2026-10-09');
        expect(dot()).not.toBeNull();
        expect(open()).toBe(false);
    });

    it('clears the date', async () => {
        const fetch = stubFetch(
            { body: { state: 'success' } },
            { body: { state: 'success' } }
        );
        await mount(page(), controllers);
        document.querySelector('.today-btn').click();
        await settle();

        document.getElementById('clear').click();
        await settle();

        expect(fetch.mock.calls[1][1].body).toBe('riddenAt=&_token=tok');
        expect(input().value).toBe('');
        expect(dot()).toBeNull();
    });

    it('saves as soon as a date is picked on a tablet or wider', async () => {
        stubViewport({ tablet: true });
        const fetch = stubFetch({ body: { state: 'success' } });
        await mount(page(), controllers);
        calendar().click();

        await pick('2024-08-15');

        expect(fetch.mock.calls[0][1].body).toBe(
            'riddenAt=2024-08-15&_token=tok'
        );
        expect(open()).toBe(false);
    });

    it('on a phone, saves when the picker closes, and only a changed date', async () => {
        stubViewport();
        const fetch = stubFetch({ body: { state: 'success' } });
        await mount(page(), controllers);

        calendar().click();
        calendar().click();
        expect(fetch).not.toHaveBeenCalled();

        calendar().click();
        await pick('2024-08-15');
        expect(fetch).not.toHaveBeenCalled();
        expect(open()).toBe(true);

        calendar().click();
        await settle();

        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch.mock.calls[0][1].body).toBe(
            'riddenAt=2024-08-15&_token=tok'
        );
    });

    it.each([
        ['before the coaster opened', {}, '2016-06-29', 'Before opening'],
        ['in the future', {}, '2026-10-10', 'In the future'],
        [
            'after the coaster closed',
            { max: '2020-01-05' },
            '2020-01-06',
            'After closing',
        ],
    ])(
        'refuses a date %s without calling the server',
        async (_, options, date, message) => {
            stubViewport({ tablet: true });
            const fetch = stubFetch();
            await mount(page(options), controllers);

            await pick(date);

            expect(toast()).toBe(message);
            expect(fetch).not.toHaveBeenCalled();
            expect(dot()).toBeNull();
        }
    );

    it.each([
        ['the opening day', {}, '2016-06-30'],
        ['today', {}, '2026-10-09'],
        ['the closing day', { max: '2020-01-05' }, '2020-01-05'],
    ])('accepts %s', async (_, options, date) => {
        stubViewport({ tablet: true });
        const fetch = stubFetch({ body: { state: 'success' } });
        await mount(page(options), controllers);

        await pick(date);

        expect(toast()).toBeUndefined();
        expect(fetch).toHaveBeenCalledOnce();
    });

    it('disables "Today" for a coaster that has closed', async () => {
        await mount(page({ max: '2020-01-05' }), controllers);

        calendar().click();

        expect(document.querySelector('.today-btn').disabled).toBe(true);
    });

    it('leaves the button unmarked and the field usable when the save fails', async () => {
        stubViewport({ tablet: true });
        stubFetch({ status: 500 });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page(), controllers);

        await pick('2024-08-15');

        expect(dot()).toBeNull();
        expect(input().disabled).toBe(false);
    });

    it('saves nothing for a coaster not rated yet', async () => {
        stubViewport({ tablet: true });
        const fetch = stubFetch();
        await mount(page({ ratingId: '' }), controllers);

        await pick('2024-08-15');

        expect(fetch).not.toHaveBeenCalled();
    });
});
