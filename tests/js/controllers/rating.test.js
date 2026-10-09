import { describe, expect, it, vi } from 'vitest';
import CsrfProtection from '../../../assets/controllers/csrf_protection_controller';
import Rating from '../../../assets/controllers/rating_controller';
import Toast from '../../../assets/controllers/toast_controller';
import {
    controllerOf,
    mount,
    patch,
    settle,
    stubFetch,
} from '../support/stimulus';

const page = ({ value = 0, ratingId = '', readonly = false } = {}) => `
    <div id="csrf" data-controller="csrf-protection" data-csrf-protection-token-value="tok"></div>
    <div id="toasts" data-controller="toast"></div>
    <div id="stars" data-controller="rating"
         data-rating-coaster-id-value="42" data-rating-locale-value="fr"
         data-rating-current-value-value="${value}" data-rating-rating-id-value="${ratingId}"
         data-rating-readonly-value="${readonly}"
         data-rating-csrf-protection-outlet="#csrf"></div>`;

const controllers = {
    rating: Rating,
    'csrf-protection': CsrfProtection,
    toast: Toast,
};

const X = (fraction) => 100 + 200 * fraction;

function touch(type, fraction, y = 0) {
    const event = new Event(type, { bubbles: true });
    event.touches =
        fraction === null ? [] : [{ clientX: X(fraction), clientY: y }];
    document.getElementById('stars').dispatchEvent(event);
}

/** Taps at `fraction` of the widget's width, as on a phone. */
function tap(fraction) {
    document.getElementById('stars').getBoundingClientRect = () => ({
        left: 100,
        width: 200,
    });
    touch('touchstart', fraction);
    touch('touchend', null);
}

const states = () =>
    [...document.querySelectorAll('.rating-star')].map((star) =>
        star.className.replace('rating-star star-', '')
    );

describe('rating', () => {
    it('draws the current rating with half stars', async () => {
        await mount(page({ value: 3.5 }), controllers);

        expect(states()).toEqual(['full', 'full', 'full', 'half', 'empty']);
    });

    it('posts a new rating with the CSRF token and announces it was created', async () => {
        const fetch = stubFetch({ body: { state: 'success', id: 77 } });
        await mount(page(), controllers);
        const created = vi.fn();
        document.addEventListener('rating:created', created);

        tap(0.88);
        await settle();

        expect(Routing.generate).toHaveBeenCalledWith('rating_edit', {
            id: 42,
            _locale: 'fr',
        });
        expect(fetch.mock.calls[0][1]).toMatchObject({
            method: 'POST',
            body: 'value=4.5&_token=tok',
        });
        expect(states()).toEqual(['full', 'full', 'full', 'full', 'half']);
        expect(created).toHaveBeenCalledOnce();
        expect(created.mock.calls[0][0].detail).toEqual({ ratingId: 77 });
    });

    it('announces an update when the coaster was already rated', async () => {
        stubFetch({ body: { state: 'success', id: 77 } });
        await mount(page({ value: 2, ratingId: 77 }), controllers);
        const created = vi.fn();
        const updated = vi.fn();
        document.addEventListener('rating:created', created);
        document.addEventListener('rating:updated', updated);

        tap(1);
        await settle();

        expect(updated).toHaveBeenCalledOnce();
        expect(created).not.toHaveBeenCalled();
    });

    it('never rates below half a star', async () => {
        const fetch = stubFetch({ body: { id: 1 } });
        await mount(page(), controllers);

        tap(0);
        await settle();

        expect(fetch.mock.calls[0][1].body).toBe('value=0.5&_token=tok');
    });

    it('does not post the rating it already shows', async () => {
        const fetch = stubFetch();
        await mount(page({ value: 5, ratingId: 77 }), controllers);

        tap(1);
        await settle();

        expect(fetch).not.toHaveBeenCalled();
    });

    it('puts the previous rating back and says so when the save fails', async () => {
        stubFetch({ status: 500 });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page({ value: 2, ratingId: 77 }), controllers);

        tap(1);
        await settle();

        expect(states()).toEqual(['full', 'full', 'empty', 'empty', 'empty']);
        expect(controllerOf('#stars', 'rating').currentValueValue).toBe(2);
        expect(
            document.querySelector(
                '.notification--danger .notification__message'
            ).textContent
        ).toBe('rating.save_error');
    });

    it('previews while the finger slides, and keeps the rating when the page is scrolled instead', async () => {
        const fetch = stubFetch();
        await mount(page({ value: 2, ratingId: 77 }), controllers);
        document.getElementById('stars').getBoundingClientRect = () => ({
            left: 100,
            width: 200,
        });

        touch('touchstart', 0.2);
        touch('touchmove', 0.8);
        expect(states()).toEqual(['full', 'full', 'full', 'full', 'empty']);

        touch('touchmove', 0.2, 40);
        touch('touchend', null);
        await settle();

        expect(states()).toEqual(['full', 'full', 'empty', 'empty', 'empty']);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('rates on click where there is no touch screen', async () => {
        const fetch = stubFetch({ body: { id: 77 } });
        patch(window, 'ontouchstart', undefined);
        await mount(page(), controllers);
        const stars = document.getElementById('stars');
        stars.getBoundingClientRect = () => ({ left: 100, width: 200 });

        stars.dispatchEvent(
            new MouseEvent('mousemove', { clientX: X(0.5), bubbles: true })
        );
        expect(states()).toEqual(['full', 'full', 'half', 'empty', 'empty']);
        stars.dispatchEvent(new MouseEvent('mouseleave'));
        expect(states()).toEqual(['empty', 'empty', 'empty', 'empty', 'empty']);

        stars.dispatchEvent(
            new MouseEvent('click', { clientX: X(0.6), bubbles: true })
        );
        await settle();

        expect(fetch.mock.calls[0][1].body).toBe('value=3&_token=tok');
    });

    it('ignores taps when read-only', async () => {
        const fetch = stubFetch();
        await mount(page({ value: 3, readonly: true }), controllers);

        tap(1);
        await settle();

        expect(fetch).not.toHaveBeenCalled();
        expect(states()).toEqual(['full', 'full', 'full', 'empty', 'empty']);
    });

    it('goes back to no rating once deleted', async () => {
        await mount(page({ value: 4, ratingId: 77 }), controllers);
        const deleted = vi.fn();
        document.addEventListener('rating:deleted', deleted);

        controllerOf('#stars', 'rating').resetToZero();

        expect(states()).toEqual(['empty', 'empty', 'empty', 'empty', 'empty']);
        expect(deleted).toHaveBeenCalledOnce();
    });
});
