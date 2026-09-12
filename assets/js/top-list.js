/*
 * Top List page specific JavaScript
 *
 * This entry point is loaded only on top list edit pages
 * and includes top list-specific functionality:
 * - SortableJS for drag-and-drop
 * - Top list Stimulus controllers
 * - Top list specific styles
 */

// Import top list specific styles. Used to also be imported globally via
// app.css, redundantly -- its classes (coaster-entry, drag-area,
// top-search-container, ...) only ever render on this page (Top/edit.html.twig)
// (captain-coaster/captain-coaster#385 consolidation).
import '../styles/top-list.css';
