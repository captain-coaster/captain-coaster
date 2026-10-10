import { describe, expect, it, vi } from 'vitest';
import Scrollspy from '../../../assets/controllers/scrollspy_controller';
import { mount } from '../support/stimulus';

const page = `
    <ul data-controller="scrollspy">
        <li><a href="#colors" data-scrollspy-target="link">Colors</a></li>
        <li><a href="#motion" data-scrollspy-target="link">Motion</a></li>
    </ul>
    <section><h2 id="colors">Colors</h2></section>
    <section><h2 id="motion">Motion</h2></section>`;

const current = () => document.querySelector('[aria-current]')?.textContent;

/** Stands in for the browser's observer: `reach(id)` says that section is now being read. */
function observer() {
    let read;
    const observed = [];
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback) {
                read = callback;
            }
            observe(element) {
                observed.push(element.querySelector('h2').id);
            }
            disconnect() {}
        }
    );
    return {
        observed,
        reach: (id, isIntersecting = true) =>
            read([
                {
                    target: document.getElementById(id).closest('section'),
                    isIntersecting,
                },
            ]),
    };
}

describe('scrollspy', () => {
    it('marks no link before a section is reached', async () => {
        const { observed } = observer();
        await mount(page, { scrollspy: Scrollspy });

        expect(observed).toEqual(['colors', 'motion']);
        expect(current()).toBeUndefined();
    });

    it('marks the link of the section being read, one at a time', async () => {
        const { reach } = observer();
        await mount(page, { scrollspy: Scrollspy });

        reach('colors');
        expect(current()).toBe('Colors');

        reach('motion');
        expect(current()).toBe('Motion');
        expect(document.querySelectorAll('[aria-current]')).toHaveLength(1);
    });

    it('keeps the last link while a section leaves and none has arrived', async () => {
        const { reach } = observer();
        await mount(page, { scrollspy: Scrollspy });

        reach('colors');
        reach('colors', false);

        expect(current()).toBe('Colors');
    });
});
