# Requirements Document

## Introduction

This document outlines the requirements for refactoring the Top List interface in Captain Coaster. The current implementation is broken and needs to be rebuilt from scratch with a modern, minimalist approach. The feature allows users to create and manage personalized rankings of roller coasters they've ridden. The interface must prioritize mobile experience (75% of users) while maintaining desktop functionality, with a focus on simplicity and intuitive interactions.

## Glossary

- **Top List**: A user-created ranked list of roller coasters
- **Top List Interface**: The UI component that allows users to edit their Top List
- **Coaster Entry**: A single coaster item within a Top List, displaying position, name, park, and rating
- **Search Component**: The interface element that allows users to search and add coasters to their list
- **Position**: The numerical rank of a coaster within the Top List (1 being the highest)
- **User Rating**: The rating value (0-10) that the current user has given to a specific coaster
- **Drag Handle**: The visual element users interact with to initiate drag operations
- **Auto-save System**: The mechanism that automatically persists changes to the server without manual save action
- **SortableJS**: A JavaScript library for drag-and-drop functionality
- **Touch Display**: Mobile devices with touch-based interaction (phones, tablets)

## Requirements

### Requirement 1: Search-First Coaster Addition

**User Story:** As a user, I want to search for coasters and quickly add them to my Top List, so that I can build my list efficiently without scrolling through long dropdowns.

#### Acceptance Criteria

1. WHEN the user types in the search field, THE Search Component SHALL display matching coaster results in real-time
2. WHEN search results are displayed, THE Search Component SHALL show the coaster name, park name, and the user's current rating for each result
3. WHEN the user selects a coaster from search results, THE Top List Interface SHALL add the coaster to the list immediately
4. WHEN a coaster is added, THE Top List Interface SHALL display a visual indication that the coaster has been added
5. WHEN a coaster already exists in the list, THE Search Component SHALL indicate this in the search results to prevent duplicates

### Requirement 2: Clear Visual Display of Coaster Information

**User Story:** As a user, I want to see essential information about each coaster in my list at a glance, so that I can make informed decisions about their ranking.

#### Acceptance Criteria

1. THE Coaster Entry SHALL display the position number prominently
2. THE Coaster Entry SHALL display the coaster name as the primary text
3. THE Coaster Entry SHALL display the park name as secondary information
4. THE Coaster Entry SHALL display the user's current rating as a visual indicator
5. WHEN the user has not rated a coaster, THE Coaster Entry SHALL display "N/A" or equivalent indicator

### Requirement 3: Drag-and-Drop Reordering with Touch Support

**User Story:** As a mobile user, I want to reorder coasters in my list using touch gestures, so that I can manage my rankings on my phone or tablet.

#### Acceptance Criteria

1. WHEN the user performs a long-press on a Drag Handle, THE Top List Interface SHALL initiate drag mode for that Coaster Entry
2. WHILE in drag mode, THE Top List Interface SHALL provide visual feedback showing the item being dragged
3. WHEN the user drags a Coaster Entry to a new position, THE Top List Interface SHALL update the position numbers of all affected entries
4. WHEN the user releases the drag, THE Top List Interface SHALL finalize the new position
5. THE Top List Interface SHALL support both mouse-based dragging on desktop and touch-based dragging on mobile devices

### Requirement 4: Long-Press to Prevent Accidental Drags

**User Story:** As a mobile user, I want to use long-press to initiate dragging, so that I can scroll through my list without accidentally moving items.

#### Acceptance Criteria

1. WHEN the user taps and immediately drags, THE Top List Interface SHALL interpret this as a scroll gesture, not a drag operation
2. WHEN the user presses and holds for more than 500 milliseconds, THE Top List Interface SHALL activate drag mode
3. WHILE drag mode is not active, THE Top List Interface SHALL allow normal scrolling behavior
4. WHEN drag mode is activated, THE Top List Interface SHALL provide haptic or visual feedback (where supported)

### Requirement 5: SortableJS Integration (If Beneficial)

**User Story:** As a developer, I want to evaluate and potentially use SortableJS, so that we can leverage a proven library if it provides significant improvements over a custom implementation.

#### Acceptance Criteria

1. IF SortableJS provides better touch support than a custom implementation, THEN THE Top List Interface SHALL use SortableJS for drag-and-drop functionality
2. IF SortableJS provides better accessibility features, THEN THE Top List Interface SHALL use SortableJS
3. IF a custom implementation is simpler and meets all requirements, THEN THE Top List Interface SHALL use the custom implementation
4. THE Top List Interface SHALL maintain the same user experience regardless of the underlying implementation

### Requirement 6: Quick Position Jump Actions

**User Story:** As a user, I want quick actions to move coasters to specific positions, so that I can efficiently organize my list without multiple drag operations.

