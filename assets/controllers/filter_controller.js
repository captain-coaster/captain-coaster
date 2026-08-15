import BaseController from './base_controller.js';

/**
 * Filter Controller
 *
 * Handles filter form changes and dispatches to the appropriate target:
 * - Map page: calls mapController.filterData()
 * - Search/Ranking pages: fetches HTML from endpoint and replaces container content
 *
 * Also supports client-side text filtering when no endpoint is configured.
 *
 * Usage (map):
 * <aside data-controller="filter-sidebar filter"
 *        data-filter-map-outlet=".map-container"
 *        data-filter-update-url-value="true">
 *
 * Usage (search/ranking):
 * <aside data-controller="filter-sidebar filter"
 *        data-filter-endpoint-value="/api/search"
 *        data-filter-container-id-value="search-result"
 *        data-filter-update-url-value="true">
 *
 * Usage (client-side text filter):
 * <div data-controller="filter">
 *   <input data-filter-target="input" data-action="input->filter#filter">
 *   <div data-filter-target="list">
 *     <div data-filter-target="item" data-filter-text="...">...</div>
 *   </div>
 *   <div class="hidden" data-filter-target="empty">No results</div>
 * </div>
 */
export default class extends BaseController {
    static values = {
        mapOutlet: { type: String, default: '' },
        endpoint: { type: String, default: '' },
        containerId: { type: String, default: '' },
        updateUrl: { type: Boolean, default: false },
        debounceDelay: { type: Number, default: 400 },
    };

    static targets = ['input', 'list', 'item', 'empty'];

    connect() {
        // Make controller accessible externally
        this.element.filterController = this;
        this.debounceTimer = null;

        // If we have an endpoint or map outlet, set up form change listeners
        if (this.endpointValue || this.mapOutletValue) {
            this.setupFormListeners();
        }

        // Intercept pagination clicks inside the results container so paging
        // stays on the public route and loads in place (the async endpoint is
        // XHR-only and would 404 on a full navigation).
        if (this.endpointValue && this.containerIdValue) {
            this.container = document.getElementById(this.containerIdValue);
            if (this.container) {
                this.boundPaginationClick = this.handlePaginationClick.bind(this);
                this.container.addEventListener(
                    'click',
                    this.boundPaginationClick
                );
            }
        }
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        if (this.container && this.boundPaginationClick) {
            this.container.removeEventListener(
                'click',
                this.boundPaginationClick
            );
        }
    }

    /**
     * Handle a click on a pagination link rendered inside the results container.
     */
    handlePaginationClick(event) {
        const link = event.target.closest('a[data-page]');
        if (!link || !this.container.contains(link)) return;

        event.preventDefault();
        this.applyFilters(parseInt(link.dataset.page, 10) || 1);
        this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Set up change/input listeners on the filter form
     */
    setupFormListeners() {
        const form =
            this.element.querySelector('form') ||
            document.getElementById('form-filter');
        if (!form) return;

        // Listen for changes on selects and checkboxes (immediate)
        form.addEventListener('change', () => this.debouncedApplyFilters());

        // Listen for text input (debounced)
        form.querySelectorAll('input[type="text"]').forEach((input) => {
            input.addEventListener('input', () => this.debouncedApplyFilters());
        });
    }

    /**
     * Debounced filter application
     */
    debouncedApplyFilters() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.debounceTimer = setTimeout(() => {
            this.applyFilters();
        }, this.debounceDelayValue);
    }

    /**
     * Apply filters — route to the correct handler.
     * @param {number} page — page to load (defaults to 1; a filter change resets paging)
     */
    applyFilters(page = 1) {
        if (this.mapOutletValue) {
            this.applyMapFilters();
        } else if (this.endpointValue) {
            this.applyAjaxFilters(page);
        }

        if (this.updateUrlValue) {
            this.updateBrowserUrl(page);
        }
    }

    /**
     * Map filtering: call mapController.filterData()
     */
    applyMapFilters() {
        const mapElement = document.querySelector(this.mapOutletValue);
        if (mapElement && mapElement.mapController) {
            mapElement.mapController.filterData();
        }
    }

    /**
     * Build URL params from the filter form, overriding the page.
     */
    buildParams(page = 1) {
        const form = this.element.querySelector('form');
        if (!form) return null;

        const params = new URLSearchParams();
        for (const [key, value] of new FormData(form).entries()) {
            if (value && value.trim() !== '') {
                params.set(key, value);
            }
        }
        params.set('page', String(page));
        return params;
    }

    /**
     * AJAX filtering: fetch HTML from endpoint and replace container
     */
    applyAjaxFilters(page = 1) {
        const params = this.buildParams(page);
        if (!params) return;

        fetch(`${this.endpointValue}?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Filter request failed: ${response.status}`);
                }
                return response.text();
            })
            .then((html) => {
                const container = document.getElementById(
                    this.containerIdValue
                );
                if (container) {
                    container.innerHTML = html;
                }
            })
            .catch((error) => {
                console.error('Filter request failed:', error);
            });
    }

    /**
     * Update browser URL with current filter params
     */
    updateBrowserUrl(page = 1) {
        const params = this.buildParams(page);
        if (!params) return;

        // Drop page=1 from the URL to keep it clean.
        if (params.get('page') === '1') {
            params.delete('page');
        }

        const newUrl = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.replaceState({}, '', newUrl);
    }

    /**
     * Client-side text filter (legacy behavior)
     */
    filter() {
        const query = this.hasInputTarget
            ? this.inputTarget.value.toLowerCase().trim()
            : '';

        let visibleCount = 0;

        this.itemTargets.forEach((item) => {
            const text = (
                item.dataset.filterText || item.textContent
            ).toLowerCase();
            const matches = !query || text.includes(query);
            item.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', visibleCount > 0);
        }
    }
}
