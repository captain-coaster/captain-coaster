# Design Document

## Overview

The modern search system will replace the legacy typeahead.js implementation with a Stimulus-based solution that provides real-time search suggestions via AJAX calls. The system will maintain the current user experience while improving performance, maintainability, and removing the dependency on large cached JSON files.

## Architecture

### High-Level Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Search UI     │    │  Search API      │    │  Search Cache   │
│  (Stimulus)     │◄──►│  (Controller)    │◄──►│   (Redis)       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌──────────────────┐
                       │   Repositories   │
                       │  (Database)      │
                       └──────────────────┘
```

### Component Interaction Flow

1. **User Input**: User types in search input field
2. **Debounced Request**: Stimulus controller debounces input and sends AJAX request
3. **Cache Check**: Search API checks Redis cache for recent queries
4. **Database Query**: If cache miss, queries repositories for matching results
5. **Response Formatting**: API formats results with metadata and caches response
6. **UI Update**: Stimulus controller updates dropdown with formatted results
7. **Navigation**: User selects result or views comprehensive results page

## Components and Interfaces

### Frontend Components

#### Search Stimulus Controller (`search_controller.js`)

**Responsibilities:**
- Handle user input with debouncing (300ms delay)
- Manage search dropdown visibility and state
- Send AJAX requests to search API
- Update UI with search results
- Handle keyboard navigation (arrow keys, enter, escape)
- Manage "Show more results" functionality

**Key Methods:**
```javascript
class SearchController extends Controller {
  static targets = ["input", "dropdown", "results"]
  static values = { 
    searchUrl: String,
    resultsUrl: String,
    minLength: { type: Number, default: 2 },
    debounceDelay: { type: Number, default: 300 }
  }

  connect()           // Initialize controller
  search()            // Handle search input with debouncing
  performSearch()     // Execute AJAX search request
  updateResults()     // Update dropdown with results
  selectResult()      // Handle result selection
  showMoreResults()   // Navigate to comprehensive results
  hideDropdown()      // Hide search dropdown
  handleKeydown()     // Keyboard navigation
}
```

**Data Attributes:**
- `data-controller="search"`
- `data-search-search-url-value="/api/search"`
- `data-search-results-url-value="/search"`
- `data-action="input->search#search keydown->search#handleKeydown"`

#### Search Results Component

**HTML Structure:**
```html
<div class="search-container" data-controller="search">
  <input type="search" 
         data-search-target="input"
         data-action="input->search#search keydown->search#handleKeydown"
         class="form-control">
  
  <div class="search-dropdown" data-search-target="dropdown">
    <div data-search-target="results">
      <!-- Dynamic results content -->
    </div>
  </div>
