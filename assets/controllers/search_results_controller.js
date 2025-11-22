import { Controller } from '@hotwired/stimulus';

/**
 * Search Results Controller - Handles interactions on the comprehensive search results page
 *
 * Responsibilities:
 * - Handle result item clicks and navigation
 * - Provide hover states and visual feedback
 * - Generate proper URLs for different entity types
 * - Maintain consistency with dropdown behavior
 *
 * Usage:
 * <div data-controller="search-results">
 *   <div data-search-results-target="resultItem"
 *        data-action="click->search-results#selectResult"
 *        data-type="coaster" data-id="123" data-slug="steel-vengeance">
 *     ...
 *   </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['resultItem'];

    connect() {
        console.log('Search results controller connected');

        // Add keyboard navigation support
        this.setupKeyboardNavigation();
    }

    disconnect() {
        // Clean up event listeners
        document.removeEventListener('keydown', this.handleKeydown);
    }

    /**
     * Set up keyboard navigation for accessibility
     */
    setupKeyboardNavigation() {
        this.selectedIndex = -1;

        // Add keyboard event listener
        this.handleKeydown = (event) => {
            if (!this.hasResultItemTargets) return;

            const items = this.resultItemTargets;

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
                        this.selectResult({
                            target: items[this.selectedIndex],
                        });
                    }
                    break;

                case 'Escape':
                    event.preventDefault();
                    this.clearSelection();
                    break;
            }
        };

        document.addEventListener('keydown', this.handleKeydown);
    }

    /**
     * Handle result item selection
     */
    selectResult(event) {
        const item = event.target.closest('[data-type]');
        if (!item) return;

        const type = item.dataset.type;
        const slug = item.dataset.slug;
        const id = item.dataset.id;

        if (!type || !slug) {
            console.error(
                'Missing required data attributes for result selection'
            );
            return;
        }

        // Generate the appropriate URL
        const url = this.generateRoute(type, { slug, id });
        if (url) {
            // Add visual feedback before navigation
            item.classList.add('search-result-item-selected');

            // Navigate after a brief delay for visual feedback
            setTimeout(() => {
                window.location.href = url;
            }, 100);
        }
    }

    /**
     * Update visual selection highlighting for keyboard navigation
     */
    updateSelection(items) {
        // Clear previous selection
        this.clearSelection();

        // Highlight current selection
        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].classList.add(
                'search-result-item-keyboard-selected'
            );

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
        const selectedItems = this.element.querySelectorAll(
            '.search-result-item-keyboard-selected'
        );
        selectedItems.forEach((item) =>
            item.classList.remove('search-result-item-keyboard-selected')
        );
        this.selectedIndex = -1;
    }

    /**
     * Generate route URL for different entity types using Symfony's routing system
     */
    generateRoute(type, params = {}) {
        // Use Symfony's FOSJsRoutingBundle - this should be the primary method
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                let routeName;
                switch (type) {
                    case 'coaster':
                        routeName = 'show_coaster';
                        break;
                    case 'park':
                        routeName = 'park_show';
                        break;
                    case 'user':
                        routeName = 'user_show';
                        break;
                    default:
                        return null;
                }

                return Routing.generate(routeName, {
                    ...params,
                    _locale: document.documentElement.lang || 'en',
                });
            } catch (error) {
                console.error(
                    'Routing.generate failed for route:',
                    routeName,
                    'with params:',
                    params,
                    error
                );
                return null;
            }
        }

        // If Routing is not available, log error and return null
        console.error(
            'FOSJsRoutingBundle not available. Cannot generate route for type:',
            type
        );
        return null;
    }

    /**
     * Handle mouse enter for hover effects
     */
    resultItemTargetConnected(element) {
        // Add hover event listeners
        element.addEventListener('mouseenter', () => {
            element.classList.add('search-result-item-hover');
        });

        element.addEventListener('mouseleave', () => {
            element.classList.remove('search-result-item-hover');
        });
    }

    /**
     * Clean up when result item is disconnected
     */
    resultItemTargetDisconnected(element) {
        // Clean up hover classes
        element.classList.remove(
            'search-result-item-hover',
            'search-result-item-selected',
            'search-result-item-keyboard-selected'
        );
    }
}
