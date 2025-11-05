# Design Document

## Overview

The modern top coaster management system will replace the current jQuery-based implementation with a lightweight, performant solution using modern web standards. The design prioritizes mobile-first responsive design, native HTML5 drag-and-drop API with touch support, and automatic persistence to eliminate manual save operations.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend Layer                           │
├─────────────────────────────────────────────────────────────┤
│  Stimulus Controller (top_list_controller.js)              │
│  ├── Drag & Drop Manager                                   │
│  ├── Auto-Save Manager                                     │
│  ├── Search & Add Manager                                  │
│  └── Import Manager                                        │
├─────────────────────────────────────────────────────────────┤
│                    Backend Layer                            │
├─────────────────────────────────────────────────────────────┤
│  TopController (existing, enhanced)                        │
│  ├── AJAX endpoints for auto-save                          │
│  ├── Import endpoints                                      │
│  └── Search endpoints (existing)                           │
├─────────────────────────────────────────────────────────────┤
│                    Data Layer                               │
├─────────────────────────────────────────────────────────────┤
│  Top & TopCoaster entities (existing)                      │
│  └── Enhanced with batch operations                        │
└─────────────────────────────────────────────────────────────┘
```

### Technology Stack

- **Frontend**: Stimulus.js (no jQuery), native HTML5 Drag & Drop API, CSS Grid/Flexbox
- **Backend**: Symfony controllers with AJAX endpoints
- **Persistence**: Doctrine ORM with optimized batch operations
- **Styling**: Bootstrap 5 utilities with custom CSS components

## Components and Interfaces

### 1. Top List Stimulus Controller

**File**: `assets/controllers/top_list_controller.js`

**Responsibilities**:
- Manage drag-and-drop interactions using native HTML5 API
- Handle auto-save operations with debouncing
- Coordinate search and position insertion
- Manage bulk operations and imports

**Key Methods**:
```javascript
class TopListController extends Controller {
    // Drag & Drop
    handleDragStart(event)
    handleDragOver(event)
    handleDrop(event)
    
    // Auto-save with debouncing
    autoSave()
    debouncedSave()
    
    // Coaster management
    addCoaster(coasterId, position)
    removeCoaster(element)
    updatePositions()
    
    // Import operations
    importByRating(minRating)
    clearAllCoasters()
}
```

### 2. Enhanced TopController

**New AJAX Endpoints**:

```php
#[Route('/tops/{id}/auto-save', name: 'top_auto_save', methods: ['POST'])]
public function autoSave(Top $top, Request $request): JsonResponse

#[Route('/tops/{id}/add-coaster', name: 'top_add_coaster', methods: ['POST'])]
public function addCoaster(Top $top, Request $request): JsonResponse

#[Route('/tops/{id}/import-by-rating', name: 'top_import_rating', methods: ['POST'])]
public function importByRating(Top $top, Request $request): JsonResponse

#[Route('/tops/{id}/clear-all', name: 'top_clear_all', methods: ['POST'])]
public function clearAll(Top $top): JsonResponse
```

### 3. Mobile-First UI Components

**Coaster Entry Component**:
```html
<div class="coaster-entry" 
     draggable="true" 
     data-coaster-id="{{ coaster.id }}"
     data-position="{{ position }}">
    <div class="drag-handle">
        <i class="icon-grip-vertical"></i>
    </div>
    <div class="position-badge">{{ position }}</div>
    <div class="coaster-info">
        <h4>{{ coaster.name }}</h4>
        <p>{{ coaster.park.name }} ({{ userRating }})</p>
    </div>
    <button class="remove-btn" data-action="click->top-list#removeCoaster">
        <i class="icon-trash"></i>
    </button>
</div>
```

**Search and Add Interface**:
```html
<div class="add-coaster-section">
    <div class="search-container">
        <input type="text" 
               data-target="top-list.searchInput"
               data-action="input->top-list#searchCoasters"
               placeholder="{{ 'top.search_placeholder'|trans }}">
        <div class="search-results" data-target="top-list.searchResults"></div>
    </div>
    <div class="position-selector">
        <label>{{ 'top.insert_at_position'|trans }}</label>
        <input type="number" 
               data-target="top-list.positionInput"
               min="1" 
               value="{{ topCoasters|length + 1 }}">
    </div>
