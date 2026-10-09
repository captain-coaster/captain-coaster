import { describe, expect, it, vi } from 'vitest';
import Filter from '../../../assets/controllers/filter_controller';
import { mount, settle, stubFetch, stubGeolocation } from '../support/stimulus';

const page = ({
    updateUrl = true,
    initialLoad = false,
    pageValue = 3,
    debounce = 300,
} = {}) => `
    <nav class="language-switch"><a id="fr" href="https://captaincoaster.test/fr/ranking/?setLocale=1">FR</a></nav>
    <div id="filters" data-controller="filter" data-filter-endpoint-value="/en/ranking/coasters"
         data-filter-container-id-value="results" data-filter-update-url-value="${updateUrl}"
         data-filter-initial-load-value="${initialLoad}" data-filter-debounce-delay-value="${debounce}">
        <form>
            <select name="filters[country]"><option value=""></option><option value="18">France</option></select>
            <input type="search" name="filters[name]">
            <input type="checkbox" name="filters[kiddie]">
            <input type="checkbox" name="filters[sortByDistance]" data-action="filter#toggleGeolocation">
            <input type="hidden" name="filters[latitude]" data-filter-target="latitude">
            <input type="hidden" name="filters[longitude]" data-filter-target="longitude">
            <input type="hidden" name="filters[user]" value="7">
            <input type="hidden" name="page" value="${pageValue}">
            <input type="text" name="other">
        </form>
    </div>
    <div id="results">server-rendered</div>`;

const field = (name) => document.querySelector(`[name="${name}"]`);

