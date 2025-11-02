import { Controller } from '@hotwired/stimulus';

/**
 * Filter Controller - Modern form filtering with Stimulus outlets
 * 
 * Responsibilities:
 * - Listen for filter form changes (selects, inputs, toggles)
 * - Debounce text input changes
 * - Communicate with map controller via outlets
 * 
 * Usage:
 * <div data-controller="filter" data-filter-map-outlet=".map-container">
 *   <form><!-- filter inputs --></form>
 * </div>
 */
export default class extends Controller {
    static outlets = ['map'];
    static values = { 
        debounceDelay: { type: Number, default: 300 }
    };

    connect() {
        console.log('Filter controller connected');
        this.debounceTimer = null;
        
        // Use event delegation for better performance
        this.element.addEventListener('change', this.handleChange.bind(this));
        this.element.addEventListener('input', this.handleInput.bind(this));
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }

    // Handle immediate changes (selects, checkboxes)
    handleChange(event) {
        if (this.isFilterInput(event.target)) {
            console.log('Filter change detected:', event.target.name, event.target.value);
            this.updateMap();
        }
    }

    // Handle text input with debouncing
    handleInput(event) {
        if (this.isFilterInput(event.target) && event.target.type === 'text') {
            console.log('Filter input detected:', event.target.name, event.target.value);
            this.debouncedUpdate();
        }
    }

    // Check if element is a filter input
    isFilterInput(element) {
        return element.matches('select[name^="filters"]') || 
               element.matches('input[name^="filters"]') ||
               element.matches('.switchery');
    }

    // Debounced update for text inputs
    debouncedUpdate() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.updateMap();
        }, this.debounceDelayValue);
    }

    // Trigger map update via outlet
    updateMap() {
        console.log('Triggering map update, has outlet:', this.hasMapOutlet);
        if (this.hasMapOutlet) {
            this.mapOutlet.filterData();
        } else {
            console.warn('Map outlet not found - check data-filter-map-outlet selector');
        }
    }

    // Manual trigger (for external use)
    triggerUpdate() {
        this.updateMap();
    }
}