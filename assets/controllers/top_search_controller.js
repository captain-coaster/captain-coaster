import { Controller } from '@hotwired/stimulus';

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
        debounceDelay: { type: Number, default: 300 }
    };

    connect() {
        console.log('Top Search controller connected');
        
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
            this.dropdownTarget.setAttribute('aria-label', 'Coaster search results');
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
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal
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
        const listElement = document.querySelector(`[data-controller~="${this.listControllerValue}"]`);
        if (listElement) {
            const items = listElement.querySelectorAll('[data-coaster-id]');
            items.forEach(item => {
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
        const formattedRating = rating ? (rating % 1 === 0 ? parseInt(rating) : rating) : null;
        
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
        const escapedQuery = this.escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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
        
        const items = this.resultsTarget.querySelectorAll('.search-result-item:not(.search-result-duplicate)');
        
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
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
                behavior: 'smooth'
            });
        }
    }

    /**
     * Clear selection
     */
    clearSelection() {
        const selectedItems = this.resultsTarget.querySelectorAll('.selected');
        selectedItems.forEach(item => item.classList.remove('selected'));
    }

    /**
     * Set up click handlers
     */
    setupResultClickHandlers() {
        const resultItems = this.resultsTarget.querySelectorAll('.search-result-item:not(.search-result-duplicate)');
        resultItems.forEach(item => {
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
            console.log('Coaster already in list');
            return;
        }
        
        console.log('Adding coaster to list:', coasterId, coasterName);
        
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
        const listElement = document.querySelector(`[data-controller~="${this.listControllerValue}"]`);
        if (!listElement) {
            console.error('Top list controller not found');
            return;
        }
        
        // Get current position (add to end)
        const existingItems = listElement.querySelectorAll('[data-top-list-target="item"]');
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
            
            // Handle rating - for now just remove it, server will handle on reload
            const ratingEl = newItem.querySelector('.coaster-rating');
            if (ratingEl) {
                ratingEl.remove();
            }
            
            // Add to list
            listElement.appendChild(newItem);
            
            // Show visual feedback
            this.showAddedFeedback(newItem);
        } else {
            // Fallback to creating from scratch if no items exist
            const newItem = this.createListItem(coasterId, coasterName, parkName, rating, newPosition);
            listElement.appendChild(newItem);
            this.showAddedFeedback(newItem);
        }
        
        // Trigger auto-save through the top list controller
        const topListController = this.application.getControllerForElementAndIdentifier(
            listElement, 
            this.listControllerValue
        );
        
        if (topListController && typeof topListController.debouncedSave === 'function') {
            topListController.debouncedSave();
        }
    }

    /**
     * Create a new list item element
     * Fallback method when no existing items to clone
     */
    createListItem(coasterId, coasterName, parkName, rating, position) {
        const li = document.createElement('li');
        li.className = 'coaster-entry';
        li.setAttribute('data-top-list-target', 'item');
        li.setAttribute('data-coaster-id', coasterId);
        li.setAttribute('data-position', position.toString());
        
        // Create star rating HTML
        let ratingHtml = '';
        if (rating) {
            const ratingValue = parseFloat(rating);
            const fullStars = Math.floor(ratingValue);
            const hasHalfStar = (ratingValue - fullStars) >= 0.5;
            
            ratingHtml = '<span class="coaster-rating"><span class="star-rating"><span class="text-warning star-rating-stars">';
            
            // Full stars
            const starSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" /></svg>`;
            
            for (let i = 0; i < fullStars; i++) {
                ratingHtml += starSvg;
            }
            
            // Half star
            if (hasHalfStar) {
                ratingHtml += `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6">
                    <defs>
                        <clipPath id="left-half-${coasterId}"><rect x="0" y="0" width="12" height="24"/></clipPath>
                        <clipPath id="right-half-${coasterId}"><rect x="12" y="0" width="12" height="24"/></clipPath>
                    </defs>
                    <path clip-path="url(#left-half-${coasterId})" fill="currentColor" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"/>
                    <path clip-path="url(#right-half-${coasterId})" fill="none" stroke="currentColor" stroke-width="1.5" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.563.563 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
                </svg>`;
            }
            
            ratingHtml += '</span></span></span>';
        }
        
        // Get heroicon SVG
        const dragIndicatorSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
        </svg>`;
        
        const cogSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>`;
        
        const arrowUpSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
        </svg>`;
        
        const arrowDownSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
        </svg>`;
        
        const arrowsUpDownSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
        </svg>`;
        
        const trashSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
        </svg>`;
        
        li.innerHTML = `
            <div class="drag-area">
                <div class="position-number">${position}</div>
                <div class="drag-indicator">
                    ${dragIndicatorSvg}
                </div>
            </div>
            <div class="coaster-content">
                <div class="coaster-main">
                    <span class="coaster-name">${this.escapeHtml(coasterName)}</span>
                    <span class="coaster-separator">•</span>
                    <span class="coaster-park">${this.escapeHtml(parkName)}</span>
                </div>
                ${ratingHtml}
            </div>
            <div class="coaster-menu">
                <div class="dropdown">
                    <button type="button" class="menu-btn" data-toggle="dropdown" aria-expanded="false">
                        ${cogSvg}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li>
                            <a href="#" data-action="click->top-list#moveToTop">
                                ${arrowUpSvg}
                                Move to top
                            </a>
                        </li>
                        <li>
                            <a href="#" data-action="click->top-list#moveToBottom">
                                ${arrowDownSvg}
                                Move to bottom
                            </a>
                        </li>
                        <li>
                            <a href="#" data-action="click->top-list#moveToPosition">
                                ${arrowsUpDownSvg}
                                Move to position...
                            </a>
                        </li>
                        <li class="dropdown-divider"></li>
                        <li>
                            <a href="#" class="text-danger" data-action="click->top-list#removeCoaster">
                                ${trashSvg}
                                Remove
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        `;
        
        return li;
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
