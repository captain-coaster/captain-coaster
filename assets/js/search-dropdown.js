/**
 * Shared behavior for the two AJAX search-dropdown controllers
 * (search_controller.js -- site-wide search bar, top_search_controller.js
 * -- top-list coaster picker). Both had accumulated nearly-identical
 * debounce/fetch/keyboard-nav/dropdown-visibility code independently;
 * this factors it into one place while leaving genuinely different parts
 * (result rendering, selection behavior, data shape) as overridable hooks.
 *
 * Usage: `class extends SearchDropdown(Controller) { ... }`, defining
 * `static values`, `buildSearchUrl()`, `updateResults()`, `selectItem()`,
 * and the `dropdownAriaLabel`/`loadingText` getters. See either
 * controller for a concrete example.
 */
export function SearchDropdown(Base) {
    return class extends Base {
        static targets = ['input', 'dropdown', 'results'];

        connect() {
            this.debounceTimer = null;
            this.currentRequest = null;
            this.selectedIndex = -1;
            this.isOpen = false;
            this.lastQuery = '';

            this.setupAccessibility();
            this.setupEventListeners();
        }

        disconnect() {
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            if (this.currentRequest) {
                this.currentRequest.abort();
            }

            document.removeEventListener('click', this.closeOnOutsideClick);
        }

        setupAccessibility() {
            if (this.hasInputTarget) {
                this.inputTarget.setAttribute('role', 'combobox');
                this.inputTarget.setAttribute('aria-expanded', 'false');
                this.inputTarget.setAttribute('aria-autocomplete', 'list');
                this.inputTarget.setAttribute('aria-haspopup', 'listbox');
            }

            if (this.hasDropdownTarget) {
                this.dropdownTarget.setAttribute('role', 'listbox');
                this.dropdownTarget.setAttribute(
                    'aria-label',
                    this.dropdownAriaLabel
                );
            }
        }

        setupEventListeners() {
            this.closeOnOutsideClick = (event) => {
                if (this.element && !this.element.contains(event.target)) {
                    this.hideDropdown();
                }
            };
            document.addEventListener('click', this.closeOnOutsideClick);
        }

        /** Handle search input with debouncing. */
        search(event) {
            const query = event.target.value.trim();

            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            if (query.length < this.minLengthValue) {
                this.hideDropdown();
                return;
            }

            if (query === this.lastQuery) {
                return;
            }

            this.debounceTimer = setTimeout(() => {
                this.performSearch(query);
            }, this.debounceDelayValue);
        }

        async performSearch(query) {
            if (this.currentRequest) {
                this.currentRequest.abort();
            }

            this.lastQuery = query;

            try {
                this.showLoadingState();

                const controller = new AbortController();
                this.currentRequest = controller;

                const response = await fetch(this.buildSearchUrl(query), {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.message || 'Search error occurred');
                }

                this.updateResults(data, query);
                this.showDropdown();
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                console.error('Search error:', error);
                this.showErrorState(error.message);
            } finally {
                this.currentRequest = null;
            }
        }

        showLoadingState() {
            if (!this.hasResultsTarget) return;
            this.resultsTarget.innerHTML = `<div class="search-loading">
                <div class="search-loading-spinner"></div>
                <div class="search-loading-text">${this.loadingText}</div>
            </div>`;
            this.showDropdown();
        }

        showErrorState(message) {
            if (!this.hasResultsTarget) return;
            this.resultsTarget.innerHTML = `<div class="search-error">
                <div class="search-error-icon">⚠️</div>
                <div class="search-error-text">${this.escapeHtml(message)}</div>
            </div>`;
            this.showDropdown();
        }

        showDropdown() {
            if (!this.hasDropdownTarget) return;

            this.isOpen = true;
            this.dropdownTarget.classList.add('show');
            this.element.classList.add('search-open');

            if (this.hasInputTarget) {
                this.inputTarget.setAttribute('aria-expanded', 'true');
            }
        }

        hideDropdown() {
            if (!this.hasDropdownTarget) return;

            this.isOpen = false;
            this.dropdownTarget.classList.remove('show');
            this.element.classList.remove('search-open');
            this.selectedIndex = -1;

            if (this.hasInputTarget) {
                this.inputTarget.setAttribute('aria-expanded', 'false');
            }

            this.clearSelection();
        }

        handleKeydown(event) {
            if (!this.isOpen) return;

            const items = this.getKeyboardNavItems();

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.selectedIndex = Math.min(
                        this.selectedIndex + 1,
                        items.length - 1
                    );
                    this.updateSelection(items);
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                    this.updateSelection(items);
                    break;

                case 'Enter':
                    event.preventDefault();
                    if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                        this.selectItem(items[this.selectedIndex]);
                    } else {
                        this.handleEmptyEnter();
                    }
                    break;

                case 'Escape':
                    event.preventDefault();
                    this.hideDropdown();
                    break;
            }
        }

        /** Elements the keyboard can move focus/selection between. */
        getKeyboardNavItems() {
            return this.resultsTarget.querySelectorAll(
                '.search-result-item, .search-show-more'
            );
        }

        /** Elements needing a manually-attached click handler. */
        getClickableResultItems() {
            return this.resultsTarget.querySelectorAll('.search-result-item');
        }

        /** No selection and Enter pressed -- no-op by default. */
        handleEmptyEnter() {}

        updateSelection(items) {
            this.clearSelection();

            if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                items[this.selectedIndex].classList.add('selected');
                items[this.selectedIndex].scrollIntoView({
                    block: 'nearest',
                    behavior: 'smooth',
                });
            }
        }

        clearSelection() {
            this.resultsTarget
                .querySelectorAll('.selected')
                .forEach((item) => item.classList.remove('selected'));
        }

        setupResultClickHandlers() {
            this.getClickableResultItems().forEach((item) => {
                item.addEventListener('click', () => this.selectItem(item));
            });
        }

        /** Accent-insensitive substring highlight (e.g. matches "café" for query "cafe"). */
        highlightSearchTerm(text, query) {
            if (!text || !query) return this.escapeHtml(text);

            const escapedText = this.escapeHtml(text);

            const normalize = (str) =>
                str
                    .normalize('NFD')
                    .replace(new RegExp('[\\u0300-\\u036f]', 'g'), '');

            const normalizedText = normalize(text.toLowerCase());
            const normalizedQuery = normalize(query.toLowerCase());

            const matchIndex = normalizedText.indexOf(normalizedQuery);
            if (matchIndex === -1) {
                return escapedText;
            }

            const matchedText = text.substring(
                matchIndex,
                matchIndex + normalizedQuery.length
            );
            const escapedMatch = this.escapeHtml(matchedText).replace(
                /[.*+?^${}()|[\]\\]/g,
                '\\$&'
            );

            const regex = new RegExp(`(${escapedMatch})`, 'gi');
            return escapedText.replace(regex, '<strong>$1</strong>');
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
}
