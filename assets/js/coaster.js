/*
 * Coaster page specific JavaScript
 * 
 * This entry point is loaded only on coaster detail pages
 * and includes coaster-specific functionality
 */

// Import component-specific styles
import '../styles/components/summary-feedback.css';
import '../styles/components/rating-actions.css';
import '../styles/components/coaster-loading.css';

// Rating functionality is now handled by Stimulus rating controller
// No need to import pages/rating anymore

console.log('Coaster page JavaScript loaded');