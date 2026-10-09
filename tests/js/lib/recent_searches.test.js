import { describe, expect, it, vi } from 'vitest';
import RecentSearches from '../../../assets/controllers/recent_searches_controller';
import {
    clearRecentSearches,
    getRecentSearches,
    rememberRecentSearch,
} from '../../../assets/js/recent-searches';
import { jsIcons, mount } from '../support/stimulus';

const pick = (n) => ({
    name: `Coaster ${n}`,
    url: `/en/coasters/${n}`,
    type: 'coaster',
});

describe('recent searches', () => {
    it('keeps the last five picks, most recent first', () => {
        for (let n = 1; n <= 6; n++) rememberRecentSearch(pick(n));

        expect(getRecentSearches().map((item) => item.name)).toEqual([
            'Coaster 6',
            'Coaster 5',
            'Coaster 4',
            'Coaster 3',
            'Coaster 2',
        ]);
    });

    it('moves a pick made again to the top instead of listing it twice', () => {
        [1, 2, 3, 1].forEach((n) => rememberRecentSearch(pick(n)));

        expect(getRecentSearches().map((item) => item.url)).toEqual([
            '/en/coasters/1',
            '/en/coasters/3',
            '/en/coasters/2',
        ]);
    });

    it('ignores a pick without a name or a URL', () => {
        rememberRecentSearch({ name: '', url: '/en/coasters/1' });
        rememberRecentSearch({ name: 'Taron' });

        expect(getRecentSearches()).toEqual([]);
    });

    it.each([
        ['is not JSON', 'not json'],
        ['is not a list', '{"name":"x"}'],
    ])('reads nothing when what is stored %s', (_, stored) => {
        localStorage.setItem('cc.recentSearches', stored);

        expect(getRecentSearches()).toEqual([]);
    });

    it('works without storage (private mode)', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('denied');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('denied');
        });

        expect(() => rememberRecentSearch(pick(1))).not.toThrow();
        expect(getRecentSearches()).toEqual([]);
    });

    it('clears', () => {
        rememberRecentSearch(pick(1));
        clearRecentSearches();

        expect(getRecentSearches()).toEqual([]);
    });
});

describe('recent-searches controller', () => {
    const page = `${jsIcons('coaster', 'park')}
        <section data-controller="recent-searches">
            <button data-action="recent-searches#clear">Clear</button>
            <ul data-recent-searches-target="list"></ul>
            <template data-recent-searches-target="item"><li><a><span></span><span></span></a></li></template>
        </section>`;
    const links = () => [...document.querySelectorAll('ul a')];

    it('stays hidden with nothing to show', async () => {
        await mount(page, { 'recent-searches': RecentSearches });

        expect(document.querySelector('section').hidden).toBe(true);
    });

    it('lists the picks with their type icon', async () => {
        rememberRecentSearch({
            name: 'Phantasialand',
            url: '/en/parks/9',
            type: 'park',
        });
        rememberRecentSearch(pick(1));
        await mount(page, { 'recent-searches': RecentSearches });

        expect(document.querySelector('section').hidden).toBe(false);
        expect(
            links().map((link) => [link.getAttribute('href'), link.textContent])
        ).toEqual([
            ['/en/coasters/1', 'Coaster 1'],
            ['/en/parks/9', 'Phantasialand'],
        ]);
        expect(links()[1].querySelector('svg').dataset.name).toBe('park');
    });

    it('drops stored entries that are not same-site paths or carry markup', async () => {
        localStorage.setItem(
            'cc.recentSearches',
            JSON.stringify([
                { name: 'Evil', url: 'javascript:alert(1)', type: 'coaster' },
                { name: 'Elsewhere', url: '//evil.example/x', type: 'coaster' },
                {
                    name: 'Absolute',
                    url: 'https://evil.example/x',
                    type: 'coaster',
                },
                { name: 'No URL', url: 7, type: 'coaster' },
                {
                    name: '<img src=x onerror=alert(1)>',
                    url: '/en/coasters/2',
                    type: 'coaster',
                },
            ])
        );
        await mount(page, { 'recent-searches': RecentSearches });

        expect(links().map((link) => link.getAttribute('href'))).toEqual([
            '/en/coasters/2',
        ]);
        expect(document.querySelector('ul img')).toBeNull();
        expect(links()[0].textContent).toBe('<img src=x onerror=alert(1)>');
    });

    it('empties the list and hides on clear', async () => {
        rememberRecentSearch(pick(1));
        await mount(page, { 'recent-searches': RecentSearches });

        document.querySelector('button').click();

        expect(links()).toEqual([]);
        expect(document.querySelector('section').hidden).toBe(true);
        expect(getRecentSearches()).toEqual([]);
    });
});
