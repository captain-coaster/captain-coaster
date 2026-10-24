import { describe, expect, it, vi } from 'vitest';
import CoasterImages from '../../../assets/controllers/coaster_images_controller';
import CoasterReviews from '../../../assets/controllers/coaster_reviews_controller';
import ReviewList from '../../../assets/controllers/review_list_controller';
import {
    mount,
    patch,
    settle,
    stubFetch,
    stubObserver,
} from '../support/stimulus';

const container = (name) =>
    document.querySelector(`[data-${name}-target="container"]`);

describe('coaster-images', () => {
    const tile = (n, hidden = false) =>
        `<div ${hidden ? 'class="max-md:hidden" data-phone-hidden' : ''}><a href="/${n}.jpg" data-gallery-target="link"><img></a></div>`;
    const page = (total, tiles) => `
        <div data-controller="coaster-images" data-coaster-images-slug-value="taron" data-coaster-images-locale-value="en"
             data-coaster-images-total-images-value="${total}">
            <div data-coaster-images-target="grid">${tiles}</div>
            <div data-coaster-images-target="more"><button type="button" data-action="coaster-images#showAll">All</button></div>
        </div>`;
    const showAll = () => document.querySelector('button').click();
    const tiles = () =>
        document.querySelectorAll('[data-coaster-images-target="grid"] a');

    it('reveals the photos a phone hid without a request when the page has them all', async () => {
        const fetch = stubFetch();
        await mount(page(3, tile(1) + tile(2) + tile(3, true)), {
            'coaster-images': CoasterImages,
        });

        showAll();
        await settle();

        expect(fetch).not.toHaveBeenCalled();
        expect(document.querySelector('.max-md\\:hidden')).toBeNull();
        expect(document.querySelector('button')).toBeNull();
    });

    it('fetches only the photos after those the page has, and adds them', async () => {
        stubFetch({ body: tile(9) + tile(10) });
        await mount(page(10, tile(1) + tile(2, true)), {
            'coaster-images': CoasterImages,
        });

        showAll();
        await settle();

        expect(Routing.generate).toHaveBeenLastCalledWith(
            'coaster_images_ajax_load',
            { slug: 'taron', offset: 2, _locale: 'en' }
        );
        expect(tiles()).toHaveLength(4);
        expect(document.querySelector('[data-phone-hidden]')).toBeNull();
        expect(document.querySelector('button')).toBeNull();
    });

    it('sends one request on a double tap', async () => {
        const fetch = stubFetch({ body: tile(3) });
        await mount(page(3, tile(1) + tile(2)), {
            'coaster-images': CoasterImages,
        });

        showAll();
        showAll();
        await settle();

        expect(fetch).toHaveBeenCalledOnce();
        expect(tiles()).toHaveLength(3);
    });

    it('keeps the photos shown and lets the button be tried again when the request fails', async () => {
        stubFetch(new Error('offline'));
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page(5, tile(1) + tile(2)), {
            'coaster-images': CoasterImages,
        });

        showAll();
        await settle();

        expect(tiles()).toHaveLength(2);
        expect(document.querySelector('button').disabled).toBe(false);
    });
});

