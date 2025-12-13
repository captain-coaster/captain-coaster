---
inclusion: always
---

# Captain Coaster Product Context

Captain Coaster is a participative guide for roller coaster enthusiasts to discover, rate, and review roller coasters worldwide.

## Critical Design Constraints

### Mobile-First Priority

- **75% mobile users** - ALL features must work perfectly on mobile devices
- **25% desktop users** - Desktop is secondary but must remain functional
- **Touch-first interactions** - Design for finger navigation, not mouse precision
- **Responsive breakpoints** - Use Bootstrap's mobile-first approach

### Multi-language Requirements

- **4 languages supported**: English (default), French, Spanish, German
- **ALL user-facing text** must be translatable using Symfony's translation system
- **Domain-specific translations** - Use appropriate translation domains (messages, database, etc.)
- **Locale-prefixed routes** - All URLs include locale prefix

## Core Domain Model

### Primary Entities

- **Coaster** - Central entity with specifications, ratings, and reviews
- **Park** - Contains coasters, has location and operational data
- **User** - Community members with profiles, ratings, and social features
- **RiddenCoaster** - User's rating/review of a specific coaster (junction entity)
- **Ranking** - Global coaster rankings calculated from user ratings
- **Top** - User-created personal coaster rankings/lists

### Supporting Entities

- **Image** - User-uploaded photos with moderation and likes
- **Review** - Detailed user experiences with voting and reporting
- **Notification** - System messages for user interactions
- **Badge** - Achievement system for user engagement

## Business Rules

### Rating System

- Users rate coasters they've ridden (1-5 scale)
- Ratings contribute to global ranking calculations
- Users can update their ratings and reviews
- Minimum rating threshold required for ranking inclusion

### Content Moderation

- User-generated content (reviews, images) requires moderation
- Reporting system for inappropriate content
- Automated and manual review processes

### Social Features

- Users can follow each other and see activity
- Image likes and review voting system
- Notification system for interactions
- Badge rewards for engagement milestones

## Feature-Specific Guidance

### Search & Discovery

- Multi-criteria search (location, manufacturer, type, etc.)
- Map-based coaster discovery
- Filter combinations with URL state preservation
- Performance-optimized for large datasets

### AI Integration

- Coaster summaries generated from user reviews and specifications
- Feedback system for AI-generated content quality
- Bedrock service integration for content generation

### User Experience Patterns

- Progressive enhancement - core functionality works without JavaScript
- Stimulus controllers for interactive features
- Modal dialogs for forms and detailed views
- Infinite scroll or pagination for large lists
- Real-time updates where appropriate (notifications, likes)

## Development Priorities

1. **Mobile experience** - Test all features on mobile devices first
2. **Performance** - Optimize for mobile networks and devices
3. **Accessibility** - Ensure keyboard navigation and screen reader support
4. **Internationalization** - All text must be translatable from day one
5. **Data integrity** - Validate user input and maintain referential integrity
