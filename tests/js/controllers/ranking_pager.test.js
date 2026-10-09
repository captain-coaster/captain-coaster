import { describe, expect, it, vi } from 'vitest';
import RankingPager from '../../../assets/controllers/ranking_pager_controller';
import { mount, settle, stubFetch, stubLocation } from '../support/stimulus';

const pager = (page) =>
    `<nav data-controller="ranking-pager" data-ranking-pager-endpoint-value="/en/ranking/coasters">
        <a href="https://captaincoaster.test/en/ranking/?filters%5Bcountry%5D=18&page=${page}" data-action="ranking-pager#more">More</a>
    </nav>`;

const list = (rows, after = '') =>
    `<section><ol data-ranking-rows><li aria-hidden="true">Rank</li>${rows.map((rank) => `<li><a href="/c/${rank}">#${rank}</a></li>`).join('')}</ol>${after}</section>`;

const ranks = () =>
    [
        ...document.querySelectorAll(
            '[data-ranking-rows] > li:not([aria-hidden])'
        ),
    ].map((row) => row.textContent);

/** Reduced motion, as jsdom has no Element.animate(); returns location.assign. */
function browser() {
    vi.stubGlobal('matchMedia', () => ({ matches: true }));

    return stubLocation().assign;
}

describe('ranking-pager', () => {
    it('appends the next page rows, keeps one heading and swaps in the next pager', async () => {
        browser();
        const fetch = stubFetch({ body: list([3, 4], pager(3)) });
        await mount(list([1, 2], pager(2)), { 'ranking-pager': RankingPager });

        document.querySelector('nav a').click();
        await settle();

        expect(fetch.mock.calls[0][0]).toBe(
            '/en/ranking/coasters?filters%5Bcountry%5D=18&page=2'
        );
        expect(ranks()).toEqual(['#1', '#2', '#3', '#4']);
        expect(
            document.querySelectorAll('[data-ranking-rows] > li[aria-hidden]')
        ).toHaveLength(1);
        expect(document.querySelector('nav a').href).toContain('page=3');
        expect(document.activeElement.textContent).toBe('#3');
    });

    it('puts the loaded page in the address bar, so a refresh lands on the same rows', async () => {
        browser();
        stubFetch({ body: list([3, 4], pager(3)) });
        const replaceState = vi.spyOn(history, 'replaceState');
        await mount(list([1, 2], pager(2)), { 'ranking-pager': RankingPager });

        document.querySelector('nav a').click();
        await settle();

        expect(replaceState).toHaveBeenCalledWith(
            null,
            '',
            '/en/ranking/?filters%5Bcountry%5D=18&page=2'
        );
    });

    it('removes the pager on the last page', async () => {
        browser();
        stubFetch({ body: list([3]) });
        await mount(list([1, 2], pager(2)), { 'ranking-pager': RankingPager });

        document.querySelector('nav a').click();
        await settle();

        expect(ranks()).toEqual(['#1', '#2', '#3']);
        expect(document.querySelector('nav')).toBeNull();
    });

    it('loads a page once when the link is clicked twice', async () => {
        browser();
        const fetch = stubFetch(
            { body: list([3, 4], pager(3)) },
            { body: list([3, 4], pager(3)) }
        );
        await mount(list([1, 2], pager(2)), { 'ranking-pager': RankingPager });
        const link = document.querySelector('nav a');

        link.click();
        link.click();
        await settle();

        expect(fetch).toHaveBeenCalledOnce();
        expect(ranks()).toEqual(['#1', '#2', '#3', '#4']);
    });

    it('falls back to a plain navigation when the request fails', async () => {
        const assign = browser();
        stubFetch({ status: 500 });
        await mount(list([1, 2], pager(2)), { 'ranking-pager': RankingPager });

        document.querySelector('nav a').click();
        await settle();

        expect(assign).toHaveBeenCalledWith(
            'https://captaincoaster.test/en/ranking/?filters%5Bcountry%5D=18&page=2'
        );
        expect(ranks()).toEqual(['#1', '#2']);
    });
});