#### Acceptance Criteria

1. THE Coaster Entry SHALL provide an action to move the coaster to position 1 (top of list)
2. THE Coaster Entry SHALL provide an action to move the coaster to the last position (bottom of list)
3. THE Coaster Entry SHALL provide an action to enter a specific position number
4. WHEN the user enters a specific position, THE Top List Interface SHALL move the coaster to that position and adjust other entries accordingly
5. WHEN a position action is triggered, THE Top List Interface SHALL update all position numbers immediately

### Requirement 7: Coaster Deletion

**User Story:** As a user, I want to remove coasters from my Top List, so that I can keep my list current and accurate.

#### Acceptance Criteria

1. THE Coaster Entry SHALL provide a delete action that is easily accessible
2. WHEN the user triggers the delete action, THE Top List Interface SHALL remove the coaster from the list immediately
3. WHEN a coaster is deleted, THE Top List Interface SHALL update the position numbers of remaining entries
4. WHEN a coaster is deleted, THE Auto-save System SHALL persist the change to the server
5. THE Top List Interface SHALL not require confirmation for deletion to maintain simplicity

### Requirement 8: Automatic Save Functionality

**User Story:** As a user, I want my changes to be saved automatically, so that I don't lose my work if I navigate away or close the browser.

#### Acceptance Criteria

1. WHEN the user makes any change to the list, THE Auto-save System SHALL save the changes to the server within 2 seconds
2. WHEN multiple changes occur rapidly, THE Auto-save System SHALL debounce save requests to avoid excessive server calls
3. WHEN a save is in progress, THE Top List Interface SHALL display a subtle saving indicator
4. WHEN a save completes successfully, THE Top List Interface SHALL display a brief success indicator
5. IF a save fails, THE Auto-save System SHALL retry the save operation after a delay

### Requirement 9: Modern and Minimalist Design

**User Story:** As a user, I want a clean and uncluttered interface, so that I can focus on managing my rankings without distraction.

#### Acceptance Criteria

1. THE Top List Interface SHALL use a minimalist design with clear visual hierarchy
2. THE Top List Interface SHALL use whitespace effectively to separate elements
3. THE Top List Interface SHALL use consistent iconography for actions
4. THE Top List Interface SHALL follow the existing Captain Coaster design system
5. THE Top List Interface SHALL be responsive and adapt to different screen sizes

### Requirement 10: Mobile-First Implementation

**User Story:** As a mobile user (representing 75% of users), I want the interface to work flawlessly on my phone, so that I can manage my Top List on the go.

#### Acceptance Criteria

1. THE Top List Interface SHALL be designed and tested for mobile devices first
2. THE Top List Interface SHALL use touch-friendly target sizes (minimum 44x44 pixels)
3. THE Top List Interface SHALL work smoothly on devices with screen widths from 320px to 768px
4. THE Top List Interface SHALL maintain functionality on desktop devices with screen widths above 768px
5. THE Top List Interface SHALL handle both portrait and landscape orientations on mobile devices

### Requirement 11: Stimulus Best Practices

**User Story:** As a developer, I want the implementation to follow Stimulus best practices, so that the code is maintainable and consistent with the rest of the application.

#### Acceptance Criteria

1. THE Top List Interface SHALL be implemented as a Stimulus controller
2. THE Stimulus controller SHALL use targets for DOM element references
3. THE Stimulus controller SHALL use values for configuration parameters
4. THE Stimulus controller SHALL use actions for event handling
5. THE Stimulus controller SHALL avoid jQuery and use modern JavaScript APIs

### Requirement 12: Separation of Concerns

**User Story:** As a developer, I want CSS and JavaScript to be in separate files, so that the code is organized and maintainable.

#### Acceptance Criteria

1. THE Top List Interface SHALL have all styles defined in a dedicated CSS file
2. THE Top List Interface SHALL have all behavior defined in a Stimulus controller JavaScript file
3. THE Twig template SHALL contain only HTML structure and Stimulus data attributes
4. THE implementation SHALL not include inline styles or inline JavaScript
5. THE CSS file SHALL be organized with clear sections and comments

### Requirement 13: CSS Reuse and Extension

**User Story:** As a developer, I want to reuse existing CSS classes from the application, so that the interface is consistent and we minimize code duplication.

#### Acceptance Criteria

1. THE Top List Interface SHALL prioritize using existing CSS classes from Bootstrap and the theme
2. THE Top List Interface SHALL reuse existing component styles where applicable
3. WHEN existing styles do not meet requirements, THE Top List Interface SHALL add new CSS rules in a dedicated component file
4. THE new CSS SHALL follow the existing naming conventions and organization patterns
5. THE implementation SHALL document which existing classes are being reused
