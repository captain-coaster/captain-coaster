import { Controller } from '@hotwired/stimulus';
import { renderStarRating } from '../js/utils/star-rating';

/**
 * Top List Search Controller
 *
 * Specialized search component for adding coasters to Top Lists.
 * Integrates with top_list_controller for seamless coaster addition.
 *
 * Features:
 * - Search input with debouncing (300ms)
 * - AJAX search using existing endpoint
 * - Display coaster name, park name, and user rating
 * - Prevent duplicate coasters
 * - Visual feedback when coaster is added
 *
 * Usage:
 * <div data-controller="top-search"
 *      data-top-search-url-value="/tops/search/coasters.json"
 *      data-top-search-list-controller-value="top-list">
 *   <input data-top-search-target="input"
 *          data-action="input->top-search#search keydown->top-search#handleKeydown">
 *   <div data-top-search-target="dropdown">
 *     <div data-top-search-target="results"></div>
 *   </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['input', 'dropdown', 'results'];
    static values = {
        url: String,
        listController: { type: String, default: 'top-list' },
        minLength: { type: Number, default: 2 },
        debounceDelay: { type: Number, default: 300 },
    };

    connect() {
        // Initialize state
        this.debounceTimer = null;
        this.currentRequest = null;
        this.selectedIndex = -1;
        this.isOpen = false;
        this.lastQuery = '';

        // Set up accessibility
        this.setupAccessibility();

        // Set up event listeners
        this.setupEventListeners();
    }

    disconnect() {
        // Clean up
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        if (this.currentRequest) {
            this.currentRequest.abort();
        }

        document.removeEventListener('click', this.closeOnOutsideClick);
    }

    /**
     * Set up accessibility attributes
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
                'Coaster search results'
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

            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', query);

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

            this.updateResults(data.items || [], query);
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
    updateResults(items, query) {
        if (!this.hasResultsTarget) return;

        // Get existing coaster IDs from the list to prevent duplicates
        const existingCoasterIds = this.getExistingCoasterIds();

        if (items.length === 0) {
            this.resultsTarget.innerHTML = this.renderNoResults();
        } else {
            let html = '';
            items.forEach((item, index) => {
                const isDuplicate = existingCoasterIds.has(item.id.toString());
                html += this.renderResultItem(item, index, query, isDuplicate);
            });
            this.resultsTarget.innerHTML = html;
        }

        this.selectedIndex = -1;
        this.setupResultClickHandlers();
    }

    /**
     * Get existing coaster IDs from the top list
     */
    getExistingCoasterIds() {
        const existingIds = new Set();

        // Find the top list controller
        const listElement = document.querySelector(
            `[data-controller~="${this.listControllerValue}"]`
        );
        if (listElement) {
            const items = listElement.querySelectorAll('[data-coaster-id]');
            items.forEach((item) => {
                existingIds.add(item.dataset.coasterId);
            });
        }

        return existingIds;
    }

    /**
     * Render a single result item
     */
    renderResultItem(item, index, query, isDuplicate) {
        const name = this.highlightSearchTerm(item.coaster, query);
        const park = this.highlightSearchTerm(item.park, query);

        // Format rating to remove unnecessary decimals (5.0 -> 5, 4.5 -> 4.5)
        const rating = item.rating ? parseFloat(item.rating) : null;
        const formattedRating = rating
            ? rating % 1 === 0
                ? parseInt(rating)
                : rating
            : null;

        const duplicateClass = isDuplicate ? 'search-result-duplicate' : '';

        return `
            <div class="search-result-item ${duplicateClass}" 
                 data-index="${index}" 
                 data-coaster-id="${item.id}"
                 data-coaster-name="${this.escapeHtml(item.coaster)}"
                 data-park-name="${this.escapeHtml(item.park)}"
                 data-rating="${rating || ''}"
                 data-duplicate="${isDuplicate}">
                <div class="search-result-emoji">🎢</div>
                <div class="search-result-content">
                    <div class="search-result-name">${name}</div>
                    <div class="search-result-subtitle">${park}</div>
                </div>
                ${formattedRating ? `<span class="badge bg-success">${formattedRating}</span>` : '<span class="badge bg-secondary">N/A</span>'}
            </div>
        `;
    }

    /**
     * Highlight search terms in text
     */
    highlightSearchTerm(text, query) {
        if (!text || !query) return this.escapeHtml(text);

        const escapedText = this.escapeHtml(text);
        const escapedQuery = this.escapeHtml(query).replace(
            /[.*+?^${}()|[\]\\]/g,
            '\\$&'
        );
        const regex = new RegExp(`(${escapedQuery})`, 'gi');

        return escapedText.replace(regex, '<strong>$1</strong>');
    }

    /**
     * Render no results state
     */
    renderNoResults() {
        return `<div class="search-no-results">
            <div class="search-no-results-icon">🔍</div>
            <div class="search-no-results-text">No coasters found</div>
        </div>`;
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        if (!this.hasResultsTarget) return;
        this.resultsTarget.innerHTML = `<div class="search-loading">
            <div class="search-loading-spinner"></div>
            <div class="search-loading-text">Searching...</div>
        </div>`;
        this.showDropdown();
    }

    /**
     * Show error state
     */
    showErrorState(message) {
        if (!this.hasResultsTarget) return;
        this.resultsTarget.innerHTML = `<div class="search-error">
            <div class="search-error-icon">⚠️</div>
            <div class="search-error-text">${this.escapeHtml(message)}</div>
        </div>`;
        this.showDropdown();
    }

    /**
     * Show dropdown
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
     * Hide dropdown
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

        this.clearSelection();
    }

    /**
     * Handle keyboard navigation
     */
    handleKeydown(event) {
        if (!this.isOpen) return;

        const items = this.resultsTarget.querySelectorAll(
            '.search-result-item:not(.search-result-duplicate)'
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
                }
                break;

            case 'Escape':
                event.preventDefault();
                this.hideDropdown();
                break;
        }
    }

    /**
     * Update visual selection
     */
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

    /**
     * Clear selection
     */
    clearSelection() {
        const selectedItems = this.resultsTarget.querySelectorAll('.selected');
        selectedItems.forEach((item) => item.classList.remove('selected'));
    }

    /**
     * Set up click handlers
     */
    setupResultClickHandlers() {
        const resultItems = this.resultsTarget.querySelectorAll(
            '.search-result-item:not(.search-result-duplicate)'
        );
        resultItems.forEach((item) => {
            item.addEventListener('click', () => this.selectItem(item));
        });
    }

    /**
     * Handle result selection - add coaster to list
     */
    selectItem(item) {
        const coasterId = item.dataset.coasterId;
        const coasterName = item.dataset.coasterName;
        const parkName = item.dataset.parkName;
        const rating = item.dataset.rating;
        const isDuplicate = item.dataset.duplicate === 'true';

        if (isDuplicate) {
            return;
        }

        // Add coaster to the list
        this.addCoasterToList(coasterId, coasterName, parkName, rating);

        // Clear search input
        if (this.hasInputTarget) {
            this.inputTarget.value = '';
        }

        // Hide dropdown
        this.hideDropdown();

        // Reset state
        this.lastQuery = '';
    }

    /**
     * Add coaster to the top list
     */
    addCoasterToList(coasterId, coasterName, parkName, rating) {
        // Find the top list element
        const listElement = document.querySelector(
            `[data-controller~="${this.listControllerValue}"]`
        );
        if (!listElement) {
            console.error('Top list controller not found');
            return;
        }

        // Get current position (add to end)
        const existingItems = listElement.querySelectorAll(
            '[data-top-list-target="item"]'
        );
        const newPosition = existingItems.length + 1;

        // Clone an existing item as template if available
        if (existingItems.length > 0) {
            const newItem = existingItems[0].cloneNode(true);

            // Update the new item with coaster data
            newItem.dataset.coasterId = coasterId;
            newItem.dataset.position = newPosition;

            // Update position number
            const positionNumber = newItem.querySelector('.position-number');
            if (positionNumber) {
                positionNumber.textContent = newPosition;
            }

            // Update coaster name
            const coasterNameEl = newItem.querySelector('.coaster-name');
            if (coasterNameEl) {
                coasterNameEl.textContent = coasterName;
            }

            // Update park name
            const parkNameEl = newItem.querySelector('.coaster-park');
            if (parkNameEl) {
                parkNameEl.textContent = parkName;
            }

            // Update rating if provided
            let ratingEl = newItem.querySelector('.coaster-rating');

            if (rating && rating !== '' && rating !== '0') {
                if (!ratingEl) {
                    // Create rating element if it doesn't exist
                    ratingEl = document.createElement('span');
                    ratingEl.className = 'coaster-rating';
                    const coasterContent = newItem.querySelector(
                        '.coaster-content .coaster-main'
                    );
                    if (coasterContent && coasterContent.parentNode) {
                        coasterContent.parentNode.appendChild(ratingEl);
                    }
                }
                ratingEl.innerHTML = renderStarRating(rating);
            } else if (ratingEl) {
                ratingEl.remove();
            }

            // Add to list
            listElement.appendChild(newItem);

            // Show visual feedback
            this.showAddedFeedback(newItem);
        } else {
            // Fallback to creating from scratch if no items exist
            const newItem = this.createListItem(
                coasterId,
                coasterName,
                parkName,
                rating,
                newPosition
            );
            if (newItem) {
                listElement.appendChild(newItem);
                this.showAddedFeedback(newItem);
            }
        }

        // Update positions and trigger auto-save (should handle new coasters)
        const topListController =
            this.application.getControllerForElementAndIdentifier(
                listElement,
                this.listControllerValue
            );

        if (topListController) {
            // Update positions first
            if (typeof topListController.updatePositions === 'function') {
                topListController.updatePositions();
            }

            // Then trigger auto-save (backend should handle new coasters)
            if (typeof topListController.debouncedSave === 'function') {
                topListController.debouncedSave();
            }
        }
    }

    /**
     * Create a new list item by cloning the template
     */
    createListItem(coasterId, coasterName, parkName, rating, position) {
        // Get the hidden template
        const template = document.getElementById('coaster-item-template');
        if (!template) {
            console.error('Coaster item template not found');
            return null;
        }

        // Clone the template content
        const newItem = template.content.cloneNode(true).firstElementChild;

        // Update with coaster data
        newItem.dataset.coasterId = coasterId;
        newItem.dataset.position = position;

        // Update position number
        const positionNumber = newItem.querySelector('.position-number');
        if (positionNumber) {
            positionNumber.textContent = position;
        }

        // Update coaster name
        const coasterNameEl = newItem.querySelector('.coaster-name');
        if (coasterNameEl) {
            coasterNameEl.textContent = coasterName;
        }

        // Update park name
        const parkNameEl = newItem.querySelector('.coaster-park');
        if (parkNameEl) {
            parkNameEl.textContent = parkName;
        }

        // Update rating if provided
        const ratingEl = newItem.querySelector('.coaster-rating');
        if (ratingEl) {
            if (rating && rating !== '' && rating !== '0') {
                ratingEl.innerHTML = renderStarRating(rating);
            } else {
                ratingEl.remove();
            }
        }

        return newItem;
    }

    /**
     * Show visual feedback when coaster is added
     */
    showAddedFeedback(item) {
        // Add animation class
        item.classList.add('coaster-entry-added');

        // Scroll into view
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Remove animation class after animation completes
        setTimeout(() => {
            item.classList.remove('coaster-entry-added');
        }, 1000);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
