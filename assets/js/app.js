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

// Every bootstrap/js/* plugin this app used is now gone: dropdown/collapse/
// modal (navbar, account menu, review report modal) were replaced by vanilla
// dropdown.js and Stimulus controllers as part of the Navigation cluster;
// alert (flash message dismiss) by alert_controller.js as part of the
// post-Phase-2 Bootstrap removal (captain-coaster/captain-coaster#380).
// `bootstrap/js/transition` was only ever a shared dependency for those
// plugins' own transition-detection -- nothing left needs it.
import './dropdown';

// Import main stylesheet (includes Bootstrap 3.3.7 and custom theme)
import '../styles/app.less';

// Tailwind v4 — components migrated off Bootstrap/LESS land here.
// See the Phase 2 tracking issue: captain-coaster/captain-coaster#380.
import '../styles/tailwind.css';

// Import theme JavaScript files (migrated from public/js/core/) - Critical for layout
// Sidebar toggling/hover-expand (formerly theme.js + layout_fixed_custom.js)
// is now sidebar_controller.js as part of the Sidebar cluster migration
// (captain-coaster/captain-coaster#380).
import './theme/theme';

// Module loading is now handled by individual Stimulus controllers

// Start the Stimulus application
import '../bootstrap';

// Components are now handled by Stimulus controllers
