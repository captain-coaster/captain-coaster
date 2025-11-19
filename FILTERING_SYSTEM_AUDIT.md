# Filtering System Audit - Captain Coaster

## Overview
This document outlines issues and improvements identified in the filtering system for Nearby, Ranking, and Map pages.

## Critical Issues

### 1. Inconsistent Filter Configuration
- **Nearby page**: Only `['status', 'name', 'manufacturer', 'score']`
- **Ranking page**: `['continent', 'country', 'status', 'notridden', 'new', 'materialType', 'seatingType', 'model', 'manufacturer', 'openingDate']`
- **Map page**: Only `['status', 'kiddie', 'notridden', 'name', 'manufacturer']`

**Impact**: Users get different filtering capabilities without clear reasoning.

### 2. Filter State Management Issues
- Filter values not restored from URL parameters on page load
- `handlePopState()` resets form but doesn't trigger filtering
- No validation of filter parameter types or ranges

### 3. Performance Problems
- No query optimization - all filters use `leftJoin` even when unused
- Filter dropdown data cached for 7 days without invalidation
- Missing eager loading causes N+1 queries

### 4. Security Vulnerabilities
- **SQL injection risk**: `filterOpeningDate()` uses `LIKE` with string interpolation
- **No input sanitization**: Filter parameters not validated
- **User ID exposure**: User filter parameter not validated

## Functional Issues

### 5. Geolocation Handling
- Race condition: filtering triggers before geolocation obtained
- No fallback when geolocation fails
- No loading indicator during location acquisition

### 6. Filter Logic Inconsistencies
- "New" filter: Different behavior on ranking vs other pages
- Kiddie filter: Inverted logic (empty string excludes instead of including all)
- Score filter: Only predefined ranges, no custom values

### 7. Map-Specific Issues
- No marker clustering for dense areas
- Complete marker reload instead of updates
- Memory leaks from improper marker cleanup

## Code Quality Issues

### 8. Duplicate Code
- Filter logic duplicated in `applyFilters()` and `applyAllFilters()`
- Query building patterns repeated
- Redundant parameter setting

### 9. Poor Error Handling
- Silent failures without user feedback
- Generic `BadRequestHttpException` without details
- No retry logic for network failures

### 10. Maintainability Problems
- Hard-coded values scattered throughout
- Tight coupling between filter controller and DOM
- Each page implements filtering differently

## Recommended Improvements

### High Priority
1. **Standardize filter configuration** across all pages
2. **Fix SQL injection** in `filterOpeningDate()` method
3. **Implement input validation** for all filter parameters
4. **Add query optimization** with conditional joins
5. **Fix geolocation race condition** in nearby page

### Medium Priority
6. **Implement marker clustering** for map page
7. **Add proper error handling** with user feedback
8. **Standardize "new" filter behavior** across pages
9. **Add loading states** for better UX
10. **Implement filter state persistence** in URL

### Low Priority
11. **Refactor duplicate code** into shared methods
12. **Add filter analytics** for usage insights
13. **Implement advanced filters** (date ranges, multiple selections)
14. **Add filter presets** for common use cases
15. **Optimize cache invalidation** strategy

## Technical Debt
- Extract filter logic into dedicated service classes
- Implement TypeScript interfaces for filter data
- Add comprehensive unit tests for filter methods
- Create filter configuration schema validation
- Implement proper dependency injection for filter services

## Files Affected
- `templates/Includes/filter_sidebar.html.twig`
- `assets/controllers/filter_controller.js`
- `src/Service/FilterService.php`
- `src/Repository/CoasterRepository.php`
- `assets/controllers/map_controller.js`
- `src/Controller/RankingController.php`
- `src/Controller/MapsController.php`
- `src/Controller/NearbyController.php`

## Next Steps
1. Address security vulnerabilities immediately
2. Standardize filter configurations
3. Implement proper error handling
4. Optimize database queries
5. Refactor for maintainability