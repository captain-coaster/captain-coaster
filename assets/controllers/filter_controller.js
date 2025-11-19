import { Controller } from '@hotwired/stimulus';

/**
 * Unified Filter Controller - Handles filtering for ranking, nearby, and map pages
 * 
 * Configurable via data attributes:
 * - data-filter-endpoint-value: AJAX endpoint URL
 * - data-filter-container-id-value: Target container for results
 * - data-filter-update-url-value: Enable browser URL updates
 * - data-filter-debounce-delay-value: Debounce delay for text inputs
 */
export default class extends Controller {
    static outlets = ['map'];
    static values = {
        endpoint: String,
        containerId: String,
        updateUrl: { type: Boolean, default: false },
        debounceDelay: { type: Number, default: 300 },
        mapOutlet: String
    };

    connect() {
        this.debounceTimer = null;
        this.setupEventListeners();
        
        // Make controller accessible
        this.element.filterController = this;
        
        // Set up popstate handling if URL updates are enabled
        if (this.updateUrlValue) {
            this.setupPopstateHandler();
        }
        
        // Auto-trigger initial load if endpoint is provided
        if (this.hasEndpointValue) {
            this.filterData();
        }
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        // Clean up popstate listener
        if (this.boundPopstateHandler) {
            window.removeEventListener('popstate', this.boundPopstateHandler);
        }
    }

    setupEventListeners() {
        // Use event delegation for better performance
        this.element.addEventListener('change', this.handleChange.bind(this));
        this.element.addEventListener('input', this.handleInput.bind(this));
    }

    setupPopstateHandler() {
        this.boundPopstateHandler = this.handlePopState.bind(this);
        window.addEventListener('popstate', this.boundPopstateHandler);
    }

    handleChange(event) {
        if (this.isFilterInput(event.target)) {
            this.filterData();
        }
    }

    handleInput(event) {
        if (this.isFilterInput(event.target) && event.target.type === 'text') {
            this.debouncedFilterData();
        }
    }

    isFilterInput(element) {
        return element.matches('select[name^="filters"]') || 
               element.matches('input[name^="filters"]') ||
               element.matches('input[name="page"]');
    }

    debouncedFilterData() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.filterData();
        }, this.debounceDelayValue);
    }

    async filterData() {
        // Handle map outlet (for map pages)
        if (this.hasMapOutlet) {
            this.mapOutlet.filterData();
            return;
        }

        // Handle AJAX filtering (for ranking/nearby pages)
        if (!this.hasEndpointValue || !this.hasContainerIdValue) {
            return;
        }

        try {
            const form = this.element.querySelector('form');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            
            const response = await fetch(`${this.endpointValue}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.text();
            document.getElementById(this.containerIdValue).innerHTML = data;
            
            this.setupPagination();
            
            if (this.updateUrlValue) {
                this.updateBrowserUrl();
            }
            
        } catch (error) {
            console.error('Filter request failed:', error);
        }
    }

    setupPagination() {
        const container = document.getElementById(this.containerIdValue);
        if (!container) return;
        
        container.querySelectorAll('ul.pagination a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('page');
                
                const pageInput = this.element.querySelector('input[name="page"]');
                if (pageInput) {
                    pageInput.value = page || 1;
                }
                
                this.filterData().then(() => {
                    container.scrollIntoView({ behavior: 'smooth' });
                });
            });
        });
    }

    updateBrowserUrl() {
        const form = this.element.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        // Only include non-empty values (exclude user field)
        for (const [key, value] of formData.entries()) {
            if (value && value.trim() !== '' && !key.includes('[user]')) {
                params.set(key, value);
            }
        }
        
        const queryString = params.toString();
        const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
        
        window.history.pushState(null, '', newUrl);
    }

    // Handle browser back/forward
    handlePopState() {
        if (!this.updateUrlValue) return;
        
        const params = new URLSearchParams(window.location.search);
        const form = this.element.querySelector('form');
        
        // Reset form
        form.reset();
        
        // Apply URL parameters to form
        for (const [key, value] of params.entries()) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = value === 'on';
                } else {
                    input.value = value;
                }
            }
        }
        
        this.filterData();
    }
}