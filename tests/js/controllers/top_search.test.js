import { Controller } from '@hotwired/stimulus';
import { describe, expect, it, vi } from 'vitest';
import TopSearch from '../../../assets/controllers/top_search_controller';
import { jsIcons, mount, patch, settle, stubFetch } from '../support/stimulus';

const list = { updatePositions: vi.fn(), debouncedSave: vi.fn() };
class TopList extends Controller {
    updatePositions = list.updatePositions;
    debouncedSave = list.debouncedSave;
}

const entry = (id, name) => `
    <li data-top-list-target="item" data-coaster-id="${id}" data-position="1">
        <span class="position-number">1</span>
        <div class="coaster-content"><div class="coaster-main"><span class="coaster-name">${name}</span><span class="coaster-park">Park</span></div>
        <span class="coaster-rating">old stars</span></div>
    </li>`;

const page = (entries = '') => `${jsIcons('search')}
    <div data-controller="top-search" data-top-search-url-value="/en/tops/search/coasters.json" data-top-search-debounce-delay-value="0">
        <input data-top-search-target="input" data-action="input->top-search#search keydown->top-search#handleKeydown">
        <div data-top-search-target="dropdown"><div data-top-search-target="results"></div></div>
    </div>
    <ul data-controller="top-list">${entries}</ul>
    <template id="coaster-item-template">${entry('', '')}</template>`;

const found = [
    { id: 12, coaster: 'Taron', park: 'Phantasialand', rating: '4.5' },
    { id: 7, coaster: 'Taiga', park: 'Linnanmäki', rating: null },
];

const input = () => document.querySelector('input');
const results = () => [...document.querySelectorAll('.search-result-item')];
const entries = () => [...document.querySelectorAll('ul > li')];

async function search(text = 'ta') {
    input().value = text;
    input().dispatchEvent(new Event('input', { bubbles: true }));
    await settle();
}

async function start(existing) {
    patch(Element.prototype, 'scrollIntoView', vi.fn());
    list.updatePositions.mockClear();
    list.debouncedSave.mockClear();
    await mount(page(existing), {
        'top-search': TopSearch,
        'top-list': TopList,
    });
}

describe('top-search', () => {
    it('lists the coasters found with their park and the rider rating', async () => {
        const fetch = stubFetch({ body: { items: found } });
        await start(entry(1, 'Helix'));

        await search();

        const url = new URL(fetch.mock.calls[0][0]);
        expect(url.pathname + url.search).toBe(
            '/en/tops/search/coasters.json?q=ta'
        );
        expect(
            results().map((item) =>
                item.textContent.replace(/\s+/g, ' ').trim()
            )
        ).toEqual(['🎢 Taron Phantasialand 4.5', '🎢 Taiga Linnanmäki N/A']);
    });

    it('adds the picked coaster at the end of the Top and saves', async () => {
        stubFetch({ body: { items: found } });
        await start(entry(1, 'Helix'));
        await search();

        results()[0].click();

        const added = entries()[1];
        expect(added.dataset.coasterId).toBe('12');
        expect(added.querySelector('.position-number').textContent).toBe('2');
        expect(added.querySelector('.coaster-name').textContent).toBe('Taron');
        expect(added.querySelector('.coaster-park').textContent).toBe(
            'Phantasialand'
        );
        expect(
            added
                .querySelector('.coaster-rating [aria-label]')
                .getAttribute('aria-label')
        ).toBe('4.5/5');
        expect(entries()[0].querySelector('.coaster-name').textContent).toBe(
            'Helix'
        );
        expect(list.updatePositions).toHaveBeenCalledOnce();
        expect(list.debouncedSave).toHaveBeenCalledOnce();
        expect(input().value).toBe('');
        expect(
            document
                .querySelector('[data-top-search-target="dropdown"]')
                .classList.contains('show')
        ).toBe(false);
    });

    it('adds a coaster the rider has not rated without stars', async () => {
        stubFetch({ body: { items: found } });
        await start(entry(1, 'Helix'));
        await search();

        results()[1].click();

        expect(entries()[1].querySelector('.coaster-name').textContent).toBe(
            'Taiga'
        );
        expect(entries()[1].querySelector('.coaster-rating')).toBeNull();
    });

    it('starts an empty Top from the row template', async () => {
        stubFetch({ body: { items: found } });
        await start('');
        await search();

        results()[0].click();

        expect(entries()).toHaveLength(1);
        expect(entries()[0].dataset.coasterId).toBe('12');
        expect(entries()[0].querySelector('.coaster-name').textContent).toBe(
            'Taron'
        );
        expect(entries()[0].querySelector('.position-number').textContent).toBe(
            '1'
        );
        expect(list.debouncedSave).toHaveBeenCalledOnce();
    });

    it('marks a coaster already in the Top and never adds it twice', async () => {
        stubFetch({ body: { items: found } });
        await start(entry(12, 'Taron'));
        await search();

        expect(
            results().map((item) =>
                item.classList.contains('search-result-duplicate')
            )
        ).toEqual([true, false]);

        results()[0].click();
        input().dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true })
        );
        expect(
            document.querySelector('.selected .search-result-name').textContent
        ).toBe('Taiga');

        expect(entries()).toHaveLength(1);
        expect(list.debouncedSave).not.toHaveBeenCalled();
    });

    it('adds the coaster selected from the keyboard', async () => {
        stubFetch({ body: { items: found } });
        await start(entry(1, 'Helix'));
        await search();

        for (const key of ['ArrowDown', 'ArrowDown', 'Enter']) {
            input().dispatchEvent(
                new KeyboardEvent('keydown', { key, bubbles: true })
            );
        }

        expect(entries().map((row) => row.dataset.coasterId)).toEqual([
            '1',
            '7',
        ]);
    });

    it('shows coaster names as text, never as markup', async () => {
        stubFetch({
            body: {
                items: [
                    {
                        id: 5,
                        coaster: '<img src=x onerror=alert(1)>',
                        park: '"><b>x</b>',
                        rating: null,
                    },
                ],
            },
        });
        await start(entry(1, 'Helix'));
        await search('img');

        results()[0].click();

        expect(
            document.querySelector(
                '.search-result-item img, .search-result-item b, ul img, ul b'
            )
        ).toBeNull();
        expect(entries()[1].querySelector('.coaster-name').textContent).toBe(
            '<img src=x onerror=alert(1)>'
        );
        expect(entries()[1].querySelector('.coaster-park').textContent).toBe(
            '"><b>x</b>'
        );
    });

    it('says when no coaster matches', async () => {
        stubFetch({ body: { items: [] } });
        await start(entry(1, 'Helix'));

        await search();

        expect(
            document.querySelector('.search-no-results-text').textContent
        ).toBe('search.no_results');
    });
});
