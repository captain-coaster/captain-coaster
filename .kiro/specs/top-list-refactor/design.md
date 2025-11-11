# Design Document

## Overview

This document outlines the technical design for refactoring the Top List interface in Captain Coaster. The refactored interface will provide a modern, mobile-first experience for users to create and manage their personalized roller coaster rankings. The design prioritizes simplicity, reusability of existing components, and follows Stimulus best practices.

### Key Design Principles

1. **Mobile-First**: Design for touch devices first (75% of users), then enhance for desktop
2. **Simplicity**: Minimal code, clear intent, avoid over-engineering
3. **Reusability**: Leverage existing CSS classes and components where possible
4. **Progressive Enhancement**: Core functionality works without JavaScript, enhanced with Stimulus

### Technology Decisions

**Drag-and-Drop Implementation**: We will use **SortableJS** for the following reasons:
- Proven library with excellent touch device support (including iOS and Android)
- Handles edge cases and browser inconsistencies that would require significant custom code
- Built-in support for touch events, long-press, and auto-scrolling
- Well-maintained with active community and regular updates
- Provides smooth animations and visual feedback out of the box
- Simpler integration than maintaining custom drag-and-drop code
- ~50KB minified is acceptable for the robust functionality it provides

## Architecture

### Component Structure

```
Top List Interface
├── Search Component (for adding coasters)
│   ├── Search Input
│   ├── Search Dropdown
│   └── Search Results
├── List Container
│   ├── Coaster Entries (draggable items)
│   │   ├── Drag Handle
│   │   ├── Position Badge
│   │   ├── Coaster Info (name, park, rating)
│   │   ├── Quick Actions Menu
│   │   └── Delete Button
│   └── Drop Zones (visual feedback)
└── Auto-save System
```

### File Organization

```
assets/
├── controllers/
│   └── top_list_controller.js (refactored Stimulus controller)
├── styles/
│   └── components/
│       └── top-list.css (updated styles)
templates/
└── Top/
    └── edit.html.twig (refactored template)
src/
└── Controller/
    └── TopController.php (backend - minimal changes)
```

## Components and Interfaces

### 1. Search Component

**Purpose**: Allow users to search for coasters and add them to their Top List

**Implementation**:
- Create new `top_search_controller.js` specifically for Top List search
- Use existing AJAX endpoint: `/tops/search/coasters.json`
- Display: coaster name, park name, user's rating

**Data Flow**:
```
User types → Debounced AJAX request → Server returns results → 
Display results with rating → User selects → Add to list → Auto-save
```

**HTML Structure**:
```html
<div data-controller="top-search" 
     data-top-search-url-value="/tops/search/coasters.json"
     data-top-search-target-list-value="#top-coaster">
  <input type="search" 
         data-top-search-target="input"
         data-action="input->top-search#search"
         placeholder="Search coasters...">
  <div data-top-search-target="dropdown" class="search-dropdown">
    <!-- Results rendered here -->
  </div>
</div>
```

**CSS Classes to Reuse**:
- `.search-container` - from search.css
- `.search-dropdown` - from search.css
- `.search-result-item` - from search.css
- `.form-control` - from Bootstrap
- `.panel`, `.panel-flat` - from theme

### 2. Coaster Entry Component

**Purpose**: Display a single coaster in the Top List with all necessary information and actions

**HTML Structure**:
```html
<li class="media coaster-entry" 
    draggable="true"
    data-top-list-target="item"
    data-coaster-id="123"
    data-position="1">
  
  <!-- Drag Handle -->
  <div class="media-left drag-handle" 
       data-action="touchstart->top-list#handleTouchStart 
                    touchend->top-list#handleTouchEnd">
    <svg><!-- bars icon --></svg>
  </div>
  
  <!-- Position Badge -->
  <div class="media-left">
    <span class="position-badge label label-rounded label-primary">1</span>
  </div>
  
  <!-- Coaster Info -->
  <div class="media-body">
    <h4 class="media-heading">
      <span class="coaster-name">Steel Vengeance</span>
      <span class="text-muted">- Cedar Point</span>
      <span class="badge bg-success">9.5</span>
    </h4>
  </div>
  
  <!-- Actions -->
  <div class="media-right">
    <!-- Quick Actions Dropdown -->
    <div class="dropdown" data-controller="dropdown">
      <button class="btn btn-link" data-action="click->dropdown#toggle">
        <svg><!-- ellipsis icon --></svg>
      </button>
      <div class="dropdown-menu" data-dropdown-target="menu">
        <a data-action="click->top-list#moveToTop">Move to top</a>
        <a data-action="click->top-list#moveToBottom">Move to bottom</a>
        <a data-action="click->top-list#moveToPosition">Move to position...</a>
      </div>
    </div>
    
    <!-- Delete Button -->
    <button class="btn btn-link remove-btn" 
            data-action="click->top-list#removeCoaster">
      <svg><!-- x-circle icon --></svg>
    </button>
  </div>
</li>
```