</div>
```

### Backend Components

#### Search API Controller

**New Route: `/api/search`**
```php
#[Route('/api/search', name: 'api_search', methods: ['GET'])]
public function search(
    Request $request,
    SearchService $searchService,
    CacheInterface $cache
): JsonResponse
```

**Response Format:**
```json
{
  "query": "steel",
  "results": {
    "coasters": [
      {
        "id": 123,
        "name": "Steel Vengeance",
        "slug": "steel-vengeance",
        "park": "Cedar Point",
        "country": "United States",
        "image": "steel-vengeance.jpg",
        "type": "coaster"
      }
    ],
    "parks": [...],
    "users": [...]
  },
  "totalResults": {
    "coasters": 45,
    "parks": 12,
    "users": 8
  },
  "hasMore": true
}
```

#### Enhanced SearchService

**New Methods:**
```php
class SearchService 
{
    public function searchAll(string $query, int $limit = 5): array
    public function searchCoasters(string $query, int $limit = 5): array
    public function searchParks(string $query, int $limit = 5): array
    public function searchUsers(string $query, int $limit = 5): array
    private function formatSearchResults(array $results, string $type): array
    private function getCacheKey(string $query): string
}
```

#### Repository Enhancements

**Optimized Search Queries:**
- Add database indexes on searchable fields (name, slug)
- Implement FULLTEXT search for better performance
- Use LIMIT clauses to restrict result sets
- Add query result caching at repository level

```php
// CoasterRepository
public function findBySearchQuery(string $query, int $limit = 5): array
{
    return $this->createQueryBuilder('c')
        ->select('c.id, c.name, c.slug, p.name as parkName, co.name as countryName')
        ->leftJoin('c.park', 'p')
        ->leftJoin('p.country', 'co')
        ->where('c.name LIKE :query OR c.slug LIKE :query')
        ->setParameter('query', '%' . $query . '%')
        ->setMaxResults($limit)
        ->getQuery()
        ->getArrayResult();
}
```

### Caching Strategy

#### Redis Cache Implementation

**Cache Structure:**
- **Key Pattern**: `search:{query_hash}:{locale}`
- **TTL**: 1 hour for search results
- **Invalidation**: Clear cache when entities are updated

**Cache Layers:**
1. **Query Results Cache**: Cache database query results
2. **Formatted Results Cache**: Cache API response format
3. **Popular Queries Cache**: Pre-cache common search terms

```php
class SearchCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'search:';
    
    public function getCachedResults(string $query): ?array
    public function setCachedResults(string $query, array $results): void
    public function invalidateSearchCache(): void
    private function getCacheKey(string $query): string
}
```

## Data Models

### Search Result Data Transfer Object

```php
class SearchResultDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $type,
        public readonly ?string $image = null,
        public readonly ?string $subtitle = null,
        public readonly array $metadata = []
    ) {}
}
```

### Search Response Data Transfer Object

```php
class SearchResponseDTO
{
    public function __construct(
        public readonly string $query,
        public readonly array $results,
        public readonly array $totalResults,
        public readonly bool $hasMore
    ) {}
}
```

## Error Handling

### Frontend Error Handling

**Error Scenarios:**
- Network connectivity issues
- API timeout (> 5 seconds)
- Invalid server responses
- Empty search results

**Error Display:**
- Show user-friendly error messages in dropdown
- Graceful degradation to basic search functionality
- Retry mechanism for transient failures

### Backend Error Handling

**Error Responses:**
```json
{
  "error": true,
  "message": "Search temporarily unavailable",
  "code": "SEARCH_ERROR"
}
```

**Error Logging:**
- Log search performance metrics
- Track failed queries for debugging
- Monitor cache hit/miss ratios

## Testing Strategy

### Frontend Testing

**Unit Tests (Jest):**
- Test Stimulus controller methods
- Mock AJAX requests and responses
- Test keyboard navigation functionality
- Verify debouncing behavior

**Integration Tests:**
- Test complete search workflow
- Verify API integration
- Test error handling scenarios

### Backend Testing

**Unit Tests (PHPUnit):**
- Test SearchService methods
- Test repository search queries
- Test cache service functionality
- Test DTO serialization

**Functional Tests:**
- Test API endpoints
- Test search performance
- Test cache invalidation
- Test error responses

## Migration Strategy

### Phase 1: Backend API Development
1. Create new search API endpoint
2. Implement enhanced SearchService
3. Add Redis caching layer
4. Optimize repository queries

### Phase 2: Frontend Implementation
1. Create Stimulus search controller
2. Update search input template
3. Implement new search UI
4. Add comprehensive results page redesign

### Phase 3: Integration and Testing
1. Connect frontend to new API
2. Performance testing and optimization
3. User acceptance testing
4. Gradual rollout with feature flags

### Phase 4: Legacy Cleanup
1. Remove typeahead.js dependency
2. Clean up old search endpoints
3. Remove cached JSON generation
4. Update documentation

## Security Considerations

### Input Validation
- Sanitize search queries to prevent XSS
- Implement rate limiting on search API
- Validate query length and characters

### Performance Protection
- Implement search query throttling per user
- Cache expensive queries to prevent DoS
- Monitor and alert on unusual search patterns

### Data Privacy
- Log search queries anonymously
- Respect user privacy in search suggestions
- Implement proper GDPR compliance for search data