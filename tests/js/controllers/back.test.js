import { describe, expect, it, vi } from 'vitest';
import Back from '../../../assets/controllers/back_controller';
import { mount } from '../support/stimulus';

const page =
    '<a href="/en/ranking/" data-controller="back" data-action="back#go">Back</a>';

/** Lands on /en/coasters/1 from `referrer`, with `entries` pages in the tab's history. */
async function arrive(referrer, entries = 2) {
    window.history.replaceState(null, '', '/en/coasters/1');
    vi.spyOn(document, 'referrer', 'get').mockReturnValue(referrer);
    vi.spyOn(window.history, 'length', 'get').mockReturnValue(entries);
    const back = vi.spyOn(window.history, 'back').mockImplementation(() => {});
    await mount(page, { back: Back });

    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.querySelector('a').dispatchEvent(click);

    return {
        wentBack: back.mock.calls.length === 1,
        followedLink: !click.defaultPrevented,
    };
}

describe('back', () => {
    it('goes back in history when arriving from another page of the site', async () => {
        expect(
            await arrive('https://captaincoaster.test/en/ranking/?page=3')
        ).toEqual({ wentBack: true, followedLink: false });
    });

    it.each([
        ['a search engine', 'https://www.google.com/'],
        ['no referrer (shared link, bookmark)', ''],
        [
            'the same page, reloaded after saving',
            'https://captaincoaster.test/en/coasters/1',
        ],
        [
            'the same page in another language',
            'https://captaincoaster.test/fr/coasters/1',
        ],
    ])('follows its link when arriving from %s', async (_, referrer) => {
        expect(await arrive(referrer)).toEqual({
            wentBack: false,
            followedLink: true,
        });
    });

    it('follows its link in a tab with no history', async () => {
        expect(
            await arrive('https://captaincoaster.test/en/ranking/', 1)
        ).toEqual({ wentBack: false, followedLink: true });
    });
});
