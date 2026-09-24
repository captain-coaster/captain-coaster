import { Controller } from '@hotwired/stimulus';
import {
    clearRecentSearches,
    getRecentSearches,
} from '../js/recent-searches';

/**
 * Full-screen mobile search (Nav:SearchDialog). Attached to <body> so the
 * Search tab and the dialog can live in different components.
 */
export default class extends Controller {
    static targets = ['dialog', 'recent', 'recentList', 'recentItem'];

    open() {
        this.renderRecent();
        this.dialogTarget.showModal();
        this.dialogTarget.querySelector('input[type="search"]')?.focus();
    }

    closed() {
        const input = this.dialogTarget.querySelector('input[type="search"]');
        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    clearRecent() {
        clearRecentSearches();
        this.renderRecent();
    }

    renderRecent() {
        // Same-site paths only: localStorage is user-writable.
        const items = getRecentSearches().filter(
            (item) =>
                typeof item.url === 'string' &&
                item.url.startsWith('/') &&
                !item.url.startsWith('//')
        );
        this.recentTarget.hidden = items.length === 0;
        this.recentListTarget.replaceChildren(
            ...items.map((item) => {
                const row = this.recentItemTarget.content.cloneNode(true);
                const link = row.querySelector('a');
                const [emoji, name] = link.querySelectorAll('span');
                link.href = item.url;
                emoji.textContent = item.emoji ?? '';
                name.textContent = item.name;
                return row;
            })
        );
    }
}
