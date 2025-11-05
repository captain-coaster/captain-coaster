# Implementation Plan

- [x] 1. Set up core infrastructure and remove legacy dependencies
  - Remove jQuery sortable plugin dependencies from webpack configuration
  - Clean up old JavaScript files (html.sortable.min.js, jquery touch-punch)
  - Update Top edit template to remove jQuery-based drag/drop code
  - _Requirements: 1.1, 1.2, 5.4_

- [ ] 2. Create modern Stimulus-based drag and drop controller
- [x] 2.1 Implement base TopListController with native HTML5 drag/drop
  - Create assets/controllers/top_list_controller.js with Stimulus controller structure
  - Implement dragstart, dragover, drop event handlers using native HTML5 API
  - Add visual feedback for drag operations (ghost image, drop zones)
  - There MUST be exactly ONE drop zone between cards
  - _Requirements: 2.1, 2.2, 2.4_

- [ ] 2.2 Add mobile touch support and responsive interactions
  - Implement touch event handlers for mobile drag/drop
  - Add visual feedback for touch interactions (haptic-like feedback)
  - Prevent scroll interference during drag operations on mobile
  - _Requirements: 2.1, 2.2, 2.5_

- [ ] 2.3 Implement position management and visual updates
  - Create updatePositions() method to recalculate and display position numbers
  - Add smooth animations for position changes
  - Implement immediate UI updates when items are reordered
  - _Requirements: 1.5, 2.3_

- [ ]* 2.4 Write unit tests for drag/drop functionality
  - Test drag event handling and position calculations
  - Test mobile touch interaction scenarios
  - Test visual feedback and animation systems
  - _Requirements: 2.1, 2.2, 2.3_

- [ ] 3. Implement auto-save system with backend endpoints
- [ ] 3.1 Create auto-save AJAX endpoints in TopController
  - Add autoSave() method in TopController with JSON response
  - Implement batch position update in TopCoasterRepository
  - Add proper error handling and validation for auto-save requests
  - _Requirements: 1.4, 3.3_

- [ ] 3.2 Implement frontend auto-save with debouncing
  - Add debouncedSave() method with 2-second delay in Stimulus controller
  - Implement save status indicators (saving, saved, error states)
  - Add retry logic for failed save attempts
  - _Requirements: 1.4, 3.3_

- [ ] 3.3 Add error handling and user feedback for auto-save
  - Display save status to users (subtle indicators)
  - Handle network errors and retry mechanisms
  - Add fallback for offline scenarios
  - _Requirements: 1.4_

- [ ]* 3.4 Write tests for auto-save functionality
  - Test debouncing behavior and save timing
  - Test error handling and retry logic
  - Test backend endpoint validation and responses
  - _Requirements: 1.4, 3.3_

- [ ] 4. Implement enhanced coaster search and position insertion
- [ ] 4.1 Create position-aware coaster addition system
  - Modify addCoaster() method to accept target position parameter
  - Implement position insertion logic that shifts existing coasters
  - Update TopController addCoaster endpoint to handle position parameter
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 4.2 Enhance search interface with position selection
  - Update search UI to include position input field
  - Modify search results to show "Add at position X" functionality
  - Implement real-time position preview when adding coasters
  - _Requirements: 3.1, 3.2_

- [ ] 4.3 Display user ratings in coaster entries
  - Update coaster entry template to show user's rating for each coaster
  - Modify backend queries to include user rating data
  - Add rating display in search results
  - _Requirements: 3.4, 6.1_

- [ ]* 4.4 Write tests for search and insertion functionality
  - Test position insertion logic and coaster shifting
  - Test search autocomplete and rating display
  - Test edge cases for position boundaries
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 5. Implement rating-based import and bulk operations
- [ ] 5.1 Create rating-based import system
  - Add importByRating() method in TopController with rating filter options
  - Implement RiddenCoasterRepository query for rating-based coaster retrieval
  - Create import preview functionality showing coasters to be added
  - _Requirements: 4.1, 4.2, 4.4_

- [ ] 5.2 Add bulk operations interface
  - Implement clearAll() functionality with confirmation dialog
  - Add import options for 5-star, 4.5+, and 4+ rated coasters
  - Create bulk operation UI with clear action buttons
  - _Requirements: 4.3, 4.5, 5.1, 5.2_

- [ ] 5.3 Implement import preview and confirmation
  - Show preview of coasters to be imported before confirmation
  - Display import statistics (number of coasters, rating distribution)
  - Add cancel/confirm options for import operations
  - _Requirements: 4.3_

- [ ]* 5.4 Write tests for import and bulk operations
  - Test rating-based import queries and filtering
  - Test bulk operations and confirmation workflows
  - Test import preview accuracy and edge cases
  - _Requirements: 4.1, 4.2, 4.3, 5.1, 5.2_

- [ ] 6. Update templates and styling for modern interface
- [ ] 6.1 Redesign Top edit template with modern HTML structure
  - Replace jQuery-based template with Stimulus data attributes
  - Implement mobile-first responsive design using CSS Grid/Flexbox
  - Add proper semantic HTML for accessibility
  - _Requirements: 1.3, 6.1, 6.4_

- [ ] 6.2 Create modern CSS components for coaster entries
  - Design drag handle, position badges, and coaster info layout
  - Implement hover and focus states for interactive elements
  - Add smooth transitions and animations for drag operations
  - _Requirements: 2.1, 6.2, 6.3_

- [ ] 6.3 Implement responsive mobile-optimized styling
  - Create touch-friendly button sizes and spacing
  - Optimize layout for mobile screens and touch interactions
  - Add visual feedback for touch events and drag operations
  - _Requirements: 1.3, 2.2, 2.5, 6.3_

- [ ] 6.4 Add import and bulk operation UI components
  - Design import dialog with rating filter options
  - Create bulk operation buttons and confirmation modals
  - Implement save status indicators and loading states
  - _Requirements: 4.1, 5.1, 5.2, 6.2_

- [ ]* 6.5 Write CSS and template tests
  - Test responsive behavior across device sizes
  - Test accessibility compliance and keyboard navigation
  - Test visual consistency with existing design system
  - _Requirements: 1.3, 6.1, 6.4_

- [ ] 7. Performance optimization and cleanup
- [ ] 7.1 Optimize performance for large lists
  - Implement virtual scrolling or pagination for 1000+ coaster lists
  - Optimize DOM manipulation and event handling
  - Add performance monitoring and metrics
  - _Requirements: 1.1, 1.2, 5.3_

- [ ] 7.2 Remove legacy code and dependencies
  - Clean up old jQuery-based JavaScript files
  - Remove unused CSS classes and styles
  - Update webpack configuration to exclude old plugins
  - _Requirements: 5.4_

- [ ] 7.3 Add comprehensive error handling
  - Implement graceful degradation for JavaScript failures
  - Add user-friendly error messages and recovery options
  - Create fallback mechanisms for critical functionality
  - _Requirements: 1.4, 3.3_

- [ ]* 7.4 Write performance and integration tests
  - Test performance with large datasets (1000+ coasters)
  - Test memory usage and cleanup
  - Test cross-browser compatibility and mobile devices
  - _Requirements: 1.1, 1.2, 5.3_