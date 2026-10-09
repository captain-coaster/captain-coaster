import { beforeEach, describe, expect, it, vi } from 'vitest';
import TopList from '../../../assets/controllers/top_list_controller';
import { jsIcons, mount, settle, stubFetch } from '../support/stimulus';

// Dragging itself is SortableJS's job: the tests drive its callbacks.
const sortable = vi.hoisted(() => ({ options: null, destroy: null }));
vi.mock('sortablejs', () => ({
    default: {
        create: (element, options) => {
            sortable.options = options;

            return { destroy: sortable.destroy };
        },
    },
}));

const item = (id) => `
    <li data-top-list-target="item" data-coaster-id="${id}">
        <span class="position-number"></span>
        <a href="#" class="top" data-action="click->top-list#moveToTop">Top</a>
        <a href="#" class="bottom" data-action="click->top-list#moveToBottom">Bottom</a>
        <a href="#" class="position" data-action="click->top-list#moveToPosition">Position</a>
        <a href="#" class="remove" data-action="click->top-list#removeCoaster">Remove</a>
    </li>`;

const page = (
    ids = [10, 20, 30, 40]
) => `${jsIcons('check', 'warning', 'loading')}
    <ul data-controller="top-list" data-top-list-auto-save-url-value="/en/tops/12/auto-save" data-top-list-save-delay-value="2000">
        ${ids.map(item).join('')}
    </ul>`;

const order = () =>
    [...document.querySelectorAll('li')].map((row) =>
        Number(row.dataset.coasterId)
    );
const numbers = () =>
    [...document.querySelectorAll('.position-number')].map(
        (number) => number.textContent
    );
const press = (coaster, button) =>
    document.querySelector(`[data-coaster-id="${coaster}"] .${button}`).click();
const saved = (fetch, call = 0) =>
    JSON.parse(fetch.mock.calls[call][1].body).positions;

async function start(ids) {
    await mount(page(ids), { 'top-list': TopList });
    // SortableJS is imported lazily: wait for it rather than for a few ticks
    await vi.waitFor(() => expect(sortable.options).not.toBeNull());
    vi.useFakeTimers();
}

