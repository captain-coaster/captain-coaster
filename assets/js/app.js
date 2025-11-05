/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Import jQuery first (required for Bootstrap 3.x compatibility)
import $ from "jquery";

// Make jQuery available globally for legacy scripts and Bootstrap 3.x
global.$ = global.jQuery = $;

// Import Bootstrap 3.3.7 JavaScript components with proper module imports
import "bootstrap/js/dropdown";
import "bootstrap/js/modal";
import "bootstrap/js/tooltip";
import "bootstrap/js/popover";
import "bootstrap/js/collapse";
import "bootstrap/js/tab";
import "bootstrap/js/alert";
import "bootstrap/js/button";
import "bootstrap/js/transition";

// Import main stylesheet (includes Bootstrap 3.3.7 and custom theme)
import "../styles/app.less";

// Import RateIt globally (used across multiple pages)
import "jquery.rateit";
import "jquery.rateit/scripts/rateit.css";

// Import theme JavaScript files (migrated from public/js/core/) - Critical for layout
import "./theme/app";
import "./theme/layout_fixed_custom";

// Module loading is now handled by individual Stimulus controllers

// Start the Stimulus application
import "../bootstrap";

// Import toggle switch functionality
import "./toggle-switch";

// Components are now handled by Stimulus controllers

// Initialize Bootstrap components after DOM is ready
document.addEventListener("DOMContentLoaded", () => {
    // Initialize tooltips efficiently - only on elements that need them
    const tooltipElements = document.querySelectorAll(
        '[data-toggle="tooltip"]'
    );
    if (tooltipElements.length > 0) {
        $(tooltipElements).tooltip();
    }

    // Initialize popovers efficiently - only on elements that need them
    const popoverElements = document.querySelectorAll(
        '[data-toggle="popover"]'
    );
    if (popoverElements.length > 0) {
        $(popoverElements).popover();
    }

    // Global modal event handling for Bootstrap 3.x compatibility
    // This ensures modals work even without the modal controller
    $(document).on("shown.bs.modal", ".modal", function () {
        const autofocusElement = this.querySelector("[autofocus]");
        if (autofocusElement) {
            autofocusElement.focus();
        }
    });

    // Optimize modal backdrop clicks for better UX
    $(document).on("click.modal-backdrop", ".modal", function (event) {
        if (event.target === this) {
            $(this).modal("hide");
        }
    });
});
