import { Controller } from '@hotwired/stimulus';

/**
 * Global Controller
 *
 * Handles global actions that need to work from anywhere in the DOM.
 * Attach to body or a high-level element.
 *
 * Usage:
 * <body data-controller="global">
 *   <button data-action="global#openFilterSidebar">Open Filters</button>
 *   <button data-action="global#openModal" data-global-modal-id-param="searchModal">Search</button>
 * </body>
 */
export default class extends Controller {
    /**
     * Open filter sidebar by dispatching event
     */
    openFilterSidebar() {
        document.dispatchEvent(new CustomEvent('filter-sidebar:open'));
    }

    /**
     * Open modal by ID
     * @param {Event} event - Click event with modal-id param
     */
    openModal(event) {
        const id = event.params?.modalId;
        if (id) {
            document.dispatchEvent(
                new CustomEvent('modal:open', { detail: { id } })
            );
        }
    }
}
