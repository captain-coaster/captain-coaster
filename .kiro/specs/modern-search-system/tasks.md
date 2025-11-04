# Implementation Plan

- [x] 1. Create search cache service and optimize repositories
  - Create SearchCacheService for Redis-based caching with TTL management
  - Optimize CoasterRepository, ParkRepository, and UserRepository with indexed search queries
  - Add database indexes on searchable fields (name, slug) for performance
  - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [x] 2. Implement new search API endpoint
  - [x] 2.1 Create SearchResultDTO and SearchResponseDTO for structured data transfer
    - Define data transfer objects for consistent API responses
    - Include proper serialization and validation
    - _Requirements: 1.1, 2.1, 2.2_

  - [x] 2.2 Create new API search controller endpoint at /api/search
    - Implement GET endpoint with query parameter validation
    - Integrate with SearchCacheService for performance
    - Return JSON responses with proper error handling
    - _Requirements: 1.1, 1.3, 4.1, 4.2, 5.3_

  - [x] 2.3 Enhance SearchService with new search methods
    - Add searchAll(), searchCoasters(), searchParks(), searchUsers() methods
    - Implement result formatting and metadata generation
    - Add cache key generation and management
    - _Requirements: 1.2, 2.1, 4.2, 4.4_

- [x] 3. Create Stimulus search controller
  - [x] 3.1 Implement search_controller.js with core functionality
    - Create Stimulus controller with input debouncing (300ms)
    - Add AJAX request handling with error management
    - Implement dropdown visibility and state management
    - _Requirements: 1.1, 1.4, 5.1, 5.2_

  - [x] 3.2 Add keyboard navigation and accessibility features
    - Implement arrow key navigation through results
    - Add Enter key selection and Escape key dismissal
    - Include proper ARIA labels and semantic HTML structure
    - _Requirements: 6.1, 6.2, 6.4, 6.5_

  - [x] 3.3 Implement result selection and "show more" functionality
    - Handle individual result selection and navigation
    - Add "Show more results" option when results exceed 5 items
    - Integrate with existing search results page
    - _Requirements: 1.1, 3.1, 3.2_

- [x] 4. Update search interface templates
  - [x] 4.1 Modify search input in page_header.html.twig
    - Replace typeahead.js data attributes with Stimulus controller
    - Add proper data targets and action bindings
    - Maintain existing styling and responsive behavior
    - _Requirements: 1.4, 5.5, 6.3_

  - [x] 4.2 Create search dropdown template component
    - Design modern dropdown with grouped results by entity type
    - Add visual indicators (emojis) for coasters, parks, users
    - Implement responsive design for mobile devices
    - _Requirements: 2.1, 2.2, 2.3, 6.3_

- [ ] 5. Redesign comprehensive search results page with unified single list
  - [x] 5.1 Create unified search results page with single mixed list
    - Replace separate entity templates with single list mixing all entity types
    - Use same visual indicators (emojis) as dropdown for consistency
    - Show total results count and implement pagination for large result sets
    - Maintain relevance-based ordering across all entity types
    - _Requirements: 3.3, 3.4, 5.1_

  - [x] 5.2 Implement consistent result items matching dropdown style
    - Design result items with same structure as dropdown (name, subtitle, visual indicator)
    - Add hover states and click interactions
    - Include relevant metadata consistently across all types
    - Replace jQuery-based AJAX calls with Stimulus controller for interactions
    - _Requirements: 3.4, 3.5, 5.1_

  - [ ] 5.3 Add optional filtering and enhanced functionality
    - Add optional filter toggles to show/hide specific entity types if needed
    - Implement pagination for handling large result sets
    - Maintain responsive design for mobile devices
    - Add proper loading states and error handling
    - _Requirements: 3.4, 6.3_

- [x] 6. Remove legacy typeahead.js implementation
  - [x] 6.1 Clean up typeahead.js dependencies and files
    - Remove typeahead.bundle.min.js and related assets
    - Update webpack configuration to exclude typeahead chunks
    - Remove typeahead CSS styles and classes
    - _Requirements: 1.4, 5.2_

  - [x] 6.2 Update base.html.twig to remove legacy search code
    - Remove typeahead initialization and event handlers
    - Clean up Bloodhound data source configuration
    - Remove legacy search-related JavaScript functions
    - _Requirements: 1.4, 1.5_

- [ ] 7. Implement performance optimizations and monitoring
  - [ ] 7.1 Add search performance monitoring
    - Implement response time tracking for search API
    - Add cache hit/miss ratio monitoring
    - Create performance alerts for slow queries
    - _Requirements: 1.3, 4.3_

  - [ ]* 7.2 Add comprehensive test coverage
    - Write unit tests for SearchService and cache functionality
    - Create functional tests for search API endpoints
    - Add Stimulus controller tests with mocked AJAX requests
    - _Requirements: 5.4_

- [x] 8. Configure comprehensive cache system following Symfony best practices
  - [x] 8.1 Review and optimize cache configuration across the application
    - Audit current cache usage and identify areas for improvement
    - Configure filesystem cache for metadata, configuration, and serialization
    - Configure Redis cache for search, sessions, Doctrine query cache, and high-frequency data
    - Update cache pool definitions with appropriate adapters and TTL values
    - _Requirements: 4.2, 4.4_

  - [x] 8.2 Implement proper cache autowiring and service configuration
    - Update services.yaml to properly inject cache pools using Symfony best practices
    - Replace direct cache instantiation (FilesystemAdapter) with proper dependency injection
    - Configure named cache pools for different use cases (search, doctrine, app data)
    - Add cache warming strategies for critical application data
    - _Requirements: 4.1, 4.2, 4.4_

  - [x] 8.3 Add database indexes and optimize Doctrine caching
    - Create database migration for search indexes on name and slug fields
    - Configure Doctrine result cache and query cache pools with Redis
    - Optimize repository queries for production performance with proper caching
    - Add metadata cache configuration for Doctrine ORM
    - _Requirements: 4.1, 4.3_

  - [x] 8.4 Configure environment-specific cache settings
    - Set up production cache configuration with Redis clustering support
    - Configure development cache with appropriate debugging and TTL settings
    - Add cache monitoring and performance metrics collection
    - Implement cache invalidation strategies for content updates
    - _Requirements: 4.2, 4.3, 4.4_