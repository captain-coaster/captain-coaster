import { afterEach, describe, expect, it } from 'vitest';
import Theme from '../../../assets/controllers/theme_controller';
import { mount, patch, unmount } from '../support/stimulus';

const page =
    '<button type="button" role="switch" aria-checked="false" data-controller="theme" data-action="theme#toggle">Dark theme</button>';

const theme = () => document.documentElement.dataset.theme;
const toggle = () => document.querySelector('button');

afterEach(() => {
    window.localStorage.clear();
});

describe('theme', () => {
    it('starts light and turns the page dark on a click', async () => {
        await mount(page, { theme: Theme });
        expect(theme()).toBeUndefined();
        expect(toggle().getAttribute('aria-checked')).toBe('false');

        toggle().click();

        expect(theme()).toBe('dark');
        expect(toggle().getAttribute('aria-checked')).toBe('true');
    });

    it('goes back to light on a second click', async () => {
        await mount(page, { theme: Theme });
        toggle().click();
        toggle().click();

        expect(theme()).toBeUndefined();
        expect(toggle().getAttribute('aria-checked')).toBe('false');
    });

    it('remembers the choice on the next visit', async () => {
        await mount(page, { theme: Theme });
        toggle().click();
        await unmount();

        await mount(page, { theme: Theme });

        expect(theme()).toBe('dark');
        expect(toggle().getAttribute('aria-checked')).toBe('true');
    });

    it('leaves the page light once the switch is gone', async () => {
        await mount(page, { theme: Theme });
        toggle().click();

        await unmount();

        expect(theme()).toBeUndefined();
    });

    it('still switches when storage is blocked', async () => {
        patch(window, 'localStorage', {
            getItem() {
                throw new Error('blocked');
            },
            setItem() {
                throw new Error('blocked');
            },
            clear() {},
        });
        await mount(page, { theme: Theme });

        toggle().click();

        expect(theme()).toBe('dark');
    });
});
