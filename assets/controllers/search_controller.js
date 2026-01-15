import { Controller } from '@hotwired/stimulus';
import { trans } from '../translator';
/**
 * Modern Search Controller - Replaces legacy typeahead.js implementation
 *
 * Responsibilities:
 * - Handle user input with debouncing (300ms delay)
 * - Send AJAX requests to search API
 * - Manage search dropdown visibility and state
 * - Handle keyboard navigation (arrow keys, enter, escape)
 * - Manage "Show more results" functionality
 * - Provide accessibility features with ARIA labels
 *
 * Usage:
 * <div data-controller="search"
 *      data-search-search-url-value="/api/search"
 *      data-search-results-url-value="/search">
 *   <input data-search-target="input" data-action="input->search#search keydown->search#handleKeydown">
 *   <div data-search-target="dropdown">
 *     <div data-search-target="results"></div>
 *   </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['input', 'dropdown', 'results'];
    static values = {
        searchUrl: String,
        resultsUrl: String,
        minLength: { type: Number, default: 2 },
        debounceDelay: { type: Number, default: 300 },
        maxResults: { type: Number, default: 5 },
    };

    connect() {
        // Initialize state
        this.debounceTimer = null;
        this.currentRequest = null;
        this.selectedIndex = -1;
        this.isOpen = false;
        this.lastQuery = '';

        // Set up accessibility attributes
        this.setupAccessibility();

        // Set up event listeners
        this.setupEventListeners();

        // Initialize clear button visibility
        this.updateClearButtonVisibility();
    }

    disconnect() {
        // Clean up timers and requests
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        if (this.currentRequest) {
            this.currentRequest.abort();
        }

        // Remove global event listeners
        document.removeEventListener('click', this.closeOnOutsideClick);
    }

    /**
     * Set up accessibility attributes for screen readers
     */
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
                'Search suggestions'
            );
        }
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Close dropdown when clicking outside
        this.closeOnOutsideClick = (event) => {
            if (this.element && !this.element.contains(event.target)) {
                this.hideDropdown();
            }
        };
        document.addEventListener('click', this.closeOnOutsideClick);
    }

    /**
     * Handle search input with debouncing
     */
    search(event) {
        const query = event.target.value.trim();

        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Hide dropdown if query is too short
        if (query.length < this.minLengthValue) {
            this.hideDropdown();
            return;
        }

        // Don't search if query hasn't changed
        if (query === this.lastQuery) {
            return;
        }

        // Debounce the search
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceDelayValue);
    }

    /**
     * Handle input focus to clear placeholder behavior
     */
    handleFocus(event) {
        // Ensure placeholder is properly handled
        const input = event.target;
        if (input.value.trim().length >= this.minLengthValue) {
            this.search(event);
        }
    }

    /**
     * Execute AJAX search request
     */
    async performSearch(query) {
        // Cancel previous request
        if (this.currentRequest) {
            this.currentRequest.abort();
        }

        this.lastQuery = query;

        try {
            // Show loading state
            this.showLoadingState();

            // Create AbortController for request cancellation
            const controller = new AbortController();
            this.currentRequest = controller;

            const url = new URL(this.searchUrlValue, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('limit', this.maxResultsValue.toString());

            const response = await fetch(url.toString(), {
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

            this.updateResults(data);
            this.showDropdown();
        } catch (error) {
            if (error.name === 'AbortError') {
                // Request was cancelled, ignore
                return;
            }

            console.error('Search error:', error);
            this.showErrorState(error.message);
        } finally {
            this.currentRequest = null;
        }
    }

    /**
     * Update dropdown with search results
     */
    updateResults(data) {
        if (!this.hasResultsTarget) return;

        const { results, totalResults, hasMore, query } = data;
        let html = '';
        let allResults = [];

        // Combine all results into a single array with type info
        if (results.coasters) {
            allResults = allResults.concat(
                results.coasters.map((item) => ({ ...item, emoji: '🎢' }))
            );
        }
        if (results.parks) {
            allResults = allResults.concat(
                results.parks.map((item) => ({ ...item, emoji: '🎡' }))
            );
        }
        if (results.users) {
            allResults = allResults.concat(
                results.users.map((item) => ({ ...item, emoji: '👤' }))
            );
        }

        if (allResults.length === 0) {
            html = this.renderNoResults(query);
        } else {
            // Limit to 5 results maximum to prevent keyboard from blocking "see all results" on mobile
            const maxDisplayResults = 5;
            const displayResults = allResults.slice(0, maxDisplayResults);
            const hasMoreResults =
                hasMore || allResults.length > maxDisplayResults;

            // Render the limited results
            displayResults.forEach((item, index) => {
                html += this.renderResultItem(item, index, query);
            });

            // Add "Show more results" if there are more results (as 6th item)
            if (hasMoreResults) {
                html += this.renderShowMoreOption(query);
            }
        }

        this.resultsTarget.innerHTML = html;
        this.selectedIndex = -1; // Reset selection

        // Set up click handlers for results
        this.setupResultClickHandlers();
    }

    /**
     * Render a single result item
     */
    renderResultItem(item, index, query) {
        const name = this.highlightSearchTerm(item.name, query);
        let subtitle = item.subtitle
            ? this.highlightSearchTerm(item.subtitle, query)
            : null;

        // Translate country keys for parks (subtitle contains country key like "country.usa")
        if (item.type === 'park' && subtitle && subtitle.includes('country.')) {
            // Extract the country key from the highlighted subtitle
            const countryMatch = item.subtitle.match(/country\.\w+/);
            if (countryMatch) {
                const translatedCountry = this.translateCountry(
                    countryMatch[0]
                );
                subtitle = subtitle.replace(
                    /<strong>.*?<\/strong>|country\.\w+/g,
                    (match) => {
                        return match.startsWith('<strong>')
                            ? match
                            : translatedCountry;
                    }
                );
            }
        }

        return `
            <div class="search-result-item" data-index="${index}" data-type="${item.type}" data-id="${item.id}" data-slug="${this.escapeHtml(item.slug)}">
                <div class="search-result-emoji">${item.emoji}</div>
                <div class="search-result-content">
                    <div class="search-result-name">${name}</div>
                    ${subtitle ? `<div class="search-result-subtitle">${subtitle}</div>` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Translate country keys using Symfony UX Translator
     */
    translateCountry(countryKey) {
        if (typeof Translator !== 'undefined' && Translator.trans) {
            try {
                const translated = Translator.trans(countryKey, {}, 'database');
                if (translated && translated !== countryKey) {
                    return translated;
                }
            } catch (error) {
                console.warn('Translation failed:', countryKey, error);
            }
        }
        return countryKey
            .replace('country.', '')
            .replace(/^\w/, (c) => c.toUpperCase());
    }

    /**
     * Highlight search terms in text with accent-insensitive matching
     */
    highlightSearchTerm(text, query) {
        if (!text || !query) return this.escapeHtml(text);

        const escapedText = this.escapeHtml(text);

        // Normalize text for accent-insensitive comparison
        const normalizeText = (str) =>
            str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        const normalizedText = normalizeText(text.toLowerCase());
        const normalizedQuery = normalizeText(query.toLowerCase());

        const matchIndex = normalizedText.indexOf(normalizedQuery);

        if (matchIndex === -1) {
            return escapedText;
        }

        // Get the actual matching text from original (with accents)
        const matchedText = text.substring(
            matchIndex,
            matchIndex + normalizedQuery.length
        );
        const escapedMatch = this.escapeHtml(matchedText).replace(
            /[.*+?^${}()|[\]\\]/g,
            '\\$&'
        );

        // Create regex and highlight
        const regex = new RegExp(`(${escapedMatch})`, 'gi');
        return escapedText.replace(regex, '<strong>$1</strong>');
    }

    /**
     * Render special states and options
     */
    renderNoResults(query) {
        // Use translated text for no results message
        return `<div class="search-no-results">
            <div class="search-no-results-icon">🔍</div>
            <div class="search-no-results-text">${trans('search_index.noResult')}</div>
        </div>`;
    }

    renderShowMoreOption(query) {
        return `<div class="search-show-more" data-action="click->search#showMoreResults" data-query="${this.escapeHtml(query)}">
            <div class="search-show-more-content">
                <span>${trans('search_index.more')}</span>
                <i class="icon-arrow-right8"></i>
            </div>
        </div>`;
    }

    /**
     * Show loading and error states
     */
    showLoadingState() {
        if (!this.hasResultsTarget) return;
        this.resultsTarget.innerHTML = `<div class="search-loading">
            <div class="search-loading-spinner"></div>
            <div class="search-loading-text">${trans('app.search.searching')}</div>
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

    /**
     * Show the dropdown
     */
    showDropdown() {
        if (!this.hasDropdownTarget) return;

        this.isOpen = true;
        this.dropdownTarget.classList.add('show');
        this.element.classList.add('search-open');

        if (this.hasInputTarget) {
            this.inputTarget.setAttribute('aria-expanded', 'true');
        }
    }

    /**
     * Hide the dropdown
     */
    hideDropdown() {
        if (!this.hasDropdownTarget) return;

        this.isOpen = false;
        this.dropdownTarget.classList.remove('show');
        this.element.classList.remove('search-open');
        this.selectedIndex = -1;

        if (this.hasInputTarget) {
            this.inputTarget.setAttribute('aria-expanded', 'false');
        }

        // Remove selection highlighting
        this.clearSelection();
    }

    /**
     * Handle keyboard navigation
     */
    handleKeydown(event) {
        if (!this.isOpen) return;

        const items = this.resultsTarget.querySelectorAll(
            '.search-result-item, .search-show-more'
        );

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
                    // No selection, go to search results page
                    this.showMoreResults();
                }
                break;

            case 'Escape':
                event.preventDefault();
                this.hideDropdown();
                break;
        }
    }

    /**
     * Update visual selection highlighting
     */
    updateSelection(items) {
        // Clear previous selection
        this.clearSelection();

        // Highlight current selection
        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].classList.add('selected');

            // Scroll into view if needed
            items[this.selectedIndex].scrollIntoView({
                block: 'nearest',
                behavior: 'smooth',
            });
        }
    }

    /**
     * Clear selection highlighting
     */
    clearSelection() {
        const selectedItems = this.resultsTarget.querySelectorAll('.selected');
        selectedItems.forEach((item) => item.classList.remove('selected'));
    }

    /**
     * Set up click handlers for result items
     */
    setupResultClickHandlers() {
        const resultItems = this.resultsTarget.querySelectorAll(
            '.search-result-item'
        );
        resultItems.forEach((item) => {
            item.addEventListener('click', () => this.selectItem(item));
        });
    }

    /**
     * Handle result selection
     */
    selectItem(item) {
        const type = item.dataset.type;
        const slug = item.dataset.slug;
        const id = item.dataset.id;

        if (!type || !slug) return;

        // Determine the route based on type
        let routeName;
        let routeParams = { slug: slug };

        switch (type) {
            case 'coaster':
                routeName = 'show_coaster';
                routeParams.id = id;
                break;
            case 'park':
                routeName = 'park_show';
                routeParams.id = id;
                break;
            case 'user':
                routeName = 'user_show';
                // User routes only need slug
                break;
            default:
                return;
        }

        // Navigate to the selected item
        const url = this.generateRoute(routeName, routeParams);
        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Navigate to comprehensive search results page
     */
    showMoreResults(event = null) {
        let query;

        if (event && event.target.closest('.search-show-more')) {
            query = event.target.closest('.search-show-more').dataset.query;
        } else {
            query = this.hasInputTarget ? this.inputTarget.value.trim() : '';
        }

        if (!query) return;

        const url = this.generateRoute('search_index', { query: query });
        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Generate route URL and escape HTML
     */
    generateRoute(routeName, params = {}) {
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate(routeName, {
                    ...params,
                    _locale: document.documentElement.lang || 'en',
                });
            } catch (error) {
                console.error('Routing failed:', routeName, params, error);
                return null;
            }
        }
        console.error('FOSJsRoutingBundle not available');
        return null;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Clear the search input and hide dropdown
     */
    clearSearch() {
        if (this.hasInputTarget) {
            this.inputTarget.value = '';
            this.inputTarget.focus();
        }
        this.hideDropdown();
        this.lastQuery = '';
        this.updateClearButtonVisibility();
    }

    /**
     * Update clear button visibility based on input content
     */
    updateClearButtonVisibility() {
        const inputContainer = this.element.querySelector(
            '.search-input-container'
        );
        if (inputContainer && this.hasInputTarget) {
            inputContainer.classList.toggle(
                'has-content',
                this.inputTarget.value.trim().length > 0
            );
        }
    }

    handleInput(event) {
        this.search(event);
        this.updateClearButtonVisibility();
    }
}
