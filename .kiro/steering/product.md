---
inclusion: always
---

# Captain Coaster Product Context

Captain Coaster is a participative guide for roller coaster enthusiasts to discover, rate, and review roller coasters worldwide.

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

- Image likes and review voting system
- Badge rewards for engagement milestones (to be refactored completely)

## Feature-Specific Guidance

### Search & Discovery

- Multi-criteria search (location, manufacturer, type, etc.)
- Map-based coaster discovery

### AI Integration

- Coaster summaries generated from user reviews and specifications
- Feedback system for AI-generated content quality
