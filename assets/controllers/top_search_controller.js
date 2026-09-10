import { Controller } from '@hotwired/stimulus';
import { renderStarRating } from '../js/utils/star-rating';
import { SearchDropdown } from '../js/search-dropdown';

// stimulusFetch: 'lazy' — only used on the top-list edit page

/**
 * Top List Search Controller
 *
 * Specialized search component for adding coasters to Top Lists. Shared
 * debounce/fetch/keyboard-nav/dropdown behavior lives in SearchDropdown
 * (see assets/js/search-dropdown.js); this defines coaster search
 * rendering and integration with top_list_controller for adding results.
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
export default class extends SearchDropdown(Controller) {
    static values = {
        url: String,
        listController: { type: String, default: 'top-list' },
        minLength: { type: Number, default: 2 },
        debounceDelay: { type: Number, default: 300 },
    };

    get dropdownAriaLabel() {
        return 'Coaster search results';
    }

    get loadingText() {
        return 'Searching...';
    }

    buildSearchUrl(query) {
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('q', query);
        return url.toString();
    }

    getKeyboardNavItems() {
        return this.getClickableResultItems();
    }

    getClickableResultItems() {
        return this.resultsTarget.querySelectorAll(
            '.search-result-item:not(.search-result-duplicate)'
        );
    }

    /**
     * Update dropdown with search results
     */
    updateResults(data, query) {
        if (!this.hasResultsTarget) return;

        const items = data.items || [];

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

    renderNoResults() {
        return `<div class="search-no-results">
            <div class="search-no-results-icon">🔍</div>
            <div class="search-no-results-text">No coasters found</div>
        </div>`;
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

        this.addCoasterToList(coasterId, coasterName, parkName, rating);

        if (this.hasInputTarget) {
            this.inputTarget.value = '';
        }

        this.hideDropdown();
        this.lastQuery = '';
    }

    /**
     * Add coaster to the top list
     */
    addCoasterToList(coasterId, coasterName, parkName, rating) {
        const listElement = document.querySelector(
            `[data-controller~="${this.listControllerValue}"]`
        );
        if (!listElement) {
            console.error('Top list controller not found');
            return;
        }

        const existingItems = listElement.querySelectorAll(
            '[data-top-list-target="item"]'
        );
        const newPosition = existingItems.length + 1;

        if (existingItems.length > 0) {
            const newItem = existingItems[0].cloneNode(true);

            newItem.dataset.coasterId = coasterId;
            newItem.dataset.position = newPosition;

            const positionNumber = newItem.querySelector('.position-number');
            if (positionNumber) {
                positionNumber.textContent = newPosition;
            }

            const coasterNameEl = newItem.querySelector('.coaster-name');
            if (coasterNameEl) {
                coasterNameEl.textContent = coasterName;
            }

            const parkNameEl = newItem.querySelector('.coaster-park');
            if (parkNameEl) {
                parkNameEl.textContent = parkName;
            }

            let ratingEl = newItem.querySelector('.coaster-rating');

            if (rating && rating !== '' && rating !== '0') {
                if (!ratingEl) {
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

            listElement.appendChild(newItem);

            this.showAddedFeedback(newItem);
        } else {
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

        const topListController =
            this.application.getControllerForElementAndIdentifier(
                listElement,
                this.listControllerValue
            );

        if (topListController) {
            if (typeof topListController.updatePositions === 'function') {
                topListController.updatePositions();
            }

            if (typeof topListController.debouncedSave === 'function') {
                topListController.debouncedSave();
            }
        }
    }

    /**
     * Create a new list item by cloning the template
     */
    createListItem(coasterId, coasterName, parkName, rating, position) {
        const template = document.getElementById('coaster-item-template');
        if (!template) {
            console.error('Coaster item template not found');
            return null;
        }

        const newItem = template.content.cloneNode(true).firstElementChild;

        newItem.dataset.coasterId = coasterId;
        newItem.dataset.position = position;

        const positionNumber = newItem.querySelector('.position-number');
        if (positionNumber) {
            positionNumber.textContent = position;
        }

        const coasterNameEl = newItem.querySelector('.coaster-name');
        if (coasterNameEl) {
            coasterNameEl.textContent = coasterName;
        }

        const parkNameEl = newItem.querySelector('.coaster-park');
        if (parkNameEl) {
            parkNameEl.textContent = parkName;
        }

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
        item.classList.add('coaster-entry-added');
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        setTimeout(() => {
            item.classList.remove('coaster-entry-added');
        }, 1000);
    }
}