</div>
```

## Data Models

### Enhanced TopCoaster Operations

**Batch Position Update**:
```php
// TopCoasterRepository
public function updatePositionsBatch(Top $top, array $positions): void
{
    $qb = $this->createQueryBuilder('tc');
    
    foreach ($positions as $coasterId => $position) {
        $qb->update()
           ->set('tc.position', ':position')
           ->where('tc.top = :top AND tc.coaster = :coaster')
           ->setParameters([
               'position' => $position,
               'top' => $top,
               'coaster' => $coasterId
           ])
           ->getQuery()
           ->execute();
    }
}
```

**Import by Rating Query**:
```php
// RiddenCoasterRepository
public function findByMinRating(User $user, float $minRating, ?Top $excludeTop = null): array
{
    $qb = $this->createQueryBuilder('rc')
        ->select('rc', 'c', 'p')
        ->join('rc.coaster', 'c')
        ->join('c.park', 'p')
        ->where('rc.user = :user')
        ->andWhere('rc.value >= :minRating')
        ->orderBy('rc.value', 'DESC')
        ->addOrderBy('rc.createdAt', 'DESC')
        ->setParameters([
            'user' => $user,
            'minRating' => $minRating
        ]);
    
    if ($excludeTop) {
        $qb->andWhere('c.id NOT IN (
            SELECT IDENTITY(tc.coaster) 
            FROM App\Entity\TopCoaster tc 
            WHERE tc.top = :excludeTop
        )')
        ->setParameter('excludeTop', $excludeTop);
    }
    
    return $qb->getQuery()->getResult();
}
```

## Error Handling

### Frontend Error Management

```javascript
// Auto-save error handling
async autoSave() {
    try {
        const response = await fetch(this.autoSaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(this.getPositionsData())
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        this.showSaveStatus('saved');
    } catch (error) {
        console.error('Auto-save failed:', error);
        this.showSaveStatus('error');
        // Retry after delay
        setTimeout(() => this.autoSave(), 5000);
    }
}
```

### Backend Validation

```php
// TopController auto-save validation
public function autoSave(Top $top, Request $request): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);
        
        if (!$this->isGranted('edit', $top)) {
            throw new AccessDeniedException();
        }
        
        $this->validatePositionsData($data);
        $this->topService->updatePositions($top, $data);
        
        return new JsonResponse(['status' => 'success']);
    } catch (\Exception $e) {
        return new JsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 400);
    }
}
```

## Testing Strategy

### Unit Tests

1. **TopController Tests**:
   - Auto-save endpoint validation
   - Import functionality
   - Position update logic
   - Error handling scenarios

2. **Repository Tests**:
   - Batch position updates
   - Import queries with rating filters
   - Performance with large datasets

### Integration Tests

1. **Stimulus Controller Tests**:
   - Drag and drop functionality
   - Auto-save debouncing
   - Search and add operations
   - Mobile touch interactions

2. **End-to-End Tests**:
   - Complete workflow from search to save
   - Mobile device compatibility
   - Performance with 1000+ coasters
   - Import and bulk operations

### Performance Tests

1. **Load Testing**:
   - Response times with large lists
   - Auto-save performance under load
   - Memory usage optimization

2. **Mobile Testing**:
   - Touch responsiveness
   - Scroll performance
   - Battery usage optimization

## Implementation Phases

### Phase 1: Core Infrastructure
- Create new Stimulus controller
- Implement basic drag-and-drop with native API
- Add auto-save functionality
- Update TopController with AJAX endpoints

### Phase 2: Enhanced Features
- Implement position insertion
- Add search and autocomplete
- Create import functionality
- Add bulk operations

### Phase 3: Mobile Optimization
- Optimize touch interactions
- Improve mobile UI/UX
- Add haptic feedback
- Performance optimization

### Phase 4: Testing and Polish
- Comprehensive testing suite
- Performance optimization
- Accessibility improvements
- Documentation and cleanup