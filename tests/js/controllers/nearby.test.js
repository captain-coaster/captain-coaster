import { describe, expect, it, vi } from 'vitest';
import Nearby from '../../../assets/controllers/nearby_controller';
import {
    mount,
    patch,
    settle,
    stubFetch,
    stubGeolocation,
} from '../support/stimulus';

const page = `
    <div id="moment" data-home-moment>Default moment</div>
    <section data-controller="nearby" data-nearby-url-value="/en/nearby" data-state="idle">
        <button data-action="nearby#locate">Parks near me</button>
        <div data-nearby-target="results"></div>
    </section>`;

const here = (success) =>
    success({ coords: { latitude: 50.80012, longitude: 6.87987 } });
const refuses = (code) => (success, failure) =>
    failure({ code, TIMEOUT: 3, PERMISSION_DENIED: 1 });
const section = () => document.querySelector('section');

function permission(state) {
    const query =
        state instanceof Error
            ? vi.fn().mockRejectedValue(state)
            : vi.fn().mockResolvedValue({ state });
    patch(navigator, 'permissions', { query });
}

describe('nearby', () => {
    it('loads the parks around a rider who already allows their position', async () => {
        permission('granted');
        stubGeolocation(here);
        const fetch = stubFetch({ body: '<ul><li>Phantasialand</li></ul>' });

        await mount(page, { nearby: Nearby });

        const [url, request] = fetch.mock.calls[0];
        expect(url).toBe('/en/nearby');
        expect(request.method).toBe('POST');
        expect(Object.fromEntries(request.body)).toEqual({
            latitude: '50.8',
            longitude: '6.88',
        });
        expect(
            document.querySelector('[data-nearby-target="results"]').textContent
        ).toBe('Phantasialand');
        expect(section().dataset.state).toBe('ready');
    });

    it('asks nothing until the rider presses the button', async () => {
        permission('prompt');
        const locate = stubGeolocation(here);
        stubFetch({ body: 'Parks' });
        await mount(page, { nearby: Nearby });
        expect(locate).not.toHaveBeenCalled();
        expect(section().dataset.state).toBe('idle');

        document.querySelector('button').click();
        await settle();

        expect(section().dataset.state).toBe('ready');
        expect(JSON.parse(localStorage.getItem('cc.position'))).toMatchObject({
            latitude: 50.8,
            longitude: 6.88,
        });
    });

    it.each([
        ['the browser asks every time (Safari)', 'prompt'],
        ['permissions cannot be queried', new Error('unsupported')],
    ])(
        'loads the parks around the position shared lately, without asking, when %s',
        async (_, state) => {
            permission(state);
            localStorage.setItem(
                'cc.position',
                JSON.stringify({
                    latitude: 46.2,
                    longitude: 6.14,
                    at: Date.now(),
                })
            );
            const locate = stubGeolocation(here);
            const fetch = stubFetch({ body: 'Parks' });

            await mount(page, { nearby: Nearby });

            expect(locate).not.toHaveBeenCalled();
            expect(Object.fromEntries(fetch.mock.calls[0][1].body)).toEqual({
                latitude: '46.2',
                longitude: '6.14',
            });
            expect(section().dataset.state).toBe('ready');
        }
    );

    it('swaps the home moment for the one the results carry', async () => {
        permission('granted');
        stubGeolocation(here);
        stubFetch({
            body: '<ul></ul><template data-nearby-moment><p>3 parks within an hour</p></template>',
        });

        await mount(page, { nearby: Nearby });

        expect(document.getElementById('moment').innerHTML).toBe(
            '<p>3 parks within an hour</p>'
        );
    });

    it('shows the denied state when the permission is blocked', async () => {
        permission('denied');
        stubGeolocation(here);
        const fetch = stubFetch();

        await mount(page, { nearby: Nearby });

        expect(section().dataset.state).toBe('denied');
        expect(fetch).not.toHaveBeenCalled();
    });

    it('shows the button, without an error, when a granted position fails on load', async () => {
        permission('granted');
        stubGeolocation(refuses(3));

        await mount(page, { nearby: Nearby });

        expect(section().dataset.state).toBe('idle');
        expect(section().hasAttribute('data-error')).toBe(false);
    });

    it('shows the denied state when the rider refuses from the button', async () => {
        permission('prompt');
        stubGeolocation(refuses(1));
        await mount(page, { nearby: Nearby });

        document.querySelector('button').click();
        await settle();

        expect(section().dataset.state).toBe('denied');
    });

    it.each([
        ['the position times out', refuses(3), undefined],
        ['the server fails', here, { status: 500 }],
    ])('offers to retry when %s', async (_, geolocation, response) => {
        permission('prompt');
        stubGeolocation(geolocation);
        stubFetch(...(response ? [response] : []));
        await mount(page, { nearby: Nearby });

        document.querySelector('button').click();
        await settle();

        expect(section().dataset.state).toBe('idle');
        expect(section().hasAttribute('data-error')).toBe(true);
    });

    it('clears the error on a new attempt', async () => {
        permission('granted');
        stubGeolocation(here);
        stubFetch({ status: 500 }, { body: 'Parks' });
        await mount(page, { nearby: Nearby });
        expect(section().hasAttribute('data-error')).toBe(true);

        document.querySelector('button').click();
        await settle();

        expect(section().hasAttribute('data-error')).toBe(false);
        expect(section().dataset.state).toBe('ready');
    });

    it('shows the denied state in a browser without geolocation', async () => {
        stubGeolocation(undefined);

        await mount(page, { nearby: Nearby });

        expect(section().dataset.state).toBe('denied');
    });
});
