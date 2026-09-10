import { Controller } from '@hotwired/stimulus';
import { trans } from '../translator';
import { SearchDropdown } from '../js/search-dropdown';

/**
 * Site-wide search bar -- shared debounce/fetch/keyboard-nav/dropdown
 * behavior lives in SearchDropdown (see assets/js/search-dropdown.js);
 * this defines what's specific to searching coasters/parks/users and
 * navigating to the selected result.
 *
 * Usage:
 * <div data-controller="search"
 *      data-search-search-url-value="/api/search"
 *      data-search-results-url-value="/search">
 *   <input data-search-target="input" data-action="input->search#handleInput keydown->search#handleKeydown">
 *   <div data-search-target="dropdown">
 *     <div data-search-target="results"></div>
 *   </div>
 * </div>
 */
export default class extends SearchDropdown(Controller) {
    static values = {
        searchUrl: String,
        resultsUrl: String,
        minLength: { type: Number, default: 2 },
        debounceDelay: { type: Number, default: 300 },
        maxResults: { type: Number, default: 5 },
    };

    get dropdownAriaLabel() {
        return 'Search suggestions';
    }

    get loadingText() {
        return trans('app.search.searching');
    }

    connect() {
        super.connect();
        this.updateClearButtonVisibility();
    }

    buildSearchUrl(query) {
        const url = new URL(this.searchUrlValue, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('limit', this.maxResultsValue.toString());
        return url.toString();
    }

    /**
     * Update dropdown with search results
     */
    updateResults(data) {
        if (!this.hasResultsTarget) return;

        const { results, hasMore, query } = data;
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

            displayResults.forEach((item, index) => {
                html += this.renderResultItem(item, index, query);
            });

            if (hasMoreResults) {
                html += this.renderShowMoreOption(query);
            }
        }

        this.resultsTarget.innerHTML = html;
        this.selectedIndex = -1;

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
            const translatedCountry = this.translateCountry(subtitle);
            subtitle = subtitle.replace(
                /<strong>.*?<\/strong>|country\.\w+/g,
                (match) => {
                    return match.startsWith('<strong>')
                        ? match
                        : translatedCountry;
                }
            );
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
    translateCountry(country) {
        try {
            const translated = trans(country, {}, 'database');
            if (translated && translated !== country) {
                return translated;
            }
        } catch (error) {
            console.warn('Translation failed:', country, error);
        }
        return country
            .replace('country.', '')
            .replace(/^\w/, (c) => c.toUpperCase());
    }

    renderNoResults(query) {
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

    handleEmptyEnter() {
        this.showMoreResults();
    }

    /**
     * Handle result selection
     */
    selectItem(item) {
        const type = item.dataset.type;
        const slug = item.dataset.slug;
        const id = item.dataset.id;

        if (!type || !slug) return;

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
                break;
            default:
                return;
        }

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

    /**
     * Handle input focus to clear placeholder behavior
     */
    handleFocus(event) {
        const input = event.target;
        if (input.value.trim().length >= this.minLengthValue) {
            this.search(event);
        }
    }

    handleInput(event) {
        this.search(event);
        this.updateClearButtonVisibility();
    }
}