describe('top-list', () => {
    beforeEach(() => {
        sortable.destroy = vi.fn();
        sortable.options = null;
    });

    it('numbers the coasters on load', async () => {
        await start();

        expect(numbers()).toEqual(['1', '2', '3', '4']);
        expect(sortable.options).toMatchObject({
            handle: '.drag-area',
            delayOnTouchOnly: true,
        });
    });

    it('saves the new order once the rider stops reordering', async () => {
        const fetch = stubFetch({ body: { status: 'success' } });
        await start();

        press(30, 'top');
        await vi.advanceTimersByTimeAsync(1000);
        press(10, 'bottom');
        await vi.advanceTimersByTimeAsync(1999);
        expect(fetch).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);

        expect(order()).toEqual([30, 20, 40, 10]);
        expect(numbers()).toEqual(['1', '2', '3', '4']);
        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch.mock.calls[0][0]).toBe('/en/tops/12/auto-save');
        expect(saved(fetch)).toEqual({ 30: 1, 20: 2, 40: 3, 10: 4 });
        expect(document.querySelector('.save-status-saved')).not.toBeNull();
    });

    it('saves after a drag that moved a coaster, not after one dropped in place', async () => {
        const fetch = stubFetch({ body: { status: 'success' } });
        await start();
        const list = document.querySelector('ul');

        sortable.options.onStart();
        expect(list.classList.contains('drag-active')).toBe(true);
        sortable.options.onEnd({ oldIndex: 1, newIndex: 1 });
        await vi.advanceTimersByTimeAsync(5000);
        expect(list.classList.contains('drag-active')).toBe(false);
        expect(fetch).not.toHaveBeenCalled();

        // SortableJS has already moved the row when onEnd fires
        list.append(list.firstElementChild);
        sortable.options.onEnd({ oldIndex: 0, newIndex: 3 });
        await vi.advanceTimersByTimeAsync(2000);

        expect(saved(fetch)).toEqual({ 20: 1, 30: 2, 40: 3, 10: 4 });
        expect(numbers()).toEqual(['1', '2', '3', '4']);
    });

    it.each([['top'], ['bottom'], ['position'], ['remove']])(
        'never jumps to the top of the page on "%s"',
        async (action) => {
            stubFetch({ body: { status: 'success' } });
            vi.stubGlobal(
                'prompt',
                vi.fn(() => '3')
            );
            await start();
            const click = new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
            });

            document
                .querySelector(`[data-coaster-id="20"] .${action}`)
                .dispatchEvent(click);

            expect(click.defaultPrevented).toBe(true);
        }
    );

    it('removes a coaster and closes the gap', async () => {
        const fetch = stubFetch({ body: { status: 'success' } });
        await start();

        press(20, 'remove');
        await vi.advanceTimersByTimeAsync(2000);

        expect(order()).toEqual([10, 30, 40]);
        expect(numbers()).toEqual(['1', '2', '3']);
        expect(saved(fetch)).toEqual({ 10: 1, 30: 2, 40: 3 });
    });

    describe('move to a position', () => {
        it.each([
            ['down the list', 10, '3', [20, 30, 10, 40]],
            ['up the list', 40, '2', [10, 40, 20, 30]],
            ['to the first place', 30, '1', [30, 10, 20, 40]],
            ['to the last place', 10, '4', [20, 30, 40, 10]],
        ])('moves a coaster %s', async (_, coaster, answer, expected) => {
            stubFetch({ body: { status: 'success' } });
            vi.stubGlobal(
                'prompt',
                vi.fn(() => answer)
            );
            await start();

            press(coaster, 'position');

            expect(order()).toEqual(expected);
            expect(
                document.querySelector(`[data-coaster-id="${coaster}"]`).dataset
                    .position
            ).toBe(answer);
        });

        it.each([['0'], ['5'], ['abc'], ['-1']])(
            'refuses %s and changes nothing',
            async (answer) => {
                const fetch = stubFetch();
                const alert = vi.fn();
                vi.stubGlobal(
                    'prompt',
                    vi.fn(() => answer)
                );
                vi.stubGlobal('alert', alert);
                await start();

                press(20, 'position');
                await vi.advanceTimersByTimeAsync(5000);

                expect(alert).toHaveBeenCalledWith('errors.invalid_position');
                expect(order()).toEqual([10, 20, 30, 40]);
                expect(fetch).not.toHaveBeenCalled();
            }
        );

        it.each([
            ['is cancelled', null],
            ['keeps the same position', '2'],
        ])('saves nothing when the prompt %s', async (_, answer) => {
            const fetch = stubFetch();
            vi.stubGlobal(
                'prompt',
                vi.fn(() => answer)
            );
            await start();

            press(20, 'position');
            await vi.advanceTimersByTimeAsync(5000);

            expect(order()).toEqual([10, 20, 30, 40]);
            expect(fetch).not.toHaveBeenCalled();
        });
    });

    it.each([
        ['the server refuses', { status: 500 }],
        [
            'the save is rejected',
            { body: { status: 'error', message: 'Invalid' } },
        ],
    ])('says the save failed and tries again when %s', async (_, failure) => {
        const fetch = stubFetch(failure, { body: { status: 'success' } });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await start();

        press(30, 'top');
        await vi.advanceTimersByTimeAsync(2000);
        expect(document.querySelector('.save-status-error')).not.toBeNull();

        await vi.advanceTimersByTimeAsync(5000);

        expect(fetch).toHaveBeenCalledTimes(2);
        expect(saved(fetch, 1)).toEqual({ 30: 1, 10: 2, 20: 3, 40: 4 });
        expect(document.querySelector('.save-status-error')).toBeNull();
        expect(document.querySelector('.save-status-saved')).not.toBeNull();
    });

    it('drops a pending save and SortableJS when the list leaves the page', async () => {
        const fetch = stubFetch();
        await start();

        press(30, 'top');
        document.querySelector('ul').remove();
        vi.useRealTimers();
        await settle();
        vi.useFakeTimers();
        await vi.advanceTimersByTimeAsync(5000);

        expect(sortable.destroy).toHaveBeenCalledOnce();
        expect(fetch).not.toHaveBeenCalled();
    });
});
