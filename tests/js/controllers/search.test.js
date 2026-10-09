import { describe, expect, it, vi } from 'vitest';
import Search from '../../../assets/controllers/search_controller';
import { getRecentSearches } from '../../../assets/js/recent-searches';
import {
    jsIcons,
    mount,
    settle,
    stubFetch,
    stubLocation,
} from '../support/stimulus';

const page = ({
    debounce = 0,
} = {}) => `${jsIcons('coaster', 'park', 'user', 'arrow-right', 'warning')}
    <button id="outside">Elsewhere</button>
    <div id="search" data-controller="search" data-search-search-url-value="/en/search/suggest"
         data-search-debounce-delay-value="${debounce}">
        <input data-search-target="input" data-action="input->search#handleInput keydown->search#handleKeydown focus->search#handleFocus">
        <button id="clear" data-action="search#clearSearch">Clear</button>
        <div data-search-target="dropdown"><div data-search-target="results"></div></div>
        <template data-search-empty>
            <p>Nothing for <q data-search-empty-query></q> <a data-search-empty-report href="/en/contact?topic=missing">Tell us</a></p>
        </template>
    </div>`;

const answer = (results, { hasMore = false, query = 'tar' } = {}) => ({
    body: { results, hasMore, query },
});
const taron = {
    id: 12,
    slug: 'taron',
    name: 'Taron',
    subtitle: 'Phantasialand',
};

const input = () => document.querySelector('input');
const dropdown = () =>
    document.querySelector('[data-search-target="dropdown"]');
const names = () =>
    [...document.querySelectorAll('.search-result-name')].map(
        (name) => name.textContent
    );
const key = (name) =>
    input().dispatchEvent(
        new KeyboardEvent('keydown', {
            key: name,
            bubbles: true,
            cancelable: true,
        })
    );

async function type(text) {
    input().value = text;
    input().dispatchEvent(new Event('input', { bubbles: true }));
    await settle();
}

async function start(options) {
    Element.prototype.scrollIntoView = vi.fn();
    await mount(page(options), { search: Search });
}

