# Implementation Plan

-   [x] 1. Create SummaryFeedback entity and database migration

    -   Create SummaryFeedback entity with proper relationships and constraints
    -   Generate database migration for summary_feedback table
    -   Add cascade delete relationship to CoasterSummary entity
    -   _Requirements: 3.1, 3.2, 3.3_

-   [x] 2. Extend CoasterSummary entity with feedback metrics

    -   Add positiveVotes, negativeVotes, and feedbackRatio properties
    -   Implement feedback calculation methods (updateFeedbackMetrics, getFeedbackRatio, getTotalVotes, hasMinimumFeedback)
    -   Generate database migration for new columns
    -   _Requirements: 2.1, 2.5_

-   [x] 3. Create SummaryFeedbackService for feedback management

    -   Implement submitFeedback method with duplicate vote handling
    -   Implement updateSummaryMetrics method to recalculate ratios
    -   Add IP address hashing for privacy
    -   Handle vote changes (user switching from thumbs up to thumbs down)
    -   _Requirements: 3.2, 3.3, 3.5_

-   [x] 4. Create SummaryFeedbackController for frontend integration

    -   Implement POST /summary/{id}/feedback endpoint
    -   Add CSRF protection and rate limiting
    -   Return JSON response with updated vote counts and user's current vote
    -   Handle authentication for both logged-in and anonymous users
    -   _Requirements: 3.1, 3.2, 3.4_

-   [x] 5. Create CoasterSummaryCrudController for Easy Admin

    -   Configure fields to display coaster name, language, feedback metrics, dates
    -   Add custom regeneration action for individual summaries
    -   Implement feedback ratio highlighting for poor ratios with sufficient votes
    -   Add filters for feedback ratio, language, and date ranges
    -   Configure sorting options by feedback ratio and vote counts
    -   _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5_

-   [x] 6. Enhance GenerateCoasterSummariesCommand with feedback filtering

    -   Add --min-ratio option to regenerate summaries below specified threshold
    -   Add --min-votes option to set minimum vote requirement for ratio filtering
    -   Implement getSummariesWithPoorFeedback method in CoasterSummaryService
    -   Update command help text and examples
    -   _Requirements: 4.4, 2.4_

-   [x] 7. Create frontend Stimulus controller for feedback interactions

    -   Implement summary_feedback_controller.js with vote handling
    -   Add thumbs up/down button interactions
    -   Handle AJAX requests to feedback endpoint
    -   Update UI with user's current vote state
    -   Add loading states and error handling
    -   _Requirements: 3.1, 3.2, 3.4, 3.5_

-   [x] 8. Update coaster show template with feedback UI

    -   Add thumbs up/down buttons to AI summary section
    -   Add Stimulus controller data attributes
    -   Include user's current vote state in template data for performance
    -   Style feedback buttons to match existing design
    -   _Requirements: 3.1, 3.4_

-   [x] 9. Add CoasterSummary to Easy Admin dashboard menu

    -   Register CoasterSummaryCrudController in DashboardController
    -   Configure menu item with appropriate icon and label
    -   Set proper permissions for admin access only
    -   _Requirements: 1.1_

-   [x] 10. Implement feedback metrics calculation and display

    -   Create method to calculate system-wide feedback statistics
    -   Add feedback trend tracking over time
    -   Display aggregate metrics in admin dashboard
    -   _Requirements: 5.1, 5.2, 5.4, 5.5_

-   [ ]\* 11. Add comprehensive test coverage

    -   Write unit tests for SummaryFeedbackService methods
    -   Write unit tests for CoasterSummary feedback calculation methods
    -   Write integration tests for SummaryFeedbackController
    -   Write integration tests for CoasterSummaryCrudController actions
    -   Write tests for command enhancements
    -   _Requirements: All requirements_

-   [ ]\* 12. Add logging and monitoring
    -   Log all feedback submissions with user/IP tracking
    -   Log regeneration attempts and results in admin actions
    -   Monitor feedback ratio trends for quality insights
    -   Add error logging for failed operations
    -   _Requirements: 4.5, 5.5_