**CSS Classes to Reuse**:
- `.media`, `.media-left`, `.media-body`, `.media-right` - from Bootstrap/theme
- `.label`, `.label-rounded`, `.label-primary` - from theme
- `.badge`, `.bg-success` - from Bootstrap
- `.btn`, `.btn-link` - from Bootstrap
- `.dropdown`, `.dropdown-menu` - from Bootstrap
- `.text-muted` - from Bootstrap

**New CSS Classes** (in top-list.css):
- `.coaster-entry` - container styling
- `.drag-handle` - cursor and hover states
- `.position-badge` - sizing and transitions
- `.remove-btn` - opacity and hover effects

### 3. Drag-and-Drop System with SortableJS

**Purpose**: Enable intuitive reordering of coasters with touch and mouse support

**Implementation Strategy**:
- Use SortableJS library for drag-and-drop functionality
- Configure for touch devices with delay option (long-press)
- Integrate with Stimulus controller lifecycle
- Trigger auto-save on sort end event

**SortableJS Configuration**:
```javascript
import Sortable from 'sortablejs';

connect() {
  this.sortable = Sortable.create(this.listTarget, {
    animation: 150,              // Smooth animation
    handle: '.drag-handle',      // Only drag from handle
    draggable: '.coaster-entry', // Items that can be dragged
    delay: 500,                  // Long-press delay for touch (ms)
    delayOnTouchOnly: true,      // Only delay on touch devices
    touchStartThreshold: 5,      // Pixels to move before canceling
    
    // Callbacks
    onStart: (evt) => this.handleDragStart(evt),
    onEnd: (evt) => this.handleDragEnd(evt),
    onMove: (evt) => this.handleDragMove(evt),
    
    // Visual feedback
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    dragClass: 'sortable-drag'
  });
}
```

**Event Handling**:
```javascript
handleDragStart(event) {
  // Add visual feedback
  this.element.classList.add('drag-active');
}

handleDragEnd(event) {
  // Remove visual feedback
  this.element.classList.remove('drag-active');
  
  // Update positions
  this.updatePositions();
  
  // Trigger auto-save
  this.debouncedSave();
}

handleDragMove(event) {
  // Optional: Add custom logic during drag
  return true; // Allow the move
}
```

**SortableJS CSS Classes**:
- `.sortable-ghost`: Applied to the placeholder element
- `.sortable-chosen`: Applied when item is selected
- `.sortable-drag`: Applied to the dragged element

**Visual Feedback**:
- Dragged item: Custom styling via `.sortable-drag` class
- Ghost placeholder: Dashed border via `.sortable-ghost` class
- Chosen item: Highlight via `.sortable-chosen` class
- Smooth animations handled by SortableJS

### 4. Auto-save System

**Purpose**: Automatically persist changes to the server without manual save action

**Implementation**:
- Debounce save requests (2 second delay)
- Use existing endpoint: `POST /tops/{id}/auto-save`
- Send position data as JSON: `{ positions: { coasterId: position } }`
- Display save status to user

**Save Flow**:
```
Change detected → Clear existing timer → Set new timer (2s) →
Timer expires → Collect positions → POST to server →
Show saving indicator → Receive response → Show success/error
```

**Error Handling**:
- Retry failed saves after 5 seconds
- Display error message to user
- Keep error visible until successful save
- Log errors to console for debugging

### 5. Quick Actions Menu

**Purpose**: Provide shortcuts for common position changes

**Actions**:
1. **Move to Top**: Set position to 1
2. **Move to Bottom**: Set position to last
3. **Move to Position**: Show modal/prompt for specific position

**Implementation**:
```javascript
moveToTop(event) {
  const item = event.target.closest('[data-top-list-target="item"]');
  this.moveItemToPosition(item, 0); // Index 0 = position 1
  this.updatePositions();
  this.debouncedSave();
}

moveToPosition(event) {
  const item = event.target.closest('[data-top-list-target="item"]');
  const currentPos = parseInt(item.dataset.position);
  const maxPos = this.itemTargets.length;
  
  const newPos = prompt(`Enter position (1-${maxPos}):`, currentPos);
  if (newPos && newPos >= 1 && newPos <= maxPos) {
    this.moveItemToPosition(item, newPos - 1);
    this.updatePositions();
    this.debouncedSave();
  }
}
```

