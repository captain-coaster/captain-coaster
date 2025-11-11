# Implementation Plan

- [x] 1. Install and configure SortableJS
  - Install sortablejs package via npm
  - Verify package is added to package.json
  - _Requirements: 5.1_

- [ ] 2. Refactor Stimulus controller for SortableJS integration
- [x] 2.1 Update top_list_controller.js to use SortableJS
  - Remove custom drag-and-drop implementation
  - Import and initialize SortableJS in connect() method
  - Configure SortableJS with handle, delay, and touch options
  - Implement onStart, onEnd, and onMove callbacks
  - Keep existing auto-save functionality with debouncing
  - Keep existing removeCoaster method
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 8.1, 8.2_

- [x] 2.2 Add quick action methods to controller
  - Implement moveToTop method
  - Implement moveToBottom method
  - Implement moveToPosition method with prompt
  - Update positions and trigger auto-save after each action
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 3. Update CSS for SortableJS styling
- [x] 3.1 Update top-list.css for SortableJS classes
  - Add styles for .sortable-ghost (placeholder)
  - Add styles for .sortable-chosen (selected item)
  - Add styles for .sortable-drag (dragged item)
  - Remove custom drop-zone styles (no longer needed)
  - Keep existing mobile optimizations and accessibility styles
  - Ensure touch-friendly target sizes (44x44px minimum)
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 10.2, 13.1, 13.2, 13.3_

- [x] 4. Create search component for adding coasters
- [x] 4.1 Create top_search_controller.js Stimulus controller
  - Implement search input with debouncing (300ms)
  - Implement AJAX search using existing endpoint
  - Display search results with coaster name, park name, and user rating
  - Handle result selection and add to list
  - Prevent duplicate coasters
  - Show visual feedback when coaster is added
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 4.2 Add CSS for search component
  - Reuse existing search.css classes where possible
  - Add any additional styles needed for Top List context
  - Ensure mobile-friendly design
  - _Requirements: 9.1, 9.2, 13.1, 13.2_

- [x] 5. Refactor edit.html.twig template
- [x] 5.1 Update template structure for new components
  - Add search component with top-search controller
  - Update coaster entry structure with new actions
  - Add quick actions dropdown menu to each entry
  - Update drag handle to work with SortableJS
  - Ensure proper Stimulus data attributes
  - Remove legacy jQuery code and comments
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 11.2, 11.4, 12.3_

- [x] 5.2 Optimize template for mobile-first design
  - Use responsive Bootstrap classes
  - Ensure touch-friendly spacing and sizing
  - Test layout on mobile viewport sizes
  - _Requirements: 10.1, 10.3, 10.4, 10.5_

- [x] 6. Update backend for search functionality
- [x] 6.1 Verify existing search endpoint returns user ratings
  - Check CoasterRepository::suggestCoasterForTop method
  - Ensure it includes user's rating in response
  - Update if necessary to include rating data
  - _Requirements: 1.2_

- [ ] 7. Manual testing and refinement
- [ ] 7.1 Test on mobile devices
  - Test on iOS Safari (iPhone)
  - Test on Android Chrome
  - Test on iPad
  - Verify long-press works correctly (500ms delay)
  - Verify scrolling doesn't trigger drag
  - Test portrait and landscape orientations
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ] 7.2 Test on desktop browsers
  - Test on Chrome (latest)
  - Test on Firefox (latest)
  - Test on Safari (latest)
  - Verify mouse drag works smoothly
  - Test keyboard navigation
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 7.3 Test all functionality
  - Search and add coasters
  - Drag and drop reordering
  - Quick actions (move to top/bottom/position)
  - Delete coasters
  - Auto-save triggers correctly
  - Error handling and retry
  - Duplicate prevention
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.1, 3.2, 3.3, 3.4, 6.1, 6.2, 6.3, 6.4, 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 7.4 Test accessibility features
  - Keyboard navigation works
  - Focus indicators visible
  - Reduced motion respected
  - High contrast mode supported
  - Touch targets meet 44x44px minimum
  - _Requirements: 10.2, 11.2_

- [x] 8. Add translations for new UI elements
  - Add translation keys for search placeholder
  - Add translation keys for quick actions menu items
  - Add translation keys for error messages
  - Add translation keys for save status indicators
  - Translate to all 4 languages (English, French, Spanish, German)
  - _Requirements: 1.1, 6.1, 6.2, 6.3, 8.3, 8.4_
