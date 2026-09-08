/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Import jQuery first (required for Bootstrap 3.x compatibility)
import $ from 'jquery';

// Make jQuery available globally for legacy scripts and Bootstrap 3.x
window.$ = window.jQuery = $;

// Import Bootstrap 3.3.7 JavaScript components with proper module imports
// Only importing components that are actually used in the application
import 'bootstrap/js/alert'; // Used in flash messages and notifications
import 'bootstrap/js/transition'; // Required dependency for other components

// dropdown/collapse/modal (navbar, account menu, review report modal) were
// bootstrap/js/* jQuery plugins -- replaced by vanilla dropdown.js and the
// collapse/modal Stimulus controllers as part of the Navigation cluster
// migration (captain-coaster/captain-coaster#380).
import './dropdown';

// Import main stylesheet (includes Bootstrap 3.3.7 and custom theme)
import '../styles/app.less';

// Tailwind v4 — components migrated off Bootstrap/LESS land here.
// See the Phase 2 tracking issue: captain-coaster/captain-coaster#380.
import '../styles/tailwind.css';

// Import theme JavaScript files (migrated from public/js/core/) - Critical for layout
import './theme/theme';
import './theme/layout_fixed_custom';

// Module loading is now handled by individual Stimulus controllers

// Start the Stimulus application
import '../bootstrap';

// Components are now handled by Stimulus controllers

// Alert (flash message dismiss) still initializes via data attributes.
// Dropdown, collapse and modal are initialized via dropdown.js and
// Stimulus controllers - no manual initialization needed.
