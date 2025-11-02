# Requirements Document

## Introduction

This feature enhances the existing AI-generated coaster summaries system by adding Easy Admin dashboard integration for better management and a user feedback system to track summary quality. The enhancement allows administrators to easily review, regenerate summaries, and identify poorly-performing summaries through user feedback metrics.

## Glossary

- **AI_Summary_System**: The existing CoasterSummary entity and related services that generate AI-powered summaries from user reviews
- **Easy_Admin_Dashboard**: The administrative interface built with EasyAdmin bundle for managing entities
- **Summary_Feedback_System**: New functionality allowing users to rate summary quality with thumbs up/down votes
- **Feedback_Ratio**: The calculated percentage of positive vs negative feedback for a summary
- **Admin_User**: Authenticated user with administrative privileges to access the Easy Admin dashboard
- **End_User**: Regular website visitor who can view summaries and provide feedback

## Requirements

### Requirement 1

**User Story:** As an Admin_User, I want to manage AI summaries through the Easy Admin dashboard, so that I can efficiently review and maintain summary quality.

#### Acceptance Criteria

1. WHEN an Admin_User accesses the Easy Admin dashboard, THE AI_Summary_System SHALL display a CoasterSummary management interface
2. THE AI_Summary_System SHALL show summary details including coaster name, language, creation date, reviews analyzed, and feedback metrics
3. THE AI_Summary_System SHALL provide a "Regenerate Summary" action button for each summary entry
4. WHEN an Admin_User clicks "Regenerate Summary", THE AI_Summary_System SHALL trigger the summary generation process for that specific coaster
5. THE AI_Summary_System SHALL display success or error messages after regeneration attempts

### Requirement 2

**User Story:** As an Admin_User, I want to identify summaries with poor feedback ratios, so that I can prioritize which summaries need regeneration.

#### Acceptance Criteria

1. THE AI_Summary_System SHALL calculate and display the Feedback_Ratio for each summary
2. THE AI_Summary_System SHALL highlight summaries with Feedback_Ratio below 30% in the admin interface
3. THE AI_Summary_System SHALL provide filtering options to show only summaries with poor feedback ratios
4. THE AI_Summary_System SHALL sort summaries by Feedback_Ratio in ascending order when requested
5. WHERE a summary has fewer than 5 feedback votes, THE AI_Summary_System SHALL indicate insufficient data

### Requirement 3

**User Story:** As an End_User, I want to provide feedback on AI summaries, so that I can help improve the quality of summaries for the community.

#### Acceptance Criteria

1. WHEN an End_User views a coaster page with an AI summary, THE Summary_Feedback_System SHALL display thumbs up and thumbs down buttons
2. WHEN an End_User clicks a feedback button, THE Summary_Feedback_System SHALL record their vote
3. THE Summary_Feedback_System SHALL prevent duplicate votes from the same user for the same summary
4. THE Summary_Feedback_System SHALL allow users to change their vote by clicking the opposite button

### Requirement 4

**User Story:** As an Admin_User, I want to bulk regenerate summaries with poor feedback, so that I can efficiently improve summary quality across the platform.

#### Acceptance Criteria

1. THE AI_Summary_System SHALL provide a bulk action to regenerate  summaries below a certain threshold

### Requirement 5

**User Story:** As an Admin_User, I want to track feedback trends over time, so that I can monitor the overall quality improvement of AI summaries.

#### Acceptance Criteria

1. THE Summary_Feedback_System SHALL store timestamps for all feedback votes
2. THE AI_Summary_System SHALL display feedback statistics including total votes and average ratio
3. THE AI_Summary_System SHALL show feedback trends for individual summaries over time
4. THE AI_Summary_System SHALL provide export functionality for feedback data analysis
5. THE AI_Summary_System SHALL calculate and display system-wide feedback metrics in the dashboard