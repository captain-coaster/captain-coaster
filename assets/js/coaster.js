/*
 * Coaster page specific JavaScript
 *
 * This entry point is loaded only on coaster detail pages
 * and includes coaster-specific functionality
 */

// Import component-specific styles. rating.css (action icons + star
// widget) is NOT imported here -- it's part of the global app.css bundle
// now (captain-coaster/captain-coaster#385 consolidation): User/list_
// ratings.html.twig renders the same rating-actions markup without
// loading this 'coaster' entry at all, so it has to be global.
import '../styles/summary-feedback.css';
import '../styles/coaster-loading.css';
import '../styles/score-card.css';
import '../styles/rating-distribution.css';
import '../styles/youtube-thumbnail.css';
