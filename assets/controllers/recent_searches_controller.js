import { Controller } from '@hotwired/stimulus';
import { clearRecentSearches, getRecentSearches } from '../js/recent-searches';
import { icon } from '../js/icons';

/**
 * Nav:RecentSearches: the last picked search results, kept on this device.
 * Renders on connect and again on `recent-searches:refresh` (search dialog
 * opening, desktop field focus).
 */
export default class extends Controller {
    static targets = ['list', 'item'];

    connect() {
        this.render();
    }

    clear() {
        clearRecentSearches();
        this.render();
    }

    render() {
        // Same-site paths only: localStorage is user-writable.
        const items = getRecentSearches().filter(
            (item) =>
                typeof item.url === 'string' &&
                item.url.startsWith('/') &&
                !item.url.startsWith('//')
        );
        this.element.hidden = items.length === 0;
        this.listTarget.replaceChildren(
            ...items.map((item) => {
                const row = this.itemTarget.content.cloneNode(true);
                const link = row.querySelector('a');
                const [iconEl, name] = link.querySelectorAll('span');
                link.href = item.url;
                iconEl.innerHTML = icon(item.type);
                name.textContent = item.name;
                return row;
            })
        );
    }
}
