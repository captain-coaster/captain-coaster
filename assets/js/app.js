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

// Import lazy loading utilities for code splitting
import {
    lazyLoadOnVisible,
    lazyLoadOnInteraction,
    preloadOnIdle,
    loadModules,
} from "./modules/lazy-loader";

// Import image optimization utilities
import {
    setupLazyLoading,
    preloadImages,
    supportsWebP,
} from "./modules/image-utils";

// Configure module loading strategy
const moduleConfig = {
    critical: [
        // No critical modules - all loaded synchronously above
    ],
    nonCritical: [
        {
            name: "PhotoSwipe",
            importFn: async () => {
                const [PhotoSwipeLightbox, PhotoSwipe] = await Promise.all([
                    import(
                        /* webpackChunkName: "photoswipe" */ "photoswipe/lightbox"
                    ),
                    import(/* webpackChunkName: "photoswipe" */ "photoswipe"),
                    import(
                        /* webpackChunkName: "photoswipe" */ "photoswipe/style.css"
                    ),
                ]);

                // Make PhotoSwipe available globally for legacy code
                window.PhotoSwipeLightbox = PhotoSwipeLightbox.default;
                window.PhotoSwipe = PhotoSwipe.default;

                // Dispatch event to notify that PhotoSwipe is ready
                window.dispatchEvent(new CustomEvent("photoswipe-ready"));

                return {
                    PhotoSwipeLightbox: PhotoSwipeLightbox.default,
                    PhotoSwipe: PhotoSwipe.default,
                };
            },
            trigger: {
                type: "visible",
                selector: "[data-photoswipe], .gallery, .image-gallery",
            },
            options: { cache: true },
        },
        {
            name: "Typeahead",
            importFn: () =>
                import(
                    /* webpackChunkName: "typeahead" */ "./plugins/typeahead.bundle.min"
                ),
            trigger: {
                type: "interaction",
                selector: '[data-typeahead], .typeahead, input[type="search"]',
                event: "focus",
            },
            options: {
                cache: true,
                onError: (error) => {
                    console.warn(
                        "Typeahead not available, search will work without autocomplete"
                    );
                },
            },
        },
    ],
};

// Initialize module loading
document.addEventListener("DOMContentLoaded", () => {
    loadModules(moduleConfig);

    // Initialize image optimization
    setupLazyLoading("img[data-src]");

    // Detect WebP support and add class to html element
    supportsWebP().then((supported) => {
        document.documentElement.classList.add(supported ? "webp" : "no-webp");
    });

    // Preload critical images (hero images, logos, etc.)
    const criticalImages = [
        // Add paths to critical images that should be preloaded
        // Example: '/build/images/logo.jpg'
    ];

    if (criticalImages.length > 0) {
        preloadImages(criticalImages);
    }
});

// Start the Stimulus application
import "../bootstrap";

// Import plugin loader for auto-initialization
import "./plugins/index";

// Import simple chart replacement
import "./plugins/rating-chart";

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
