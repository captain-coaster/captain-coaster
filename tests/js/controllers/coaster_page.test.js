import { describe, expect, it, vi } from 'vitest';
import CoasterImages from '../../../assets/controllers/coaster_images_controller';
import CoasterReviews from '../../../assets/controllers/coaster_reviews_controller';
import CoasterSummary from '../../../assets/controllers/coaster_summary_controller';
import ReviewList from '../../../assets/controllers/review_list_controller';
import {
    mount,
    settle,
    stubFetch,
    stubObserver,
    stubViewport,
} from '../support/stimulus';

const container = (name) =>
    document.querySelector(`[data-${name}-target="container"]`);

describe('coaster-summary', () => {
    const page = `
        <div data-controller="coaster-summary" data-coaster-summary-slug-value="taron" data-coaster-summary-locale-value="fr"
             data-coaster-summary-csrf-token-value="tok">
            <div data-coaster-summary-target="container"></div>
        </div>`;

    it('loads the summary and hands its CSRF token to the feedback buttons', async () => {
        const fetch = stubFetch({
            body: '<p>Intense.</p><div id="feedback" data-controller="summary-feedback"></div>',
        });

        await mount(page, { 'coaster-summary': CoasterSummary });

        expect(Routing.generate).toHaveBeenCalledWith(
            'coaster_summary_ajax_load',
            { slug: 'taron', _locale: 'fr' }
        );
        expect(fetch.mock.calls[0][0]).toBe(
            '/fr/coaster_summary_ajax_load/taron'
        );
        expect(
            container('coaster-summary').querySelector('p').textContent
        ).toBe('Intense.');
        expect(
            document.getElementById('feedback').dataset
                .summaryFeedbackCsrfTokenValue
        ).toBe('tok');
    });

    it('hides its block when the request fails', async () => {
        stubFetch(new Error('offline'));
        vi.spyOn(console, 'error').mockImplementation(() => {});

        await mount(page, { 'coaster-summary': CoasterSummary });

        expect(container('coaster-summary').style.display).toBe('none');
    });
});

describe('coaster-images', () => {
    const page = `
        <div data-controller="coaster-images" data-coaster-images-slug-value="taron" data-coaster-images-locale-value="en"
             data-coaster-images-total-images-value="31">
            <div data-coaster-images-target="container"></div>
        </div>`;
    const loaded = (count) => [
        'coaster_images_ajax_load',
        { slug: 'taron', imageNumber: count, _locale: 'en' },
    ];

    it.each([
        ['2 photos on a phone', false, 2],
        ['8 photos on a tablet or wider', true, 8],
    ])('loads %s', async (_, tablet, count) => {
        stubViewport({ tablet });
        stubFetch({ body: '<img>' });

        await mount(page, { 'coaster-images': CoasterImages });

        expect(Routing.generate).toHaveBeenCalledWith(...loaded(count));
        expect(container('coaster-images').innerHTML).toBe('<img>');
    });

    it('loads every photo on "show all"', async () => {
        stubViewport();
        stubFetch(
            { body: '<img><button id="show-all">All</button>' },
            { body: '<img><img><img>' }
        );
        await mount(page, { 'coaster-images': CoasterImages });

        document.getElementById('show-all').click();
        await settle();

        expect(Routing.generate).toHaveBeenLastCalledWith(...loaded(31));
        expect(
            container('coaster-images').querySelectorAll('img')
        ).toHaveLength(3);
    });
});

describe('coaster-reviews', () => {
    const page = `
        <div data-controller="coaster-reviews" data-coaster-reviews-slug-value="taron" data-coaster-reviews-locale-value="en">
            <form><select name="sort"><option value="recent">Recent</option><option value="top">Top</option></select>
                <input type="hidden" name="page" value="1"></form>
            <div data-coaster-reviews-target="container"></div>
        </div>`;
    const reviews = (text) => ({
        body: `<p>${text}</p><ul class="cc-pagination"><li><a href="?page=2" data-page="2">2</a></li></ul>`,
    });
    const route = (params) => [
        'coaster_reviews_ajax_load',
        { slug: 'taron', _locale: 'en', ...params },
    ];

    it('loads the reviews with the sort the form shows', async () => {
        stubFetch(reviews('first page'));

        await mount(page, { 'coaster-reviews': CoasterReviews });

        expect(Routing.generate).toHaveBeenCalledWith(
            ...route({ sort: 'recent', page: '1' })
        );
        expect(
            container('coaster-reviews').querySelector('p').textContent
        ).toBe('first page');
    });

    it('loads another page in place and scrolls back to the list', async () => {
        const scrollIntoView = (Element.prototype.scrollIntoView = vi.fn());
        stubFetch(reviews('first page'), reviews('second page'));
        await mount(page, { 'coaster-reviews': CoasterReviews });

        document.querySelector('.cc-pagination a').click();
        await settle();

        expect(Routing.generate).toHaveBeenLastCalledWith(
            ...route({ sort: 'recent', page: '2' })
        );
        expect(
            container('coaster-reviews').querySelector('p').textContent
        ).toBe('second page');
        expect(scrollIntoView).toHaveBeenCalledOnce();
    });

    it('reloads once after the sort changes, however many times the list was loaded', async () => {
        const fetch = stubFetch(
            reviews('first'),
            reviews('second'),
            reviews('sorted')
        );
        await mount(page, { 'coaster-reviews': CoasterReviews });
        Element.prototype.scrollIntoView = vi.fn();
        document.querySelector('.cc-pagination a').click();
        await settle();
        vi.useFakeTimers();

        const sort = document.querySelector('select');
        sort.value = 'top';
        sort.dispatchEvent(new Event('change', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(300);

        expect(fetch).toHaveBeenCalledTimes(3);
        expect(Routing.generate).toHaveBeenLastCalledWith(
            ...route({ sort: 'top', page: '2' })
        );
    });

    it('hides its block when the request fails', async () => {
        stubFetch(new Error('offline'));
        vi.spyOn(console, 'error').mockImplementation(() => {});

        await mount(page, { 'coaster-reviews': CoasterReviews });

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