/** Sets a field as a rider would: a browser fires input, then change. */
function change(name, value) {
    const input = field(name);
    if (input.type === 'checkbox') input.checked = value;
    else input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

const requested = (fetch, call = 0) =>
    new URL(fetch.mock.calls[call][0], 'https://captaincoaster.test');

describe('filter', () => {
    it('leaves server-rendered results alone on connect', async () => {
        const fetch = stubFetch();
        await mount(page(), { filter: Filter });

        expect(fetch).not.toHaveBeenCalled();
        expect(document.getElementById('results').textContent).toBe(
            'server-rendered'
        );
    });

    it('loads the results on connect when asked to', async () => {
        const fetch = stubFetch({ body: '<p>loaded</p>' });
        await mount(page({ initialLoad: true, updateUrl: false }), {
            filter: Filter,
        });

        expect(fetch).toHaveBeenCalledOnce();
        expect(document.getElementById('results').innerHTML).toBe(
            '<p>loaded</p>'
        );
    });

    it('fetches the filtered results from page 1 and shows them', async () => {
        const fetch = stubFetch({ body: '<p>France</p>' });
        await mount(page(), { filter: Filter });

        change('filters[country]', '18');
        await settle();

        const url = requested(fetch);
        expect(url.pathname).toBe('/en/ranking/coasters');
        expect(Object.fromEntries(url.searchParams)).toEqual({
            'filters[country]': '18',
            'filters[user]': '7',
            page: '1',
        });
        expect(fetch.mock.calls[0][1].headers['X-Requested-With']).toBe(
            'XMLHttpRequest'
        );
        expect(document.getElementById('results').innerHTML).toBe(
            '<p>France</p>'
        );
    });

    it('puts the filters in the address bar, without the rider', async () => {
        stubFetch({ body: '' });
        await mount(page(), { filter: Filter });

        change('filters[country]', '18');
        await settle();

        expect(window.location.pathname + window.location.search).toBe(
            '/en/?filters%5Bcountry%5D=18&page=1'
        );
    });

    it('carries the filters to the language switcher, keeping setLocale', async () => {
        stubFetch({ body: '' });
        await mount(page(), { filter: Filter });

        change('filters[country]', '18');
        await settle();

        const link = new URL(document.getElementById('fr').href);
        expect(link.pathname).toBe('/fr/ranking/');
        expect(Object.fromEntries(link.searchParams)).toEqual({
            'filters[country]': '18',
            page: '1',
            setLocale: '1',
        });
    });

    it('leaves the address bar alone when URL updates are off', async () => {
        stubFetch({ body: '' });
        await mount(page({ updateUrl: false }), { filter: Filter });

        change('filters[country]', '18');
        await settle();

        expect(window.location.search).toBe('');
    });

    it('ignores fields that are not filters', async () => {
        const fetch = stubFetch();
        await mount(page(), { filter: Filter });

        change('other', 'x');
        await settle();

        expect(fetch).not.toHaveBeenCalled();
    });

    it('waits for typing to pause before searching by name', async () => {
        const fetch = stubFetch({ body: '' });
        await mount(page({ debounce: 300 }), { filter: Filter });
        vi.useFakeTimers();
        const name = field('filters[name]');

        for (const value of ['t', 'ta', 'tar']) {
            name.value = value;
            name.dispatchEvent(new Event('input', { bubbles: true }));
            await vi.advanceTimersByTimeAsync(100);
        }
        expect(fetch).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(300);

        expect(fetch).toHaveBeenCalledOnce();
        expect(requested(fetch).searchParams.get('filters[name]')).toBe('tar');
        expect(requested(fetch).searchParams.get('page')).toBe('1');
    });

    it('keeps the current results when the request fails', async () => {
        stubFetch({ status: 500 });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page(), { filter: Filter });

        change('filters[country]', '18');
        await settle();

        expect(document.getElementById('results').textContent).toBe(
            'server-rendered'
        );
        expect(window.location.search).toBe('');
    });

    it('restores the filters of the URL on load', async () => {
        window.history.replaceState(
            null,
            '',
            '/en/?filters%5Bcountry%5D=18&filters%5Bkiddie%5D=on&filters%5Bname%5D=tar'
        );
        stubFetch();
        await mount(page(), { filter: Filter });

        expect(field('filters[country]').value).toBe('18');
        expect(field('filters[kiddie]').checked).toBe(true);
        expect(field('filters[name]').value).toBe('tar');
    });

    it('follows the back button: form and results match the restored URL', async () => {
        const fetch = stubFetch({ body: '' }, { body: '<p>all</p>' });
        await mount(page({ pageValue: 1 }), { filter: Filter });
        change('filters[kiddie]', true);
        await settle();

        window.history.replaceState(null, '', '/en/');
        window.dispatchEvent(new PopStateEvent('popstate'));
        await settle();

        expect(field('filters[kiddie]').checked).toBe(false);
        expect(requested(fetch, 1).searchParams.has('filters[kiddie]')).toBe(
            false
        );
        expect(document.getElementById('results').innerHTML).toBe('<p>all</p>');
    });

    it('asks the map to filter instead of fetching, on the map page', async () => {
        const fetch = stubFetch();
        const filterData = vi.fn();
        const { Controller } = await import('@hotwired/stimulus');
        class Map extends Controller {
            filterData = filterData;
        }
        await mount(
            `<div id="map" data-controller="map"></div>
             <div data-controller="filter" data-filter-map-outlet="#map" data-filter-initial-load-value="false">
                <form><select name="filters[country]"><option value="18">France</option></select></form>
             </div>`,
            { filter: Filter, map: Map }
        );

        document
            .querySelector('select')
            .dispatchEvent(new Event('change', { bubbles: true }));
        await settle();

        expect(filterData).toHaveBeenCalledOnce();
        expect(fetch).not.toHaveBeenCalled();
    });

    describe('sort by distance', () => {
        it('sends the position once it is known, in a single request', async () => {
            let located;
            stubGeolocation((success) => (located = success));
            const fetch = stubFetch({ body: '' });
            await mount(page(), { filter: Filter });

            change('filters[sortByDistance]', true);
            await settle();
            expect(fetch).not.toHaveBeenCalled();

            located({ coords: { latitude: 48.8566141, longitude: 2.3522219 } });
            await settle();

            expect(fetch).toHaveBeenCalledOnce();
            expect(requested(fetch).searchParams.get('filters[latitude]')).toBe(
                '48.856614'
            );
            expect(
                requested(fetch).searchParams.get('filters[longitude]')
            ).toBe('2.352222');
        });

        it('unchecks the toggle when the position is refused', async () => {
            stubGeolocation((success, failure) => failure());
            stubFetch({ body: '' }, { body: '' });
            await mount(page(), { filter: Filter });

            change('filters[sortByDistance]', true);
            await settle();

            expect(field('filters[sortByDistance]').checked).toBe(false);
            expect(field('filters[latitude]').value).toBe('');
        });

        it('clears the position when switched off', async () => {
            stubGeolocation(() => {});
            const fetch = stubFetch({ body: '' });
            await mount(page(), { filter: Filter });
            field('filters[latitude]').value = '48.856614';
            field('filters[longitude]').value = '2.352222';

            change('filters[sortByDistance]', false);
            await settle();

            expect(field('filters[latitude]').value).toBe('');
            expect(fetch).toHaveBeenCalledOnce();
            expect(requested(fetch).searchParams.has('filters[latitude]')).toBe(
                false
            );
        });

        it('reloads the results once when the position is refused after a while', async () => {
            let refuse;
            stubGeolocation((success, failure) => (refuse = failure));
            const fetch = stubFetch({ body: '' });
            await mount(page(), { filter: Filter });
            change('filters[sortByDistance]', true);
            await settle();
            expect(fetch).not.toHaveBeenCalled();

            refuse();
            await settle();

            expect(fetch).toHaveBeenCalledOnce();
            expect(
                requested(fetch).searchParams.has('filters[sortByDistance]')
            ).toBe(false);
        });

        it('unchecks the toggle in a browser without geolocation', async () => {
            stubGeolocation(undefined);
            stubFetch({ body: '' });
            await mount(page(), { filter: Filter });

            change('filters[sortByDistance]', true);
            await settle();

            expect(field('filters[sortByDistance]').checked).toBe(false);
        });
    });
});
