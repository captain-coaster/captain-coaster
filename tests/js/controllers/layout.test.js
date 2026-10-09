import { describe, expect, it, vi } from 'vitest';
import Alert from '../../../assets/controllers/alert_controller';
import Clamp from '../../../assets/controllers/clamp_controller';
import FilterSheet from '../../../assets/controllers/filter_sheet_controller';
import Navigate from '../../../assets/controllers/navigate_controller';
import PageHeader from '../../../assets/controllers/page_header_controller';
import TabBar from '../../../assets/controllers/tab_bar_controller';
import {
    mount,
    patch,
    stubDialog,
    stubLocation,
    stubObserver,
    stubViewport,
} from '../support/stimulus';

describe('tab-bar', () => {
    function scrollTo(y) {
        patch(window, 'scrollY', y);
        window.dispatchEvent(new Event('scroll'));
    }
    const compact = () =>
        document.querySelector('nav').hasAttribute('data-compact');

    it('shrinks while scrolling down past the top of the page, and comes back on the way up', async () => {
        await mount('<nav data-controller="tab-bar"></nav>', {
            'tab-bar': TabBar,
        });

        scrollTo(40);
        expect(compact()).toBe(false);
        scrollTo(200);
        expect(compact()).toBe(true);
        scrollTo(204);
        expect(compact()).toBe(true);
        scrollTo(150);
        expect(compact()).toBe(false);
    });

    it('ignores the rubber-band overscroll above the page', async () => {
        await mount('<nav data-controller="tab-bar"></nav>', {
            'tab-bar': TabBar,
        });

        scrollTo(-80);

        expect(compact()).toBe(false);
    });
});

describe('page-header', () => {
    it('marks its bar once the title row has scrolled under it', async () => {
        const observers = stubObserver('IntersectionObserver');
        await mount(
            '<header data-controller="page-header"><div id="bar" data-page-header-target="bar"></div><h1 data-page-header-target="row">Taron</h1></header>',
            { 'page-header': PageHeader }
        );
        const bar = document.getElementById('bar');
        expect(observers[0].observed).toEqual([document.querySelector('h1')]);

        observers[0].trigger({
            isIntersecting: false,
            boundingClientRect: { top: -30 },
        });
        expect(bar.hasAttribute('data-scrolled')).toBe(true);

        observers[0].trigger({
            isIntersecting: true,
            boundingClientRect: { top: 10 },
        });
        expect(bar.hasAttribute('data-scrolled')).toBe(false);

        // Out of view below the fold is not "scrolled under"
        observers[0].trigger({
            isIntersecting: false,
            boundingClientRect: { top: 900 },
        });
        expect(bar.hasAttribute('data-scrolled')).toBe(false);
    });

    it('does nothing on a page without a bar', async () => {
        const observers = stubObserver('IntersectionObserver');

        await mount('<header data-controller="page-header"></header>', {
            'page-header': PageHeader,
        });

        expect(observers).toHaveLength(0);
    });
});

describe('clamp', () => {
    const page = `
        <div data-controller="clamp">
            <p data-clamp-target="text" data-action="click->clamp#toggle">A long review</p>
            <button data-clamp-target="toggle" data-action="clamp#toggle" aria-expanded="false">More</button>
        </div>`;
    const text = () => document.querySelector('p');
    const toggle = () => document.querySelector('button');

    async function start() {
        const observers = stubObserver('ResizeObserver');
        await mount(page, { clamp: Clamp });

        return observers[0];
    }

    /** Lays the text out at `scroll` px of content in a `client` px box. */
    function layout(observer, scroll, client) {
        Object.defineProperty(text(), 'scrollHeight', {
            value: scroll,
            configurable: true,
        });
        Object.defineProperty(text(), 'clientHeight', {
            value: client,
            configurable: true,
        });
        observer.trigger();
    }

    it('offers "More" only when the text is cut', async () => {
        const observer = await start();

        layout(observer, 60, 60);
        expect(toggle().hidden).toBe(true);

        layout(observer, 180, 60);
        expect(toggle().hidden).toBe(false);
    });

    it('expands and collapses from the button or the text', async () => {
        const observer = await start();
        layout(observer, 180, 60);

        toggle().click();
        expect(toggle().ariaExpanded).toBe('true');

        // Expanded, the text fits: the button must stay to collapse it
        layout(observer, 180, 180);
        expect(toggle().hidden).toBe(false);

        text().click();
        expect(toggle().ariaExpanded).toBe('false');
    });

    it('does not toggle a text that fits, nor while the rider selects words in it', async () => {
        const observer = await start();
        layout(observer, 60, 60);
        text().click();
        expect(toggle().ariaExpanded).toBe('false');

        layout(observer, 180, 60);
        vi.spyOn(window, 'getSelection').mockReturnValue({
            toString: () => 'long review',
        });
        text().click();
        expect(toggle().ariaExpanded).toBe('false');
    });
});

describe('alert', () => {
    it('removes the flash message on close', async () => {
        await mount(
            '<div id="flash" data-controller="alert"><button data-action="alert#close">×</button></div>',
            { alert: Alert }
        );

        document.querySelector('button').click();

        expect(document.getElementById('flash')).toBeNull();
    });
});

describe('navigate', () => {
    const page = (...urls) =>
        `<select data-controller="navigate" data-action="navigate#go">${urls.map((url) => `<option value="${url}">x</option>`).join('')}</select>`;

    async function choose(url) {
        const location = stubLocation();
        await mount(page('/en/', url), { navigate: Navigate });
        const select = document.querySelector('select');
        select.value = url;
        select.dispatchEvent(new Event('change', { bubbles: true }));

        return location.assign;
    }

    it('goes to the chosen page', async () => {
        expect(await choose('/fr/ranking/?page=2')).toHaveBeenCalledWith(
            '/fr/ranking/?page=2'
        );
    });

    it.each([
        ['https://evil.example/'],
        ['//evil.example/'],
        ['javascript:alert(1)'],
    ])('never leaves the site for %s', async (url) => {
        expect(await choose(url)).not.toHaveBeenCalled();
    });
});

describe('filter-sheet', () => {
    const page = `
        <div data-controller="filter-sheet">
            <button id="open" data-action="filter-sheet#open">Filters</button>
            <dialog data-filter-sheet-target="dialog" data-action="click->filter-sheet#backdropClose"><form id="content"></form></dialog>
        </div>`;
    const dialog = () => document.querySelector('dialog');

    async function opened() {
        stubDialog();
        await mount(page, { 'filter-sheet': FilterSheet });
        document.getElementById('open').click();
    }

    it('opens as a sheet and closes on its backdrop, not on its content', async () => {
        await opened();
        expect(dialog().open).toBe(true);

        document.getElementById('content').click();
        expect(dialog().open).toBe(true);

        dialog().click();
        expect(dialog().open).toBe(false);
    });

    it('closes when the window grows to the width where the filters sit in the page', async () => {
        await opened();

        stubViewport({ tablet: false });
        window.dispatchEvent(new Event('resize'));
        expect(dialog().open).toBe(true);

        stubViewport({ tablet: true });
        window.dispatchEvent(new Event('resize'));
        expect(dialog().open).toBe(false);
    });
});
