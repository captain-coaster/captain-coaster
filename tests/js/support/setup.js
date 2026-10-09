import { afterEach, beforeEach, vi } from 'vitest';
import { restorePatches, unmount } from './stimulus';

beforeEach(() => {
    // FOSJsRouting's global, set by assets/js/routing.js: /{locale}/{route}/{other params, in order}
    globalThis.Routing = {
        generate: vi.fn(
            (route, { _locale, ...params }) =>
                `/${_locale}/${route}/${Object.values(params).join('/')}`
        ),
    };
});

afterEach(async () => {
    vi.useRealTimers();
    await unmount();
    restorePatches();
    document.documentElement.removeAttribute('lang');
    localStorage.clear();
    window.history.replaceState(null, '', '/en/');
});