describe('coaster-reviews', () => {
    // The panel's form carries the page it shows, as _review_panel renders it.
    const list = (text, current = 1) =>
        `<form data-coaster-reviews-target="sortForm"><select name="sort"><option value="recent">Recent</option><option value="top">Top</option></select>
            <input type="hidden" name="page" value="${current}"></form>
        <p>${text}</p><ul class="cc-pagination"><li><a href="?page=2" data-page="2">2</a></li></ul>`;
    const page = `
        <div data-controller="coaster-reviews" data-coaster-reviews-slug-value="taron" data-coaster-reviews-locale-value="en">
            <div data-coaster-reviews-target="container">${list('first page')}</div>
        </div>`;
    const reviews = (text, current) => ({ body: list(text, current) });
    const route = (params) => [
        'coaster_reviews_ajax_load',
        { slug: 'taron', _locale: 'en', ...params },
    ];
    const shown = () =>
        container('coaster-reviews').querySelector('p').textContent;

    it('shows the first page the page came with, without a request', async () => {
        const fetch = stubFetch();

        await mount(page, { 'coaster-reviews': CoasterReviews });

        expect(fetch).not.toHaveBeenCalled();
        expect(shown()).toBe('first page');
    });

    it('loads another page in place and scrolls back to the list', async () => {
        const scrollIntoView = vi.fn();
        patch(Element.prototype, 'scrollIntoView', scrollIntoView);
        stubFetch(reviews('second page'));
        await mount(page, { 'coaster-reviews': CoasterReviews });

        document.querySelector('.cc-pagination a').click();
        await settle();

        expect(Routing.generate).toHaveBeenLastCalledWith(
            ...route({ sort: 'recent', page: '2' })
        );
        expect(shown()).toBe('second page');
        expect(scrollIntoView).toHaveBeenCalledOnce();
    });

    it('reloads once after the sort changes, from the page shown', async () => {
        const fetch = stubFetch(reviews('second', 2), reviews('sorted', 2));
        await mount(page, { 'coaster-reviews': CoasterReviews });
        patch(Element.prototype, 'scrollIntoView', vi.fn());
        document.querySelector('.cc-pagination a').click();
        await settle();
        vi.useFakeTimers();

        const sort = document.querySelector('select');
        sort.value = 'top';
        sort.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(300);

        expect(fetch).toHaveBeenCalledTimes(2);
        expect(Routing.generate).toHaveBeenLastCalledWith(
            ...route({ sort: 'top', page: '2' })
        );
    });

    it("leaves a review's report form alone when the coaster has a single review", async () => {
        const fetch = stubFetch();
        await mount(
            `<div data-controller="coaster-reviews" data-coaster-reviews-slug-value="taron" data-coaster-reviews-locale-value="en">
                <div data-coaster-reviews-target="container"><p>only one</p>
                    <dialog open><form><select name="reason"><option value="spam">Spam</option><option value="rude">Rude</option></select></form></dialog>
                </div>
            </div>`,
            { 'coaster-reviews': CoasterReviews }
        );
        vi.useFakeTimers();

        const reason = document.querySelector('select[name="reason"]');
        reason.value = 'rude';
        reason.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(300);

        expect(fetch).not.toHaveBeenCalled();
        expect(document.querySelector('dialog').open).toBe(true);
    });

    it('hides its block when another page fails to load', async () => {
        stubFetch(new Error('offline'));
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page, { 'coaster-reviews': CoasterReviews });

        document.querySelector('.cc-pagination a').click();
        await settle();

        expect(container('coaster-reviews').style.display).toBe('none');
    });
});

describe('review-list', () => {
    const more = (count) =>
        `<li data-review-list-target="trigger"><a href="/en/reviews/list?count=${count}" data-action="review-list#loadMore">More</a></li>`;
    const page = `<div data-controller="review-list"><ul data-review-list-target="container"><li>Review 1</li>${more(20)}</ul></div>`;

    it('loads the longer list when its trigger scrolls into view, then watches the new trigger', async () => {
        const observers = stubObserver('IntersectionObserver');
        const fetch = stubFetch({
            body: `<li>Review 1</li><li>Review 2</li>${more(30)}`,
        });
        await mount(page, { 'review-list': ReviewList });
        const [observer] = observers;
        const trigger = document.querySelector(
            '[data-review-list-target="trigger"]'
        );
        expect(observer.observed).toEqual([trigger]);

        observer.trigger({ isIntersecting: false, target: trigger });
        expect(fetch).not.toHaveBeenCalled();

        observer.trigger({ isIntersecting: true, target: trigger });
        await settle();

        expect(fetch.mock.calls[0][0]).toBe('/en/reviews/list?count=20');
        expect(document.querySelectorAll('ul > li')).toHaveLength(3);
        expect(
            observer.observed[0].querySelector('a').getAttribute('href')
        ).toBe('/en/reviews/list?count=30');
    });

    it('loads on a click on the link too, and stops watching at the end of the list', async () => {
        const observers = stubObserver('IntersectionObserver');
        const fetch = stubFetch({ body: '<li>Review 1</li><li>Review 2</li>' });
        await mount(page, { 'review-list': ReviewList });

        document.querySelector('a').click();
        await settle();

        expect(fetch).toHaveBeenCalledOnce();
        expect(document.querySelectorAll('ul > li')).toHaveLength(2);
        expect(observers[0].observed).toEqual([]);
    });
});
