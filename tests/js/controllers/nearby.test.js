import { describe, expect, it, vi } from 'vitest';
import Nearby from '../../../assets/controllers/nearby_controller';
import { mount, settle, stubFetch, stubGeolocation } from '../support/stimulus';

const page = `
    <div id="moment" data-home-moment>Default moment</div>
    <section data-controller="nearby" data-nearby-url-value="/en/nearby">
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
    Object.defineProperty(navigator, 'permissions', {
        value: { query },
        configurable: true,
    });
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
            latitude: '50.800',
            longitude: '6.880',
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

        document.querySelector('button').click();
        await settle();

        expect(section().dataset.state).toBe('ready');
        expect(localStorage.getItem('nearby-shared')).toBe('1');
    });

    it.each([
        ['the browser asks every time (Safari)', 'prompt'],
        ['permissions cannot be queried', new Error('unsupported')],
    ])(
        'locates on load a rider who shared their position before, when %s',
        async (_, state) => {
            permission(state);
            localStorage.setItem('nearby-shared', '1');
            const locate = stubGeolocation(here);
            stubFetch({ body: 'Parks' });

            await mount(page, { nearby: Nearby });

            expect(locate).toHaveBeenCalledOnce();
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

    it.each([
        ['the permission is blocked', () => permission('denied'), here],
        ['the rider refuses', () => permission('granted'), refuses(1)],
    ])('shows the denied state when %s', async (_, arrange, geolocation) => {
        arrange();
        localStorage.setItem('nearby-shared', '1');
        stubGeolocation(geolocation);
        const fetch = stubFetch();

        await mount(page, { nearby: Nearby });

        expect(section().dataset.state).toBe('denied');
        expect(fetch).not.toHaveBeenCalled();
    });

    it('forgets a rider who takes the permission back', async () => {
        permission('granted');
        localStorage.setItem('nearby-shared', '1');
        stubGeolocation(refuses(1));

        await mount(page, { nearby: Nearby });

        expect(localStorage.getItem('nearby-shared')).toBeNull();
    });

    it.each([
        ['the position times out', refuses(3), undefined],
        ['the server fails', here, { status: 500 }],
    ])('offers to retry when %s', async (_, geolocation, response) => {
        permission('granted');
        stubGeolocation(geolocation);
        stubFetch(...(response ? [response] : []));

        await mount(page, { nearby: Nearby });

        expect(section().dataset.state).toBe('idle');
        expect(section().hasAttribute('data-error')).toBe(true);
    });

    it('clears the error on a new attempt', async () => {
        permission('granted');
        stubGeolocation(here);
        stubFetch({ status: 500 }, { body: 'Parks' });
        await mount(page, { nearby: Nearby });

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
