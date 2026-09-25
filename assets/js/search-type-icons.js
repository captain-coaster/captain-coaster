/**
 * SVG markup of a search result's type marker, rendered server-side once per
 * page in <template id="search-type-icons"> (Nav:SearchTypeIcons).
 */
const LEGACY_EMOJI = { '🎢': 'coaster', '🎡': 'park', '👤': 'user' };

export function searchTypeIcon(type) {
    const template = document.getElementById('search-type-icons');
    return (
        template?.content.querySelector(`[data-type="${type}"]`)?.innerHTML ??
        ''
    );
}

/** Recent searches saved before types were stored carry an emoji instead. */
export function recentSearchType(item) {
    return item.type ?? LEGACY_EMOJI[item.emoji] ?? null;
}
