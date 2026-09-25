/**
 * Recent search picks, kept on this device only (localStorage). Storage can
 * be unavailable (private mode, blocked site data): reads then return [] and
 * writes are dropped.
 */
const KEY = 'cc.recentSearches';
const MAX = 5;

export function getRecentSearches() {
    try {
        const items = JSON.parse(localStorage.getItem(KEY) ?? '[]');
        return Array.isArray(items) ? items.slice(0, MAX) : [];
    } catch {
        return [];
    }
}

export function rememberRecentSearch(entry) {
    if (!entry.name || !entry.url) return;

    try {
        const items = getRecentSearches().filter((item) => item.url !== entry.url);
        localStorage.setItem(KEY, JSON.stringify([entry, ...items].slice(0, MAX)));
    } catch {
        // Storage unavailable: nothing to remember.
    }
}

export function clearRecentSearches() {
    try {
        localStorage.removeItem(KEY);
    } catch {
        // Storage unavailable: nothing to clear.
    }
}