## Data Models

### Frontend Data Structures

**Coaster Entry Data**:
```javascript
{
  coasterId: number,      // Unique coaster ID
  position: number,       // Current position in list (1-based)
  name: string,          // Coaster name
  parkName: string,      // Park name
  userRating: number|null // User's rating (0-10) or null
}
```

**Position Update Payload**:
```javascript
{
  positions: {
    [coasterId]: position,  // Map of coaster IDs to new positions
    // Example: { "123": 1, "456": 2, "789": 3 }
  }
}
```

### Backend Data Models

No changes to existing entities:
- `Top` entity - represents a user's top list
- `TopCoaster` entity - represents a coaster in a top list with position
- Existing relationships and validation remain unchanged

## Error Handling

### Frontend Error Scenarios

1. **Search Fails**:
   - Display error message in dropdown
   - Allow user to retry
   - Log error to console

2. **Auto-save Fails**:
   - Display error indicator
   - Retry after 5 seconds
   - Keep trying until successful
   - User can continue editing (optimistic UI)

3. **Invalid Position**:
   - Validate position input
   - Show error message
   - Don't update UI until valid

### Backend Error Scenarios

1. **Invalid Data**:
   - Return 400 Bad Request
   - Include error message in response
   - Frontend displays message to user

2. **Authorization Failure**:
   - Return 403 Forbidden
   - Redirect to login if needed
   - Display appropriate message

3. **Coaster Not Found**:
   - Return 404 Not Found
   - Remove from UI
   - Show notification

## Performance Considerations

### Optimization Strategies

1. **Debouncing**:
   - Search input: 300ms debounce
   - Auto-save: 2000ms debounce
   - Prevents excessive API calls

2. **DOM Updates**:
   - Batch position updates
   - Use DocumentFragment for multiple insertions
   - Minimize reflows and repaints

3. **Event Delegation**:
   - Use Stimulus actions for event handling
   - Avoid adding listeners to each item
   - Better performance with many items

4. **CSS Performance**:
   - Use transforms for animations (GPU accelerated)
   - Avoid expensive properties (box-shadow during drag)
   - Use will-change for dragged elements

### Performance Targets

- Search results: < 500ms response time
- Drag operation: 60fps (16ms per frame)
- Auto-save: < 1s response time
- Initial render: < 100ms for 50 items

## Mobile-Specific Considerations

### Touch Interactions

1. **Long-press to Drag**:
   - 500ms threshold
   - Haptic feedback (if available)
   - Visual feedback (scale up slightly)

2. **Scroll vs Drag**:
   - Short tap + immediate move = scroll
   - Long-press + move = drag
   - Clear visual distinction

3. **Touch Targets**:
   - Minimum 44x44px for all interactive elements
   - Adequate spacing between targets
   - Larger drag handles on mobile

### Mobile Performance

- Minimize JavaScript execution during scroll
- Use passive event listeners where possible
- Optimize for slower mobile CPUs
- Test on mid-range devices, not just flagships

### Responsive Design

**Breakpoints**:
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

**Layout Adjustments**:
- Mobile: Stack elements vertically, larger touch targets
- Tablet: Hybrid layout, medium touch targets
- Desktop: Horizontal layout, smaller targets, hover states

## Security Considerations

### CSRF Protection

- Use Symfony's CSRF token for form submissions
- Include token in AJAX requests
- Validate token on server side

### Authorization

- Verify user owns the Top List before allowing edits
- Use existing `TopVoter` for authorization checks
- Return 403 if unauthorized

### Input Validation

- Validate position numbers (1 to list length)
- Sanitize search queries
- Validate coaster IDs exist
- Prevent duplicate coasters in list

### XSS Prevention

- Escape all user-generated content
- Use Twig's auto-escaping
- Sanitize search results
- Don't use innerHTML for dynamic content

## Migration Strategy

### Phased Rollout

**Phase 1**: Refactor existing functionality
- Update Stimulus controller
- Update Twig template
- Update CSS
- Maintain existing backend

**Phase 2**: Add new features
- Implement search component
- Add quick actions menu
- Enhance mobile experience

**Phase 3**: Polish and optimize
- Performance optimization
- Accessibility improvements
- Cross-browser testing

### Backward Compatibility

- Maintain existing URL structure
- Keep existing API endpoints
- Don't break existing Top Lists
- Graceful degradation for old browsers

### Rollback Plan

- Keep old code in version control
- Feature flag for new interface
- Easy rollback if issues found
- Monitor error rates after deployment