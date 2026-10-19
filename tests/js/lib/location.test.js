import { describe, expect, it, vi } from 'vitest';
import {
    position,
    locate,
    permission,
    regionBounds,
    remember,
    rememberedPosition,
} from '../../../assets/js/location';
import { patch, stubGeolocation } from '../support/stimulus';

const here = (success) =>
    success({ coords: { latitude: 50.80012, longitude: 6.87987 } });
const refuses = (code) => (success, failure) =>
    failure({ code, TIMEOUT: 3, PERMISSION_DENIED: 1 });

function grant(state) {
    const query =
        state instanceof Error
            ? vi.fn().mockRejectedValue(state)
            : vi.fn().mockResolvedValue({ state });
    patch(navigator, 'permissions', { query });
}

function timeZone(zone) {
    patch(Intl, 'DateTimeFormat', () => ({
        resolvedOptions: () => ({ timeZone: zone }),
    }));
}

describe('location', () => {
    it('rounds the position to about 1 km and keeps it on the device', async () => {
        stubGeolocation(here);

        expect(await locate()).toEqual({ latitude: 50.8, longitude: 6.88 });
        expect(rememberedPosition()).toEqual({
            latitude: 50.8,
            longitude: 6.88,
        });
    });

    it('forgets the position after a few days', () => {
        vi.useFakeTimers();
        remember({ latitude: 50.8, longitude: 6.88 });

        vi.advanceTimersByTime(3 * 24 * 3600 * 1000);

        expect(rememberedPosition()).toBeNull();
        expect(localStorage.getItem('cc.position')).toBeNull();
    });

    it.each([
        [1, 'denied'],
        [2, 'unavailable'],
        [3, 'timeout'],
    ])('rejects error %i as %s', async (code, reason) => {
        stubGeolocation(refuses(code));

        await expect(locate()).rejects.toBe(reason);
    });

    it('forgets the position of a rider who refuses it', async () => {
        remember({ latitude: 50.8, longitude: 6.88 });
        stubGeolocation(refuses(1));

        await locate().catch(() => {});

        expect(rememberedPosition()).toBeNull();
    });

    it('reads an unknown permission as one to ask for', async () => {
        stubGeolocation(here);
        grant(new Error('unsupported'));

        expect(await permission()).toBe('prompt');
    });

    describe('position', () => {
        it('locates without a prompt when the permission is granted', async () => {
            grant('granted');
            const ask = stubGeolocation(here);

            expect(await position()).toEqual({
                latitude: 50.8,
                longitude: 6.88,
            });
            expect(ask).toHaveBeenCalledOnce();
        });

        it.each([
            ['the browser would ask (Safari)', 'prompt'],
            ['permissions cannot be queried', new Error('unsupported')],
        ])(
            'uses the remembered one, never asking, when %s',
            async (_, state) => {
                grant(state);
                const ask = stubGeolocation(here);
                remember({ latitude: 46.2, longitude: 6.14 });

                expect(await position()).toEqual({
                    latitude: 46.2,
                    longitude: 6.14,
                });
                expect(ask).not.toHaveBeenCalled();
            }
        );

        it('falls back on the remembered one when a granted fix times out', async () => {
            grant('granted');
            stubGeolocation(refuses(3));
            remember({ latitude: 46.2, longitude: 6.14 });

            expect(await position()).toEqual({
                latitude: 46.2,
                longitude: 6.14,
            });
        });

        it('does not ask twice when a granted fix fails', async () => {
            grant('granted');
            const ask = stubGeolocation(refuses(3));

            expect(await position({ ask: true })).toBeNull();
            expect(ask).toHaveBeenCalledOnce();
        });

        it('is null when nothing is known', async () => {
            grant('prompt');
            stubGeolocation(here);

            expect(await position()).toBeNull();
        });

        it('asks the browser when nothing is known and the rider asked', async () => {
            grant('prompt');
            const ask = stubGeolocation(here);

            expect(await position({ ask: true })).toEqual({
                latitude: 50.8,
                longitude: 6.88,
            });
            expect(ask).toHaveBeenCalledOnce();
        });

        it('forgets the remembered one once the permission is blocked', async () => {
            grant('denied');
            remember({ latitude: 46.2, longitude: 6.14 });

            expect(await position({ ask: true })).toBeNull();
            expect(rememberedPosition()).toBeNull();
        });
    });

    it.each([
        ['America/New_York', [-124, 25]],
        ['America/Sao_Paulo', [-81, -55]],
        ['Asia/Tokyo', [70, -8]],
        ['Asia/Dubai', [26, 13]],
        ['Australia/Sydney', [113, -47]],
        ['Europe/Paris', [-10, 36]],
        [undefined, [-10, 36]],
    ])('frames a rider in %s on their part of the world', (zone, southWest) => {
        timeZone(zone);

        expect(regionBounds()[0]).toEqual(southWest);
    });
});