describe('search', () => {
    it('suggests coasters, parks and riders for what is typed', async () => {
        const fetch = stubFetch(
            answer({
                coasters: [taron],
                parks: [{ id: 9, slug: 'taronga', name: 'Taronga Zoo' }],
                users: [{ id: 3, slug: 'tara', name: 'Tara' }],
            })
        );
        await start();

        await type('tar');

        const url = new URL(fetch.mock.calls[0][0]);
        expect(url.pathname).toBe('/en/search/suggest');
        expect(Object.fromEntries(url.searchParams)).toEqual({
            q: 'tar',
            limit: '5',
        });
        expect(names()).toEqual(['Taron', 'Taronga Zoo', 'Tara']);
        expect(
            [...document.querySelectorAll('.search-result-item')].map(
                (item) => item.dataset.type
            )
        ).toEqual(['coaster', 'park', 'user']);
        expect(
            document.querySelector('.search-result-icon svg').dataset.name
        ).toBe('coaster');
        expect(dropdown().classList.contains('show')).toBe(true);
        expect(input().getAttribute('aria-expanded')).toBe('true');
    });

    it('highlights the match, accents ignored', async () => {
        stubFetch(
            answer(
                {
                    coasters: [
                        {
                            id: 1,
                            slug: 'x',
                            name: 'Kärnan',
                            subtitle: 'Hansa-Park',
                        },
                    ],
                },
                { query: 'karn' }
            )
        );
        await start();

        await type('karn');

        expect(document.querySelector('.search-result-name').innerHTML).toBe(
            '<strong>Kärn</strong>an'
        );
    });

    it.each([
        [
            'every occurrence',
            'Tarantula Taron',
            'tar',
            '<strong>Tar</strong>antula <strong>Tar</strong>on',
        ],
        [
            'a name with a quote, searched by a word found in its entity',
            'Der "Quote"',
            'quot',
            'Der &quot;<strong>Quot</strong>e&quot;',
        ],
        [
            'a name with an ampersand',
            'Tom &amp; Jerry'.replace('&amp;', '&'),
            'amp',
            'Tom &amp; Jerry',
        ],
    ])('highlights %s', async (_, name, query, html) => {
        stubFetch(
            answer({ coasters: [{ id: 1, slug: 'x', name }] }, { query })
        );
        await start();

        await type(query);

        expect(
            document
                .querySelector('.search-result-name')
                .innerHTML.replace(/"/g, '&quot;')
        ).toBe(html);
    });

    it('shows names as text, never as markup', async () => {
        stubFetch(
            answer({
                coasters: [
                    {
                        id: 1,
                        slug: '"><b>',
                        name: '<img src=x onerror=alert(1)>',
                        subtitle: '<script>x</script>',
                    },
                ],
            })
        );
        await start();

        await type('img');

        expect(
            document.querySelector(
                '.search-result-item img, .search-result-item script, .search-result-item b'
            )
        ).toBeNull();
        expect(names()).toEqual(['<img src=x onerror=alert(1)>']);
        expect(document.querySelector('.search-result-item').dataset.slug).toBe(
            '"><b>'
        );
    });

    it('lists five suggestions at most, then a link to all results', async () => {
        const coasters = Array.from({ length: 7 }, (_, n) => ({
            id: n,
            slug: `c${n}`,
            name: `Coaster ${n}`,
        }));
        stubFetch(answer({ coasters }, { query: 'coa' }));
        const location = stubLocation();
        await start();

        await type('coa');
        expect(names()).toHaveLength(5);

        document.querySelector('.search-show-more').click();

        expect(Routing.generate).toHaveBeenCalledWith('search_index', {
            query: 'coa',
            _locale: 'en',
        });
        expect(location.href).toBe('/en/search_index/coa');
    });

    it('offers all results when the server has more', async () => {
        stubFetch(answer({ coasters: [taron] }, { hasMore: true }));
        await start();

        await type('tar');

        expect(document.querySelector('.search-show-more')).not.toBeNull();
    });

    it('says nothing was found, with a report link carrying the query', async () => {
        stubFetch(answer({ coasters: [], parks: [], users: [] }));
        await start();

        await type('tar');

        expect(
            document.querySelector('[data-search-empty-query]').textContent
        ).toBe('tar');
        expect(
            document
                .querySelector('[data-search-empty-report]')
                .getAttribute('href')
        ).toBe('/en/contact?topic=missing&q=tar');
    });

    it('opens the picked result and remembers it', async () => {
        stubFetch(answer({ coasters: [taron] }));
        const location = stubLocation();
        await start();
        await type('tar');

        document.querySelector('.search-result-item').click();

        expect(Routing.generate).toHaveBeenCalledWith('show_coaster', {
            slug: 'taron',
            id: '12',
            _locale: 'en',
        });
        expect(location.href).toBe('/en/show_coaster/taron/12');
        expect(getRecentSearches()).toEqual([
            {
                name: 'Taron',
                type: 'coaster',
                url: '/en/show_coaster/taron/12',
            },
        ]);
    });

    it('is driven from the keyboard', async () => {
        stubFetch(
            answer({
                coasters: [taron],
                parks: [{ id: 9, slug: 'taronga', name: 'Taronga Zoo' }],
            })
        );
        const location = stubLocation();
        await start();
        await type('tar');
        const selected = () =>
            document.querySelector('.selected .search-result-name')
                ?.textContent;

        key('ArrowDown');
        expect(selected()).toBe('Taron');
        key('ArrowDown');
        key('ArrowDown');
        expect(selected()).toBe('Taronga Zoo');
        key('ArrowUp');
        expect(selected()).toBe('Taron');

        key('Enter');
        expect(location.href).toBe('/en/show_coaster/taron/12');
    });

    it('goes to all results on Enter with no suggestion selected', async () => {
        stubFetch(answer({ coasters: [taron] }));
        const location = stubLocation();
        await start();
        await type('tar');

        key('Enter');

        expect(location.href).toBe('/en/search_index/tar');
    });

    it.each([
        ['Escape', () => key('Escape')],
        ['a click elsewhere', () => document.getElementById('outside').click()],
    ])('closes on %s', async (_, close) => {
        stubFetch(answer({ coasters: [taron] }));
        await start();
        await type('tar');

        close();

        expect(dropdown().classList.contains('show')).toBe(false);
        expect(input().getAttribute('aria-expanded')).toBe('false');
    });

    it('waits for two characters and for typing to pause', async () => {
        const fetch = stubFetch(answer({ coasters: [taron] }));
        await start({ debounce: 300 });
        vi.useFakeTimers();
        const typeNow = (text) => {
            input().value = text;
            input().dispatchEvent(new Event('input', { bubbles: true }));
        };

        typeNow('t');
        await vi.advanceTimersByTimeAsync(1000);
        expect(fetch).not.toHaveBeenCalled();

        typeNow('ta');
        await vi.advanceTimersByTimeAsync(200);
        typeNow('tar');
        await vi.advanceTimersByTimeAsync(299);
        expect(fetch).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(fetch).toHaveBeenCalledOnce();
        expect(new URL(fetch.mock.calls[0][0]).searchParams.get('q')).toBe(
            'tar'
        );
    });

    it('drops the answer to a query that was typed over', async () => {
        const answers = [];
        const fetch = vi.fn(
            (url, { signal }) =>
                new Promise((resolve, reject) => {
                    signal.addEventListener('abort', () =>
                        reject(new DOMException('Aborted', 'AbortError'))
                    );
                    answers.push((results) =>
                        resolve(
                            new Response(
                                JSON.stringify({ results, query: 'x' })
                            )
                        )
                    );
                })
        );
        vi.stubGlobal('fetch', fetch);
        await start();

        await type('tar');
        await type('taro');
        answers[1]({ coasters: [taron] });
        await settle();

        expect(fetch.mock.calls[0][1].signal.aborted).toBe(true);
        expect(names()).toEqual(['Taron']);
        expect(document.querySelector('.search-error')).toBeNull();
    });

    it.each([
        ['the server fails', { status: 500 }],
        [
            'the server answers with an error',
            { body: { error: true, message: 'Too many requests' } },
        ],
    ])('says the search failed when %s', async (_, response) => {
        stubFetch(response);
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await start();

        await type('tar');

        expect(document.querySelector('.search-error-text').textContent).toBe(
            'search_index.error'
        );
    });

    it('shows its clear button only with something typed, and empties the field with it', async () => {
        stubFetch(answer({ coasters: [taron] }));
        await start();
        const search = document.getElementById('search');
        expect(search.hasAttribute('data-has-content')).toBe(false);

        await type('tar');
        expect(search.hasAttribute('data-has-content')).toBe(true);

        document.getElementById('clear').click();

        expect(input().value).toBe('');
        expect(search.hasAttribute('data-has-content')).toBe(false);
        expect(dropdown().classList.contains('show')).toBe(false);
        expect(document.activeElement).toBe(input());
    });
});
