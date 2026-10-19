/**
 * The rider's position, for every feature that sorts or centres by distance.
 *
 * - One rule for every feature, `position()`: a fresh position when the
 *   permission is granted, else the one remembered on this device, else none.
 *   The browser is only asked from a rider's gesture (`position({ ask: true })`,
 *   or `locate()` for a fresh one), so loading a page never brings up a prompt.
 * - iOS forgets the permission between visits, and on every launch of the home
 *   screen app, while Permissions API still answers "prompt". So the last
 *   position is kept on this device, rounded to about 1 km, for a few days:
 *   it is what spares an iPhone rider the prompt on each visit. It is only sent
 *   to the server by the feature that needs it, never stored there.
 * - Positions are { latitude, longitude }, always rounded.
 */
const KEY = 'cc.position';
const MAX_AGE = 3 * 24 * 3600 * 1000;

// Two decimals: about 1 km, plenty to find the nearest parks.
const round = (value) => Math.round(value * 100) / 100;

export function isSupported() {
    return 'geolocation' in navigator;
}

/** 'granted', 'denied' or 'prompt' (also when the browser can't tell). */
export async function permission() {
    if (!isSupported()) return 'denied';
    try {
        const status = await navigator.permissions.query({
            name: 'geolocation',
        });
        return status.state;
    } catch {
        return 'prompt';
    }
}

/**
 * Asks the browser for the position: call it from a rider's gesture.
 * Rejects with 'denied', 'unavailable' or 'timeout'.
 */
export function locate() {
    return new Promise((resolve, reject) => {
        if (!isSupported()) return reject('denied');

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => resolve(remember(coords)),
            (error) => {
                if (error?.code === error?.PERMISSION_DENIED) {
                    forget();
                    return reject('denied');
                }
                reject(
                    error?.code === error?.TIMEOUT ? 'timeout' : 'unavailable'
                );
            },
            { maximumAge: 600000, timeout: 10000 }
        );
    });
}

/**
 * The rider's position, or null: fresh when the permission is granted, else
 * the one remembered on this device. With `ask` (a rider's gesture), the
 * browser is asked when neither is known; it can then reject like `locate`.
 */
export async function position({ ask = false } = {}) {
    const state = await permission();
    if (state === 'denied') {
        forget();
        return null;
    }
    if (state === 'granted') {
        try {
            return await locate();
        } catch {
            // No fix this time, and asking again won't get one sooner.
            return rememberedPosition();
        }
    }
    return rememberedPosition() ?? (ask ? locate() : null);
}

/** Keeps a position the rider shared some other way (the map's locate button). */
export function remember({ latitude, longitude }) {
    const position = { latitude: round(latitude), longitude: round(longitude) };
    try {
        localStorage.setItem(
            KEY,
            JSON.stringify({ ...position, at: Date.now() })
        );
    } catch {
        // Storage unavailable (private mode): nothing to remember.
    }
    return position;
}

export function rememberedPosition() {
    try {
        const { latitude, longitude, at } = JSON.parse(
            localStorage.getItem(KEY)
        );
        if (
            Date.now() - at < MAX_AGE &&
            Number.isFinite(latitude) &&
            Number.isFinite(longitude)
        ) {
            return { latitude, longitude };
        }
    } catch {
        // Nothing usable stored.
    }
    forget();
    return null;
}

function forget() {
    try {
        localStorage.removeItem(KEY);
    } catch {
        // Storage unavailable: nothing to forget.
    }
}
