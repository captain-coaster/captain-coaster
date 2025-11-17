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
// Only importing components that are actually used in the application
import "bootstrap/js/dropdown";  // Used in navbar (language, notifications, user profile)
import "bootstrap/js/collapse";  // Used in mobile navbar toggle
import "bootstrap/js/alert";     // Used in flash messages and notifications
import "bootstrap/js/modal";     // Used in review report modal
import "bootstrap/js/transition"; // Required dependency for other components

// Import main stylesheet (includes Bootstrap 3.3.7 and custom theme)
import "../styles/app.less";

// Import theme JavaScript files (migrated from public/js/core/) - Critical for layout
import "./theme/theme";
import "./theme/layout_fixed_custom";

// Module loading is now handled by individual Stimulus controllers

// Start the Stimulus application
import "../bootstrap";

// Import toggle switch functionality
import "./toggle-switch";

// Components are now handled by Stimulus controllers

// Bootstrap components (dropdown, collapse, alert, modal) are initialized automatically
// via data attributes and Stimulus controllers - no manual initialization needed
