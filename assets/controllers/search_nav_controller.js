import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'dropdown', 'recentSearches'];
    static values = {
        storageKey: { type: String, default: 'cc_recent_searches' },
        maxRecent: { type: Number, default: 5 },
    };

    #blurTimer = null;

    connect() {
        this.#updateShortcutKey();
    }

    handleFocus() {
        clearTimeout(this.#blurTimer);
        this.#open();
    }

    handleBlur() {
        this.#blurTimer = setTimeout(() => this.#close(), 150);
    }

    handleInput() {
        if (this.hasRecentSearchesTarget && this.inputTarget.value.length < 2) {
            this.#renderRecent();
        }
    }

    recentSearchesTargetConnected() {
        this.#renderRecent();
    }

    handleKeydown(event) {
        const items = this.#navigableItems();
        const current = items.findIndex((el) => el.dataset.selected === 'true');

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.#select(items, current < items.length - 1 ? current + 1 : 0);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.#select(items, current > 0 ? current - 1 : items.length - 1);
                break;
            case 'Enter':
                event.preventDefault();
                if (current >= 0) {
                    const el = items[current];
                    if (el.dataset.resultUrl) {
                        this.#saveQuery(this.inputTarget.value);
                        window.location.href = el.dataset.resultUrl;
                    } else if (el.dataset.searchQuery) {
                        this.#populateFromRecent(el.dataset.searchQuery);
                    }
                } else if (this.inputTarget.value.length >= 2) {
                    this.#saveQuery(this.inputTarget.value);
                    this.element.closest('form')?.submit();
                }
                break;
            case 'Escape':
                this.#close();
                this.inputTarget.blur();
                break;
        }
    }

    selectRecent(event) {
        this.#populateFromRecent(event.currentTarget.dataset.searchQuery);
    }

    removeRecent(event) {
        event.stopPropagation();
        const chip = event.currentTarget.closest('[data-search-query]');
        const query = chip?.dataset.searchQuery;
        if (!query) return;

        const updated = this.#getRecent().filter((q) => q !== query);
        localStorage.setItem(this.storageKeyValue, JSON.stringify(updated));
        this.#renderRecent();
    }

    saveAndNavigate() {
        this.#saveQuery(this.inputTarget.value);
    }

    #open() {
        if (!this.hasDropdownTarget) return;
        this.dropdownTarget.classList.remove('hidden');
        this.inputTarget.setAttribute('aria-expanded', 'true');
    }

    #close() {
        if (!this.hasDropdownTarget) return;
        this.dropdownTarget.classList.add('hidden');
        this.inputTarget.setAttribute('aria-expanded', 'false');
        this.#clearSelection();
    }

    #navigableItems() {
        if (!this.hasDropdownTarget) return [];
        return [
            ...this.dropdownTarget.querySelectorAll('[data-result-url], [data-search-query]'),
        ];
    }

    #select(items, index) {
        items.forEach((el, i) => {
            const active = i === index;
            el.dataset.selected = active ? 'true' : 'false';
            el.setAttribute('aria-selected', active ? 'true' : 'false');
            el.classList.toggle('bg-surface-raised', active);
        });
        items[index]?.scrollIntoView({ block: 'nearest' });
    }

    #clearSelection() {
        this.#navigableItems().forEach((el) => {
            delete el.dataset.selected;
            el.removeAttribute('aria-selected');
            el.classList.remove('bg-surface-raised');
        });
    }

    #getRecent() {
        try {
            return JSON.parse(localStorage.getItem(this.storageKeyValue) || '[]');
        } catch {
            return [];
        }
    }

    #saveQuery(query) {
        if (!query || query.trim().length < 2) return;
        const term = query.trim();
        const updated = [term, ...this.#getRecent().filter((q) => q !== term)].slice(
            0,
            this.maxRecentValue
        );
        localStorage.setItem(this.storageKeyValue, JSON.stringify(updated));
    }

    #populateFromRecent(query) {
        this.inputTarget.value = query;
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.inputTarget.focus();
    }

    #renderRecent() {
        if (!this.hasRecentSearchesTarget) return;
        const recent = this.#getRecent();
        if (!recent.length) {
            this.recentSearchesTarget.innerHTML = '';
            return;
        }
        this.recentSearchesTarget.innerHTML = recent
            .map(
                (q) => `
            <div class="flex items-center gap-2 px-4 py-2.5 hover:bg-surface-raised cursor-pointer rounded-lg transition-colors"
                 data-search-query="${this.#esc(q)}"
                 data-action="click->search-nav#selectRecent">
                <svg class="w-3.5 h-3.5 text-content-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="flex-1 text-sm text-content truncate">${this.#esc(q)}</span>
                <button type="button"
                        class="p-1 rounded text-content-muted hover:text-content transition-colors"
                        data-action="click->search-nav#removeRecent"
                        aria-label="Remove">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>`
            )
            .join('');
    }

    #esc(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;');
    }

    #updateShortcutKey() {
        const el = document.getElementById('search-shortcut-key');
        if (el && /Mac|iPhone|iPad/.test(navigator.platform)) {
            el.textContent = '⌘';
        }
    }
}
