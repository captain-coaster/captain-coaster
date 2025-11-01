/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)
require("../css/icons/icomoon/styles.css");
require("../css/bootstrap.css");
require("../css/core.css");
require("../css/components.css");
require("../css/colors.css");

// Enhanced select component styles
import "../css/enhanced-select.css";

// Import jQuery and make it globally available for legacy scripts
import $ from "jquery";

// Make jQuery available globally for legacy scripts (as per Encore docs)
global.$ = global.jQuery = $;

// Import RateIt globally (used across multiple pages)
import "jquery.rateit";
import "jquery.rateit/scripts/rateit.css";

// Temporarily disable custom dropdown CSS to test Bootstrap dropdowns
// import "../css/dropdown-theme.css";

// Import modern PhotoSwipe globally (used for image lightboxes)
import PhotoSwipeLightbox from "photoswipe/lightbox";
import PhotoSwipe from "photoswipe";
import "photoswipe/style.css";

// Make PhotoSwipe available globally for legacy code
window.PhotoSwipeLightbox = PhotoSwipeLightbox;
window.PhotoSwipe = PhotoSwipe;

// Bootstrap Datepicker replaced with modern Stimulus solution

// Start the Stimulus application
import "../bootstrap";