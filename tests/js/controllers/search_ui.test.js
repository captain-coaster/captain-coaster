import { describe, expect, it, vi } from 'vitest';
import SearchDialog from '../../../assets/controllers/search_dialog_controller';
import SearchResults from '../../../assets/controllers/search_results_controller';
import SearchShortcut from '../../../assets/controllers/search_shortcut_controller';
import { mount, stubDialog, stubLocation } from '../support/stimulus';

describe('search-dialog', () => {
    const page = `
        <div data-controller="search-dialog">
            <button data-action="search-dialog#open">Search</button>
            <dialog data-search-dialog-target="dialog" data-action="close->search-dialog#closed"><input type="search"></dialog>
        </div>`;

    it('opens on the search field, with fresh recent searches', async () => {
        stubDialog();
        await mount(page, { 'search-dialog': SearchDialog });
        const refreshed = vi.fn();
        window.addEventListener('recent-searches:refresh', refreshed, {
            once: true,
        });

        document.querySelector('button').click();

        expect(document.querySelector('dialog').open).toBe(true);
        expect(document.activeElement).toBe(document.querySelector('input'));
        expect(refreshed).toHaveBeenCalledOnce();
    });

    it('empties the field on close, and tells the search so', async () => {
        stubDialog();
        await mount(page, { 'search-dialog': SearchDialog });
        const input = document.querySelector('input');
        const typed = vi.fn();
        input.addEventListener('input', typed);
        document.querySelector('button').click();
        input.value = 'taron';

        document.querySelector('dialog').close();

        expect(input.value).toBe('');
        expect(typed).toHaveBeenCalledOnce();
    });
});

describe('search-shortcut', () => {
    const page = `
        <div data-controller="search-shortcut" data-action="keydown@window->search-shortcut#focus">
            <input id="search" data-search-shortcut-target="input" value="old">
        </div>
        <textarea id="review"></textarea><p id="text" tabindex="0">text</p>`;

    async function press(from, init) {
        await mount(page, { 'search-shortcut': SearchShortcut });
        const event = new KeyboardEvent('keydown', {
            bubbles: true,
            cancelable: true,
            ...init,
        });
        document.getElementById(from).dispatchEvent(event);

        return {
            focused: document.activeElement.id === 'search',
            prevented: event.defaultPrevented,
        };
    }

    it.each([
        ['/', { key: '/' }],
        ['Ctrl+K', { key: 'k', ctrlKey: true }],
        ['Cmd+K', { key: 'K', metaKey: true }],
    ])('focuses the search field on %s', async (_, init) => {
        expect(await press('text', init)).toEqual({
            focused: true,
            prevented: true,
        });
    });

    it('lets a rider type a slash in a review', async () => {
        expect(await press('review', { key: '/' })).toEqual({
            focused: false,
            prevented: false,
        });
    });

    it('still answers Ctrl+K from a field', async () => {
        expect(await press('review', { key: 'k', ctrlKey: true })).toEqual({
            focused: true,
            prevented: true,
        });
    });

    it('ignores other keys', async () => {
        expect(await press('text', { key: 'k' })).toEqual({
            focused: false,
            prevented: false,
        });
    });
});

describe('search-results', () => {
    const item = (type, id, slug) =>
        `<li id="${slug}" data-search-results-target="resultItem" data-action="click->search-results#selectResult" data-type="${type}" data-id="${id}" data-slug="${slug}"><span>${slug}</span></li>`;
    const page = `<ul data-controller="search-results">${item('coaster', 12, 'taron')}${item('park', 9, 'phantasialand')}${item('user', 3, 'tara')}</ul>`;
    const key = (name) =>
        document.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: name,
                bubbles: true,
                cancelable: true,
            })
        );
    const highlighted = () =>
        document.querySelector('.search-result-item-keyboard-selected')?.id;

    async function start() {
        Element.prototype.scrollIntoView = vi.fn();
        document.documentElement.lang = 'de';
        const location = stubLocation();
        await mount(page, { 'search-results': SearchResults });
        vi.useFakeTimers();

        return location;
    }

    it.each([
        [
            'coaster',
            'taron',
            ['show_coaster', { slug: 'taron', id: '12', _locale: 'de' }],
        ],
        [
            'park',
            'phantasialand',
            ['park_show', { slug: 'phantasialand', id: '9', _locale: 'de' }],
        ],
        [
            'rider',
            'tara',
            ['user_show', { slug: 'tara', id: '3', _locale: 'de' }],
        ],
    ])('opens a %s in the page language', async (_, slug, route) => {
        const location = await start();

        document.querySelector(`#${slug} span`).click();
        vi.advanceTimersByTime(100);

        expect(Routing.generate).toHaveBeenCalledWith(...route);
        expect(location.href).toBe(Routing.generate.mock.results[0].value);
    });

    it('is driven from the keyboard', async () => {
        const location = await start();

        key('ArrowDown');
        key('ArrowDown');
        expect(highlighted()).toBe('phantasialand');
        key('ArrowUp');
        expect(highlighted()).toBe('taron');
        key('Escape');
        expect(highlighted()).toBeUndefined();

        key('ArrowDown');
        key('Enter');
        vi.advanceTimersByTime(100);
        expect(location.href).toBe('/de/show_coaster/taron/12');
    });

    it('stops at the last result', async () => {
        await start();

        for (let i = 0; i < 6; i++) key('ArrowDown');

        expect(highlighted()).toBe('tara');
    });

    it('stops answering the keyboard once it leaves the page', async () => {
        await start();
        const list = document.querySelector('ul');
        vi.useRealTimers();
        list.remove();
        await new Promise((resolve) => setTimeout(resolve, 0));
        document.body.append(list);

        key('ArrowDown');

        expect(
            list.querySelector('.search-result-item-keyboard-selected')
        ).toBeNull();
    });
});
